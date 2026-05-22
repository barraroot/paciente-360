<?php

declare(strict_types=1);

namespace App\Console\Commands\SuperAdmin;

use App\Domain\SuperAdmin\Services\AnomalyDetectorService;
use Illuminate\Console\Command;

/**
 * **T135 (Fase 8 — Lote B US-12.3)** — Cron every 15min (`super-admin:detect-anomalies`).
 *
 * Roda os 4 detectores configurados (Q22) — conversion_drop, ai_usage_spike,
 * webhook_failure_rate, payment_overdue. Cooldown enforçado no Service.
 */
final class DetectAnomaliesCommand extends Command
{
    protected $signature = 'super-admin:detect-anomalies';

    protected $description = 'Detecta anomalias (4 categorias Q22) e notifica Super Admin via inbox + e-mail crítico.';

    public function handle(AnomalyDetectorService $service): int
    {
        $detected = $service->detectAll();

        $this->info(sprintf('Anomalias detectadas neste ciclo: %d', count($detected)));

        foreach ($detected as $anomaly) {
            $this->line(sprintf(
                '  - %s | tenant_id=%s | severity=%s',
                $anomaly->categoria->value,
                $anomaly->tenant_id ?? 'global',
                $anomaly->severity->value,
            ));
        }

        return self::SUCCESS;
    }
}
