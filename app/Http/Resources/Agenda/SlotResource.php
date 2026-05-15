<?php

namespace App\Http\Resources\Agenda;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T081b — SlotResource (US-6.5 — IA consome).
 *
 * Slot é um array associativo (não Eloquent), então recebe
 * {starts_at: Carbon, ends_at: Carbon, professional_id: int, appointment_type_id: int}.
 */
class SlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'starts_at' => $this->resource['starts_at']?->toIso8601String(),
            'ends_at' => $this->resource['ends_at']?->toIso8601String(),
            'professional_id' => $this->resource['professional_id'],
            'appointment_type_id' => $this->resource['appointment_type_id'],
        ];
    }
}
