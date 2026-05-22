<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * **T140 (Fase 8 — Lote B US-12.3)** — Métricas Prometheus do módulo Super Admin.
 *
 * Conforme plan.md §7:
 *   - tenant_lifecycle_total{action, from_status, to_status}
 *   - impersonate_sessions_total{result}
 *   - anomalies_detected_total{category, severity}
 *   - mrr_total (gauge)
 *   - arr_total (gauge)
 *   - churn_rate_percent (gauge)
 *
 * Estende {@see AbstractModuleMetrics} (T005).
 */
final class SuperAdminMetrics extends AbstractModuleMetrics
{
    public function tenantLifecycle(string $action, string $fromStatus, string $toStatus): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_super_admin_tenant_lifecycle_total',
            labels: ['action' => $action, 'from_status' => $fromStatus, 'to_status' => $toStatus],
            help: 'Total de transições de lifecycle de tenants (suspend, reactivate, cancel, create).',
        );
    }

    public function impersonateSession(string $result): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_super_admin_impersonate_sessions_total',
            labels: ['result' => $result], // started | ended | concurrent_blocked
            help: 'Total de sessões de impersonate por resultado.',
        );
    }

    public function anomalyDetected(string $categoria, string $severity): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_super_admin_anomalies_detected_total',
            labels: ['category' => $categoria, 'severity' => $severity],
            help: 'Total de anomalias detectadas por categoria e severidade.',
        );
    }

    public function mrr(float $cents): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_super_admin_mrr_cents',
            labels: [],
            value: $cents,
            help: 'Monthly Recurring Revenue em centavos.',
        );
    }

    public function arr(float $cents): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_super_admin_arr_cents',
            labels: [],
            value: $cents,
            help: 'Annual Recurring Revenue em centavos (MRR × 12).',
        );
    }

    public function churnRatePercent(float $percent): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_super_admin_churn_rate_percent',
            labels: [],
            value: $percent,
            help: 'Churn rate primário (cancelamentos / ativos início período).',
        );
    }

    public function tenantsActive(int $count): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_super_admin_tenants_active',
            labels: [],
            value: (float) $count,
            help: 'Número de tenants ativos.',
        );
    }
}
