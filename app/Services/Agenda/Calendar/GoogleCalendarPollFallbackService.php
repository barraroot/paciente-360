<?php

namespace App\Services\Agenda\Calendar;

use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Agenda\ExternalCalendarBusy;
use Illuminate\Support\Carbon;

/**
 * T151 — Polling fallback Google Calendar (R3 — clarify nº 10).
 *
 * Cron 5min — varre cada CalendarSyncAccount connected e busca eventos
 * atualizados desde last_polled_at. Cria/atualiza ExternalCalendarBusy.
 *
 * Cobre o caso de watch channels expirarem silenciosamente.
 */
final class GoogleCalendarPollFallbackService
{
    public function __construct(private readonly GoogleCalendarApiClient $client) {}

    /**
     * @return int número de busy entries upserted
     */
    public function pollAccount(CalendarSyncAccount $account): int
    {
        if ($account->status !== 'connected' || $account->google_calendar_id === null) {
            return 0;
        }

        $since = $account->last_polled_at;
        $events = $this->client->listEventsUpdatedSince($account, $account->google_calendar_id, $since);

        $count = 0;
        foreach ($events as $event) {
            // Skip eventos cancelados
            if (($event['status'] ?? '') === 'cancelled') {
                ExternalCalendarBusy::query()
                    ->where('calendar_sync_account_id', $account->id)
                    ->where('external_event_id', $event['id'])
                    ->delete();

                continue;
            }

            ExternalCalendarBusy::query()->updateOrCreate(
                [
                    'calendar_sync_account_id' => $account->id,
                    'external_event_id' => $event['id'],
                ],
                [
                    'tenant_id' => $account->tenant_id,
                    'professional_id' => $account->professional_id,
                    'starts_at' => Carbon::parse($event['start']['dateTime'] ?? $event['start']['date']),
                    'ends_at' => Carbon::parse($event['end']['dateTime'] ?? $event['end']['date']),
                    'provider' => 'google',
                    'summary_redacted' => substr((string) ($event['summary'] ?? ''), 0, 200),
                    'synced_at' => now(),
                ],
            );
            $count++;
        }

        $account->update(['last_polled_at' => now()]);

        return $count;
    }
}
