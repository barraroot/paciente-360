<?php

declare(strict_types=1);

namespace App\Console\Commands\SuperAdmin;

use App\Domain\SuperAdmin\Services\GlobalMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * **T135 (Fase 8 — Lote B US-12.3)** — Cron horário (`super-admin:compute-global-metrics`).
 *
 * Calcula o snapshot completo de KPIs e armazena em cache para o painel
 * Filament consumir sem rodar queries pesadas a cada visualização (AC-12.3.3).
 *
 * Cache key: `super_admin.global_metrics.snapshot` (TTL 65min — coberto pelo
 * próximo run hourly).
 */
final class ComputeGlobalMetricsCommand extends Command
{
    protected $signature = 'super-admin:compute-global-metrics';

    protected $description = 'Calcula KPIs globais (MRR, ARR, churn, conversão, IA) e cacheia para o painel.';

    public function handle(GlobalMetricsService $service): int
    {
        $snapshot = $service->snapshot();

        Cache::put(
            key: 'super_admin.global_metrics.snapshot',
            value: $snapshot,
            ttl: now()->addMinutes(65),
        );

        $this->info(sprintf(
            'Snapshot computed: MRR=R$ %s, ARR=R$ %s, tenants_active=%d, churn=%s%%',
            number_format($snapshot['mrr_cents'] / 100, 2, ',', '.'),
            number_format($snapshot['arr_cents'] / 100, 2, ',', '.'),
            $snapshot['tenants_active'],
            $snapshot['churn_primary']['rate_percent'],
        ));

        return self::SUCCESS;
    }
}
