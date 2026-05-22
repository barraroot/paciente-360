<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T098 (Fase 8 — Lote B US-12.1)** — Tenant criado manualmente pelo Super Admin (Q23).
 *
 * Distinto do `TenantRegistered` da Fase 0 (self-service público).
 * Enterprise sales (`billing_mode=offline_invoice`) usa este caminho.
 */
final class TenantCriadoPorSuperAdmin implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $createdByUserId,
        public readonly string $billingMode, // 'stripe' | 'offline_invoice'
    ) {}

    public function auditAction(): string
    {
        return 'tenant.created_by_super_admin';
    }

    public function auditPayload(): array
    {
        return [
            'billing_mode' => $this->billingMode,
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
        return $this->createdByUserId;
    }
}
