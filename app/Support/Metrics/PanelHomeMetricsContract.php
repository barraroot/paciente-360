<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * **T005 (Fase 10 — Spec 010)** — Contrato de métricas do Dashboard Home.
 *
 * Princípio V (Observabilidade): expõe contadores e histograms para
 * monitorar performance e cobertura do endpoint `GET /api/v1/panel/home`.
 */
interface PanelHomeMetricsContract
{
    public function recordRequest(string $tenant, string $scope, bool $cacheHit, float $durationSeconds): void;

    public function recordCacheHit(string $tenant): void;

    public function recordSectionFailure(string $section): void;

    public function recordSectionDuration(string $section, float $durationSeconds): void;
}
