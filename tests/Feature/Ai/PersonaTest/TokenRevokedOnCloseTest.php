<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Events\Ai\Persona\PersonaTestSessionClosed;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G7 — Verifica que o PAT é revogado quando a sessão é fechada,
 * e que o evento PersonaTestSessionClosed é disparado.
 */
final class TokenRevokedOnCloseTest extends TestCase
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
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('persona-test-g7', 'admin-clinica');
        $this->admin->givePermissionTo('ai.persona.test');
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function pat_is_deleted_on_session_close(): void
    {
        Event::fake([PersonaTestSessionClosed::class]);

        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $sessionId = $openResponse->json('data.id');
        $mcpToken = $openResponse->json('data.mcp_token');

        // Extrai o token ID do formato "{id}|{raw_token}".
        [$tokenId] = explode('|', $mcpToken);

        // Verifica que PAT existe.
        $token = PersonalAccessToken::find($tokenId);
        $this->assertNotNull($token);

        // Fecha a sessão.
        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/close"))
            ->assertOk();

        // Verifica que PAT foi deletado.
        $this->assertNull(PersonalAccessToken::find($tokenId));

        // Verifica evento.
        Event::assertDispatched(PersonaTestSessionClosed::class);
    }

    #[Test]
    public function session_status_is_closed_with_timestamp(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $sessionId = $openResponse->json('data.id');

        $closeResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/close"))
            ->assertOk();

        $this->assertEquals('closed', $closeResponse->json('data.status'));
        $this->assertNotNull($closeResponse->json('data.closed_at'));
    }

    #[Test]
    public function mcp_token_id_is_null_after_close(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/close"));

        $this->assertDatabaseHas('persona_test_sessions', [
            'id' => $sessionId,
            'status' => 'closed',
            'mcp_token_id' => null,
        ]);
    }
}
