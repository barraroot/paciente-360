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
 * **T121 (Fase 8 — Lote B US-12.2)** — Plano de um tenant alterado pelo Super Admin (AC-12.2.3).
 *
 * Distinto do `PlanoEditado` (que muda o catálogo). Este evento sinaliza
 * MIGRAÇÃO DE UM TENANT específico de um plan_version para outro — dispara
 * proration via Cashier (lógica já existente da Fase 0).
 */
final class PlanoAlteradoPeloSuperAdmin implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $oldPlanVersionId,
        public readonly int $newPlanVersionId,
        public readonly int $changedByUserId,
        public readonly Carbon $effectiveAt,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.plan.changed_by_super_admin';
    }

    public function auditPayload(): array
    {
        return [
            'old_plan_version_id' => $this->oldPlanVersionId,
            'new_plan_version_id' => $this->newPlanVersionId,
            'effective_at' => $this->effectiveAt->toIso8601String(),
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
        return $this->changedByUserId;
    }
}
