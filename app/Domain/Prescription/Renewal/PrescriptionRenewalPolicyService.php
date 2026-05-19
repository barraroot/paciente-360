<?php

namespace App\Domain\Prescription\Renewal;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use Illuminate\Support\Carbon;

/**
 * T129 — Política de renovação de receitas.
 *
 * Avalia se uma receita pode ser renovada pela IA ou por profissional.
 *
 * Critérios (todos devem ser verdadeiros):
 *  1. status === active.
 *  2. expires_at <= today + 30 dias (janela D-30).
 *  3. Não existe PrescriptionRenewal com renewed_prescription_id preenchido
 *     (i.e., não foi renovada ainda).
 *
 * @see specs/007-gestao-receituario/spec.md Q3, US-8.3
 */
class PrescriptionRenewalPolicyService
{
    private const RENEWAL_WINDOW_DAYS = 30;

    public function canRenew(Prescription $prescription): bool
    {
        return $this->reasonForDeniedRenewal($prescription) === null;
    }

    /**
     * Retorna motivo de recusa, ou null se elegível.
     *
     * @return string|null — `prescription_not_active` | `prescription_outside_renewal_window`
     *                     | `prescription_already_renewed` | null
     */
    public function reasonForDeniedRenewal(Prescription $prescription): ?string
    {
        if ($prescription->status !== PrescriptionStatus::Active) {
            return 'prescription_not_active';
        }

        $windowLimit = Carbon::today()->addDays(self::RENEWAL_WINDOW_DAYS);
        if ($prescription->expires_at === null || $prescription->expires_at->gt($windowLimit)) {
            return 'prescription_outside_renewal_window';
        }

        $alreadyRenewed = PrescriptionRenewal::withoutTenantScope()
            ->where('original_prescription_id', $prescription->id)
            ->whereNotNull('renewed_prescription_id')
            ->exists();

        if ($alreadyRenewed) {
            return 'prescription_already_renewed';
        }

        return null;
    }
}
