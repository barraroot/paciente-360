<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de recuperação do tenant após inadimplência (T184).
 * Disparado quando `invoice.payment_succeeded` e o tenant estava `overdue`.
 */
final class TenantRecovered implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.recovered';
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
        return 'webhook';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'recovered_at' => now()->toIso8601String(),
        ];
    }
}
