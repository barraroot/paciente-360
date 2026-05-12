<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T038 — Factory para `AssignmentRule`.
 *
 * @extends Factory<AssignmentRule>
 */
class AssignmentRuleFactory extends Factory
{
    protected $model = AssignmentRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => null,
            'strategy' => fake()->randomElement(['round_robin', 'patient_owner', 'manual']),
            'priority' => fake()->numberBetween(1, 200),
            'is_active' => true,
            'config' => [],
        ];
    }

    /**
     * Estratégia round-robin.
     */
    public function roundRobin(): static
    {
        return $this->state([
            'strategy' => 'round_robin',
            'config' => ['exclude_user_ids' => []],
        ]);
    }

    /**
     * Estratégia patient_owner (paciente vai para profissional responsável).
     */
    public function patientOwner(): static
    {
        return $this->state([
            'strategy' => 'patient_owner',
            'config' => ['fallback_strategy' => 'round_robin'],
        ]);
    }

    /**
     * Regra inativa.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
