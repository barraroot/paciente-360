<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\CircuitBreaker;

use App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitClosed;
use App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitOpened;
use App\Support\Metrics\AiMetricsContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Redis;

/**
 * **T053 (Fase 18 — US7, FR-053b/c/d)** — circuit breaker Redis-backed sobre
 * o servidor MCP local.
 *
 * Estados (chave `mcp:cb:state`):
 *   - `closed` (default): toda chamada vai ao MCP. Erros incrementam contador.
 *     Se ≥ `failure_threshold` em `failure_window_s` → `open`.
 *   - `open`: chamadas pulam o MCP. `shouldAllowMcpCall()` retorna false →
 *     ToolRunner usa tools nativas (FR-052/053b). Após `cooldown_seconds`
 *     transita para `half_open`.
 *   - `half_open`: a próxima chamada é uma CANÁRIO; sucesso → `closed`,
 *     falha → `open` com cooldown dobrado (backoff, cap `max_cooldown_s`).
 *
 * Toda transição emite evento Auditable + métrica Prometheus + snapshot DB
 * (PersistMcpCircuitSnapshotListener — T054).
 *
 * O caminho de "uso manual" (admin desligou `AI_TOOLS_VIA_MCP=false`)
 * NÃO passa por aqui — é decisão em `ToolRunner::isMcpEnabled()`. CB é
 * automático e distinguível em audit (FR-053d).
 */
final class McpCircuitBreaker
{
    private const KEY_STATE = 'mcp:cb:state';

    private const KEY_FAILURES = 'mcp:cb:failures';

    private const KEY_OPENED_AT = 'mcp:cb:opened_at';

    private const KEY_COOLDOWN = 'mcp:cb:cooldown_seconds';

    private const KEY_CANARY_LOCK = 'mcp:cb:canary_in_flight';

    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly Dispatcher $events,
        private readonly AiMetricsContract $metrics,
    ) {}

    /**
     * Retorna o estado lógico ATUAL (já considerando expiração do cooldown).
     * Se estava `open` mas o cooldown elapsou, transita para `half_open`
     * dentro deste método (lazy).
     */
    public function state(): string
    {
        $raw = (string) (Redis::get(self::KEY_STATE) ?? self::STATE_CLOSED);

        if ($raw === self::STATE_OPEN) {
            $openedAt = (int) (Redis::get(self::KEY_OPENED_AT) ?? 0);
            $cooldown = (int) (Redis::get(self::KEY_COOLDOWN) ?? config('ai.matricial.mcp.circuit_breaker.initial_cooldown_s', 60));

            if ($openedAt > 0 && (time() - $openedAt) >= $cooldown) {
                $this->transitionTo(self::STATE_HALF_OPEN, source: 'automatic');

                return self::STATE_HALF_OPEN;
            }
        }

        return $raw;
    }

    /**
     * Decide se a chamada DEVE ir ao MCP agora.
     *
     * - `closed` → sempre sim.
     * - `half_open` → sim (canário) — apenas a primeira chamada (lock).
     * - `open` → não.
     */
    public function shouldAllowMcpCall(): bool
    {
        $state = $this->state();

        if ($state === self::STATE_OPEN) {
            return false;
        }

        if ($state === self::STATE_HALF_OPEN) {
            // Lock atômico — só uma canário por vez.
            return (bool) Redis::set(self::KEY_CANARY_LOCK, '1', 'NX', 'EX', 30);
        }

        return true;
    }

    public function recordSuccess(): void
    {
        $state = (string) (Redis::get(self::KEY_STATE) ?? self::STATE_CLOSED);

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_CLOSED, source: 'automatic');
        }

        // Reset failures em closed.
        Redis::del(self::KEY_FAILURES);
        Redis::del(self::KEY_CANARY_LOCK);
    }

    public function recordFailure(string $errorCode, string $errorMessage): void
    {
        $state = (string) (Redis::get(self::KEY_STATE) ?? self::STATE_CLOSED);

        if ($state === self::STATE_HALF_OPEN) {
            // Canário falhou → reabre com cooldown dobrado (backoff).
            $current = (int) (Redis::get(self::KEY_COOLDOWN) ?? config('ai.matricial.mcp.circuit_breaker.initial_cooldown_s', 60));
            $max = (int) config('ai.matricial.mcp.circuit_breaker.max_cooldown_s', 600);
            $next = min($current * 2, $max);

            $this->transitionToOpen(
                cooldownSeconds: $next,
                source: 'automatic',
                errorCode: $errorCode,
                errorMessage: $errorMessage,
            );

            return;
        }

        $threshold = (int) config('ai.matricial.mcp.circuit_breaker.failure_threshold', 3);
        $window = (int) config('ai.matricial.mcp.circuit_breaker.failure_window_s', 30);

        // INCR atômico com TTL inicial.
        $count = (int) Redis::incr(self::KEY_FAILURES);
        if ($count === 1) {
            Redis::expire(self::KEY_FAILURES, $window);
        }

        if ($count >= $threshold && $state === self::STATE_CLOSED) {
            $initialCooldown = (int) config('ai.matricial.mcp.circuit_breaker.initial_cooldown_s', 60);

            $this->transitionToOpen(
                cooldownSeconds: $initialCooldown,
                source: 'automatic',
                errorCode: $errorCode,
                errorMessage: $errorMessage,
            );
        }
    }

    /**
     * **MANUAL** — admin desligou `AI_TOOLS_VIA_MCP=false` (rollback
     * operacional) — registra snapshot distinguível (FR-053d).
     */
    public function recordManualRollback(int $actorUserId): void
    {
        $this->transitionTo(
            self::STATE_OPEN,
            source: 'manual_flag',
            actorUserId: $actorUserId,
        );
    }

    private function transitionToOpen(
        int $cooldownSeconds,
        string $source,
        string $errorCode,
        string $errorMessage,
    ): void {
        Redis::set(self::KEY_STATE, self::STATE_OPEN);
        Redis::set(self::KEY_OPENED_AT, time());
        Redis::set(self::KEY_COOLDOWN, $cooldownSeconds);
        Redis::del(self::KEY_CANARY_LOCK);

        $failures = (int) (Redis::get(self::KEY_FAILURES) ?? 0);

        $this->events->dispatch(new McpCircuitOpened(
            failuresObserved: $failures,
            cooldownSeconds: $cooldownSeconds,
            source: $source,
            lastErrorCode: $errorCode,
            lastErrorMessage: $errorMessage,
        ));

        $this->metrics->mcpCircuitTransition(to: 'open', source: $source);
        $this->metrics->mcpCircuitState(2);
    }

    private function transitionTo(
        string $state,
        string $source,
        ?int $actorUserId = null,
    ): void {
        Redis::set(self::KEY_STATE, $state);

        if ($state === self::STATE_CLOSED) {
            Redis::del(self::KEY_OPENED_AT);
            Redis::del(self::KEY_COOLDOWN);

            $this->events->dispatch(new McpCircuitClosed(source: $source));
            $this->metrics->mcpCircuitState(0);
        } elseif ($state === self::STATE_HALF_OPEN) {
            $this->metrics->mcpCircuitState(1);
        }

        $this->metrics->mcpCircuitTransition(to: $state, source: $source);
    }
}
