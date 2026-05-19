<?php

namespace App\Http\Resources\Prescriptions;

use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T138 — Resource de PrescriptionRenewal.
 *
 * Expõe apenas os campos de junção — sem dados clínicos.
 *
 * @mixin PrescriptionRenewal
 */
class PrescriptionRenewalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_prescription_id' => $this->original_prescription_id,
            'renewed_prescription_id' => $this->renewed_prescription_id,
            'initiated_by' => $this->initiated_by?->value,
            'appointment_id' => $this->appointment_id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
