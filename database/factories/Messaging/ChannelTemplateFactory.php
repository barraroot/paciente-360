<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `ChannelTemplate`.
 *
 * @extends Factory<ChannelTemplate>
 */
class ChannelTemplateFactory extends Factory
{
    protected $model = ChannelTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = implode('_', fake()->words(2));

        return [
            'provider_template_id' => 'HX'.Str::random(32),
            'meta_template_name' => $name,
            'meta_template_status' => fake()->randomElement(['approved', 'pending', 'rejected', 'paused']),
            'language' => 'pt_BR',
            'category' => fake()->randomElement(['marketing', 'utility', 'authentication', null]),
            'body_preview' => fake()->sentence(),
            'variables_schema' => [],
            'last_synced_at' => now(),
        ];
    }

    /**
     * Template aprovado (pré-requisito para envio fora da janela 24h).
     */
    public function approved(): static
    {
        return $this->state(['meta_template_status' => 'approved']);
    }

    /**
     * Template pendente de aprovação.
     */
    public function pending(): static
    {
        return $this->state(['meta_template_status' => 'pending']);
    }

    /**
     * Template com variáveis.
     *
     * @param array<int, array<string, string>> $schema
     */
    public function withVariables(array $schema = []): static
    {
        $schema = $schema ?: [
            ['key' => '1', 'label' => 'nome_paciente', 'type' => 'text'],
            ['key' => '2', 'label' => 'data_consulta', 'type' => 'text'],
        ];

        return $this->state(['variables_schema' => $schema]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
