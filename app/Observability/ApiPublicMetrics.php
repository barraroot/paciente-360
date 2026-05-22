<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Support\Facades\Log;

/**
 * **T242 (Fase 8 — Lote D US-11.2)** — Métricas Prometheus da API pública.
 *
 *   - api_public_request_total{tenant, resource, method, status}
 *   - api_public_rate_limit_blocked_total{tenant, scope}
 *   - api_public_tokens_emitted_total{tenant}
 *   - api_public_tokens_revoked_total{tenant, reason}
 *   - api_public_idempotency_hits_total{tenant, resource}
 */
final class ApiPublicMetrics
{
    public static function requestServed(int $tenantId, string $resource, string $method, int $status): void
    {
        self::log('api_public_request_total', [
            'tenant_id' => $tenantId,
            'resource' => $resource,
            'method' => $method,
            'status' => $status,
        ]);
    }

    public static function rateLimitBlocked(int $tenantId, string $scope): void
    {
        self::log('api_public_rate_limit_blocked_total', [
            'tenant_id' => $tenantId,
            'scope' => $scope,
        ]);
    }

    public static function tokenEmitted(int $tenantId): void
    {
        self::log('api_public_tokens_emitted_total', ['tenant_id' => $tenantId]);
    }

    public static function tokenRevoked(int $tenantId, string $reason): void
    {
        self::log('api_public_tokens_revoked_total', [
            'tenant_id' => $tenantId,
            'reason' => $reason,
        ]);
    }

    public static function idempotencyHit(int $tenantId, string $resource): void
    {
        self::log('api_public_idempotency_hits_total', [
            'tenant_id' => $tenantId,
            'resource' => $resource,
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
