<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConversationAssignment>
 */
class AiConversationAssignmentFactory extends Factory
{
    protected $model = AiConversationAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'conversation_id' => Conversation::factory(),
            'channel_type' => 'whatsapp',
            'ai_persona_id' => AiPersona::factory(),
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'metadata' => [],
        ];
    }

    public function paused(): static
    {
        return $this->state([
            'status' => AiConversationAssignment::STATUS_PAUSED,
            'paused_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => AiConversationAssignment::STATUS_CLOSED,
            'unassigned_at' => now(),
        ]);
    }
}
