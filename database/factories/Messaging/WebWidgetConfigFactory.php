<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `WebWidgetConfig`.
 *
 * @extends Factory<WebWidgetConfig>
 */
class WebWidgetConfigFactory extends Factory
{
    protected $model = WebWidgetConfig::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_key' => Str::random(64),
            'allowed_origins' => [],
            'appearance' => [
                'primary_color' => fake()->hexColor(),
                'position' => fake()->randomElement(['bottom-right', 'bottom-left']),
                'button_label' => 'Falar conosco',
            ],
            'initial_message' => 'Olá! Como posso ajudar?',
            'business_hours' => [
                'monday' => '08:00-18:00',
                'tuesday' => '08:00-18:00',
                'wednesday' => '08:00-18:00',
                'thursday' => '08:00-18:00',
                'friday' => '08:00-18:00',
                'saturday' => null,
                'sunday' => null,
                'timezone' => 'America/Sao_Paulo',
            ],
            'outside_hours_behavior' => 'fila',
            'pre_chat_form' => 'opcional',
            'outside_hours_message' => null,
        ];
    }

    /**
     * Widget com origens restritas.
     *
     * @param array<int, string> $origins
     */
    public function withAllowedOrigins(array $origins): static
    {
        return $this->state(['allowed_origins' => $origins]);
    }

    /**
     * Widget que bloqueia fora do horário comercial.
     */
    public function bloqueiaForaDoHorario(): static
    {
        return $this->state([
            'outside_hours_behavior' => 'bloqueia',
            'outside_hours_message' => 'Nosso atendimento funciona de segunda a sexta, das 08h às 18h.',
        ]);
    }

    /**
     * Formulário pré-chat obrigatório para enviar.
     */
    public function formObrigatorio(): static
    {
        return $this->state(['pre_chat_form' => 'exigido_para_enviar']);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
