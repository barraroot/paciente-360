<?php

namespace App\Listeners\Agenda;

use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Events\Agenda\CancelamentoSolicitadoForaDoPrazo;
use App\Models\Agenda\Appointment;

/**
 * Feature 013 — Escalonamento de cancelamento fora do prazo (FR-010).
 *
 * Entrega via {@see OutboundNotificationDispatcher}; quando não houver canal/template
 * aprovado, o dispatcher roteia para contato manual (mensagem de sistema na conversa).
 *
 * Auto-discovered (Laravel 11+) — NÃO registrar manualmente.
 */
class EscalateCancellationOutsideWindowToInbox
{
    public function __construct(
        private readonly OutboundNotificationDispatcher $dispatcher,
    ) {}

    public function handle(CancelamentoSolicitadoForaDoPrazo $event): void
    {
        $appointment = $event->appointment;

        $this->dispatcher->dispatch(new NotificationRequest(
            tenantId: $appointment->tenant_id,
            patientId: $appointment->paciente_id,
            type: NotificationType::CancellationEscalation,
            milestone: 'escalation',
            sourceType: Appointment::class,
            sourceId: $appointment->id,
            professionalId: $appointment->professional_id,
        ));
    }
}
