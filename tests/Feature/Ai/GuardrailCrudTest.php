<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US5 / G1 — CRUD de guardrails, escopo de tenant e cross-tenant 404.
 */
final class GuardrailCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('guardrail-admin', 'admin-clinica');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_creates_guardrail_scoped_to_tenant(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/guardrails'),
            [
                'name' => 'Sem promessas',
                'category' => 'restricoes_comerciais',
                'markdown_content' => "# Restrições\n\nNão prometer prazos de resultado.",
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Sem promessas')
            ->assertJsonPath('data.category', 'restricoes_comerciais')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('ai_guardrails', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Sem promessas',
        ]);
    }

    public function test_rejects_invalid_category(): void
    {
        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/guardrails'),
            ['name' => 'X', 'markdown_content' => '# Y', 'category' => 'categoria_invalida'],
        )->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_sanitizes_markdown_on_store(): void
    {
        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/guardrails'),
            ['name' => 'Com script', 'markdown_content' => "# Ok\n\n<script>alert(1)</script> texto."],
        )->assertCreated();

        $guardrail = AiGuardrail::first();
        $this->assertStringNotContainsString('<script>', (string) $guardrail->markdown_content);
    }

    public function test_is_active_is_prohibited_on_update(): void
    {
        $guardrail = AiGuardrail::factory()->forTenant($this->tenant)->create();

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/guardrails/{$guardrail->id}"),
            ['is_active' => false],
        )->assertStatus(422)->assertJsonValidationErrors('is_active');
    }

    public function test_activate_and_deactivate_endpoints(): void
    {
        $guardrail = AiGuardrail::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/guardrails/{$guardrail->id}/deactivate"),
        )->assertOk()->assertJsonPath('data.is_active', false);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/guardrails/{$guardrail->id}/activate"),
        )->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_cross_tenant_guardrail_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherGuardrail = AiGuardrail::factory()->forTenant($otherTenant)->create();

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/ai/guardrails/{$otherGuardrail->id}"),
        )->assertNotFound();
    }

    public function test_manage_requires_permission(): void
    {
        // 'medico' tem ai.guardrail.view mas NÃO ai.guardrail.manage.
        $medico = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($medico, ['*']);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/guardrails'),
            ['name' => 'X', 'markdown_content' => '# Y'],
        )->assertForbidden();
    }
}
