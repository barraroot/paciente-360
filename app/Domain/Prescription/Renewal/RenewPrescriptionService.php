<?php

namespace App\Domain\Prescription\Renewal;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Renewal\Exceptions\PrescriptionNotEligibleForRenewalException;
use App\Events\Prescription\ReceitaRenovada;
use App\Events\Prescription\RenovacaoSolicitadaPelaIA;
use App\Support\Metrics\PrescriptionMetricsContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T130 — Serviço de domínio para ciclo de renovação de receitas.
 *
 * Responsabilidades:
 *  - `initiate()` — cria PrescriptionRenewal pendente + emite evento.
 *  - `complete()` — sela a renovação com nova receita + emite ReceitaRenovada.
 *
 * Deferred:
 *  - Eventos `RenovacaoSolicitadaPeloMedico` / `RenovacaoSolicitadaPeloPaciente` —
 *    apenas `RenovacaoSolicitadaPelaIA` implementado no MVP. Os outros types
 *    emitem RenovacaoSolicitadaPelaIA como fallback temporário.
 *    TODO: criar eventos sibling quando Fase IA tiver spec concreta.
 *
 * @see specs/007-gestao-receituario/spec.md US-8.3, Q3
 */
class RenewPrescriptionService
{
    public function __construct(
        private readonly PrescriptionRenewalPolicyService $policyService,
        private readonly PrescriptionMetricsContract $metrics,
    ) {}

    /**
     * Inicia uma renovação de receita.
     *
     * @throws PrescriptionNotEligibleForRenewalException
     */
    public function initiate(
        Prescription $original,
        InitiatedByType $by,
        ?int $appointmentId = null,
        ?int $userId = null,
    ): PrescriptionRenewal {
        $reason = $this->policyService->reasonForDeniedRenewal($original);

        if ($reason !== null) {
            throw PrescriptionNotEligibleForRenewalException::forReason($reason);
        }

        $renewal = PrescriptionRenewal::create([
            'tenant_id' => $original->tenant_id,
            'original_prescription_id' => $original->id,
            'renewed_prescription_id' => null,
            'initiated_by' => $by,
            'appointment_id' => $appointmentId,
            'requested_by_user_id' => $userId,
        ]);

        Log::info('prescription.renewal.initiated', [
            'tenant_id' => $original->tenant_id,
            'prescription_id' => $original->id,
            'initiated_by' => $by->value,
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
        ]);

        // MVP: apenas InitiatedByType::Ai emite evento dedicado.
        // Outros tipos (Professional, Patient) usam o mesmo evento como fallback
        // até que a spec IA Matricial defina os contratos de RenovacaoSolicitadaPelo*.
        // TODO: criar RenovacaoSolicitadaPeloMedico + RenovacaoSolicitadaPeloPaciente
        //       quando spec futura definir contratos concretos.
        RenovacaoSolicitadaPelaIA::dispatch(
            prescriptionId: $original->id,
            patientId: $original->patient_id,
            professionalId: $original->professional_id,
            appointmentId: $appointmentId,
        );

        $this->metrics->renewalInitiated($original->tenant_id, $by);

        return $renewal;
    }

    /**
     * Completa uma renovação vinculando a nova receita e transitando a original.
     *
     * Executado em transação para rollback unificado.
     */
    public function complete(PrescriptionRenewal $renewal, Prescription $newPrescription): void
    {
        DB::transaction(function () use ($renewal, $newPrescription): void {
            $renewal->update(['renewed_prescription_id' => $newPrescription->id]);

            $renewal->originalPrescription()->update(['status' => PrescriptionStatus::Superseded]);

            $renewedAt = Carbon::now()->toIso8601String();

            Log::info('prescription.renewal.completed', [
                'tenant_id' => $newPrescription->tenant_id,
                'old_id' => $renewal->original_prescription_id,
                'new_id' => $newPrescription->id,
            ]);

            ReceitaRenovada::dispatch(
                oldPrescriptionId: $renewal->original_prescription_id,
                newPrescriptionId: $newPrescription->id,
                renewedAt: $renewedAt,
            );
        });
    }
}
