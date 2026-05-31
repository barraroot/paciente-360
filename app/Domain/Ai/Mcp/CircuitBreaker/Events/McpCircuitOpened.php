<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\CircuitBreaker\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T054 (Fase 18 — US7, FR-053b/d)** — circuit breaker do MCP foi ABERTO.
 *
 * `source` ∈ {`automatic`, `manual_flag`} — auditoria distinguível.
 * Persistido em `mcp_circuit_breaker_snapshots` via
 * PersistMcpCircuitSnapshotListener; também grava em `audit_logs` (Auditable).
 */
final class McpCircuitOpened implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $failuresObserved,
        public readonly int $cooldownSeconds,
        public readonly string $source,
        public readonly ?string $lastErrorCode = null,
        public readonly ?string $lastErrorMessage = null,
        public readonly ?int $actorUserId = null,
    ) {}

    public function auditAction(): string
    {
        return 'mcp.circuit.opened';
    }

    public function auditPayload(): array
    {
        return [
            'failures_observed' => $this->failuresObserved,
            'cooldown_seconds' => $this->cooldownSeconds,
            'source' => $this->source,
            'last_error_code' => $this->lastErrorCode,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return null; // estado global do MCP
    }

    public function auditUserId(): ?int
    {
        return $this->actorUserId;
    }
}
