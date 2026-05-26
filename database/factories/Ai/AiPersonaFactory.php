<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPersona>
 */
class AiPersonaFactory extends Factory
{
    protected $model = AiPersona::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ai_model_id' => AiModel::factory(),
            'name' => fake()->randomElement(['Recepção Geral', 'Agendamento', 'Pré-triagem']).' '.fake()->unique()->numerify('##'),
            'description' => fake()->sentence(),
            'markdown_content' => "# Identidade da Persona\n\nAtendente virtual da clínica.",
            'tone' => 'cordial e objetivo',
            'objective' => 'Auxiliar o paciente com informações e agendamento.',
            'limitations' => 'Não fornece orientação clínica.',
            'initial_message' => 'Olá! Como posso ajudar?',
            'fallback_message' => 'Não entendi, pode reformular?',
            'handoff_rules' => 'Encaminhar para humano em dúvida clínica.',
            'model_settings' => ['temperature' => 0.5, 'max_tokens' => 1024],
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
