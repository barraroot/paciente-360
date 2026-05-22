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
 * **T098 (Fase 8 — Lote B US-12.1)** — Sessão de impersonate encerrada (AC-12.1.6).
 *
 * `screens_visited_count` no payload permite alerta Sentry quando sessão
 * navega muitas telas sem fechar (potencial leak de auditoria) — R-8-3.
 */
final class ImpersonateEncerrado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $superAdminId,
        public readonly int $tenantId,
        public readonly Carbon $endedAt,
        public readonly int $durationSeconds,
        public readonly int $screensVisitedCount,
    ) {}

    public function auditAction(): string
    {
        return 'impersonate.ended';
    }

    public function auditPayload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'ended_at' => $this->endedAt->toIso8601String(),
            'duration_seconds' => $this->durationSeconds,
            'screens_visited_count' => $this->screensVisitedCount,
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
