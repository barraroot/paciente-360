<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Services\AiGuardrailEnforcer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US5 / G7 / G8 / SC-004 — guardrails ativos da clínica somam-se ao piso de
 * segurança obrigatório; guardrails inativos não são aplicados; sem guardrails
 * da clínica, os mínimos continuam presentes.
 */
final class GuardrailApplicationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('guardrail-app', 'admin-clinica');
    }

    private function persona(): AiPersona
    {
        $model = AiModel::factory()->create();

        return AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
    }

    public function test_minimal_guardrails_present_without_any_clinic_guardrail(): void
    {
        $persona = $this->persona();
        $persona->setRelation('guardrails', collect());

        $instructions = app(AiGuardrailEnforcer::class)->composeInstructions($persona);

        $this->assertStringContainsString('diagnóstico', $instructions);
        $this->assertStringContainsString('emergência', $instructions);
    }

    public function test_active_guardrail_is_applied_inactive_is_not(): void
    {
        $persona = $this->persona();

        $active = AiGuardrail::factory()->forTenant($this->tenant)->create([
            'name' => 'Regra Ativa',
            'markdown_content' => 'NUNCA mencionar concorrentes.',
            'is_active' => true,
        ]);
        $inactive = AiGuardrail::factory()->forTenant($this->tenant)->create([
            'name' => 'Regra Inativa',
            'markdown_content' => 'Texto que NAO deve aparecer no prompt.',
            'is_active' => false,
        ]);

        $persona->guardrails()->sync($persona->pivotTenantMap([$active->id, $inactive->id]));
        $persona->load('guardrails');

        $instructions = app(AiGuardrailEnforcer::class)->composeInstructions($persona);

        // Mínimos sempre presentes + guardrail ativo aplicado.
        $this->assertStringContainsString('diagnóstico', $instructions);
        $this->assertStringContainsString('NUNCA mencionar concorrentes.', $instructions);
        // Guardrail inativo NÃO aplicado (SC-004).
        $this->assertStringNotContainsString('NAO deve aparecer', $instructions);
    }

    public function test_associates_guardrails_to_persona(): void
    {
        $persona = $this->persona();
        $g1 = AiGuardrail::factory()->forTenant($this->tenant)->create();
        $g2 = AiGuardrail::factory()->forTenant($this->tenant)->create();

        $this->withHeaders(['X-Tenant-Slug' => $this->tenant->slug])->putJson(
            $this->tenantUrl($this->tenant, "/ai/personas/{$persona->id}/guardrails"),
            ['guardrail_ids' => [$g1->id, $g2->id]],
        )->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('ai_persona_guardrail', [
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'ai_guardrail_id' => $g1->id,
        ]);
    }

    public function test_cannot_associate_other_tenant_guardrail(): void
    {
        $persona = $this->persona();
        $otherTenant = Tenant::factory()->create();
        $foreign = AiGuardrail::factory()->forTenant($otherTenant)->create();

        $this->withHeaders(['X-Tenant-Slug' => $this->tenant->slug])->putJson(
            $this->tenantUrl($this->tenant, "/ai/personas/{$persona->id}/guardrails"),
            ['guardrail_ids' => [$foreign->id]],
        )->assertStatus(422)->assertJsonValidationErrors('guardrail_ids');

        $this->assertDatabaseMissing('ai_persona_guardrail', [
            'ai_persona_id' => $persona->id,
            'ai_guardrail_id' => $foreign->id,
        ]);
    }
}
