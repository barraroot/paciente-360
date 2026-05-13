<?php

namespace App\Http\Resources\Agenda;

use App\Models\Agenda\SlotReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T081c — SlotReservationResource.
 *
 * @mixin SlotReservation
 */
class SlotReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'professional_id' => $this->professional_id,
            'appointment_type_id' => $this->appointment_type_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'holder_type' => $this->holder_type,
            'holder_id' => $this->holder_id,
            'acquired_at' => $this->acquired_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'release_reason' => $this->release_reason,
        ];
    }
}
