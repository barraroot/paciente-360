<?php

namespace App\Events\Tenant;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando o status de um tenant é alterado por uma ação administrativa.
 *
 * Exemplos: `trial → active`, `active → suspended`, `suspended → active`.
 * Persiste em `audit_logs` com action `tenant.status.changed`.
 */
final class TenantStatusChanged implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.status.changed';
    }

    public function auditableModel(): ?Model
    {
        return $this->tenant;
    }

    public function auditTenantId(): ?int
    {
        return (int) $this->tenant->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'old' => $this->oldStatus,
            'new' => $this->newStatus,
            'reason' => $this->reason,
        ];
    }
}
