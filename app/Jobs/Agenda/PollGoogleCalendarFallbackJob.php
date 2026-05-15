<?php

namespace App\Jobs\Agenda;

use App\Jobs\TenantAwareJob;
use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarPollFallbackService;

/**
 * T154 — Polling fallback para 1 account (R3 — clarify nº 10).
 *
 * Dispatched pelo cron every5min `agenda:google-poll-fallback` para cada
 * CalendarSyncAccount connected.
 */
final class PollGoogleCalendarFallbackJob extends TenantAwareJob
{
    public function __construct(public readonly int $accountId)
    {
        parent::__construct();
    }

    protected function run(): void
    {
        $account = CalendarSyncAccount::find($this->accountId);
        if (! $account) {
            return;
        }

        app(GoogleCalendarPollFallbackService::class)->pollAccount($account);
    }
}
