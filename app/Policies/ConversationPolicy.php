<?php

namespace App\Policies;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;

/**
 * T119 — Policy de autorização para `Conversation`.
 *
 * Regras especiais para médicos:
 * - Médico vê apenas conversas atribuídas a si OU
 *   conversas cujo paciente tem ele como profissional_responsavel_id.
 * - Atendente, recepcionista e admin-clinica veem todas.
 * - Financeiro não tem inbox.view.
 */
final class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inbox.view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if (! $user->can('inbox.view')) {
            return false;
        }

        // Tenant isolation
        if ($conversation->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Médico: visibilidade restrita
        if ($user->hasRole('medico')) {
            return $this->medicoCanSee($user, $conversation);
        }

        return true;
    }

    public function respond(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('inbox.respond');
    }

    public function resolve(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('inbox.respond');
    }

    public function reopen(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('inbox.respond');
    }

    public function linkPatient(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('inbox.respond');
    }

    public function read(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /**
     * Verifica se o usuário pode atribuir a conversa (inbox.assign ability).
     * T154 — US-4.5.
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
     * T154 — US-4.5.
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
     * T154 — US-4.5.
     */
    public function viewAssignments(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /**
     * Verifica se o médico pode ver a conversa.
     * Condição: atribuída ao médico (assigned_user_id === user.id)
     * OU paciente tem o médico como profissional responsável
     * (patient.profissionalResponsavel.user_id === user.id).
     */
    private function medicoCanSee(User $user, Conversation $conversation): bool
    {
        if ($conversation->assigned_user_id === $user->id) {
            return true;
        }

        if ($conversation->patient_id !== null) {
            $conversation->loadMissing(['patient.profissionalResponsavel']);

            $profissional = $conversation->patient?->profissionalResponsavel;

            if ($profissional !== null && $profissional->user_id === $user->id) {
                return true;
            }
        }

        return false;
    }
}
