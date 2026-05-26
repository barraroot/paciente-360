<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US1 / G1 — CRUD de personas, isolamento multi-tenant e regras de modelo.
 */
final class PersonaCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('ai-persona-admin', 'admin-clinica');
        $this->model = AiModel::factory()->create();
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'ai_model_id' => $this->model->id,
            'name' => 'Recepção Geral',
            'markdown_content' => "# Identidade\n\nAtendente da clínica.",
            'tone' => 'cordial',
            'model_settings' => ['temperature' => 0.5, 'max_tokens' => 1024],
        ], $overrides);
    }

    public function test_creates_persona_scoped_to_tenant(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/personas'),
            $this->validPayload(),
        );

        $response->assertCreated()->assertJsonPath('data.name', 'Recepção Geral');

        $this->assertDatabaseHas('ai_personas', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Recepção Geral',
            'is_active' => true,
        ]);
    }

    public function test_rejects_inactive_model_on_create(): void
    {
        $inactive = AiModel::factory()->inactive()->create();

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/personas'),
            $this->validPayload(['ai_model_id' => $inactive->id]),
        );

        $response->assertStatus(422)->assertJsonValidationErrors('ai_model_id');
    }

    public function test_rejects_settings_outside_schema(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/personas'),
            $this->validPayload(['model_settings' => ['temperature' => 9]]),
        );

        $response->assertStatus(422)->assertJsonValidationErrors('model_settings.temperature');
    }

    public function test_sanitizes_markdown_on_save(): void
    {
        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/personas'),
            $this->validPayload(['markdown_content' => "# Oi\n\n<script>alert(1)</script>"]),
        )->assertCreated();

        $persona = AiPersona::first();
        $this->assertStringNotContainsString('<script', $persona->markdown_content);
    }

    public function test_cross_tenant_persona_is_not_found(): void
    {
        $tenantB = $this->createTenant(['slug' => 'ai-persona-b']);
        $personaB = AiPersona::factory()->forTenant($tenantB)->create(['ai_model_id' => $this->model->id]);

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, "/ai/personas/{$personaB->id}"))
            ->assertNotFound();
    }

    public function test_index_lists_only_current_tenant_personas(): void
    {
        AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $this->model->id]);
        $tenantB = $this->createTenant(['slug' => 'ai-persona-b2']);
        AiPersona::factory()->forTenant($tenantB)->create(['ai_model_id' => $this->model->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/personas'));

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_update_cannot_change_is_active(): void
    {
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $this->model->id]);

        $this->withHeaders($this->headers())
            ->putJson($this->tenantUrl($this->tenant, "/ai/personas/{$persona->id}"), [
                'name' => 'Novo Nome',
                'is_active' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');
    }

    public function test_activate_and_deactivate_endpoints(): void
    {
        $persona = AiPersona::factory()->forTenant($this->tenant)->create([
            'ai_model_id' => $this->model->id,
            'is_active' => false,
        ]);

        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$persona->id}/activate"))
            ->assertOk()->assertJsonPath('data.is_active', true);

        $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$persona->id}/deactivate"))
            ->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_models_endpoint_returns_active_models(): void
    {
        AiModel::factory()->inactive()->create();

        $response = $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/models'));

        $response->assertOk();
        foreach ($response->json('data') as $model) {
            // Só ativos (nenhuma persona referencia o inativo recém-criado).
            $this->assertTrue($model['is_active']);
        }
    }

    public function test_requires_manage_permission_to_create(): void
    {
        // financeiro não tem ai.persona.manage.
        [$tenant] = $this->tenantAndUserForRole('ai-persona-fin', 'financeiro');

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->postJson($this->tenantUrl($tenant, '/ai/personas'), [
                'ai_model_id' => $this->model->id,
                'name' => 'X',
                'markdown_content' => '# x',
            ])
            ->assertForbidden();
    }
}
