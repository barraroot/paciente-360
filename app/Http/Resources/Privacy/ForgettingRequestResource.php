<?php

declare(strict_types=1);

namespace App\Http\Resources\Privacy;

use App\Domain\Privacy\Models\ForgettingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T054 (Fase 8 — Lote A US-13.2)** — Serialização de ForgettingRequest.
 *
 * @property-read ForgettingRequest $resource
 */
class ForgettingRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'patient_id' => $this->resource->patient_id,
            'requested_at' => $this->resource->requested_at->toIso8601String(),
            'deadline_at' => $this->resource->deadline_at->toIso8601String(),
            'days_until_deadline' => $this->resource->daysUntilDeadline(),
            'channel_of_request' => $this->resource->channel_of_request,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'is_terminal' => $this->resource->status->isTerminal(),
            'executed_at' => $this->resource->executed_at?->toIso8601String(),
            'executed_by_user_id' => $this->resource->executed_by_user_id,
            'fields_anonymized' => $this->resource->fields_anonymized,
            'fields_deleted' => $this->resource->fields_deleted,
            'fields_preserved_reason' => $this->resource->fields_preserved_reason,
            'denial_reason' => $this->resource->denial_reason,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
