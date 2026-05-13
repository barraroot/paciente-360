<?php

namespace Tests\Unit\Auth;

use App\Domain\Auth\Services\SlidingExpirationService;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T024 — Testes unitários do `SlidingExpirationService` (TDD).
 *
 * Cobre: renovação dentro do buffer de 5 dias, idempotência fora do buffer,
 * tokens sem expiração, custom buffer e window.
 *
 * @see App\Domain\Auth\Services\SlidingExpirationService
 * @see specs/004-token-auth-migration/spec.md §FR-006, §NC-2
 */
class SlidingExpirationServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlidingExpirationService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlidingExpirationService;

        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($tenant)->create();
    }

    /**
     * Cria um PersonalAccessToken no DB para o usuário via `createToken()`.
     */
    private function createToken(?Carbon $expiresAt = null): PersonalAccessToken
    {
        $newToken = $this->user->createToken('test-device', ['*'], $expiresAt);

        /** @var PersonalAccessToken $token */
        $token = $newToken->accessToken;

        return $token;
    }

    /** @test */
    public function test_it_renews_when_within_5_day_buffer(): void
    {
        Carbon::setTestNow('2026-01-20 12:00:00');

        // Token expira em 3 dias — dentro do buffer de 5 dias
        $token = $this->createToken(Carbon::parse('2026-01-23 12:00:00'));

        $renewed = $this->service->renewIfDue($token);

        $this->assertTrue($renewed);

        // Após renovação, expires_at deve ser now() + 30d
        $freshToken = PersonalAccessToken::find($token->id);
        $expectedExpiry = Carbon::parse('2026-01-20 12:00:00')->addMinutes((int) config('sanctum.expiration', 43200));
        $this->assertEqualsWithDelta($expectedExpiry->timestamp, $freshToken->expires_at->timestamp, 60);

        Carbon::setTestNow();
    }

    /** @test */
    public function test_it_does_no_t_renew_when_more_than_5_days_remaining(): void
    {
        Carbon::setTestNow('2026-01-20 12:00:00');

        // Token expira em 10 dias — fora do buffer de 5 dias
        $originalExpiry = Carbon::parse('2026-01-30 12:00:00');
        $token = $this->createToken($originalExpiry);

        $renewed = $this->service->renewIfDue($token);

        $this->assertFalse($renewed);

        // expires_at não deve ter mudado
        $freshToken = PersonalAccessToken::find($token->id);
        $this->assertEqualsWithDelta($originalExpiry->timestamp, $freshToken->expires_at->timestamp, 5);

        Carbon::setTestNow();
    }

    /** @test */
    public function test_it_does_no_t_renew_tokens_without_expires_at(): void
    {
        // Token sem expiração (null) — não deve ser renovado (não se aplica)
        $token = $this->createToken(null);

        $renewed = $this->service->renewIfDue($token);

        $this->assertFalse($renewed);

        $freshToken = PersonalAccessToken::find($token->id);
        $this->assertNull($freshToken->expires_at);
    }

    /** @test */
    public function test_it_respects_custom_buffer_days_and_window(): void
    {
        Carbon::setTestNow('2026-01-20 12:00:00');

        // Token expira em 8 dias — fora do buffer padrão (5d), mas dentro de 10d custom
        $token = $this->createToken(Carbon::parse('2026-01-28 12:00:00'));

        // Com bufferDays=10 deve renovar; com janela customizada de 60d
        $renewed = $this->service->renewIfDue($token, bufferDays: 10, windowMinutes: 60 * 24 * 60);

        $this->assertTrue($renewed);

        $freshToken = PersonalAccessToken::find($token->id);
        $expectedExpiry = Carbon::parse('2026-01-20 12:00:00')->addMinutes(60 * 24 * 60);
        $this->assertEqualsWithDelta($expectedExpiry->timestamp, $freshToken->expires_at->timestamp, 60);

        Carbon::setTestNow();
    }

    /** @test */
    public function test_it_renews_already_expired_token(): void
    {
        Carbon::setTestNow('2026-01-20 12:00:00');

        // Token já expirado — ainda está dentro do buffer (negativo)
        $token = $this->createToken(Carbon::parse('2026-01-18 12:00:00'));

        $renewed = $this->service->renewIfDue($token);

        $this->assertTrue($renewed);

        Carbon::setTestNow();
    }

    /** @test */
    public function test_idempotency_renews_correctly_when_called_twice_in_buffer(): void
    {
        Carbon::setTestNow('2026-01-20 12:00:00');

        // Token expira em 2 dias
        $token = $this->createToken(Carbon::parse('2026-01-22 12:00:00'));

        // Primeira chamada: renova
        $first = $this->service->renewIfDue($token);
        $this->assertTrue($first);

        // Refresh model — agora expira em 30 dias (fora do buffer)
        $token = PersonalAccessToken::find($token->id);
        $second = $this->service->renewIfDue($token);
        $this->assertFalse($second);

        Carbon::setTestNow();
    }
}
