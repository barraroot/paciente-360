<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\ConsultaCancelada;
use App\Services\Agenda\WaitlistService;

/**
 * T131 — Quando consulta é cancelada, notifica o primeiro da lista de espera
 * compatível (mesmo professional + appointment_type — clarify nº 8).
 */
class OpenWaitlistOnCancellation
{
    public function __construct(private readonly WaitlistService $waitlist) {}

    public function handle(ConsultaCancelada $event): void
    {
        $appointment = $event->appointment;

        // Slot só faz sentido se ainda está no futuro
        if ($appointment->starts_at->isPast()) {
            return;
        }

        $this->waitlist->notifyNext(
            $appointment->tenant_id,
            $appointment->professional_id,
            $appointment->appointment_type_id,
            $appointment->starts_at,
        );
    }
}
