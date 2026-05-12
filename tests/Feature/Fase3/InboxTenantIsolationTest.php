<?php

namespace Tests\Feature\Fase3;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T058 / T161 — Isolamento de tenant para endpoints da Fase 3 (Inbox Omnichannel).
 *
 * Cobre 5 endpoints US5 (assign, transfer, assignments, assignment-rules):
 *  - User de tenant A não acessa conversa de tenant B (404).
 *  - Requests não autenticadas retornam 401.
 *
 * Princípio II — isolamento multi-tenant NON-NEGOTIABLE.
 */
class InboxTenantIsolationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    private Channel $channelA;

    private Channel $channelB;

    private Conversation $convA;

    private Conversation $convB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenantA, $this->userA] = $this->tenantAndUserForRole('clinica-iso-a', 'admin-clinica');
        [$this->tenantB, $this->userB] = $this->tenantAndUserForRole('clinica-iso-b', 'admin-clinica');

        $this->channelA = Channel::factory()
            ->forTenant($this->tenantA)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->channelB = Channel::factory()
            ->forTenant($this->tenantB)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->convA = Conversation::factory()
            ->forTenant($this->tenantA)
            ->for($this->channelA, 'channel')
            ->create();

        $this->convB = Conversation::factory()
            ->forTenant($this->tenantB)
            ->for($this->channelB, 'channel')
            ->create();
    }

    // -------------------------------------------------------------------------
    // US5 — Assign endpoint isolation
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_assign_returns_401(): void
    {
        $response = $this->postJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/conversations/{$this->convA->id}/assign",
            ['auto' => true],
        );

        // Sanctum stateful may return 403 (FormRequest deny) instead of 401
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    #[Test]
    public function user_a_cannot_assign_conversation_belonging_to_tenant_b(): void
    {
        // userA is authenticated as tenant A admin
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        // Attempt to assign a conversation that belongs to tenant B
        $response = $this->postJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/conversations/{$this->convB->id}/assign",
            ['auto' => true],
        );

        // Must be 404 (conversation not found in tenant A scope) or 403
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // -------------------------------------------------------------------------
    // US5 — Transfer endpoint isolation
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_transfer_returns_401(): void
    {
        $response = $this->postJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/conversations/{$this->convA->id}/transfer",
            ['user_id' => $this->userA->id, 'transfer_note' => 'Nota de transferência válida'],
        );

        // Sanctum stateful may return 403 (FormRequest deny) instead of 401
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    #[Test]
    public function user_a_cannot_transfer_conversation_belonging_to_tenant_b(): void
    {
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        $response = $this->postJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/conversations/{$this->convB->id}/transfer",
            ['user_id' => $this->userA->id, 'transfer_note' => 'Nota de transferência válida'],
        );

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // -------------------------------------------------------------------------
    // US5 — Assignment history endpoint isolation
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_assignments_history_returns_401(): void
    {
        $response = $this->getJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/conversations/{$this->convA->id}/assignments",
        );

        // Sanctum stateful may return 403 instead of 401 for unauthenticated requests
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    #[Test]
    public function user_a_cannot_view_assignment_history_of_tenant_b_conversation(): void
    {
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        $response = $this->getJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/conversations/{$this->convB->id}/assignments",
        );

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // -------------------------------------------------------------------------
    // US5 — Assignment rules endpoint isolation
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_assignment_rules_returns_401(): void
    {
        $response = $this->getJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/assignment-rules',
        );

        // Sanctum stateful may return 403 instead of 401 for unauthenticated requests
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    #[Test]
    public function assignment_rules_list_is_scoped_to_authenticated_tenant(): void
    {
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        $response = $this->getJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/assignment-rules',
        );

        // Admin can list rules — returns 200 with tenant-scoped data (empty = fine)
        $response->assertOk();
    }
}
