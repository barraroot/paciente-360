<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\CancelamentoSolicitadoForaDoPrazo;
use Illuminate\Support\Facades\Log;

/**
 * T125 — Escala cancelamento fora do prazo para inbox da Fase 3 (clarify nº 3).
 *
 * Stub para integração — Fase 3 cria handoff/note quando MessagingDispatcher
 * expor entry-point. Por enquanto loga.
 */
class EscalateCancellationOutsideWindowToInbox
{
    public function handle(CancelamentoSolicitadoForaDoPrazo $event): void
    {
        Log::info('agenda.cancellation.escalated_to_inbox', [
            'tenant_id' => $event->appointment->tenant_id,
            'appointment_id' => $event->appointment->id,
            'requested_by' => $event->requestedBy,
            'window_hours' => $event->windowHours,
            'current_hours_until_appt' => $event->currentHoursUntilAppt,
        ]);

        // TODO: chamar MessagingDispatcher::createHandoffForCancellation($event)
    }
}
