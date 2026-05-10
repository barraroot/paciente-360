<?php

namespace App\Events\Tenant;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado ao criar um novo tenant via cadastro público (US-1.1, FR-001).
 *
 * Persistido como `audit_logs` com action `tenant.registered` pelo
 * `PersistAuditLogListener`. Princípio I (LGPD) — payload NÃO inclui CNPJ
 * inteiro nem dados do responsável; apenas slug + 4 últimos dígitos.
 */
final class TenantRegistered implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $adminUser,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.registered';
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
        return (int) $this->adminUser->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'slug' => $this->tenant->slug,
            'cnpj_last4' => substr((string) $this->tenant->cnpj, -4),
            'terms_version' => $this->tenant->terms_version,
        ];
    }
}
