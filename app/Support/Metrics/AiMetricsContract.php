<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Feature 015 — Contrato das métricas Prometheus da IA Matricial (R11).
 *
 * @see AiMetrics
 */
interface AiMetricsContract
{
    /**
     * Observa a latência de geração da resposta (segundos). Alvo p95 ≤ 5s.
     */
    public function responseLatency(float $seconds): void;

    /**
     * Incrementa o total de mensagens da IA por tenant (consumo mensal).
     */
    public function message(int $tenantId): void;

    /**
     * Incrementa o total de escalonamentos para humano por tenant/motivo.
     */
    public function escalation(int $tenantId, string $reason): void;

    /**
     * Observa o nº de round-trips de ferramenta por resposta (feature 017, alvo ≤ 3).
     */
    public function toolRoundTrips(int $tenantId, int $count): void;
}
