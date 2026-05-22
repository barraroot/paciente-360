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
 * **T098 (Fase 8 — Lote B US-12.1)** — Tenant suspenso pelo Super Admin (AC-12.1.3).
 *
 * Aciona listener `ApplyTenantSuspensionEffectsListener` (T093) que revoga
 * sessões + pausa jobs em fila + marca Filament users logout-on-next-request.
 */
final class TenantSuspenso implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $suspendedByUserId,
        public readonly string $reason,
        public readonly Carbon $suspendedAt,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.suspended_by_super_admin';
    }

    public function auditPayload(): array
    {
        return [
            'reason' => $this->reason,
            'suspended_at' => $this->suspendedAt->toIso8601String(),
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
        return $this->suspendedByUserId;
    }
}
