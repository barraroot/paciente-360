<?php

namespace Tests\Unit\Auth;

use App\Domain\Auth\Contracts\BearerAuthContract;
use App\Domain\Auth\Events\TokenEmitido;
use App\Domain\Auth\Services\TokenIssuerService;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T021 — Testes unitários do `TokenIssuerService` (TDD — escreve antes da impl).
 *
 * Cobre: emissão de token via Sanctum, hash SHA-256 no DB, disparo de
 * `TokenEmitido` com token_id_prefix, resolução de tenant por email.
 *
 * @see App\Domain\Auth\Services\TokenIssuerService
 * @see specs/004-token-auth-migration/spec.md §FR-001, §FR-021, §FR-023
 */
class TokenIssuerServiceTest extends TestCase
{
    use RefreshDatabase;

    private TokenIssuerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenIssuerService;
    }

    /** @test */
    public function test_it_issues_token_via_sanctum_with_correct_expiration(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        Carbon::setTestNow(now());

        $result = $this->service->issueToken($user, 'test-device');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('token_model', $result);
        $this->assertArrayHasKey('expires_at', $result);

        $this->assertInstanceOf(PersonalAccessToken::class, $result['token_model']);
        // Format: "<id>|paciente360_<random><crc32b>" — o prefix fica após o "<id>|"
        $this->assertStringContainsString('paciente360_', $result['token']);

        // Expira em ~30 dias (dentro de 1 minuto de tolerância)
        $expectedExpiration = now()->addMinutes((int) config('sanctum.expiration'));
        $this->assertEqualsWithDelta(
            $expectedExpiration->timestamp,
            $result['expires_at']->timestamp,
            60,
        );

        Carbon::setTestNow();
    }

    /** @test */
    public function test_it_stores_sha256_hash_in_db_not_plaintext(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $result = $this->service->issueToken($user, 'test-device');

        // NewAccessToken::plainTextToken = "<id>|<plainTextToken>"
        // plainTextToken (gerado pelo Sanctum) = "paciente360_<random40><crc32b>"
        // O DB armazena hash('sha256', plainTextToken) — sem o "<id>|" prefix.
        $fullPlainTextToken = $result['token'];

        // Remove o "<id>|" prefix para obter o plainTextToken original
        [, $plainTextToken] = explode('|', $fullPlainTextToken, 2);

        $dbToken = PersonalAccessToken::find($result['token_model']->id);

        // DB deve conter SHA-256 do plain text — nunca o plain text direto
        $this->assertEquals(hash('sha256', $plainTextToken), $dbToken->token);
        $this->assertNotEquals($plainTextToken, $dbToken->token);
    }

    /** @test */
    public function test_it_fires_token_emitido_event_with_token_id_prefix(): void
    {
        Event::fake([TokenEmitido::class]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->service->issueToken($user, 'test-device', ['*']);

        Event::assertDispatched(TokenEmitido::class, function (TokenEmitido $event) use ($user): bool {
            $this->assertEquals($user->id, $event->userId);
            $this->assertNotEmpty($event->tokenIdPrefix);
            $this->assertEquals(8, strlen($event->tokenIdPrefix));
            $this->assertIsArray($event->abilities);
            $this->assertContains('*', $event->abilities);

            return true;
        });
    }

    /** @test */
    public function test_it_resolves_tenant_by_email_uniquely(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->for($tenant)->create(['email' => 'joao@clinica.com']);

        $resolved = $this->service->resolveTenantByEmail('joao@clinica.com');

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->id);
    }

    /** @test */
    public function test_it_returns_null_when_email_not_found(): void
    {
        $resolved = $this->service->resolveTenantByEmail('naoexiste@example.com');

        $this->assertNull($resolved);
    }

    /** @test */
    public function test_it_implements_bearer_auth_contract(): void
    {
        $this->assertInstanceOf(BearerAuthContract::class, $this->service);
    }

    /** @test */
    public function test_it_respects_custom_abilities_in_issued_token(): void
    {
        Event::fake([TokenEmitido::class]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $abilities = ['inbox.view', 'paciente.view'];
        $result = $this->service->issueToken($user, 'restricted-device', $abilities);

        $this->assertEquals($abilities, $result['token_model']->abilities);

        Event::assertDispatched(TokenEmitido::class, function (TokenEmitido $event) use ($abilities): bool {
            $this->assertEquals($abilities, $event->abilities);

            return true;
        });
    }
}
