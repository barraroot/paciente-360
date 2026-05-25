<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Feature 013 — Métricas Prometheus de notificações outbound (R12).
 *
 * Degrada para `Log::debug` quando `promphp/prometheus_client_php` ausente
 * (herda lifecycle defensivo de {@see AbstractModuleMetrics}).
 */
final class OutboundNotificationMetrics extends AbstractModuleMetrics implements OutboundNotificationMetricsContract
{
    public function recorded(int $tenantId, string $type, string $status): void
    {
        $this->recordCounterOrLog(
            'outbound_notifications_total',
            ['tenant' => (string) $tenantId, 'type' => $type, 'status' => $status],
            'Total de notificações outbound por tenant/tipo/status.',
        );
    }

    public function pendingManual(int $tenantId, string $reason): void
    {
        $this->recordCounterOrLog(
            'outbound_notifications_pending_manual_total',
            ['tenant' => (string) $tenantId, 'reason' => $reason],
            'Notificações roteadas para contato manual por motivo.',
        );
    }

    public function skipped(string $reason): void
    {
        $this->recordCounterOrLog(
            'outbound_notifications_skipped_total',
            ['reason' => $reason],
            'Notificações suprimidas (opt-out/debounce) por motivo.',
        );
    }

    public function deliveryLatency(float $seconds): void
    {
        $this->recordHistogramOrLog(
            'outbound_notifications_delivery_latency_seconds',
            [],
            $seconds,
            'Latência de entrega sent → delivered.',
        );
    }
}
