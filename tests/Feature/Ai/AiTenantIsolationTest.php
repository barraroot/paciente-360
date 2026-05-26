<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Distribution\Services\AiPersonaSelectorService;
use App\Domain\Ai\Execution\Models\AiExecutionLog;
use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Polish / G1 consolidado / Princípio II — clínica A nunca lê/acessa dados de B,
 * em TODAS as entidades ai_*, nem recebe persona de B numa seleção.
 */
final class AiTenantIsolationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenantA] = $this->tenantAndUserForRole('tenant-a', 'admin-clinica');
        $this->tenantB = Tenant::factory()->create();
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenantA->slug];
    }

    public function test_personas_index_and_show_are_tenant_isolated(): void
    {
        $model = AiModel::factory()->create();
        AiPersona::factory()->forTenant($this->tenantA)->create(['ai_model_id' => $model->id]);
        $foreign = AiPersona::factory()->forTenant($this->tenantB)->create(['ai_model_id' => $model->id]);

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, '/ai/personas'))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, "/ai/personas/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_knowledge_bases_are_tenant_isolated(): void
    {
        AiKnowledgeBase::factory()->forTenant($this->tenantA)->create();
        $foreign = AiKnowledgeBase::factory()->forTenant($this->tenantB)->create();

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, '/ai/knowledge-bases'))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, "/ai/knowledge-bases/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_guardrails_are_tenant_isolated(): void
    {
        AiGuardrail::factory()->forTenant($this->tenantA)->create();
        $foreign = AiGuardrail::factory()->forTenant($this->tenantB)->create();

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, '/ai/guardrails'))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, "/ai/guardrails/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_execution_logs_are_tenant_isolated(): void
    {
        AiExecutionLog::factory()->create(['tenant_id' => $this->tenantA->id]);
        $foreign = AiExecutionLog::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, '/ai/execution-logs'))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenantA, "/ai/execution-logs/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_cannot_associate_foreign_base_or_guardrail_to_persona(): void
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenantA)->create(['ai_model_id' => $model->id]);
        $foreignBase = AiKnowledgeBase::factory()->forTenant($this->tenantB)->create();
        $foreignGuardrail = AiGuardrail::factory()->forTenant($this->tenantB)->create();

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenantA, "/ai/personas/{$persona->id}/knowledge-bases"),
            ['knowledge_base_ids' => [$foreignBase->id]],
        )->assertStatus(422);

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenantA, "/ai/personas/{$persona->id}/guardrails"),
            ['guardrail_ids' => [$foreignGuardrail->id]],
        )->assertStatus(422);
    }

    public function test_selector_never_returns_a_persona_from_another_tenant(): void
    {
        // Apenas a clínica B tem persona ativa no canal whatsapp.
        $model = AiModel::factory()->create();
        $this->app->instance('tenant', $this->tenantB);
        $personaB = AiPersona::factory()->forTenant($this->tenantB)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenantB->id,
            'ai_persona_id' => $personaB->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        // A clínica A não tem persona → seleção retorna null (não vaza a de B).
        $this->app->instance('tenant', $this->tenantA);
        $selected = app(AiPersonaSelectorService::class)
            ->selectForNewConversation($this->tenantA->id, 'whatsapp');

        $this->assertNull($selected);
    }
}
