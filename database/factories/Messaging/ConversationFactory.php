<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T038 — Factory para `Conversation`.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $threadId = '+55'.fake()->numerify('##9########');

        return [
            'external_thread_id' => $threadId,
            'status' => 'aberta',
            'assigned_user_id' => null,
            'assigned_at' => null,
            'assignment_strategy' => null,
            'ai_paused_until' => null,
            'ai_pause_set_by' => null,
            'last_message_at' => now()->subMinutes(fake()->numberBetween(1, 1440)),
            'last_inbound_message_at' => now()->subMinutes(fake()->numberBetween(1, 1440)),
            'opened_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'resolved_at' => null,
            'resolution_mode' => null,
            'priority' => 'normal',
            'received_outside_hours' => false,
            'unread_count' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Conversa com status "aberta".
     */
    public function aberta(): static
    {
        return $this->state([
            'status' => 'aberta',
            'resolved_at' => null,
        ]);
    }

    /**
     * Conversa com status "pendente".
     */
    public function pendente(): static
    {
        return $this->state([
            'status' => 'pendente',
            'resolved_at' => null,
        ]);
    }

    /**
     * Conversa com status "resolvida".
     */
    public function resolvida(): static
    {
        return $this->state([
            'status' => 'resolvida',
            'resolved_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'resolution_mode' => 'manual',
        ]);
    }

    /**
     * Janela 24h aberta (última mensagem do paciente há 30 minutos).
     */
    public function comJanela24hAberta(): static
    {
        return $this->state([
            'last_inbound_message_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Janela 24h fechada (última mensagem do paciente há 25 horas).
     */
    public function comJanela24hFechada(): static
    {
        return $this->state([
            'last_inbound_message_at' => now()->subHours(25),
        ]);
    }

    /**
     * Conversa sem paciente identificado (lead anônimo).
     */
    public function semPaciente(): static
    {
        return $this->state(['patient_id' => null]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
