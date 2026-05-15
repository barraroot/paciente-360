<?php

namespace App\Console\Commands;

use App\Services\Agenda\SlotReservationService;
use Illuminate\Console\Command;

/**
 * T085 — Cleanup periódico de slot_reservations expiradas (clarify nº 2).
 *
 * Schedule: everyMinute()->onOneServer() em routes/console.php.
 */
class AgendaCleanupExpiredReservationsCommand extends Command
{
    protected $signature = 'agenda:cleanup-expired-reservations';

    protected $description = 'Libera slot_reservations expiradas (release_reason=expired). Cron 1min.';

    public function handle(SlotReservationService $service): int
    {
        $count = $service->cleanupExpired();

        if ($count > 0) {
            $this->info("Liberadas {$count} reservas expiradas.");
        }

        return self::SUCCESS;
    }
}
