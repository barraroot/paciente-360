<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Services\AiContextBuilderService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US3 / G9 / SC-011 — o prompt enviado ao LLM é pseudonimizado (sem PII).
 */
final class AiPseudonymizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_message_is_scrubbed_before_llm(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $tenant);
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($tenant)->create(['ai_model_id' => $model->id]);
        $persona->setRelation('guardrails', collect());

        $builder = app(AiContextBuilderService::class);

        $message = 'Meu CPF é 123.456.789-09 e meu telefone (11) 99999-8888.';
        $context = $builder->build($persona, $message);

        $this->assertStringNotContainsString('123.456.789-09', $context->prompt);
        $this->assertStringNotContainsString('99999-8888', $context->prompt);
        // As instruções incluem os guardrails mínimos obrigatórios.
        $this->assertStringContainsString('diagnóstico', $context->instructions);
    }
}
