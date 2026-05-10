<?php

namespace App\Events\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Implementações default para a interface `Auditable`.
 *
 * Use esta trait em eventos que implementam `Auditable` para evitar
 * boilerplate repetitivo. Os métodos podem ser sobrescritos na classe
 * concreta quando necessário.
 *
 * @see App\Events\Contracts\Auditable
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
trait IsAuditable
{
    /**
     * Por padrão, o payload é vazio. Sobrescreva para incluir dados.
     *
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [];
    }

    /**
     * Por padrão, não há model alvo.
     */
    public function auditableModel(): ?Model
    {
        return null;
    }

    /**
     * Resolve o `tenant_id` do container (tenant resolvido pelo middleware
     * `ResolveTenant`). Retorna `null` para ações de Super Admin sem tenant.
     */
    public function auditTenantId(): ?int
    {
        if (! app()->bound('tenant')) {
            return null;
        }

        $tenant = app('tenant');

        return (is_object($tenant) && isset($tenant->id)) ? (int) $tenant->id : null;
    }

    /**
     * Resolve o `user_id` do guard de autenticação padrão.
     * Retorna `null` para jobs/webhooks sem usuário autenticado.
     */
    public function auditUserId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Por padrão, não faz override do `actor_type` — o listener infere
     * automaticamente (`'user'` ou `'system'`).
     */
    public function auditActorType(): ?string
    {
        return null;
    }
}
