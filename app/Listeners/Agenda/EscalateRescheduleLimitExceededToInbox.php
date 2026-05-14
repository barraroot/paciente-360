<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\LimiteDeReagendamentoExcedido;
use Illuminate\Support\Facades\Log;

/**
 * T126 — Escala limite de reagendamentos excedido para inbox da Fase 3 (clarify nº 7).
 */
class EscalateRescheduleLimitExceededToInbox
{
    public function handle(LimiteDeReagendamentoExcedido $event): void
    {
        Log::info('agenda.reschedule.limit_exceeded.escalated', [
            'tenant_id' => $event->appointment->tenant_id,
            'appointment_id' => $event->appointment->id,
            'current_count' => $event->currentCount,
            'limit' => $event->limit,
        ]);

        // TODO: chamar MessagingDispatcher::createHandoffForRescheduleLimit($event)
    }
}
