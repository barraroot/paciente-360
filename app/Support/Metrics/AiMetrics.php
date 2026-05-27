<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Feature 015 — Métricas Prometheus da IA Matricial (R11).
 *
 * Degrada para `Log::debug` quando `promphp/prometheus_client_php` ausente
 * (herda lifecycle defensivo de {@see AbstractModuleMetrics}).
 */
final class AiMetrics extends AbstractModuleMetrics implements AiMetricsContract
{
    public function responseLatency(float $seconds): void
    {
        $this->recordHistogramOrLog(
            'ai_response_latency_seconds',
            [],
            $seconds,
            'Latência de geração da resposta da IA (alvo p95 ≤ 5s).',
        );
    }

    public function message(int $tenantId): void
    {
        $this->recordCounterOrLog(
            'ai_messages_total',
            ['tenant' => (string) $tenantId],
            'Total de respostas enviadas pela IA por tenant.',
        );
    }

    public function escalation(int $tenantId, string $reason): void
    {
        $this->recordCounterOrLog(
            'ai_escalation_total',
            ['tenant' => (string) $tenantId, 'reason' => $reason],
            'Total de escalonamentos da IA para humano por tenant/motivo.',
        );
    }

    public function toolRoundTrips(int $tenantId, int $count): void
    {
        $this->recordHistogramOrLog(
            'ai_tool_round_trips',
            ['tenant' => (string) $tenantId],
            (float) $count,
            'Round-trips de ferramenta por resposta da IA (feature 017, alvo ≤ 3).',
        );
    }
}
