<?php

declare(strict_types=1);

namespace App\Listeners\Ai\Mcp;

use App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitClosed;
use App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitOpened;
use App\Domain\Ai\Mcp\Models\McpCircuitBreakerSnapshot;

/**
 * **T054 (Fase 18 — US7, FR-053d)** — persiste transições do circuit breaker
 * em `mcp_circuit_breaker_snapshots` para analytics/auditoria distinguível.
 *
 * Auto-discovered Laravel 11+ (registro no EventServiceProvider não é
 * necessário se o handler é uma classe com métodos `handle*Event`).
 */
final class PersistMcpCircuitSnapshotListener
{
    public function handleOpened(McpCircuitOpened $event): void
    {
        McpCircuitBreakerSnapshot::create([
            'transition_to' => 'open',
            'failures_observed' => $event->failuresObserved,
            'cooldown_seconds' => $event->cooldownSeconds,
            'last_error_code' => $event->lastErrorCode,
            'last_error_message' => $event->lastErrorMessage,
            'source' => $event->source,
            'actor_user_id' => $event->actorUserId,
            'created_at' => now(),
        ]);
    }

    public function handleClosed(McpCircuitClosed $event): void
    {
        McpCircuitBreakerSnapshot::create([
            'transition_to' => 'closed',
            'failures_observed' => 0,
            'cooldown_seconds' => 0,
            'last_error_code' => null,
            'last_error_message' => null,
            'source' => $event->source,
            'actor_user_id' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * Manual subscriber — registra ambos handlers no boot do app.
     *
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            McpCircuitOpened::class => 'handleOpened',
            McpCircuitClosed::class => 'handleClosed',
        ];
    }
}
