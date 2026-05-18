<?php

namespace App\Domain\Prescription\Report;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\User;

/**
 * T053 — Serviço de mascaramento de receitas controladas (Q8).
 *
 * Quando um usuário sem `prescription.view_controlled` tenta acessar
 * uma receita controlada da qual não é emissor, os campos clínicos
 * (medication_name, posology, notes) são omitidos do payload.
 *
 * Campos sempre visíveis (coordenação operacional — Q8a):
 *  - id, type, status, issued_at, expires_at, patient_id, professional_id
 *  - masked=true, masked_reason='controlled_access_restricted'
 *
 * Campos omitidos quando mascarado:
 *  - items (medication_name, posology, concentration, pharmaceutical_form...)
 *  - notes
 *
 * @see specs/007-gestao-receituario/spec.md Q8
 */
class ControlledPrescriptionMaskingService
{
    /**
     * Retorna array de representação da receita, mascarando campos clínicos
     * quando o viewer não tem acesso ao conteúdo de controladas.
     *
     * Regras (Q8 da spec):
     *  - Apenas o EMISSOR pode ver conteúdo completo (independente de ability).
     *  - Admin Clínica com `prescription.view_controlled` pode ver completo.
     *  - Outros médicos (mesmo com `view_controlled`) veem mascarado.
     *  - Atendente/Recepcionista sempre mascarado.
     *
     * @return array<string, mixed>
     */
    public function mask(Prescription $prescription, User $viewer): array
    {
        // Receitas não-controladas nunca são mascaradas
        if ($prescription->type !== PrescriptionType::Controlled) {
            return $this->fullArray($prescription);
        }

        $isEmissor = $prescription->professional_id === $viewer->id;
        // Admin Clínica com view_controlled tem acesso completo (Q8b)
        $isAdminWithControl = $viewer->hasRole('admin-clinica')
            && $viewer->can('prescription.view_controlled');

        if ($isEmissor || $isAdminWithControl) {
            return $this->fullArray($prescription);
        }

        return $this->maskedArray($prescription);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullArray(Prescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'type' => $prescription->type?->value ?? $prescription->type,
            'status' => $prescription->status?->value ?? $prescription->status,
            'issued_at' => $prescription->issued_at?->toDateString(),
            'expires_at' => $prescription->expires_at?->toDateString(),
            'patient_id' => $prescription->patient_id,
            'professional_id' => $prescription->professional_id,
            'notes' => $prescription->notes,
            'masked' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function maskedArray(Prescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'type' => $prescription->type?->value ?? $prescription->type,
            'status' => $prescription->status?->value ?? $prescription->status,
            'issued_at' => $prescription->issued_at?->toDateString(),
            'expires_at' => $prescription->expires_at?->toDateString(),
            'patient_id' => $prescription->patient_id,
            'professional_id' => $prescription->professional_id,
            'masked' => true,
            'masked_reason' => 'controlled_access_restricted',
        ];
    }
}
