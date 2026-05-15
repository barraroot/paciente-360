<?php

namespace App\Console\Commands;

use App\Services\Agenda\ConfirmationDispatcherService;
use Illuminate\Console\Command;

/**
 * T108 — Cron everyFiveMinutes — dispatch de confirmações T-24h/T-2h/retry/escalation.
 */
class AgendaDispatchConfirmationsCommand extends Command
{
    protected $signature = 'agenda:dispatch-confirmations';

    protected $description = 'Dispara ConsultaConfirmacaoPendente para appointments elegíveis (clarify nº 6).';

    public function handle(ConfirmationDispatcherService $service): int
    {
        $stats = $service->dispatchPending();
        $total = array_sum($stats);

        if ($total > 0) {
            $this->info("Dispatched {$total}: ".json_encode($stats));
        }

        return self::SUCCESS;
    }
}
