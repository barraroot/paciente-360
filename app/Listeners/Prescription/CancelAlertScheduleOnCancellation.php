<?php

namespace App\Listeners\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Events\Prescription\PrescricaoCancelada;

/**
 * T099 — Cancela alertas pendentes quando uma receita é cancelada (AC-8.2.3).
 *
 * Apenas alerts com status `pending` são cancelados.
 * Alerts `dispatched` (disparo já saiu) são preservados.
 *
 * IMPORTANTE: Não registrar manualmente em AppServiceProvider.
 * Laravel 13 auto-descobre via type-hint do método handle().
 * Veja CLAUDE.md → "Agendamento (Fase 5)" §5.
 */
class CancelAlertScheduleOnCancellation
{
    public function handle(PrescricaoCancelada $event): void
    {
        PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $event->prescriptionId)
            ->where('status', AlertStatus::Pending->value)
            ->update(['status' => AlertStatus::Cancelled->value]);
    }
}
