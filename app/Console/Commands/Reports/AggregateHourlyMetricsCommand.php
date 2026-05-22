<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Domain\Reports\Jobs\AggregateHourlyMetricsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * **T252 (Fase 8 — Lote E US-10.1)** — Cron `reports:aggregate-hourly` (hourlyAt 5).
 *
 * Enfileira `AggregateHourlyMetricsJob` na fila `reports`. Já agendado em
 * routes/console.php (T009).
 */
final class AggregateHourlyMetricsCommand extends Command
{
    protected $signature = 'reports:aggregate-hourly {--sync}';

    protected $description = 'Pré-computa KPIs horários para dashboard executivo (Q9).';

    public function handle(): int
    {
        $sync = $this->option('sync');

        $this->info('Enfileirando AggregateHourlyMetricsJob '.($sync ? '[sync]' : ''));

        $job = new AggregateHourlyMetricsJob;

        if ($sync) {
            Bus::dispatchSync($job);
        } else {
            Bus::dispatch($job);
        }

        return self::SUCCESS;
    }
}
