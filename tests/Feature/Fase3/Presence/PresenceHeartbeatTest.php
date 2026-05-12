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
 * T260 — Feature tests para POST /api/v1/inbox/presence/heartbeat (NC-6.b).
 *
 * Cobre: atualização de last_seen_at, criação de linha se não existir,
 * isolamento por tenant.
 */
class PresenceHeartbeatTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-presence', 'atendente');
    }

    #[Test]
    public function heartbeat_updates_last_seen_at_for_authenticated_user(): void
    {
        $oldTime = now()->subMinutes(10);

        // Cria presença existente com timestamp antigo
        UserPresence::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->attendant->id,
            'last_seen_at' => $oldTime,
        ]);

        $response = $this->postJson('/api/v1/inbox/presence/heartbeat');

        $response->assertStatus(204);

        $presence = UserPresence::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->attendant->id)
            ->first();

        $this->assertNotNull($presence);
        // last_seen_at deve ser MAIS RECENTE que o timestamp antigo (10 minutos atrás)
        $this->assertTrue(
            $presence->last_seen_at->greaterThan($oldTime),
            "last_seen_at deveria ser mais recente que {$oldTime->toIso8601String()}, mas é {$presence->last_seen_at->toIso8601String()}"
        );
    }

    #[Test]
    public function heartbeat_creates_row_if_not_exists(): void
    {
        $this->assertDatabaseMissing('messaging_user_presence', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->attendant->id,
        ]);

        $response = $this->postJson('/api/v1/inbox/presence/heartbeat');

        $response->assertStatus(204);

        $this->assertDatabaseHas('messaging_user_presence', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->attendant->id,
        ]);
    }

    #[Test]
    public function heartbeat_is_isolated_per_tenant_user(): void
    {
        [$tenantB, $userB] = $this->tenantAndUserForRole('clinica-presence-b', 'atendente');

        // Heartbeat do tenant B
        $response = $this->postJson('/api/v1/inbox/presence/heartbeat');
        $response->assertStatus(204);

        // Volta para tenant A
        $this->actingAs($this->attendant);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->postJson('/api/v1/inbox/presence/heartbeat');
        $response->assertStatus(204);

        // Cada tenant/usuário tem sua própria linha
        $this->assertDatabaseHas('messaging_user_presence', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->attendant->id,
        ]);

        $this->assertDatabaseHas('messaging_user_presence', [
            'tenant_id' => $tenantB->id,
            'user_id' => $userB->id,
        ]);
    }

    #[Test]
    public function unauthenticated_heartbeat_returns_401(): void
    {
        auth()->logout();

        $response = $this->postJson('/api/v1/inbox/presence/heartbeat');

        $response->assertStatus(401);
    }
}
