<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

/**
 * T032 — Policy de autorização para `Tag` (spec § 2.4).
 *
 * Tags livres (`tipo = 'livre'`) podem ser criadas e removidas por quem
 * tem `paciente.create`. Tags sistêmicas (`tipo = 'sistemica'`, prefixo
 * `sys:`) são gerenciadas exclusivamente pelo sistema — qualquer
 * tentativa de criar/atualizar/deletar via usuário é negada.
 */
final class TagPolicy
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

    public function view(User $user, Tag $tag): bool
    {
        return $user->can('paciente.view');
    }

    public function create(User $user): bool
    {
        return $user->can('paciente.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        if ($tag->tipo === 'sistemica') {
            return false;
        }

        return $user->can('paciente.create');
    }

    public function delete(User $user, Tag $tag): bool
    {
        if ($tag->tipo === 'sistemica') {
            return false;
        }

        return $user->can('paciente.create');
    }
}
