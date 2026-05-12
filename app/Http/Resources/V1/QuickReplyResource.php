<?php

namespace App\Http\Resources\V1;

use App\Domain\Messaging\QuickReply\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T239 — Resource para QuickReply conforme schema OpenAPI `QuickReplyResource`.
 *
 * @mixin QuickReply
 */
final class QuickReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->owner_user_id === null ? 'tenant' : 'private',
            'owner' => $this->when(
                $this->owner_user_id !== null,
                fn () => [
                    'id' => $this->owner_user_id,
                    'nome' => $this->whenLoaded('owner', fn () => $this->owner?->name),
                ]
            ),
            'shortcut' => $this->shortcut,
            'content' => $this->content,
            'variables_used' => $this->variables_used ?? [],
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
