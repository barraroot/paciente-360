<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Support\Facades\Log;

/**
 * **T242 (Fase 8 — Lote D US-11.1/11.2)** — Métricas Prometheus webhooks.
 *
 *   - webhook_delivery_attempts_total{tenant, event_type, outcome}
 *   - webhook_delivery_duration_seconds{tenant, event_type}
 *   - webhook_dlq_size_total{tenant}
 *   - webhook_dlq_resent_total{tenant}
 *   - webhook_consent_masked_total{tenant, event_type}
 */
final class WebhookMetrics
{
    public static function deliveryAttempted(int $tenantId, string $eventType, string $outcome): void
    {
        self::log('webhook_delivery_attempts_total', [
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'outcome' => $outcome,
        ]);
    }

    public static function deliveryDuration(int $tenantId, string $eventType, int $durationMs): void
    {
        self::log('webhook_delivery_duration_ms', [
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'duration_ms' => $durationMs,
        ]);
    }

    public static function dlqSize(int $tenantId, int $size): void
    {
        self::log('webhook_dlq_size_total', [
            'tenant_id' => $tenantId,
            'size' => $size,
        ]);
    }

    public static function dlqResent(int $tenantId): void
    {
        self::log('webhook_dlq_resent_total', ['tenant_id' => $tenantId]);
    }

    public static function consentMasked(int $tenantId, string $eventType): void
    {
        self::log('webhook_consent_masked_total', [
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
        ]);
    }

    /**
     * @param array<string, mixed> $labels
     */
    private static function log(string $metric, array $labels): void
    {
        Log::info('metric.'.$metric, $labels);
    }
}
