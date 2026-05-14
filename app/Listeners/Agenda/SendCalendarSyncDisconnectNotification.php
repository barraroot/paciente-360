<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\CalendarioExternoSincronizado;
use App\Mail\CalendarSyncDisconnectedMail;
use Illuminate\Support\Facades\Mail;

/**
 * T158 — Notifica profissional quando OAuth disconnect (R5).
 *
 * Dedup via last_disconnect_notified_at — evita spam se evento dispara N vezes.
 */
class SendCalendarSyncDisconnectNotification
{
    public function handle(CalendarioExternoSincronizado $event): void
    {
        if ($event->status !== 'disconnected') {
            return;
        }

        $account = $event->account;

        // Dedup: 1 email por evento de desconexão
        if ($account->last_disconnect_notified_at !== null
            && $account->last_disconnect_at !== null
            && $account->last_disconnect_notified_at->gte($account->last_disconnect_at)) {
            return;
        }

        $professional = $account->professional;

        if ($professional?->user?->email) {
            Mail::to($professional->user->email)->queue(new CalendarSyncDisconnectedMail($account));
        }

        $account->update(['last_disconnect_notified_at' => now()]);
    }
}
