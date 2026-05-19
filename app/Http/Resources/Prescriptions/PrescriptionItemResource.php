<?php

namespace App\Http\Resources\Prescriptions;

use App\Domain\Prescription\PrescriptionItem\PrescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T067 — Resource de item de receita.
 *
 * @mixin PrescriptionItem
 */
class PrescriptionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_name' => $this->medication_name,
            'concentration' => $this->concentration,
            'pharmaceutical_form' => $this->pharmaceutical_form,
            'posology' => $this->posology,
            'quantity' => $this->quantity,
            'treatment_duration' => $this->treatment_duration,
            'sort_order' => $this->sort_order,
        ];
    }
}
