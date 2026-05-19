<?php

namespace App\Listeners\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescricaoCancelada;
use App\Events\Prescription\PrescricaoCriada;
use App\Models\EventoTimeline;

/**
 * T058 — Projeta eventos de receita na timeline do paciente (AC-8.1.10).
 *
 * Consome `PrescricaoCriada` e `PrescricaoCancelada`, gravando entradas
 * em `eventos_timeline` para exibição na ficha do paciente (Fase 2).
 *
 * Alertas (`ReceitaProximaDoVencimento`, `ReceitaVencida`) NÃO são projetados
 * — preserva timeline limpa conforme Q11.
 *
 * IMPORTANTE: Não registrar manualmente em AppServiceProvider.
 * Laravel 13 auto-descobre via type-hint dos métodos handle* abaixo.
 * Veja CLAUDE.md → "Agendamento (Fase 5)" §5.
 */
class ProjectPrescriptionToPatientTimeline
{
    /**
     * Projeta criação de receita na timeline do paciente.
     */
    public function handleCriada(PrescricaoCriada $event): void
    {
        EventoTimeline::create([
            'tenant_id' => $event->tenantId,
            'paciente_id' => $event->patientId,
            'tipo' => 'receita.criada',
            'autor_id' => $event->professionalId,
            'actor_type' => 'user',
            'payload' => [
                'prescription_id' => $event->prescriptionId,
                'type' => $event->type,
                'issued_at' => $event->issuedAt->toDateString(),
                'expires_at' => $event->expiresAt->toDateString(),
            ],
            'referencia_tipo' => Prescription::class,
            'referencia_id' => $event->prescriptionId,
        ]);
    }

    /**
     * Projeta cancelamento de receita na timeline do paciente.
     * A receita cancelada permanece visível na timeline (Q11c).
     */
    public function handleCancelada(PrescricaoCancelada $event): void
    {
        EventoTimeline::create([
            'tenant_id' => $event->tenantId,
            'paciente_id' => $event->patientId,
            'tipo' => 'receita.cancelada',
            'autor_id' => $event->cancelledByUserId,
            'actor_type' => 'user',
            'payload' => [
                'prescription_id' => $event->prescriptionId,
                'cancellation_reason_category' => $event->categoryReason,
                'cancelled_at' => $event->cancelledAt->toIso8601String(),
            ],
            'referencia_tipo' => Prescription::class,
            'referencia_id' => $event->prescriptionId,
        ]);
    }
}
