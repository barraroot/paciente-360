<?php

namespace App\Listeners\Agenda;

use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Events\Agenda\LimiteDeReagendamentoExcedido;
use App\Models\Agenda\Appointment;

/**
 * Feature 013 — Escalonamento de limite de reagendamento excedido (FR-010).
 *
 * Entrega via {@see OutboundNotificationDispatcher}; sem canal/template aprovado,
 * roteia para contato manual.
 *
 * Auto-discovered (Laravel 11+) — NÃO registrar manualmente.
 */
class EscalateRescheduleLimitExceededToInbox
{
    public function __construct(
        private readonly OutboundNotificationDispatcher $dispatcher,
    ) {}

    public function handle(LimiteDeReagendamentoExcedido $event): void
    {
        $appointment = $event->appointment;

        $this->dispatcher->dispatch(new NotificationRequest(
            tenantId: $appointment->tenant_id,
            patientId: $appointment->paciente_id,
            type: NotificationType::RescheduleLimitEscalation,
            milestone: 'escalation',
            sourceType: Appointment::class,
            sourceId: $appointment->id,
            professionalId: $appointment->professional_id,
        ));
    }
}
