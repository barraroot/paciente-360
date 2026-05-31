<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G8 — Verifica que ao abrir segunda sessão da mesma (admin, persona),
 * a sessão anterior é auto-fechada com motivo 'superseded' e seu PAT revogado.
 */
final class SupersedeOpenSessionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private AiPersona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('persona-test-g8', 'admin-clinica');
        $this->admin->givePermissionTo('ai.persona.test');
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function second_session_closes_first_with_superseded_status(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        // Abre primeira sessão.
        $response1 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $sessionId1 = $response1->json('data.id');
        $mcpToken1 = $response1->json('data.mcp_token');
        [$tokenId1] = explode('|', $mcpToken1);

        // Abre segunda sessão.
        $response2 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $sessionId2 = $response2->json('data.id');

        // Verifica que sessão 1 foi fechada.
        $session1 = PersonaTestSession::find($sessionId1);
        $this->assertEquals('closed', $session1->status);
        $this->assertNotNull($session1->closed_at);

        // Verifica que sessão 2 está aberta.
        $session2 = PersonaTestSession::find($sessionId2);
        $this->assertEquals('open', $session2->status);
    }

    #[Test]
    public function first_session_pat_is_revoked_on_supersede(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        // Abre primeira sessão.
        $response1 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $mcpToken1 = $response1->json('data.mcp_token');
        [$tokenId1] = explode('|', $mcpToken1);

        // Verifica que PAT existe.
        $tokenBefore = PersonalAccessToken::find($tokenId1);
        $this->assertNotNull($tokenBefore);

        // Abre segunda sessão (isso deve fechar a primeira).
        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        // Verifica que PAT foi revogado.
        $tokenAfter = PersonalAccessToken::find($tokenId1);
        $this->assertNull($tokenAfter);
    }

    #[Test]
    public function only_one_open_session_per_admin_persona_pair(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        // Abre 3 sessões sequencialmente.
        $response1 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));
        $sessionId1 = $response1->json('data.id');

        $response2 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));
        $sessionId2 = $response2->json('data.id');

        $response3 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));
        $sessionId3 = $response3->json('data.id');

        // Apenas S3 deve estar aberta.
        $openSessions = PersonaTestSession::query()
            ->where('admin_user_id', $this->admin->id)
            ->where('persona_id', $this->persona->id)
            ->where('status', 'open')
            ->count();

        $this->assertEquals(1, $openSessions);

        // Verifica que apenas S3 está aberta.
        $this->assertEquals('open', PersonaTestSession::find($sessionId3)->status);
        $this->assertEquals('closed', PersonaTestSession::find($sessionId2)->status);
        $this->assertEquals('closed', PersonaTestSession::find($sessionId1)->status);
    }

    #[Test]
    public function supersede_works_across_different_personas(): void
    {
        $persona2 = AiPersona::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($this->admin, ['*']);

        // Abre sessão com persona 1.
        $response1 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));
        $sessionId1 = $response1->json('data.id');

        // Abre sessão com persona 2.
        $response2 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$persona2->id}/test-sessions"));
        $sessionId2 = $response2->json('data.id');

        // Ambas devem estar abertas (personas diferentes).
        $this->assertEquals('open', PersonaTestSession::find($sessionId1)->status);
        $this->assertEquals('open', PersonaTestSession::find($sessionId2)->status);

        // Abre segunda sessão com persona 1 (supersede apenas persona 1).
        $response3 = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));
        $sessionId3 = $response3->json('data.id');

        // Sessão 1 deve estar fechada, 2 e 3 abertas.
        $this->assertEquals('closed', PersonaTestSession::find($sessionId1)->status);
        $this->assertEquals('open', PersonaTestSession::find($sessionId2)->status);
        $this->assertEquals('open', PersonaTestSession::find($sessionId3)->status);
    }
}
