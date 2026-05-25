<?php

namespace App\Listeners\Prescription;

use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Renewal\InitiatedByType;
use App\Events\Prescription\RenovacaoSolicitadaPelaIA;
use App\Support\Metrics\PrescriptionMetricsContract;
use Illuminate\Support\Facades\Log;

/**
 * Feature 013 — Renovação solicitada pela IA: entrega/roteia via dispatcher.
 *
 * Substitui o stub de log (Fase 7). O dispatcher entrega ao paciente quando há
 * canal/template aprovado, ou roteia para contato manual (mensagem de sistema na
 * conversa) — garantindo que a pendência seja visível ao médico emissor (Q13).
 *
 * Auto-discovered (Laravel 11+) — NÃO registrar manualmente.
 */
class EnqueueInboxTaskOnAiRenewal
{
    public function __construct(
        private readonly PrescriptionMetricsContract $metrics,
        private readonly OutboundNotificationDispatcher $dispatcher,
    ) {}

    public function handle(RenovacaoSolicitadaPelaIA $event): void
    {
        $prescription = Prescription::withoutTenantScope()
            ->with('patient')
            ->find($event->prescriptionId);

        if ($prescription === null) {
            Log::warning('prescription.ai_renewal.prescription_not_found', [
                'prescription_id' => $event->prescriptionId,
            ]);

            return;
        }

        $this->dispatcher->dispatch(new NotificationRequest(
            tenantId: $prescription->tenant_id,
            patientId: $event->patientId,
            type: NotificationType::AiRenewalTask,
            milestone: 'escalation',
            sourceType: Prescription::class,
            sourceId: $prescription->id,
            professionalId: $event->professionalId,
        ));

        $this->metrics->renewalInitiated($prescription->tenant_id, InitiatedByType::Ai);
    }
}
