<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T039a — Cross-tenant token abuse (Princípio II + amendment v1.4.0).
 *
 * Valida que o middleware EnsureTenantSlugHeader bloqueia tentativas de
 * usar tokens de um tenant em rotas de outro tenant, e que falhas de
 * mismatch são auditadas via log estruturado.
 */
class CrossTenantTokenAbuseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $this->userA = User::factory()->for($this->tenantA)->create([
            'email' => 'dr@tenant-a.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
    }

    public function test_token_with_x_tenant_slug_mismatch_returns_403_tenant_mismatch(): void
    {
        $token = $this->userA->createToken('device-a');

        // User do tenant A apresenta token válido + X-Tenant-Slug de tenant B → 403
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'X-Tenant-Slug' => 'tenant-b',
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'tenant_mismatch']);
    }

    public function test_missing_x_tenant_slug_header_returns_400_tenant_header_required(): void
    {
        $token = $this->userA->createToken('device-a');

        // Rota autenticada que exige X-Tenant-Slug (me exige conforme OpenAPI)
        // Sem o header → 400 tenant_header_required
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(400)
            ->assertJsonFragment(['error' => 'tenant_header_required']);
    }

    public function test_matching_x_tenant_slug_passes_to_controller(): void
    {
        $token = $this->userA->createToken('device-a');

        // User do tenant A + X-Tenant-Slug=tenant-a → deve passar e retornar 200
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'X-Tenant-Slug' => 'tenant-a',
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $this->userA->id]);
    }

    public function test_cross_tenant_attempt_records_audit_log_with_executor_id(): void
    {
        $token = $this->userA->createToken('device-a');

        // Usa spy (permissivo) para validar apenas o warning de mismatch sem
        // bloquear outros logs que possam ocorrer na stack de middleware.
        Log::spy();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'X-Tenant-Slug' => 'tenant-b',
        ])->getJson('/api/v1/auth/me');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'auth.tenant_mismatch'
                    && isset($context['user_id'])
                    && $context['user_id'] === $this->userA->id;
            });
    }
}
