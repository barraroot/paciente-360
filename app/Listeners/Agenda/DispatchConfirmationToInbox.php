<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\ConsultaConfirmacaoPendente;
use Illuminate\Support\Facades\Log;

/**
 * T107 — Listener: encaminha ConsultaConfirmacaoPendente para Fase 3 enviar mensagem.
 *
 * Stub para integração com MessagingDispatcher (Fase 3). Por enquanto loga
 * a intenção; integração real será adicionada quando MessagingDispatcher
 * expor entry-point para confirmações de agenda (próxima iteração de Fase 3).
 */
class DispatchConfirmationToInbox
{
    public function handle(ConsultaConfirmacaoPendente $event): void
    {
        if ($event->viaIa) {
            // IA assume — Fase 5 não envia template (clarify nº 6 / FR-018a)
            Log::info('agenda.confirmation.via_ia', [
                'appointment_id' => $event->appointment->id,
                'kind' => $event->kind,
            ]);

            return;
        }

        // Fase 3 dispatcher entry point — placeholder
        Log::info('agenda.confirmation.dispatch', [
            'appointment_id' => $event->appointment->id,
            'kind' => $event->kind,
            'horario' => $event->horarioBrasilia,
            'tz_label' => $event->tzLabel,
            'paciente_id' => $event->appointment->paciente_id,
        ]);

        // TODO: chamar MessagingDispatcher::sendAgendaConfirmation($event)
        // quando esse método existir na Fase 3.
    }
}
