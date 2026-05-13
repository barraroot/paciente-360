<?php

namespace Tests\Feature\Fase0\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature test do isolamento dos canais privados de broadcast (T046).
 *
 * Bate em `/broadcasting/auth` com payload `socket_id` + `channel_name` e
 * valida o veredito do callback registrado em `routes/channels.php`.
 *
 * Princípio II: User do tenant A NÃO pode autorizar canal do tenant B.
 *
 * Fase 4 Lote F (T062 — broadcasting Bearer): este teste foi migrado de
 * `actingAs($user)` (cookie-session) para Bearer Sanctum + X-Tenant-Slug,
 * antecipando o trabalho do Lote I (T083-T089) para manter a suite verde
 * após a troca de middleware de `/broadcasting/auth` para `auth:sanctum`.
 */
class ChannelAuthorizationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Em phpunit.xml o broadcaster default é `null` (no-op em auth).
        // Para validar os callbacks de `Broadcast::channel(...)` via
        // `/broadcasting/auth`, trocamos o default para `reverb`
        // (Pusher-compat) e re-carregamos `routes/channels.php` no novo
        // driver — caso contrário, os channels ficam registrados apenas
        // no `NullBroadcaster` original.
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app-id',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
            'client_options' => [],
        ]);

        require base_path('routes/channels.php');
    }

    /**
     * Constrói os headers Bearer + X-Tenant-Slug exigidos pela cadeia de
     * middleware `auth:sanctum` + `tenant.slug` do endpoint /broadcasting/auth.
     *
     * @return array<string, string>
     */
    private function bearerHeaders(User $user, Tenant $tenant): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test-broadcast')->plainTextToken,
            'X-Tenant-Slug' => $tenant->slug,
        ];
    }

    public function test_user_can_authorize_own_tenant_channel(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUserForTenant($tenant);

        $response = $this->withHeaders($this->bearerHeaders($user, $tenant))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-tenant.'.$tenant->id,
            ]);

        $response->assertOk();
    }

    public function test_user_cannot_authorize_other_tenant_channel(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $userA = $this->createUserForTenant($tenantA);

        // X-Tenant-Slug=A (próprio) + channel de tenantB → channel callback
        // rejeita (user.tenant_id !== tenantId do canal) → 403.
        $response = $this->withHeaders($this->bearerHeaders($userA, $tenantA))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-tenant.'.$tenantB->id,
            ]);

        $response->assertForbidden();
    }

    public function test_user_can_authorize_own_user_channel_within_tenant(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUserForTenant($tenant);

        $response = $this->withHeaders($this->bearerHeaders($user, $tenant))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$tenant->id}.user.{$user->id}",
            ]);

        $response->assertOk();
    }

    public function test_user_cannot_authorize_another_users_channel_in_same_tenant(): void
    {
        $tenant = $this->createTenant();
        $userA = $this->createUserForTenant($tenant);
        $userB = $this->createUserForTenant($tenant);

        $response = $this->withHeaders($this->bearerHeaders($userA, $tenant))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$tenant->id}.user.{$userB->id}",
            ]);

        $response->assertForbidden();
    }

    public function test_super_admin_without_tenant_id_cannot_subscribe_to_tenant_channel(): void
    {
        // Defesa em profundidade: Super Admin (tenant_id NULL) NÃO deve entrar
        // em canais privados de tenant — painel super-admin tem fluxo separado.
        //
        // Pós-Lote F: a rejeição passa a ocorrer ANTES do channel callback,
        // no middleware `tenant.slug` (super admin envia slug de algum tenant,
        // mas user.tenant_id=null !== tenant.id → 403 tenant_mismatch). O
        // efeito de bloqueio é equivalente (403 Forbidden).
        $tenant = $this->createTenant();
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        $response = $this->withHeaders($this->bearerHeaders($superAdmin, $tenant))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-tenant.'.$tenant->id,
            ]);

        $response->assertForbidden();
    }
}
