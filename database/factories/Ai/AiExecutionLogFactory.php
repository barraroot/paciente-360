<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\Execution\Models\AiExecutionLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiExecutionLog>
 */
class AiExecutionLogFactory extends Factory
{
    protected $model = AiExecutionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel_type' => 'whatsapp',
            'correlation_id' => (string) Str::uuid(),
            'prompt_summary' => 'Pergunta do paciente (pseudonimizada).',
            'context_summary' => ['bases' => [], 'guardrails' => []],
            'classified_intent' => 'informacao_geral',
            'confidence_score' => 0.92,
            'response_summary' => 'Resposta gerada.',
            'action' => 'sent',
            'status' => 'success',
            'latency_ms' => fake()->numberBetween(300, 4000),
            'input_tokens' => fake()->numberBetween(50, 500),
            'output_tokens' => fake()->numberBetween(20, 300),
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'action' => null,
            'error_message' => 'provider_timeout',
            'response_summary' => null,
        ]);
    }

    public function escalated(): static
    {
        return $this->state([
            'status' => 'escalated',
            'action' => 'escalated_human',
        ]);
    }
}
