<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Services\AiGuardrailEnforcer;
use App\Domain\Ai\WorkContext\Models\AiWorkContext;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T125b — Persona context respects lead vs existing patient (FR-011b, C1).
 *
 * Verifica que o guardrail enforcer injeta instruções diferentes baseado
 * se o interlocutor é um lead (novo) ou um paciente já cadastrado.
 *
 * @group persona-context
 */
final class PersonaContextRespectsLeadVsExistingPatientTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AiPersona $persona;

    private AiWorkContext $workContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar AiModel primeiro
        $aiModel = AiModel::factory()->create();

        // Criar persona
        $this->persona = AiPersona::create([
            'tenant_id' => $this->tenant->id,
            'ai_model_id' => $aiModel->id,
            'name' => 'Test Persona',
            'slug' => 'test-persona',
            'description' => 'Test',
            'markdown_content' => 'Test persona body',
            'system_prompt' => 'You are a helpful assistant.',
            'is_active' => true,
        ]);

        // Criar work context
        $this->workContext = AiWorkContext::create([
            'tenant_id' => $this->tenant->id,
            'content' => 'Clinic info: open 9-18',
        ]);
    }

    /**
     * @test
     */
    public function test_prompt_for_lead_does_not_contain_existing_patient_instruction(): void
    {
        $enforcer = app(AiGuardrailEnforcer::class);

        $instructions = $enforcer->composeInstructions(
            $this->persona,
            [], // rag
            null, // summary
            isLead: true
        );

        // Verify: prompt NÃO contém "Paciente já cadastrado"
        $this->assertStringNotContainsString(
            'já cadastrado',
            $instructions,
            'Prompt for lead should NOT mention existing patient'
        );

        // Verify: prompt tem instruções da persona (markdown_content)
        $this->assertStringContainsString(
            'Test persona body',
            $instructions,
            'Prompt should contain persona markdown_content'
        );
    }

    /**
     * @test
     */
    public function test_prompt_for_existing_patient_contains_instruction(): void
    {
        $enforcer = app(AiGuardrailEnforcer::class);

        $instructions = $enforcer->composeInstructions(
            $this->persona,
            [], // rag
            null, // summary
            isLead: false
        );

        // Verify: prompt CONTÉM "Paciente já cadastrado"
        $this->assertStringContainsString(
            'já cadastrado',
            $instructions,
            'Prompt for existing patient should mention existing patient status'
        );

        // Verify: contém instrução de não refazer qualificação
        $this->assertStringContainsString(
            'NÃO refaça qualificação',
            $instructions,
            'Prompt should instruct not to requalify existing patient'
        );

        // Verify: persona markdown_content still present
        $this->assertStringContainsString(
            'Test persona body',
            $instructions
        );
    }

    /**
     * @test
     */
    public function test_null_islead_does_not_inject_instruction(): void
    {
        $enforcer = app(AiGuardrailEnforcer::class);

        $instructions = $enforcer->composeInstructions(
            $this->persona,
            [], // rag
            null, // summary
            isLead: null // Default behavior
        );

        // Verify: no existing patient instruction
        $this->assertStringNotContainsString(
            'já cadastrado',
            $instructions
        );
    }

    /**
     * @test
     */
    public function test_instruction_is_deterministic(): void
    {
        $enforcer = app(AiGuardrailEnforcer::class);

        // Call twice with same params
        $instructions1 = $enforcer->composeInstructions(
            $this->persona,
            [],
            null,
            isLead: false
        );

        $instructions2 = $enforcer->composeInstructions(
            $this->persona,
            [],
            null,
            isLead: false
        );

        // Verify: identical
        $this->assertEquals($instructions1, $instructions2);
    }
}
