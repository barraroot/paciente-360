<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Public;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T229 (Fase 8 — Lote D US-11.2 + R-8-4)** — Prescription via API pública.
 *
 * Controladas SEMPRE mascaradas (independente do scope do token).
 *
 * @mixin Prescription
 */
class PrescriptionPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'type' => $this->type,
            'patient_id' => $this->patient_id,
            'professional_id' => $this->professional_id,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->type === 'controlled') {
            $base['masked'] = true;
            $base['note'] = 'Receita controlada (Portaria 344/98) — itens e observações não expostos via API pública.';

            return $base;
        }

        $base['items'] = $this->items?->map(fn ($i) => [
            'medication_name' => $i->medication_name,
            'posology' => $i->posology,
        ])->toArray();

        return $base;
    }
}
