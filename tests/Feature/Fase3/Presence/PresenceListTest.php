<?php

namespace Tests\Feature\Fase3\Presence;

use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T261 — Feature tests para GET /api/v1/inbox/presence (NC-6.b).
 *
 * Cobre: status online inferido (≤ 5min), exclusão de usuários sem inbox.view,
 * isolamento por tenant.
 */
class PresenceListTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-plist', 'atendente');
    }

    #[Test]
    public function list_returns_attendants_with_inferred_online_status_when_last_seen_within_5_min(): void
    {
        // Atendente online (3 min atrás)
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->create([
                'user_id' => $this->attendant->id,
                'last_seen_at' => now()->subMinutes(3),
                'status' => 'online',
            ]);

        $response = $this->getJson('/api/v1/inbox/presence');

        $response->assertStatus(200);
        $data = $response->json('data');

        $attendantEntry = collect($data)->firstWhere('user_id', $this->attendant->id);
        $this->assertNotNull($attendantEntry);
        $this->assertEquals('online', $attendantEntry['status']);
    }

    #[Test]
    public function list_returns_offline_when_last_seen_more_than_5_min_ago(): void
    {
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->create([
                'user_id' => $this->attendant->id,
                'last_seen_at' => now()->subMinutes(10),
                'status' => 'online',
            ]);

        $response = $this->getJson('/api/v1/inbox/presence');

        $response->assertStatus(200);
        $data = $response->json('data');

        $attendantEntry = collect($data)->firstWhere('user_id', $this->attendant->id);
        $this->assertNotNull($attendantEntry);
        $this->assertEquals('offline', $attendantEntry['status']);
    }

    #[Test]
    public function list_excludes_users_without_inbox_view_ability(): void
    {
        // Cria financeiro (sem inbox.view)
        $financeiro = $this->userForRole($this->tenant, 'financeiro');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->create([
                'user_id' => $financeiro->id,
                'last_seen_at' => now()->subMinutes(1),
            ]);

        $response = $this->getJson('/api/v1/inbox/presence');

        $response->assertStatus(200);
        $data = $response->json('data');

        $financeiroEntry = collect($data)->firstWhere('user_id', $financeiro->id);
        $this->assertNull($financeiroEntry, 'Financeiro não deve aparecer na lista de presença');
    }

    #[Test]
    public function list_is_isolated_per_tenant(): void
    {
        [$tenantB, $userB] = $this->tenantAndUserForRole('clinica-plist-b', 'atendente');

        // Cria presença para usuário do tenant B
        UserPresence::factory()
            ->forTenant($tenantB)
            ->create([
                'user_id' => $userB->id,
                'last_seen_at' => now()->subMinutes(1),
            ]);

        // Consulta como usuário do tenant A
        $this->actingAs($this->attendant);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->getJson('/api/v1/inbox/presence');

        $response->assertStatus(200);
        $data = $response->json('data');

        $userBEntry = collect($data)->firstWhere('user_id', $userB->id);
        $this->assertNull($userBEntry, 'Usuário de outro tenant não deve aparecer na lista');
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        auth()->logout();

        $response = $this->getJson('/api/v1/inbox/presence');

        $response->assertStatus(401);
    }
}
