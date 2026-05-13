<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T060 — Broadcasting auth via Bearer Sanctum (US3 — Reverb).
 *
 * Valida que POST /broadcasting/auth:
 *  (a) aceita Bearer Sanctum token (auth:sanctum) — retorna assinatura 200
 *  (b) rejeita request sem Bearer (cookie/session apenas) — 401
 *  (c) exige X-Tenant-Slug (FR-011 — triple-check)
 *  (d) bloqueia subscrição cross-tenant (Princípio II) — channel callback retorna false → 403
 *
 * O canal /broadcasting/auth é registrado em bootstrap/app.php (T061) com
 * middleware ['auth:sanctum', 'tenant.slug'].
 *
 * @see specs/004-token-auth-migration/spec.md §AC-A.3.x
 * @see routes/channels.php (channel callbacks)
 */
class BroadcastingAuthBearerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml força BROADCAST_CONNECTION=null. O null broadcaster NÃO
        // executa o channel callback (devolve sempre 200/empty), o que torna
        // impossível verificar 403 em callbacks que retornam false. Trocamos
        // para `pusher` com credenciais fake — a assinatura HMAC retornada é
        // suficiente para validar 200 + permite que verifyUserCanAccessChannel
        // lance AccessDeniedHttpException quando callback retorna false.
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => 'test-app-id',
                'options' => ['host' => '127.0.0.1', 'port' => 6001, 'scheme' => 'http', 'useTLS' => false],
            ],
        ]);
        Broadcast::purge('pusher');
        Broadcast::purge('null');

        // Channels foram registrados durante o boot contra o driver default
        // (`null`, conforme phpunit.xml). Após trocarmos o default para `pusher`
        // e purgar, o novo PusherBroadcaster nasce sem channels — precisa
        // reexecutar routes/channels.php para reattach na nova instância.
        require base_path('routes/channels.php');

        $this->tenantA = Tenant::factory()->create(['slug' => 'tenant-bcast-a']);
        $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-bcast-b']);

        $this->userA = User::factory()->for($this->tenantA)->create([
            'email' => 'dr-a@bcast.test',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $this->userB = User::factory()->for($this->tenantB)->create([
            'email' => 'dr-b@bcast.test',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
    }

    #[Test]
    public function broadcasting_auth_accepts_bearer_token_returns_200_signature(): void
    {
        $token = $this->userA->createToken('device-a')->plainTextToken;

        // O canal `tenant.{tenantId}.user.{userId}` autoriza quando
        // user.tenant_id === tenantId && user.id === userId.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $this->tenantA->slug,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$this->tenantA->id}.user.{$this->userA->id}",
        ]);

        $response->assertStatus(200);
        // O payload de Pusher é `{"auth":"<key>:<hmac>"}`. Validamos a presença
        // da chave `auth` em vez do valor exato (depende de REVERB_APP_KEY).
        $this->assertArrayHasKey('auth', $response->json());
    }

    #[Test]
    public function broadcasting_auth_rejects_request_without_bearer_returns_401(): void
    {
        // Nenhum Authorization header → auth:sanctum nega → 401.
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$this->tenantA->id}.user.{$this->userA->id}",
        ], [
            'X-Tenant-Slug' => $this->tenantA->slug,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function broadcasting_auth_rejects_missing_x_tenant_slug_returns_400(): void
    {
        $token = $this->userA->createToken('device-a')->plainTextToken;

        // Bearer válido mas sem X-Tenant-Slug → tenant.slug middleware nega → 400.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$this->tenantA->id}.user.{$this->userA->id}",
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['error' => 'tenant_header_required']);
    }

    #[Test]
    public function broadcasting_auth_rejects_x_tenant_slug_mismatch_returns_403(): void
    {
        $token = $this->userA->createToken('device-a')->plainTextToken;

        // Bearer do tenant A + X-Tenant-Slug do tenant B → tenant.slug middleware
        // rejeita com 403 tenant_mismatch antes mesmo de avaliar o channel callback.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $this->tenantB->slug,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$this->tenantB->id}.user.{$this->userB->id}",
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'tenant_mismatch']);
    }

    #[Test]
    public function cross_tenant_broadcast_subscription_blocked_403(): void
    {
        // Cenário Princípio II: user A do tenant A apresenta token válido +
        // X-Tenant-Slug correto (próprio tenant), mas tenta subscribir a um
        // canal cujo tenant_id é do tenant B (canal_name manipulado).
        //
        // Fluxo:
        //  1. auth:sanctum → OK (token válido)
        //  2. tenant.slug → OK (X-Tenant-Slug=tenant-a bate com user.tenant_id)
        //  3. Channel callback `tenant.{B}.user.{A}` → retorna false (user.tenant_id !== B)
        //  4. Broadcast lança AccessDeniedHttpException → 403
        $token = $this->userA->createToken('device-a')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $this->tenantA->slug,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$this->tenantB->id}.user.{$this->userA->id}",
        ]);

        $response->assertStatus(403);
    }
}
