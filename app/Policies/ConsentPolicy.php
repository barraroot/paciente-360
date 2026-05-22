<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Privacy\Models\ConsentRecord;
use App\Models\User;

/**
 * **T032 (Fase 8 — Lote A US-13.1)** — Policy para o domínio Consentimento.
 *
 * Regras (Princípio I — menor exposição):
 *   - `viewAny` / `view`  → ability `privacy.view` (Admin Clínica + Médico+ veem; demais negado).
 *   - `create` / `revoke` → ability `privacy.view` (mesma — mesma camada de gestão).
 *   - `export`            → ability `privacy.export` (Admin Clínica apenas; auditoria reforçada).
 *
 * **Cross-tenant**: o BelongsToTenant scope já filtra por `tenant_id` no
 * Eloquent. A policy aqui não precisa revalidar — confia no global scope.
 * Caso o `ConsentRecord` tenha sido fetchado sem scope (cenário Filament
 * Super Admin), o método `view()` ainda valida que o usuário tem ability.
 */
class ConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('privacy.view');
    }

    public function view(User $user, ConsentRecord $consent): bool
    {
        if (! $user->can('privacy.view')) {
            return false;
        }

        // Defesa em profundidade: confirma que o consent pertence ao mesmo
        // tenant do usuário. Em cenários normais, BelongsToTenant já filtrou —
        // mas se o controller obteve o consent via Filament withoutTenantScope(),
        // este check ainda protege o endpoint.
        return $user->tenant_id === null || $consent->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('privacy.view');
    }

    public function revoke(User $user, ConsentRecord $consent): bool
    {
        return $this->view($user, $consent);
    }

    public function export(User $user): bool
    {
        return $user->can('privacy.export');
    }
}
