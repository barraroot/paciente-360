<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Agenda\ProcessGoogleCalendarPushJob;
use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Http\Request;

/**
 * T163 — Webhook receiver para push notifications do Google Calendar (R3).
 *
 * SEMPRE retorna 200 — evita retry storms. Erros logados Sentry pelo middleware.
 */
class GoogleCalendarWebhookController extends Controller
{
    public function __invoke(Request $request, string $channelId)
    {
        /** @var CalendarSyncAccount|null $account */
        $account = $request->attributes->get('agenda.calendar_sync_account');

        if (! $account) {
            // Middleware já logou; ack 200
            return response()->json(['ok' => true], 200);
        }

        // Resource state: sync (handshake) | exists | not_exists
        $state = (string) $request->header('X-Goog-Resource-State', '');

        if ($state === 'sync') {
            // Handshake inicial — acknowledge sem processar
            return response()->json(['ok' => true], 200);
        }

        // Dispatch job assíncrono (não bloqueia o webhook)
        ProcessGoogleCalendarPushJob::dispatch($channelId);

        return response()->json(['ok' => true], 200);
    }
}
