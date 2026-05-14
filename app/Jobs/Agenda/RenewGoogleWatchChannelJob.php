<?php

namespace App\Jobs\Agenda;

use App\Services\Agenda\Calendar\GoogleCalendarWatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * T155 — Renova watch channels antes do TTL ~7 dias (R3).
 *
 * Cross-tenant — chamado pelo cron diário `agenda:google-renew-watch-channels`.
 */
final class RenewGoogleWatchChannelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(GoogleCalendarWatchService $service): void
    {
        $service->renewIfNearExpiry();
    }
}
