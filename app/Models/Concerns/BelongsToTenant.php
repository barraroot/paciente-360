<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait que torna um Model "tenant-aware":
 *
 *  1. Aplica `TenantScope` como global scope (Princípio II — todas as
 *     queries são automaticamente filtradas por `tenant_id`).
 *  2. Auto-popula `tenant_id` no evento `creating` quando o atributo
 *     está vazio E o container tem `app('tenant')` resolvido.
 *  3. Expõe `withoutTenantScope()` para uso EXPLÍCITO em painéis
 *     cross-tenant (Filament Super Admin) — qualquer chamada a este
 *     método DEVE ser justificada/auditada via comentário no PR.
 *
 * IMPORTANTE: o Model `Tenant` NÃO usa esta trait — ele é o próprio
 * tenant.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    /**
     * Hook automático do Laravel: chamado pelo `bootTraits()` do Model.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(static function (Model $model): void {
            if (! empty($model->getAttribute('tenant_id'))) {
                return;
            }

            if (! app()->bound('tenant')) {
                return;
            }

            $tenant = app('tenant');

            if (is_object($tenant) && isset($tenant->id)) {
                $model->setAttribute('tenant_id', $tenant->id);
            }
        });
    }

    /**
     * Remove o global scope `TenantScope` da query atual. Use **somente**
     * em Filament Super Admin ou jobs de manutenção cross-tenant —
     * qualquer outro uso é violação do Princípio II.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
