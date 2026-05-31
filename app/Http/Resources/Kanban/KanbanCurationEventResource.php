<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KanbanCurationEvent
 */
final class KanbanCurationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'event_kind' => $this->event_kind,
            'source' => $this->source,
            'from_coluna_id' => $this->from_coluna_id,
            'to_coluna_id' => $this->to_coluna_id,
            'applied' => (bool) $this->applied,
            'suppression_reason' => $this->suppression_reason,
            'field_changed' => $this->field_changed,
            'value_before' => $this->value_before,
            'value_after' => $this->value_after,
            'turn_version' => $this->turn_version,
            'actor_type' => $this->actor_type,
            'reason' => $this->reason,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
