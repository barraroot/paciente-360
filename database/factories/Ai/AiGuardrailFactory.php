<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGuardrail>
 */
class AiGuardrailFactory extends Factory
{
    protected $model = AiGuardrail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement([
                'seguranca', 'lgpd', 'atendimento_medico', 'encaminhamento',
                'tom_de_voz', 'restricoes_comerciais', 'emergencia', 'privacidade',
            ]),
            'markdown_content' => "# Guardrails\n\n## Restrições\n\nNão fornecer diagnóstico.",
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
