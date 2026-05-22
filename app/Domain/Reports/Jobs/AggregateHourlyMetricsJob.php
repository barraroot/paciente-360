<?php

declare(strict_types=1);

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Services\MetricAggregator;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * **T251 (Fase 8 — Lote E US-10.1)** — Pré-computação horária de KPIs.
 *
 * Itera tenants ativos em chunks e dispara aggregation diária via
 * MetricAggregator. Fila `reports` (concurrency 3 — config/horizon.php).
 *
 * Idempotência via UPSERT no MetricAggregator (UNIQUE constraint
 * `uq_metric_aggregations_idempotency`).
 */
final class AggregateHourlyMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public string $queue = 'reports';

    public function handle(MetricAggregator $aggregator): void
    {
        $processed = 0;
        $totalMetrics = 0;

        Tenant::query()
            ->whereIn('status', ['active', 'trial'])
            ->chunk(20, function ($tenants) use ($aggregator, &$processed, &$totalMetrics): void {
                foreach ($tenants as $tenant) {
                    try {
                        $count = $aggregator->aggregateDailyForTenant($tenant);
                        $totalMetrics += $count;
                        $processed++;
                    } catch (\Throwable $e) {
                        Log::warning('reports.aggregate.tenant_failed', [
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('reports.aggregate.completed', [
            'tenants_processed' => $processed,
            'metrics_aggregated' => $totalMetrics,
        ]);
    }
}
