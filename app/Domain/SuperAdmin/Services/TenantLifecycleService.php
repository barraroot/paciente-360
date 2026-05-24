<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Services;

use App\Domain\SuperAdmin\Events\TenantCancelado;
use App\Domain\SuperAdmin\Events\TenantCriadoPorSuperAdmin;
use App\Domain\SuperAdmin\Events\TenantReativado;
use App\Domain\SuperAdmin\Events\TenantSuspenso;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * **T092 (Fase 8 — Lote B US-12.1)** — Gerencia lifecycle de tenants pelo Super Admin.
 *
 * Complementa o `App\Services\Tenant\TenantStateService` da Fase 0:
 *   - TenantStateService permanece para transições automáticas (overdue, etc.).
 *   - TenantLifecycleService adiciona rastreio "quem suspendeu/cancelou" + motivo
 *     com gate ≥10 chars + emite eventos da Fase 8 (Auditable).
 *
 * Operações:
 *   - `createByAdmin(data, Super Admin, billingMode)` — Q23 enterprise sales
 *   - `suspend(Tenant, Super Admin, reason)` — AC-12.1.3
 *   - `reactivate(Tenant, Super Admin)` — AC-12.1.4
 *   - `cancel(Tenant, Super Admin, reason)` — AC-12.1.10
 */
// NB: não-final para permitir spy/mock (Mockery) nos testes das actions Filament.
class TenantLifecycleService
{
    public function createByAdmin(array $data, User $createdBy, string $billingMode = 'stripe'): Tenant
    {
        if (! in_array($billingMode, ['stripe', 'offline_invoice'], true)) {
            throw new InvalidArgumentException('billing_mode deve ser "stripe" ou "offline_invoice".');
        }

        return DB::transaction(function () use ($data, $createdBy, $billingMode): Tenant {
            $tenant = Tenant::query()->create(array_merge($data, [
                'billing_mode' => $billingMode,
                'status' => $data['status'] ?? 'trial',
            ]));

            Event::dispatch(new TenantCriadoPorSuperAdmin(
                tenantId: $tenant->id,
                createdByUserId: $createdBy->id,
                billingMode: $billingMode,
            ));

            return $tenant;
        });
    }

    public function suspend(Tenant $tenant, User $suspendedBy, string $reason): Tenant
    {
        $this->assertReasonOk($reason);

        return DB::transaction(function () use ($tenant, $suspendedBy, $reason): Tenant {
            $now = Carbon::now();

            $tenant->update([
                'status' => 'suspended',
                'suspended_at' => $now,
                'suspended_by_user_id' => $suspendedBy->id,
                'suspended_reason' => trim($reason),
            ]);

            Event::dispatch(new TenantSuspenso(
                tenantId: $tenant->id,
                suspendedByUserId: $suspendedBy->id,
                reason: trim($reason),
                suspendedAt: $now,
            ));

            return $tenant->refresh();
        });
    }

    public function reactivate(Tenant $tenant, User $reactivatedBy): Tenant
    {
        return DB::transaction(function () use ($tenant, $reactivatedBy): Tenant {
            $now = Carbon::now();

            $tenant->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspended_by_user_id' => null,
                'suspended_reason' => null,
            ]);

            Event::dispatch(new TenantReativado(
                tenantId: $tenant->id,
                reactivatedByUserId: $reactivatedBy->id,
                reactivatedAt: $now,
            ));

            return $tenant->refresh();
        });
    }

    public function cancel(Tenant $tenant, User $canceledBy, string $reason): Tenant
    {
        $this->assertReasonOk($reason);

        return DB::transaction(function () use ($tenant, $canceledBy): Tenant {
            $now = Carbon::now();

            $tenant->update([
                'status' => 'cancelled',
                'canceled_at' => $now,
                'retention_policy' => 'differentiated_per_category',
            ]);

            Event::dispatch(new TenantCancelado(
                tenantId: $tenant->id,
                canceledByUserId: $canceledBy->id,
                canceledAt: $now,
                retentionPolicy: 'differentiated_per_category',
            ));

            return $tenant->refresh();
        });
    }

    private function assertReasonOk(string $reason): void
    {
        if (strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Motivo deve ter no mínimo 10 caracteres.');
        }
    }
}
