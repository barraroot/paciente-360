<?php

declare(strict_types=1);

namespace App\Http\Resources\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KanbanPipelineMapping
 */
final class KanbanPipelineMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_kind' => $this->event_kind,
            'event_kind_label' => __('kanban.events.'.$this->event_kind),
            'funil_coluna' => $this->whenLoaded('funilColuna', fn () => [
                'id' => $this->funilColuna->id,
                'nome' => $this->funilColuna->nome,
                'slug' => $this->funilColuna->slug,
            ]),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
