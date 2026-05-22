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
 * **T098 (Fase 8 — Lote B US-12.1)** — Tenant cancelado pelo Super Admin (AC-12.1.10).
 *
 * Inicia o relógio da política de retenção diferenciada Q20 (config tenant 30d,
 * paciente 90d, audit 1a, controladas 2a, billing 5a) — processada pelo cron
 * `super-admin:apply-retention-policy` (T104).
 */
final class TenantCancelado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $canceledByUserId,
        public readonly Carbon $canceledAt,
        public readonly string $retentionPolicy,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.canceled_by_super_admin';
    }

    public function auditPayload(): array
    {
        return [
            'canceled_at' => $this->canceledAt->toIso8601String(),
            'retention_policy' => $this->retentionPolicy,
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
        return $this->canceledByUserId;
    }
}
