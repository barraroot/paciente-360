<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G6 — Verifica que draft persona é capturado no snapshot,
 * sem modificar a persona persistida.
 */
final class DraftPersonaTest extends TestCase
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
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('persona-test-g6', 'admin-clinica');
        $this->admin->givePermissionTo('ai.persona.test');
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create([
            'name' => 'Persona Original',
            'markdown_content' => '# Original Content',
        ]);
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function opens_session_with_draft_persona(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $draftContent = [
            'name' => 'Draft Persona Test',
            'markdown_content' => '# Draft Content',
            'tone' => 'formal',
            'objective' => 'Draft objective',
            'limitations' => 'Draft limitations',
            'initial_message' => 'Olá draft!',
            'fallback_message' => 'Draft fallback',
            'handoff_rules' => 'Draft handoff',
            'voice_id' => null,
            'ai_model_id' => $this->persona->ai_model_id,
        ];

        $response = $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"),
                [
                    'use_draft' => true,
                    'persona_draft' => $draftContent,
                ]
            )
            ->assertCreated();

        $snapshot = $response->json('data.persona_snapshot');
        $this->assertEquals('Draft Persona Test', $snapshot['name']);
        $this->assertEquals('# Draft Content', $snapshot['markdown_content']);
        $this->assertEquals('formal', $snapshot['tone']);
    }

    #[Test]
    public function original_persona_is_not_modified(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $draftContent = [
            'name' => 'Modified Name',
            'markdown_content' => '# Modified Content',
            'tone' => 'aggressive',
            'objective' => 'Modified objective',
            'limitations' => 'Modified limitations',
            'initial_message' => 'Oi modificado!',
            'fallback_message' => 'Modified fallback',
            'handoff_rules' => 'Modified handoff',
            'voice_id' => null,
            'ai_model_id' => $this->persona->ai_model_id,
        ];

        $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"),
                [
                    'use_draft' => true,
                    'persona_draft' => $draftContent,
                ]
            );

        // Reload persona from DB.
        $refreshed = AiPersona::find($this->persona->id);
        $this->assertEquals('Persona Original', $refreshed->name);
        $this->assertEquals('# Original Content', $refreshed->markdown_content);
    }

    #[Test]
    public function opens_session_with_published_persona_when_no_draft(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"),
                [
                    'use_draft' => false,
                ]
            )
            ->assertCreated();

        $snapshot = $response->json('data.persona_snapshot');
        $this->assertEquals('Persona Original', $snapshot['name']);
        $this->assertEquals('# Original Content', $snapshot['markdown_content']);
    }
}
