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
 * **T098 (Fase 8 — Lote B US-12.1)** — Sessão de impersonate iniciada (AC-12.1.5).
 *
 * Banner amarelo persistente "MODO IMPERSONATE" deve ser exibido em TODAS as
 * telas do tenant durante esta sessão (validado por `ImpersonateBannerTest`).
 */
final class ImpersonateIniciado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $superAdminId,
        public readonly int $tenantId,
        public readonly Carbon $startedAt,
        public readonly string $scope,
        public readonly string $ipAddress,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'impersonate.started';
    }

    public function auditPayload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'started_at' => $this->startedAt->toIso8601String(),
            'scope' => $this->scope,
            'ip_address' => $this->ipAddress,
            'reason' => $this->reason,
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
