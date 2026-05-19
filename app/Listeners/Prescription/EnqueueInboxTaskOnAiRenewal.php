<?php

namespace App\Listeners\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Renewal\InitiatedByType;
use App\Events\Prescription\RenovacaoSolicitadaPelaIA;
use App\Support\Metrics\PrescriptionMetricsContract;
use Illuminate\Support\Facades\Log;

/**
 * T133 — Cria item de tarefa na Inbox para o médico emissor quando a IA
 * solicita renovação de receita (AC-8.3.7 / Q13).
 *
 * IMPORTANT: Auto-discovered pelo Laravel 13 via type-hint de handle().
 * NÃO registrar manualmente em AppServiceProvider.
 *
 * DEFERRED: Integração real com Inbox Fase 3 (`ConversationService::createForPatient`)
 * não está disponível nesta fase. MVP usa Log::info com payload completo + métrica.
 * TODO: Integrar com InboxTask real quando ConversationService suportar criação de
 *       tarefa médico (Q13 — "Renovação agendada pela IA — paciente {nome}").
 *       Referência: specs/007-gestao-receituario/spec.md Q13.
 */
class EnqueueInboxTaskOnAiRenewal
{
    public function __construct(
        private readonly PrescriptionMetricsContract $metrics,
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

        // MVP: Log estruturado em substituição à criação real de InboxTask.
        // Pail-compatible para debug em dev.
        Log::info('prescription.ai_renewal.inbox_task_requested', [
            'prescription_id' => $event->prescriptionId,
            'patient_id' => $event->patientId,
            'professional_id' => $event->professionalId,
            'appointment_id' => $event->appointmentId,
            'tenant_id' => $prescription->tenant_id,
            'note' => 'Inbox Fase 3 integration deferred — criar tarefa real ao médico emissor.',
        ]);

        // Métrica de renovação iniciada pela IA (T148).
        $this->metrics->renewalInitiated($prescription->tenant_id, InitiatedByType::Ai);
    }
}
