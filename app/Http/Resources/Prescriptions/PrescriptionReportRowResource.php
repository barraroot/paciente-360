<?php

namespace App\Http\Resources\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Domain\Prescription\Report\ControlledPrescriptionMaskingService;
use App\Events\Prescription\PrescricaoControladaVisualizada;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * T161 — Resource de linha do relatório de receitas.
 *
 * Campos adicionais vs PrescriptionResource:
 *  - `criticality`: 'green' | 'yellow' | 'red' (computado)
 *  - `is_renewed`: bool (true quando renewed_from_id != null)
 *
 * Mascaramento de controladas idêntico ao PrescriptionResource (Q8).
 *
 * @mixin Prescription
 */
class PrescriptionReportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Prescription $prescription */
        $prescription = $this->resource;
        /** @var User|null $user */
        $user = $request->user();

        if ($prescription->type === PrescriptionType::Controlled && $user !== null) {
            return $this->toArrayWithMasking($prescription, $user, $request);
        }

        return $this->toArrayFull($prescription);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArrayWithMasking(Prescription $prescription, User $user, Request $request): array
    {
        $service = new ControlledPrescriptionMaskingService;
        $data = $service->mask($prescription, $user);

        if (! ($data['masked'] ?? false)) {
            PrescricaoControladaVisualizada::dispatch(
                actorUserId: $user->id,
                prescriptionId: $prescription->id,
                tenantId: $prescription->tenant_id,
                viewedAt: Carbon::now(),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return array_merge($data, [
            'id' => $prescription->id,
            'tenant_id' => $prescription->tenant_id,
            'appointment_id' => $prescription->appointment_id,
            'renewed_from_id' => $prescription->renewed_from_id,
            'is_renewed' => $prescription->renewed_from_id !== null,
            'criticality' => $prescription->criticality(),
            'created_at' => $prescription->created_at?->toIso8601String(),
            'updated_at' => $prescription->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArrayFull(Prescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'tenant_id' => $prescription->tenant_id,
            'patient_id' => $prescription->patient_id,
            'professional_id' => $prescription->professional_id,
            'appointment_id' => $prescription->appointment_id,
            'renewed_from_id' => $prescription->renewed_from_id,
            'is_renewed' => $prescription->renewed_from_id !== null,
            'type' => $prescription->type?->value,
            'status' => $prescription->status?->value,
            'issued_at' => $prescription->issued_at?->toDateString(),
            'expires_at' => $prescription->expires_at?->toDateString(),
            'criticality' => $prescription->criticality(),
            'notes' => $prescription->notes,
            'masked' => false,
            'cancellation_reason_category' => $prescription->cancellation_reason_category?->value,
            'cancellation_reason' => $prescription->cancellation_reason,
            'cancelled_at' => $prescription->cancelled_at?->toIso8601String(),
            'created_at' => $prescription->created_at?->toIso8601String(),
            'updated_at' => $prescription->updated_at?->toIso8601String(),
        ];
    }
}
