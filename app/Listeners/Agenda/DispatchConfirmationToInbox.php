<?php

namespace App\Listeners\Agenda;

use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Events\Agenda\ConsultaConfirmacaoPendente;
use App\Models\Agenda\Appointment;
use Illuminate\Support\Facades\Log;

/**
 * Feature 013 — Entrega real da confirmação de consulta (T-24h/T-2h) ao paciente.
 *
 * Substitui o stub de log (Fase 5) pelo despacho via {@see OutboundNotificationDispatcher}.
 * Quando a IA assume (`viaIa=true`), a Fase 5 não envia template — a IA injeta a
 * pergunta no fluxo conversacional (clarify nº 6 / FR-018a).
 *
 * Auto-discovered (Laravel 11+) — NÃO registrar manualmente (lição Fase 5).
 *
 * @see specs/013-outbound-notifications/research.md §R11
 */
class DispatchConfirmationToInbox
{
    public function __construct(
        private readonly OutboundNotificationDispatcher $dispatcher,
    ) {}

    public function handle(ConsultaConfirmacaoPendente $event): void
    {
        if ($event->viaIa) {
            Log::info('agenda.confirmation.via_ia', [
                'appointment_id' => $event->appointment->id,
                'kind' => $event->kind,
            ]);

            return;
        }

        $appointment = $event->appointment;

        $this->dispatcher->dispatch(new NotificationRequest(
            tenantId: $appointment->tenant_id,
            patientId: $appointment->paciente_id,
            type: NotificationType::AppointmentConfirmation,
            milestone: $this->milestoneFor($event->kind),
            sourceType: Appointment::class,
            sourceId: $appointment->id,
            context: [
                'appointment_datetime' => "{$event->horarioBrasilia} ({$event->tzLabel})",
            ],
            professionalId: $appointment->professional_id,
        ));
    }

    private function milestoneFor(string $kind): string
    {
        return match ($kind) {
            '24h' => 't_minus_24h',
            '2h' => 't_minus_2h',
            default => $kind,
        };
    }
}
