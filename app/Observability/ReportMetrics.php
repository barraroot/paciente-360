<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Support\Facades\Log;

/**
 * **T278 (Fase 8 — Lote E US-10.1/10.2/10.3)** — Métricas Prometheus.
 *
 * Counters mantidos via cache facade quando exporter Prometheus estiver
 * disponível; fallback log-only para compatibilidade com Fase 7
 * (`PrescriptionMetrics` segue mesmo padrão).
 *
 * Métricas:
 *   - `report_executive_dashboard_views_total{tenant_id}` — AC-10.1.1
 *   - `report_drilldown_requests_total{tenant_id, metric}` — AC-10.1.4
 *   - `report_pdf_exports_total{tenant_id, success}` — AC-10.1.5
 *   - `report_operational_views_total{tenant_id}` — AC-10.2.x
 *   - `report_clinical_views_total{tenant_id, scope}` — AC-10.3.x (scope=tenant|own)
 *   - `report_aggregation_runs_total{outcome}` — job horário
 *   - `report_aggregation_stale_alerts_total{tenant_id}` — R-8-5
 */
final class ReportMetrics
{
    public static function dashboardViewed(int $tenantId): void
    {
        self::log('report_executive_dashboard_views_total', ['tenant_id' => $tenantId]);
    }

    public static function drillDownRequested(int $tenantId, string $metric): void
    {
        self::log('report_drilldown_requests_total', ['tenant_id' => $tenantId, 'metric' => $metric]);
    }

    public static function pdfExported(int $tenantId, bool $success): void
    {
        self::log('report_pdf_exports_total', ['tenant_id' => $tenantId, 'success' => $success]);
    }

    public static function operationalViewed(int $tenantId): void
    {
        self::log('report_operational_views_total', ['tenant_id' => $tenantId]);
    }

    public static function clinicalViewed(int $tenantId, string $scope): void
    {
        self::log('report_clinical_views_total', ['tenant_id' => $tenantId, 'scope' => $scope]);
    }

    public static function aggregationRun(string $outcome): void
    {
        self::log('report_aggregation_runs_total', ['outcome' => $outcome]);
    }

    public static function staleAggregationAlert(int $tenantId): void
    {
        self::log('report_aggregation_stale_alerts_total', ['tenant_id' => $tenantId]);
    }

    /**
     * @param array<string, mixed> $labels
     */
    private static function log(string $metric, array $labels): void
    {
        Log::info('metric.'.$metric, $labels);
    }
}
