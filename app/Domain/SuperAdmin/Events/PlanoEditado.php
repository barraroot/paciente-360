<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T121 (Fase 8 — Lote B US-12.2)** — Plano editado, criando nova versão.
 *
 * AC-12.2.2 / Q12.2.2 — edição NÃO altera tenants existentes; cria nova
 * versão e novos tenants veem-na. Tenants antigos ficam vinculados à
 * versão original via tenant_plan_bindings.
 */
final class PlanoEditado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $planId,
        public readonly int $oldVersion,
        public readonly int $newVersion,
        public readonly int $oldVersionId,
        public readonly int $newVersionId,
        public readonly ?int $changedByUserId,
    ) {}

    public function auditAction(): string
    {
        return 'plan.edited';
    }

    public function auditPayload(): array
    {
        return [
            'plan_id' => $this->planId,
            'old_version' => $this->oldVersion,
            'new_version' => $this->newVersion,
            'old_version_id' => $this->oldVersionId,
            'new_version_id' => $this->newVersionId,
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
        return $this->changedByUserId;
    }
}
