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
 * US6 / G3 — Usuários sem permission 'ai.persona.test' recebem 403.
 */
final class PermissionDeniedTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $unprivileged;

    private AiPersona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->unprivileged] = $this->tenantAndUserForRole('persona-test-g3', 'financeiro');
        // Financeiro NÃO tem ai.persona.test.
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function forbids_opening_test_session_without_permission(): void
    {
        Sanctum::actingAs($this->unprivileged, ['*']);

        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertForbidden();
    }

    #[Test]
    public function forbids_sending_message_without_permission(): void
    {
        // Usa um admin para abrir a sessão.
        $admin = User::factory()->for($this->tenant)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $admin->givePermissionTo('ai.persona.test');
        Sanctum::actingAs($admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $sessionId = $openResponse->json('data.id');

        // Agora muda para unprivileged.
        Sanctum::actingAs($this->unprivileged, ['*']);

        $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                [
                    'content_type' => 'text',
                    'text' => 'oi',
                ]
            )
            ->assertForbidden();
    }

    #[Test]
    public function forbids_listing_sessions_without_permission(): void
    {
        Sanctum::actingAs($this->unprivileged, ['*']);

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/persona-test-sessions'))
            ->assertForbidden();
    }

    #[Test]
    public function forbids_closing_session_without_permission(): void
    {
        // Admin abre.
        $admin = User::factory()->for($this->tenant)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $admin->givePermissionTo('ai.persona.test');
        Sanctum::actingAs($admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        // Unprivileged tenta fechar.
        Sanctum::actingAs($this->unprivileged, ['*']);

        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/close"))
            ->assertForbidden();
    }
}
