<?php

namespace Tests\Feature\Fase4\Auth;

use App\Domain\Auth\Events\LoginFalhouViaToken;
use App\Domain\Auth\Events\TokenEmitido;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T035 — Login emite Bearer token (AC-A.1.1, AC-A.1.2, AC-A.1.5, FR-024).
 *
 * Cobre o fluxo completo do endpoint POST /api/v1/auth/login na variante
 * Bearer: emissão de token, rejeição de credenciais, bloqueio de conta
 * após 5 tentativas falhas, isolamento por IP e resolução de tenant por email.
 */
class LoginEmitsTokenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'clinica-teste']);
        $this->user = User::factory()->for($this->tenant)->create([
            'email' => 'dr@clinica-teste.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
    }

    public function test_login_success_returns_token_with_user_and_tenant(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'token_expires_at',
                'user' => ['id', 'name', 'email'],
                'tenant' => ['id', 'slug', 'name'],
            ])
            ->assertJsonFragment(['id' => $this->user->id])
            ->assertJsonFragment(['slug' => $this->tenant->slug]);

        // Token formato: "{id}|paciente360_{random}" — o prefixo paciente360_ aparece após o "id|"
        $this->assertStringContainsString('paciente360_', $response->json('token'));
    }

    public function test_login_rejects_invalid_credentials_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['error' => 'invalid_credentials']);

        // Mensagem deve ser genérica — não pode revelar se email existe
        $this->assertStringNotContainsString('email', strtolower($response->json('message') ?? ''));
    }

    public function test_login_blocks_after_5_failed_attempts_returns_423(): void
    {
        // Bypass throttle para testar apenas o account lock (5 senhas erradas → 423).
        // O throttle:login retornaria 429 após 5 requests, mas o account lock é mecanismo diferente.
        $this->withoutMiddleware(ThrottleRequests::class);

        // 5 tentativas com senha errada devem bloquear a conta
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $this->user->email,
                'password' => 'wrong-password',
            ]);
        }

        // 6ª tentativa deve retornar 423 account_locked (conta bloqueada — mesmo com senha correta)
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(423)
            ->assertJsonFragment(['error' => 'account_locked']);
    }

    public function test_login_locked_until_timestamp_returned_in_423_response(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $this->user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(423);
        $lockedUntil = $response->json('locked_until');
        $this->assertNotNull($lockedUntil);

        // Valida formato ISO 8601
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $lockedUntil,
        );
    }

    public function test_login_rate_limit_isolated_per_ip(): void
    {
        // IP A — 5 tentativas com senha errada bloqueiam a conta deste user
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
                ->postJson('/api/v1/auth/login', [
                    'email' => $this->user->email,
                    'password' => 'wrong',
                ]);
        }

        // Criar segundo user no mesmo tenant (IP B — não afetado pelo lock do user1)
        $user2 = User::factory()->for($this->tenant)->create([
            'email' => 'dr2@clinica-teste.com',
            'password' => Hash::make('secret456'),
            'status' => 'active',
        ]);

        // IP B com user diferente — deve ter sucesso (lock é por conta, não por IP global)
        $response = $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
            ->postJson('/api/v1/auth/login', [
                'email' => $user2->email,
                'password' => 'secret456',
            ]);

        $response->assertStatus(201);
    }

    public function test_login_resolves_tenant_via_email_lookup_globally_unique(): void
    {
        // Login sem informar X-Tenant-Slug — backend resolve o tenant via email
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['slug' => $this->tenant->slug])
            ->assertJsonFragment(['id' => $this->user->id]);
    }

    public function test_login_records_token_emitido_audit(): void
    {
        Event::fake([TokenEmitido::class]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ])->assertStatus(201);

        Event::assertDispatched(TokenEmitido::class, function (TokenEmitido $event): bool {
            return $event->userId === $this->user->id;
        });
    }

    public function test_login_failure_records_login_falhou_via_token_audit(): void
    {
        Event::fake([LoginFalhouViaToken::class]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        Event::assertDispatched(LoginFalhouViaToken::class);
    }

    public function test_token_expires_at_is_30d_from_now(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ])->assertStatus(201);

        $expiresAt = $response->json('token_expires_at');
        $this->assertNotNull($expiresAt);

        $parsed = Carbon::parse($expiresAt);
        $expected = now()->addDays(30);

        // Tolerância de ±2 minutos para evitar flakiness
        $this->assertEqualsWithDelta(
            $expected->timestamp,
            $parsed->timestamp,
            120,
            'token_expires_at deve ser ~30 dias a partir de agora',
        );
    }

    /**
     * Lote I — restaurar invariant FR-005: tenant suspenso bloqueia login com 403.
     * (Regressão introduzida em Lote D que rewrote LoginController sem a check.)
     */
    public function test_login_rejects_suspended_tenant_403(): void
    {
        $this->tenant->forceFill(['status' => 'suspended'])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'tenant_suspended']);
    }

    /**
     * Lote I — usuário desativado retorna 401 genérico (não vaza status,
     * preserva timing constante vs senha errada). FR-032.
     */
    public function test_login_rejects_disabled_user_401(): void
    {
        $this->user->forceFill(['status' => 'disabled'])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['error' => 'invalid_credentials']);
    }
}
