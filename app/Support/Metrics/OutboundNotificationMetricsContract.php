<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Feature 013 — Contrato das métricas Prometheus de notificações outbound.
 *
 * @see OutboundNotificationMetrics
 * @see specs/013-outbound-notifications/research.md §R12
 */
interface OutboundNotificationMetricsContract
{
    /**
     * Incrementa o counter de notificações por tenant/tipo/status.
     */
    public function recorded(int $tenantId, string $type, string $status): void;

    /**
     * Incrementa o counter de notificações roteadas para contato manual.
     */
    public function pendingManual(int $tenantId, string $reason): void;

    /**
     * Incrementa o counter de notificações suprimidas (opt-out/debounce).
     */
    public function skipped(string $reason): void;

    /**
     * Observa a latência sent → delivered (segundos).
     */
    public function deliveryLatency(float $seconds): void;
}
