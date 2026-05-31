<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G4 — Isolamento por admin: usuários veem apenas suas próprias
 * sessões; acesso cruzado resulta em 404.
 */
final class IsolationBetweenAdminsTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminA;

    private User $adminB;

    private AiPersona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminA] = $this->tenantAndUserForRole('persona-test-g4', 'admin-clinica');
        $this->adminA->givePermissionTo('ai.persona.test');

        // Cria segundo admin no mesmo tenant.
        $this->adminB = User::factory()->for($this->tenant)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $this->adminB->givePermissionTo('ai.persona.test');

        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function admin_a_session_is_not_visible_to_admin_b(): void
    {
        // Admin A abre sessão.
        Sanctum::actingAs($this->adminA, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $sessionId = $openResponse->json('data.id');

        // Admin B lista suas sessões.
        Sanctum::actingAs($this->adminB, ['*']);

        $listResponse = $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/persona-test-sessions'))
            ->assertOk();

        $sessionIds = array_map(
            fn (array $item) => $item['id'],
            $listResponse->json('data') ?? []
        );

        $this->assertNotContains($sessionId, $sessionIds);
    }

    #[Test]
    public function admin_b_cannot_send_message_to_admin_a_session(): void
    {
        // Admin A abre sessão.
        Sanctum::actingAs($this->adminA, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        // Admin B tenta enviar mensagem.
        Sanctum::actingAs($this->adminB, ['*']);

        $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                [
                    'content_type' => 'text',
                    'text' => 'oi',
                ]
            )
            ->assertNotFound();
    }

    #[Test]
    public function admin_b_cannot_close_admin_a_session(): void
    {
        // Admin A abre sessão.
        Sanctum::actingAs($this->adminA, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        // Admin B tenta fechar.
        Sanctum::actingAs($this->adminB, ['*']);

        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/close"))
            ->assertNotFound();
    }

    #[Test]
    public function admin_lists_only_their_own_sessions(): void
    {
        // Admin A abre 2 sessões.
        Sanctum::actingAs($this->adminA, ['*']);

        $sessionIdA1 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->json('data.id');

        $sessionIdA2 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->json('data.id');

        // Admin B abre 1 sessão.
        Sanctum::actingAs($this->adminB, ['*']);

        $sessionIdB1 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->json('data.id');

        // Admin A lista.
        Sanctum::actingAs($this->adminA, ['*']);

        $listResponseA = $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/persona-test-sessions'))
            ->assertOk();

        $sessionIdsA = array_map(
            fn (array $item) => $item['id'],
            $listResponseA->json('data') ?? []
        );

        $this->assertContains($sessionIdA1, $sessionIdsA);
        $this->assertContains($sessionIdA2, $sessionIdsA);
        $this->assertNotContains($sessionIdB1, $sessionIdsA);
    }
}
