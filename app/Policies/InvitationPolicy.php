<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy de autorização para `Invitation` (FR-025).
 *
 * Apenas `admin-clinica` pode listar, criar e revogar convites.
 * O Gate `super-admin` (definido em AppServiceProvider) já bypassa todas
 * as policies automaticamente para usuários com role `super-admin`.
 */
final class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }
}
