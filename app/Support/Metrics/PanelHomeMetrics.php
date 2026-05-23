<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * **T006 (Fase 10 — Spec 010)** — Métricas Prometheus para Dashboard Home.
 *
 * Métricas:
 *   - panel_home_requests_total{tenant,scope,cache_hit}
 *   - panel_home_duration_seconds (histogram, buckets [0.05, 0.1, 0.25, 0.5, 1.0, 2.5])
 *   - panel_home_cache_hits_total{tenant}
 *   - panel_home_section_failures_total{section}
 *   - panel_home_section_duration_seconds{section}
 *
 * Buckets do duration histogram alinhados com SC-003 (p95 < 500ms).
 *
 * @see specs/010-dashboard-home/research.md R12
 */
final class PanelHomeMetrics extends AbstractModuleMetrics implements PanelHomeMetricsContract
{
    private const DURATION_BUCKETS = [0.05, 0.1, 0.25, 0.5, 1.0, 2.5];

    public function recordRequest(string $tenant, string $scope, bool $cacheHit, float $durationSeconds): void
    {
        $this->recordCounterOrLog(
            name: 'panel_home_requests_total',
            labels: [
                'tenant' => $tenant,
                'scope' => $scope,
                'cache_hit' => $cacheHit ? 'true' : 'false',
            ],
            help: 'Total de requests ao endpoint Dashboard Home.',
        );

        $this->recordHistogramOrLog(
            name: 'panel_home_duration_seconds',
            labels: ['scope' => $scope, 'cache_hit' => $cacheHit ? 'true' : 'false'],
            value: $durationSeconds,
            help: 'Duração das requests ao endpoint Dashboard Home.',
            buckets: self::DURATION_BUCKETS,
        );
    }

    public function recordCacheHit(string $tenant): void
    {
        $this->recordCounterOrLog(
            name: 'panel_home_cache_hits_total',
            labels: ['tenant' => $tenant],
            help: 'Cache hits do Dashboard Home.',
        );
    }

    public function recordSectionFailure(string $section): void
    {
        $this->recordCounterOrLog(
            name: 'panel_home_section_failures_total',
            labels: ['section' => $section],
            help: 'Falhas isoladas em collectors do Dashboard Home (degradação graceful).',
        );
    }

    public function recordSectionDuration(string $section, float $durationSeconds): void
    {
        $this->recordHistogramOrLog(
            name: 'panel_home_section_duration_seconds',
            labels: ['section' => $section],
            value: $durationSeconds,
            help: 'Duração de cada collector individualmente.',
            buckets: self::DURATION_BUCKETS,
        );
    }
}
