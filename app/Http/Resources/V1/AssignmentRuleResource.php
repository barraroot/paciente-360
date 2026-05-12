<?php

namespace App\Http\Resources\V1;

use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T153 — Resource para AssignmentRule.
 *
 * @mixin AssignmentRule
 */
final class AssignmentRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'strategy' => $this->strategy,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'config' => $this->config,
            'channel_id' => $this->channel_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
