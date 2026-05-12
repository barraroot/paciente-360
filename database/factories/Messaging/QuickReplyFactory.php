<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T038 — Factory para `QuickReply`.
 *
 * @extends Factory<QuickReply>
 */
class QuickReplyFactory extends Factory
{
    protected $model = QuickReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => null,
            'shortcut' => '/'.fake()->unique()->word(),
            'content' => fake('pt_BR')->sentence(),
            'has_media' => false,
            'variables_used' => [],
            'usage_count' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Resposta rápida compartilhada no tenant (owner_user_id IS NULL).
     */
    public function tenantScope(): static
    {
        return $this->state(['owner_user_id' => null]);
    }

    /**
     * Resposta rápida privada do usuário especificado.
     */
    public function privateOf(User $user): static
    {
        return $this->state(['owner_user_id' => $user->id]);
    }

    /**
     * Com variáveis detectadas no conteúdo.
     */
    public function comVariaveis(): static
    {
        return $this->state([
            'content' => 'Olá {nome_paciente}, sua consulta é no dia {data_consulta}.',
            'variables_used' => ['nome_paciente', 'data_consulta'],
        ]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
