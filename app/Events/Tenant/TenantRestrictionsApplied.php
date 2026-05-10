<?php

namespace App\Events\Tenant;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando restrições de inadimplência são aplicadas ao tenant
 * (T187 — FR-014). Ocorre quando `overdue_since + 7 dias` passa sem pagamento.
 */
final class TenantRestrictionsApplied implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.restrictions.applied';
    }

    public function auditableModel(): ?Model
    {
        return $this->tenant;
    }

    public function auditTenantId(): ?int
    {
        return (int) $this->tenant->id;
    }

    public function auditUserId(): ?int
    {
        return null;
    }

    public function auditActorType(): ?string
    {
        return 'system';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'overdue_since' => $this->tenant->overdue_since?->toIso8601String(),
            'restrictions_applied_at' => $this->tenant->restrictions_applied_at?->toIso8601String(),
        ];
    }
}
