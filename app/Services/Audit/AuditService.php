<?php

namespace App\Services\Audit;

use App\Events\Contracts\Auditable;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Serviço de auditoria — rota direta para persistência e consulta.
 *
 * Provê duas formas de persistir audit logs:
 *  1. Via `Event::dispatch()` → `PersistAuditLogListener` (fluxo assíncrono/desacoplado).
 *  2. Via `AuditService::log()` (rota direta; útil em Filament Actions e
 *     código que precisa do retorno imediato do `AuditLog` criado).
 *
 * Ambas as formas utilizam o mesmo `AuditAttributesBuilder`, garantindo
 * que as entradas produzidas sejam idênticas (critério de aceitação T074).
 *
 * @see App\Listeners\PersistAuditLogListener
 * @see App\Services\Audit\AuditAttributesBuilder
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
final class AuditService
{
    public function __construct(
        private readonly AuditAttributesBuilder $builder,
    ) {}

    /**
     * Persiste um evento `Auditable` diretamente em `audit_logs`, sem passar
     * pelo dispatcher de eventos. Retorna o `AuditLog` criado.
     *
     * Use quando precisar do ID/timestamp do log imediatamente após a ação,
     * ou em contextos (Filament Actions) onde o dispatcher não está disponível.
     */
    public function log(Auditable $event): AuditLog
    {
        $attributes = $this->builder->build($event);

        /** @var AuditLog */
        return AuditLog::create($attributes);
    }

    /**
     * Retorna um QueryBuilder para `audit_logs`, escopado ao tenant atual
     * quando há tenant resolvido no container.
     *
     * Super Admin (sem tenant no container): sem escopo — enxerga tudo.
     */
    public function query(): Builder
    {
        $query = AuditLog::query();

        if (app()->bound('tenant')) {
            $tenant = app('tenant');

            if (is_object($tenant) && isset($tenant->id)) {
                $query->where('tenant_id', $tenant->id);
            }
        }

        return $query;
    }

    /**
     * Retorna um QueryBuilder filtrado explicitamente pelo `$tenantId`
     * informado, sem depender do container (útil para Super Admin e
     * relatórios cross-tenant).
     */
    public function queryForTenant(int $tenantId): Builder
    {
        return AuditLog::query()->where('tenant_id', $tenantId);
    }
}
