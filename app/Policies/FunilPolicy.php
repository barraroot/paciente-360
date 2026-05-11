<?php

namespace App\Policies;

use App\Models\FunilColuna;
use App\Models\User;

/**
 * T032 — Policy de autorização para `FunilColuna` (spec § 2.4).
 *
 * Configuração do funil (criar/renomear/excluir colunas) é privilégio
 * de Admin Clínica. Operações de listagem e movimentação de leads
 * dentro do Kanban exigem apenas `paciente.view`.
 */
final class FunilPolicy
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

    public function view(User $user, FunilColuna $coluna): bool
    {
        return $user->can('paciente.view');
    }

    /**
     * Movimentar paciente entre colunas (drag-and-drop no Kanban).
     */
    public function move(User $user): bool
    {
        return $user->can('paciente.view') && $user->can('paciente.update');
    }

    /**
     * Reconfigurar colunas (criar, renomear, reordenar, excluir).
     */
    public function configure(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function create(User $user): bool
    {
        return $this->configure($user);
    }

    public function update(User $user, FunilColuna $coluna): bool
    {
        return $this->configure($user);
    }

    public function delete(User $user, FunilColuna $coluna): bool
    {
        // Colunas system não podem ser deletadas (mas podem ser renomeadas).
        if ($coluna->is_system) {
            return false;
        }

        return $this->configure($user);
    }
}
