<?php

namespace App\Policies;

use App\Models\Convenio;
use App\Models\User;

/**
 * T032 — Policy de autorização para `Convenio` (spec § 2.4).
 *
 * Decisão de produto: apenas Admin Clínica gerencia o catálogo de
 * convênios da clínica (CRUD). Demais perfis com `paciente.view`
 * conseguem listar/ler para fins de vínculo paciente↔convênio.
 */
final class ConvenioPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('paciente.view');
    }

    public function view(User $user, Convenio $convenio): bool
    {
        return $user->can('paciente.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function update(User $user, Convenio $convenio): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function delete(User $user, Convenio $convenio): bool
    {
        return $user->hasRole('admin-clinica');
    }
}
