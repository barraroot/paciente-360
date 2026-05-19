<?php

namespace App\Http\Resources\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * T139 — Resource de Prescription para consumo da IA.
 *
 * GATE LGPD / Princípio I: apenas 7 campos explícitos da allowlist.
 * NUNCA deve conter: medication_name, posology, notes, concentration,
 * pharmaceutical_form ou qualquer outro dado clínico.
 *
 * Auditado pelo gate T123 (PrescriptionAiContextEndpointTest).
 *
 * @see specs/007-gestao-receituario/spec.md Q5
 * @see specs/007-gestao-receituario/contracts/openapi.yaml (PrescriptionForAi schema)
 *
 * @mixin Prescription
 */
class PrescriptionForAiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Prescription $prescription */
        $prescription = $this->resource;

        $today = Carbon::today();
        $expiresAt = $prescription->expires_at
            ? Carbon::parse($prescription->expires_at)->startOfDay()
            : null;

        $daysUntilExpiry = $expiresAt !== null
            ? (int) $today->diffInDays($expiresAt, false)
            : 0;

        // default_appointment_type_id: lê de tenant.settings.agenda.default_appointment_type_id
        $tenant = app('tenant');
        $defaultAppointmentTypeId = $tenant?->settings['agenda']['default_appointment_type_id'] ?? null;

        return [
            'prescription_id' => $prescription->id,
            'patient_id' => $prescription->patient_id,
            'professional_id' => $prescription->professional_id,
            'professional_name' => $prescription->professional?->name ?? '',
            'days_until_expiry' => $daysUntilExpiry,
            'prescription_type' => $prescription->type?->value,
            'default_appointment_type_id' => $defaultAppointmentTypeId,
        ];
    }
}
