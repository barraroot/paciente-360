<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy de autorização para `User` (FR-026).
 *
 * Apenas `admin-clinica` pode listar, editar e desativar usuários internos.
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('admin-clinica');
    }
}
