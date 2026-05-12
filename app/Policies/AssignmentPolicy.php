<?php

namespace App\Policies;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;

/**
 * T154 — Policy para operações de atribuição/transferência de conversas.
 *
 * Garante que o usuário autenticado tem a ability necessária E que a conversa
 * pertence ao mesmo tenant. A validação de cross-tenant do usuário ALVO
 * é feita no AssignmentService (defesa em profundidade).
 */
final class AssignmentPolicy
{
    /**
     * Verifica se o usuário pode atribuir a conversa (inbox.assign ability).
     */
    public function assign(User $user, Conversation $conversation): bool
    {
        if (! $user->can('inbox.assign')) {
            return false;
        }

        return $conversation->tenant_id === $user->tenant_id;
    }

    /**
     * Verifica se o usuário pode transferir a conversa (inbox.transfer ability).
     */
    public function transfer(User $user, Conversation $conversation): bool
    {
        if (! $user->can('inbox.transfer')) {
            return false;
        }

        return $conversation->tenant_id === $user->tenant_id;
    }

    /**
     * Verifica se o usuário pode ver o histórico de atribuições (inbox.view ability).
     */
    public function viewAssignments(User $user, Conversation $conversation): bool
    {
        if (! $user->can('inbox.view')) {
            return false;
        }

        return $conversation->tenant_id === $user->tenant_id;
    }
}
