<?php

namespace App\Http\Resources\Prescriptions;

use App\Domain\Prescription\Alert\PrescriptionAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T111 — Resource de alerta de receita.
 *
 * @mixin PrescriptionAlert
 */
class PrescriptionAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_id' => $this->prescription_id,
            'alert_type' => $this->alert_type->value,
            'status' => $this->status->value,
            'scheduled_for' => $this->scheduled_for?->toDateString(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'skip_reason' => $this->skip_reason,
        ];
    }
}
