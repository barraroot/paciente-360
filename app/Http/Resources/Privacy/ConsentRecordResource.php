<?php

declare(strict_types=1);

namespace App\Http\Resources\Privacy;

use App\Domain\Privacy\Models\ConsentRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T030 (Fase 8 — Lote A US-13.1)** — Serialização de ConsentRecord.
 *
 * **Não expõe** o `evidence_snapshot` completo na resposta — apenas referência
 * (`evidence_message_id`) e snippet (primeiros 80 chars). Snapshot completo
 * fica disponível apenas via export auditado (AC-13.1.6).
 *
 * @property-read ConsentRecord $resource
 */
class ConsentRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'patient_id' => $this->resource->patient_id,
            'channel' => $this->resource->channel,
            'finalidade' => $this->resource->finalidade->value,
            'finalidade_label' => $this->resource->finalidade->label(),
            'state' => $this->resource->state->value,
            'is_active' => $this->resource->is_active,
            'granted_at' => $this->resource->granted_at?->toIso8601String(),
            'revoked_at' => $this->resource->revoked_at?->toIso8601String(),
            'evidence_message_id' => $this->resource->evidence_message_id,
            'evidence_excerpt' => $this->buildEvidenceExcerpt(),
            'terms_version' => $this->resource->terms_version,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }

    private function buildEvidenceExcerpt(): ?string
    {
        $snapshot = $this->resource->evidence_snapshot;
        $text = is_array($snapshot) && isset($snapshot['text']) && is_string($snapshot['text'])
            ? $snapshot['text']
            : null;

        if ($text === null) {
            return null;
        }

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 80).'…' : $text;
    }
}
