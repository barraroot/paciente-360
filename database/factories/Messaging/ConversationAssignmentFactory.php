<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T038 — Factory para `ConversationAssignment`.
 *
 * @extends Factory<ConversationAssignment>
 */
class ConversationAssignmentFactory extends Factory
{
    protected $model = ConversationAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'assigned_by' => null,
            'assignment_role' => null,
            'assigned_at' => now(),
            'unassigned_at' => null,
            'transfer_note' => null,
            'reason' => fake()->randomElement(['inicial', 'manual', 'transferencia', 'reassign_offline', 'auto_atribuicao']),
        ];
    }

    /**
     * Atribuição inicial (primeira vez que a conversa é atribuída).
     */
    public function inicial(): static
    {
        return $this->state(['reason' => 'inicial']);
    }

    /**
     * Transferência manual com nota obrigatória.
     */
    public function transferencia(string $note = 'Transferindo para especialista.'): static
    {
        return $this->state([
            'reason' => 'transferencia',
            'transfer_note' => $note,
        ]);
    }

    /**
     * Atribuição encerrada (sucedida por outra).
     */
    public function encerrada(): static
    {
        return $this->state([
            'unassigned_at' => now(),
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
