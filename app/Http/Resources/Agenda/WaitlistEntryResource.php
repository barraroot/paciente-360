<?php

namespace App\Http\Resources\Agenda;

use App\Models\Agenda\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T135 — WaitlistEntryResource (US-6.6).
 *
 * @mixin WaitlistEntry
 */
class WaitlistEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'professional_id' => $this->professional_id,
            'appointment_type_id' => $this->appointment_type_id,
            'status' => $this->status,
            'position' => $this->position,
            'notified_at' => $this->notified_at?->toIso8601String(),
            'notified_for_slot_starts_at' => $this->notified_for_slot_starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_appointment_id' => $this->accepted_appointment_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
