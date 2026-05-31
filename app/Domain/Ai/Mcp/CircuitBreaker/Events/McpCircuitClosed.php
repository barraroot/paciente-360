<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\CircuitBreaker\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T054 (Fase 18 — US7, FR-053b/c)** — circuit breaker do MCP voltou a
 * `closed` (recuperação via canário com sucesso).
 */
final class McpCircuitClosed implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly string $source,
    ) {}

    public function auditAction(): string
    {
        return 'mcp.circuit.closed';
    }

    public function auditPayload(): array
    {
        return [
            'source' => $this->source,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return null;
    }

    public function auditUserId(): ?int
    {
        return null;
    }
}
