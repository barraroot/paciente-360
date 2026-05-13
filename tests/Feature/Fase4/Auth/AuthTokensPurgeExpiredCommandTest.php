<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T090 — Testes do AuthTokensPurgeExpiredCommand (Fase 4 Lote J).
 *
 * Valida que tokens expirados há mais de N dias são deletados, tokens
 * recentes são preservados, e o comando é idempotente.
 *
 * @see app/Console/Commands/AuthTokensPurgeExpiredCommand.php
 */
class AuthTokensPurgeExpiredCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create(['slug' => 'tenant-purge']);
        $this->user = User::factory()->for($tenant)->create();
    }

    #[Test]
    public function it_deletes_tokens_expired_more_than_keep_days_ago(): void
    {
        // Cria 2 tokens: um expirado há 100d (deve ser purgado) e um expirado
        // há 30d (deve ser preservado — dentro da janela de 90d default).
        $this->createTokenExpiredDaysAgo(100);
        $this->createTokenExpiredDaysAgo(30);

        $this->assertSame(2, DB::table('personal_access_tokens')->count());

        $this->artisan('auth:tokens-purge-expired')
            ->expectsOutputToContain('Purgados 1 token')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    #[Test]
    public function it_respects_custom_keep_days_option(): void
    {
        $this->createTokenExpiredDaysAgo(45);
        $this->createTokenExpiredDaysAgo(15);

        $this->artisan('auth:tokens-purge-expired', ['--keep-days' => 30])
            ->assertSuccessful();

        // Com keep-days=30: token de 45d → purgado; token de 15d → preservado.
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    #[Test]
    public function it_skips_tokens_with_null_expires_at(): void
    {
        // Token sem expires_at (legado pré-Fase 4) → não deve ser purgado.
        $this->createTokenExpiredDaysAgo(null);

        $this->artisan('auth:tokens-purge-expired')->assertSuccessful();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    #[Test]
    public function dry_run_does_not_delete(): void
    {
        $this->createTokenExpiredDaysAgo(100);

        $this->artisan('auth:tokens-purge-expired', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    #[Test]
    public function is_idempotent_when_no_tokens_eligible(): void
    {
        $this->artisan('auth:tokens-purge-expired')
            ->expectsOutputToContain('Nenhum token elegível')
            ->assertSuccessful();
    }

    /**
     * Cria um token "expirado há N dias atrás" via inserção direta no DB
     * (Sanctum não permite expires_at no passado via createToken; faz update
     * após a criação).
     */
    private function createTokenExpiredDaysAgo(?int $daysAgo): void
    {
        $token = $this->user->createToken('test-'.uniqid());

        DB::table('personal_access_tokens')
            ->where('id', $token->accessToken->id)
            ->update([
                'expires_at' => $daysAgo === null ? null : now()->subDays($daysAgo),
            ]);
    }
}
