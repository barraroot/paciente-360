<?php

namespace App\Console\Commands;

use App\Jobs\Agenda\RenewGoogleWatchChannelJob;
use Illuminate\Console\Command;

/**
 * T160 — Cron diário 02:00 BRT — renova watch channels Google que vão expirar (R3).
 */
class AgendaGoogleRenewWatchChannelsCommand extends Command
{
    protected $signature = 'agenda:google-renew-watch-channels';

    protected $description = 'Renova Google Calendar watch channels que expiram nas próximas N horas (R3).';

    public function handle(): int
    {
        RenewGoogleWatchChannelJob::dispatch();
        $this->info('Renew job dispatched.');

        return self::SUCCESS;
    }
}
