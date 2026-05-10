<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope que aplica `WHERE tenant_id = ?` em toda query de Models
 * que usam a trait `BelongsToTenant` (Princípio II — isolamento de
 * tenants por padrão).
 *
 * Comportamento:
 *  - Se `app('tenant')` está bound (request HTTP autenticado, job
 *    rehidratado pelo `TenantAwareJob`), aplica o filtro.
 *  - Se não há tenant resolvido (CLI, scheduler, super admin sem
 *    contexto, jobs de webhook do Stripe), **não aplica** — cabe ao
 *    autor do job/comando setar o contexto antes ou usar
 *    `withoutGlobalScope(TenantScope::class)`.
 *
 * Filament Super Admin: usar `Model::withoutTenantScope()` (helper na
 * trait) para queries cross-tenant explícitas e auditadas.
 */
class TenantScope implements Scope
{
    /**
     * Aplica o scope na query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenant')) {
            return;
        }

        $tenant = app('tenant');

        if (! is_object($tenant) || ! isset($tenant->id)) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenant->id);
    }
}
