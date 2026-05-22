<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T098 (Fase 8 — Lote B US-12.1)** — Audit granular: tela visitada durante
 * impersonate (Gate 7 — Q19).
 *
 * Audit-only (não dispara workflow). Persistido na tabela `super_admin_audit_screens`.
 * Volume alto esperado — não despachado a Sentry, apenas log estruturado.
 */
final class ImpersonateTelaVisitada implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $superAdminId,
        public readonly int $tenantId,
        public readonly string $route,
        public readonly string $path,
        public readonly string $method,
        public readonly Carbon $visitedAt,
        public readonly string $ipAddress,
    ) {}

    public function auditAction(): string
    {
        return 'impersonate.screen_visited';
    }

    public function auditPayload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'route' => $this->route,
            'path' => $this->path,
            'method' => $this->method,
            'visited_at' => $this->visitedAt->toIso8601String(),
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function auditUserId(): ?int
    {
        return $this->superAdminId;
    }
}
