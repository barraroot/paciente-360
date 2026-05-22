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
 * **T098 (Fase 8 — Lote B US-12.1)** — Tenant reativado pelo Super Admin (AC-12.1.4).
 */
final class TenantReativado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $reactivatedByUserId,
        public readonly Carbon $reactivatedAt,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.reactivated_by_super_admin';
    }

    public function auditPayload(): array
    {
        return [
            'reactivated_at' => $this->reactivatedAt->toIso8601String(),
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
        return $this->reactivatedByUserId;
    }
}
