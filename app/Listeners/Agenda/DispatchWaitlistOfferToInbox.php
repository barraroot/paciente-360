<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\VagaAbertaNaListaDeEspera;
use Illuminate\Support\Facades\Log;

/**
 * T132 — Encaminha oferta de vaga ao paciente via Fase 3 (clarify nº 8).
 *
 * Stub para integração com MessagingDispatcher (idem outros listeners).
 */
class DispatchWaitlistOfferToInbox
{
    public function handle(VagaAbertaNaListaDeEspera $event): void
    {
        Log::info('agenda.waitlist.offer_dispatched', [
            'tenant_id' => $event->entry->tenant_id,
            'waitlist_entry_id' => $event->entry->id,
            'paciente_id' => $event->entry->paciente_id,
            'slot_starts_at' => $event->entry->notified_for_slot_starts_at?->toIso8601String(),
            'window_minutes' => $event->notificationWindowMinutes,
        ]);

        // TODO: chamar MessagingDispatcher::sendWaitlistOffer($event)
    }
}
