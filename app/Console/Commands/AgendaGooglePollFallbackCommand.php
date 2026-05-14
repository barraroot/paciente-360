<?php

namespace App\Console\Commands;

use App\Jobs\Agenda\PollGoogleCalendarFallbackJob;
use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Console\Command;

/**
 * T159 — Cron every5min — polling fallback Google (R3 / clarify nº 10).
 */
class AgendaGooglePollFallbackCommand extends Command
{
    protected $signature = 'agenda:google-poll-fallback';

    protected $description = 'Polling fallback Google Calendar para todas as accounts connected (clarify nº 10).';

    public function handle(): int
    {
        $count = 0;
        CalendarSyncAccount::query()->withoutTenantScope()->connected()->each(function ($account) use (&$count): void {
            PollGoogleCalendarFallbackJob::dispatch($account->id);
            $count++;
        });

        if ($count > 0) {
            $this->info("Dispatched {$count} poll jobs.");
        }

        return self::SUCCESS;
    }
}
