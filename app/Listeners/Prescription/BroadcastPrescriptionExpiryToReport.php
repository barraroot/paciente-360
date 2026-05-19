<?php

namespace App\Listeners\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescriptionsReportRefresh;
use App\Events\Prescription\ReceitaProximaDoVencimento;

/**
 * T101 — Broadcast de alerta de vencimento para o canal Reverb (AC-8.2 frontend).
 *
 * Emite evento `PrescriptionsReportRefresh` no canal `prescriptions.{tenant_id}`
 * sem dados clínicos — apenas prescription_id e criticality.
 *
 * IMPORTANTE: Não registrar manualmente em AppServiceProvider.
 * Laravel 13 auto-descobre via type-hint do método handle().
 */
class BroadcastPrescriptionExpiryToReport
{
    public function handle(ReceitaProximaDoVencimento $event): void
    {
        $prescription = Prescription::withoutGlobalScopes()->find($event->prescriptionId);

        if ($prescription === null) {
            return;
        }

        broadcast(new PrescriptionsReportRefresh(
            tenantId: $prescription->tenant_id,
            prescriptionId: $event->prescriptionId,
            criticality: $prescription->criticality(),
        ))->toOthers();
    }
}
