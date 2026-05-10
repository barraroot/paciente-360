<?php

namespace App\Services\Tenant;

use App\Events\Tenant\TenantStatusChanged;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;

/**
 * Gerencia transições de estado de tenants (T283).
 *
 * Todas as transições disparam `TenantStatusChanged` (auditável) e
 * atualizam o campo `status` no banco. O tenant cancelado não é
 * soft-deleted — preservado para auditoria com status='cancelled'.
 */
class TenantStateService
{
    /**
     * Ativa um tenant em trial (trial → active).
     */
    public function activate(Tenant $tenant): void
    {
        $oldStatus = $tenant->status;

        $tenant->update(['status' => 'active']);

        Event::dispatch(new TenantStatusChanged(
            tenant: $tenant,
            oldStatus: $oldStatus,
            newStatus: 'active',
        ));
    }

    /**
     * Suspende um tenant (qualquer status → suspended).
     */
    public function suspend(Tenant $tenant, ?string $reason = null): void
    {
        $oldStatus = $tenant->status;

        $tenant->update(['status' => 'suspended']);

        Event::dispatch(new TenantStatusChanged(
            tenant: $tenant,
            oldStatus: $oldStatus,
            newStatus: 'suspended',
            reason: $reason,
        ));
    }

    /**
     * Reativa um tenant suspenso (suspended → active).
     */
    public function reactivate(Tenant $tenant): void
    {
        $oldStatus = $tenant->status;

        $tenant->update(['status' => 'active']);

        Event::dispatch(new TenantStatusChanged(
            tenant: $tenant,
            oldStatus: $oldStatus,
            newStatus: 'active',
        ));
    }

    /**
     * Cancela um tenant (qualquer → cancelled).
     *
     * O tenant não é soft-deleted — preservado integralmente para auditoria.
     */
    public function cancel(Tenant $tenant): void
    {
        $oldStatus = $tenant->status;

        $tenant->update(['status' => 'cancelled']);

        Event::dispatch(new TenantStatusChanged(
            tenant: $tenant,
            oldStatus: $oldStatus,
            newStatus: 'cancelled',
        ));
    }
}
