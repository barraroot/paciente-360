<?php

namespace App\Http\Resources\V1;

use App\Domain\Messaging\Conversation\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T118 — Resource de `Conversation` para API responses.
 *
 * Inclui window_24h computada via model methods.
 *
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => [
                'id' => $this->channel?->id,
                'type' => $this->channel?->type,
                'name' => $this->channel?->name,
            ],
            'patient' => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->nome,
            ],
            'status' => $this->status,
            'assigned_user' => [
                'id' => $this->assignedUser?->id,
                'name' => $this->assignedUser?->name,
            ],
            'assignment_strategy' => $this->assignment_strategy,
            'priority' => $this->priority,
            'ai_paused_until' => $this->ai_paused_until?->toIso8601String(),
            'ai_pause_set_by' => $this->ai_pause_set_by,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_inbound_message_at' => $this->last_inbound_message_at?->toIso8601String(),
            'last_message_preview' => $this->last_message_preview ?? null,
            'unread_count' => $this->unread_count,
            'window_24h' => [
                'open' => $this->isWindow24hOpen(),
                'seconds_remaining' => $this->secondsRemainingInWindow(),
            ],
            'resolution_mode' => $this->resolution_mode,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            // Feature 018 (Polish T205, FR-008b) — estado de cooldown anti-abuso.
            'cooldown_until' => $this->cooldown_until?->toIso8601String(),
            'cooldown_reason' => $this->cooldown_reason,
            'is_on_cooldown' => $this->isOnCooldown(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
