<?php

namespace App\Console\Commands;

use App\Services\Agenda\WaitlistService;
use Illuminate\Console\Command;

/**
 * T133 — Cron everyMinute — expira notificações de waitlist (clarify nº 8).
 *
 * Marca status=expired e re-notifica o próximo da fila.
 */
class AgendaExpireWaitlistNotificationsCommand extends Command
{
    protected $signature = 'agenda:expire-waitlist-notifications';

    protected $description = 'Expira notificações de lista de espera vencidas e re-notifica o próximo (clarify nº 8).';

    public function handle(WaitlistService $service): int
    {
        $count = $service->expireNotifications();

        if ($count > 0) {
            $this->info("Expiradas {$count} notificações de waitlist.");
        }

        return self::SUCCESS;
    }
}
