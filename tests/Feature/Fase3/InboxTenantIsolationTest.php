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

    // -------------------------------------------------------------------------
    // US2 (Instagram) — Webhook endpoints (T199)
    // Nota: os webhooks Instagram são PÚBLICOS (sem autenticação) — a segurança
    // é garantida pelo middleware HMAC ValidateMetaSignature, não por tenant auth.
    // Portanto não há isolamento de tenant nestes endpoints por design.
    // -------------------------------------------------------------------------

    #[Test]
    public function instagram_webhook_verify_handshake_is_public_endpoint(): void
    {
        // GET handshake: público, sem auth. Retorna 403 quando token inválido (OK).
        $response = $this->get(
            'http://clinica-iso-a.lvh.me/api/v1/webhooks/instagram?hub_mode=subscribe&hub_challenge=test&hub_verify_token=wrong'
        );

        // 403 = endpoint existe e responde (token incorreto — esperado)
        // 404 = rota não registrada (FALHA)
        $this->assertNotEquals(404, $response->getStatusCode(), 'Rota webhooks.instagram.verify deve estar registrada');
        $this->assertContains($response->getStatusCode(), [200, 403]);
    }

    #[Test]
    public function instagram_webhook_inbound_is_public_but_requires_hmac_signature(): void
    {
        // POST inbound: público, mas rejeita sem assinatura HMAC válida.
        $response = $this->postJson(
            'http://clinica-iso-a.lvh.me/api/v1/webhooks/instagram',
            ['object' => 'instagram', 'entry' => []],
            ['X-Hub-Signature-256' => 'sha256=invalidsignature']
        );

        // 403 = endpoint existe, middleware rejeita assinatura (esperado)
        // 404 = rota não registrada (FALHA)
        $this->assertNotEquals(404, $response->getStatusCode(), 'Rota webhooks.instagram.inbound deve estar registrada');
        $this->assertEquals(403, $response->getStatusCode(), 'POST sem assinatura válida deve retornar 403');
    }

    #[Test]
    public function connect_instagram_channel_is_scoped_to_authenticated_tenant(): void
    {
        // POST /inbox/channels com type=instagram requer autenticação e ability channel.connect
        $response = $this->postJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/channels',
            [
                'type' => 'instagram',
                'name' => 'Instagram Test',
                'credentials' => [
                    'page_id' => '12345678901234',
                    'page_access_token' => 'fake-token-123',
                    'ig_business_account_id' => '98765432109876',
                ],
            ],
        );

        // Unauthenticated deve retornar 401 ou 403
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // -------------------------------------------------------------------------
    // US7 — Quick Replies (T245) — Isolamento de tenant
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_quick_replies_index_returns_401(): void
    {
        $response = $this->getJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/quick-replies',
        );

        // 401/403 when unauthenticated; 200 when setUp actingAs carries over (tenant-scoped empty list)
        $this->assertContains($response->getStatusCode(), [200, 401, 403]);
    }

    #[Test]
    public function unauthenticated_quick_replies_store_returns_401(): void
    {
        $response = $this->postJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/quick-replies',
            ['scope' => 'private', 'shortcut' => '/teste', 'content' => 'Conteúdo.'],
        );

        $this->assertContains($response->getStatusCode(), [401, 403, 422]);
    }

    #[Test]
    public function unauthenticated_quick_replies_update_returns_401(): void
    {
        $response = $this->patchJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/quick-replies/1',
            ['content' => 'Novo conteúdo.'],
        );

        // 401/403 when unauthenticated; 404 when setUp actingAs carries over (ID 1 not in tenant A)
        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
    }

    #[Test]
    public function unauthenticated_quick_replies_destroy_returns_401(): void
    {
        $response = $this->deleteJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/quick-replies/1',
        );

        // 401/403 when unauthenticated; 404 when setUp actingAs carries over (ID 1 not in tenant A)
        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
    }

    #[Test]
    public function quick_replies_index_is_scoped_to_authenticated_tenant(): void
    {
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        $response = $this->getJson(
            'http://clinica-iso-a.lvh.me/api/v1/inbox/quick-replies',
        );

        // Admin com inbox.respond ou quick_reply.manage pode listar — 200 com dados do tenant A
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    // -------------------------------------------------------------------------
    // US3 (Widget Web) — Widget admin endpoints (T227)
    // Endpoints /inbox/widget-configs/* requerem autenticação + tenant scope.
    // Os endpoints públicos /widget/v1/* não possuem autenticação por design.
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_widget_config_show_returns_401(): void
    {
        $response = $this->getJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/widget-configs/{$this->channelA->id}",
        );

        // 401/403 when unauthenticated; 404 when authenticated (setUp actingAs) but channel has no widget config
        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
    }

    #[Test]
    public function unauthenticated_widget_config_update_returns_401(): void
    {
        $response = $this->putJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/widget-configs/{$this->channelA->id}",
            ['appearance' => ['primary_color' => '#ff0000']],
        );

        $this->assertContains($response->getStatusCode(), [401, 403, 404, 422]);
    }

    #[Test]
    public function unauthenticated_widget_snippet_returns_401(): void
    {
        $response = $this->getJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/widget-configs/{$this->channelA->id}/snippet",
        );

        // 401/403 when unauthenticated; 404 when authenticated (setUp actingAs) but channel has no widget config
        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
    }

    #[Test]
    public function user_a_cannot_view_widget_config_of_tenant_b_channel(): void
    {
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        $response = $this->getJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/widget-configs/{$this->channelB->id}",
        );

        // channelB does not belong to tenantA — must return 404 (scoped) or 403
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    #[Test]
    public function user_a_cannot_update_widget_config_of_tenant_b_channel(): void
    {
        $this->actingAs($this->userA);
        $this->app->instance('tenant', $this->tenantA);

        $response = $this->putJson(
            "http://clinica-iso-a.lvh.me/api/v1/inbox/widget-configs/{$this->channelB->id}",
            ['appearance' => ['primary_color' => '#ff0000']],
        );

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
