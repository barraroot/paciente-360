<?php

namespace App\Listeners\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Events\Prescription\ReceitaRenovada;

/**
 * T134 — Cancela alertas pending da receita original quando renovada (AC-8.2.3).
 *
 * Análogo ao CancelAlertScheduleOnCancellation (T099), mas consome
 * ReceitaRenovada para bloquear notificações da receita substituída.
 *
 * IMPORTANT: Auto-discovered pelo Laravel 13 via type-hint de handle().
 * NÃO registrar manualmente em AppServiceProvider.
 */
class CancelAlertScheduleOnRenewal
{
    public function handle(ReceitaRenovada $event): void
    {
        PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $event->oldPrescriptionId)
            ->where('status', AlertStatus::Pending->value)
            ->update(['status' => AlertStatus::Cancelled->value]);
    }
}
