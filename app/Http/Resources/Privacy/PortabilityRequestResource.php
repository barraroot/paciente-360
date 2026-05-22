<?php

declare(strict_types=1);

namespace App\Http\Resources\Privacy;

use App\Domain\Privacy\Models\PortabilityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T054 (Fase 8 — Lote A US-13.2)** — Serialização de PortabilityRequest.
 *
 * **Não expõe** `file_path` nem `file_signed_url_id` (sensível) — apenas
 * sinaliza com `has_signed_url` quando aplicável. URL real vai ao paciente
 * via e-mail separado, NÃO por response da API.
 *
 * @property-read PortabilityRequest $resource
 */
class PortabilityRequestResource extends JsonResource
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
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'executed_at' => $this->resource->executed_at?->toIso8601String(),
            'executed_by_user_id' => $this->resource->executed_by_user_id,
            'file_size_bytes' => $this->resource->file_size_bytes,
            'has_signed_url' => $this->resource->status->isUrlActive() && ! $this->resource->signedUrlExpired(),
            'url_expires_at' => $this->resource->url_expires_at?->toIso8601String(),
            'downloaded_at' => $this->resource->downloaded_at?->toIso8601String(),
            'schema_version' => $this->resource->schema_version,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
