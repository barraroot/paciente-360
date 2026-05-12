<?php

namespace App\Http\Resources\V1;

use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T153 — Resource para ConversationAssignment (histórico de atribuições).
 *
 * @mixin ConversationAssignment
 */
final class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user' => $this->when(
                $this->relationLoaded('user') || $this->user_id !== null,
                fn () => [
                    'id' => $this->user?->id ?? $this->user_id,
                    'name' => $this->user?->name,
                ]
            ),
            'assigned_by' => $this->when(
                $this->assigned_by !== null,
                fn () => ['id' => $this->assigned_by]
            ),
            'assignment_role' => $this->assignment_role,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'unassigned_at' => $this->unassigned_at?->toIso8601String(),
            'transfer_note' => $this->transfer_note,
            'reason' => $this->reason,
        ];
    }
}
