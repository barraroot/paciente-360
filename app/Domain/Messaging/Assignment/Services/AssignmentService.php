<?php

namespace App\Domain\Messaging\Assignment\Services;

use App\Domain\Messaging\Assignment\Events\ConversaAtribuida;
use App\Domain\Messaging\Assignment\Events\ConversaAtribuidaParaInbox;
use App\Domain\Messaging\Assignment\Events\ConversaTransferida;
use App\Domain\Messaging\Assignment\Exceptions\CrossTenantAssignmentException;
use App\Domain\Messaging\Assignment\Exceptions\UserAtMaxLimitException;
use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use App\Domain\Messaging\Assignment\Services\Strategies\PatientOwnerStrategy;
use App\Domain\Messaging\Assignment\Services\Strategies\RoundRobinStrategy;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T146 — Service centralizado para atribuição e transferência de conversas.
 *
 * Pipeline: Controller (thin) → AssignmentService → Events → Audit.
 *
 * Princípio II (não-negociável): todo método valida que o usuário alvo
 * pertence ao mesmo tenant da conversa.
 *
 * Spec § R9 (risco 🟡 Média): race condition em round-robin é mitigado via
 * pessimistic lock (DB::transaction + lockForUpdate) em TODA atribuição.
 */
final class AssignmentService
{
    public function __construct(
        private readonly RoundRobinStrategy $roundRobin,
        private readonly PatientOwnerStrategy $patientOwner,
    ) {}

    /**
     * Atribuição manual: admin/atendente escolhe o destinatário explicitamente.
     *
     * Valida limite max_concurrent_conversations via UserPresence.
     * Se target User não tem registro de presence, atribuição é permitida (sem limite).
     */
    public function assignManual(
        Conversation $conversation,
        User $target,
        ?User $assignedBy,
        ?string $note = null,
    ): Conversation {
        $this->guardCrossTenant($conversation, $target);

        // Check max limit for manual assign (only if presence exists)
        $presence = UserPresence::where('tenant_id', $conversation->tenant_id)
            ->where('user_id', $target->id)
            ->first();

        if ($presence !== null && $presence->current_assigned_count >= $presence->max_concurrent_conversations) {
            throw new UserAtMaxLimitException(
                $target->id,
                $presence->current_assigned_count,
                $presence->max_concurrent_conversations,
            );
        }

        return DB::transaction(function () use ($conversation, $target, $assignedBy, $note): Conversation {
            /** @var Conversation $conversation */
            $conversation = Conversation::where('id', $conversation->id)->lockForUpdate()->firstOrFail();

            $this->closeCurrentAssignment($conversation);

            $this->createAssignmentRow(
                conversation: $conversation,
                userId: $target->id,
                assignedBy: $assignedBy?->id,
                reason: 'manual',
                note: $note,
            );

            $conversation->update([
                'assigned_user_id' => $target->id,
                'assigned_at' => now(),
                'assignment_strategy' => 'manual',
            ]);

            ConversaAtribuida::dispatch($conversation, 'manual', $assignedBy?->id);
            ConversaAtribuidaParaInbox::dispatch($conversation, 'manual', $target->id);

            Log::info('assignment.manual', [
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'target_user_id' => $target->id,
                'assigned_by' => $assignedBy?->id,
            ]);

            return $conversation->fresh();
        });
    }

    /**
     * Atribuição automática: resolve via regras configuradas do tenant.
     *
     * Consulta `messaging_assignment_rules` ordenado por priority e aplica
     * a estratégia configurada. Fallback: round-robin → sem atribuição (fila).
     */
    public function assignAuto(Conversation $conversation, ?User $assignedBy, ?string $strategyOverride = null): Conversation
    {
        $strategy = $strategyOverride ?? $this->resolveActiveStrategy($conversation);
        $target = null;
        $mode = 'auto_round_robin';

        if ($strategy === 'patient_owner') {
            $target = $this->patientOwner->pickUser($conversation);

            if ($target !== null) {
                $mode = 'auto_patient_owner';
            } else {
                // fallback para round-robin
                $target = $this->roundRobin->pickUser($conversation);
                $mode = 'auto_round_robin';
            }
        } else {
            $target = $this->roundRobin->pickUser($conversation);
            $mode = 'auto_round_robin';
        }

        if ($target === null) {
            // Sem atendente elegível — mantém na fila sem atribuição
            return $conversation;
        }

        return DB::transaction(function () use ($conversation, $target, $assignedBy, $mode): Conversation {
            /** @var Conversation $conversation */
            $conversation = Conversation::where('id', $conversation->id)->lockForUpdate()->firstOrFail();

            $this->closeCurrentAssignment($conversation);

            $this->createAssignmentRow(
                conversation: $conversation,
                userId: $target->id,
                assignedBy: $assignedBy?->id,
                reason: 'auto_atribuicao',
            );

            $conversation->update([
                'assigned_user_id' => $target->id,
                'assigned_at' => now(),
                'assignment_strategy' => $mode,
            ]);

            ConversaAtribuida::dispatch($conversation, $mode, $assignedBy?->id);
            ConversaAtribuidaParaInbox::dispatch($conversation, $mode, $target->id);

            return $conversation->fresh();
        });
    }

    /**
     * Transferência para usuário específico com nota obrigatória (mín. 10 chars).
     *
     * Nota: validação do tamanho mínimo é feita no FormRequest. Aqui é defensivo.
     */
    public function transferToUser(
        Conversation $conversation,
        User $target,
        User $by,
        string $note,
    ): Conversation {
        $this->guardCrossTenant($conversation, $target, $by);

        return DB::transaction(function () use ($conversation, $target, $by, $note): Conversation {
            /** @var Conversation $conversation */
            $conversation = Conversation::where('id', $conversation->id)->lockForUpdate()->firstOrFail();

            $fromUserId = $conversation->assigned_user_id;
            $this->closeCurrentAssignment($conversation);

            $this->createAssignmentRow(
                conversation: $conversation,
                userId: $target->id,
                assignedBy: $by->id,
                reason: 'transferencia',
                note: $note,
            );

            $conversation->update([
                'assigned_user_id' => $target->id,
                'assigned_at' => now(),
                'assignment_strategy' => 'transfer',
            ]);

            ConversaTransferida::dispatch(
                conversation: $conversation,
                fromUserId: $fromUserId,
                toUserId: $target->id,
                toRole: null,
                noteSanitized: $this->sanitizeNote($note),
                executorId: $by->id,
            );
            ConversaAtribuidaParaInbox::dispatch($conversation, 'transfer', $target->id, $fromUserId);

            return $conversation->fresh();
        });
    }

    /**
     * Transferência para role: aplica round-robin restrito à role especificada.
     * Se ninguém disponível, conversa vai para fila (assigned_user_id = null).
     */
    public function transferToRole(
        Conversation $conversation,
        string $role,
        User $by,
        string $note,
    ): Conversation {
        $target = $this->pickUserByRole($conversation, $role);

        return DB::transaction(function () use ($conversation, $target, $by, $note, $role): Conversation {
            /** @var Conversation $conversation */
            $conversation = Conversation::where('id', $conversation->id)->lockForUpdate()->firstOrFail();

            $fromUserId = $conversation->assigned_user_id;
            $this->closeCurrentAssignment($conversation);

            $this->createAssignmentRow(
                conversation: $conversation,
                userId: $target?->id,
                assignedBy: $by->id,
                reason: 'transferencia',
                note: $note,
                assignmentRole: $role,
            );

            $conversation->update([
                'assigned_user_id' => $target?->id,
                'assigned_at' => $target !== null ? now() : null,
                'assignment_strategy' => $target !== null ? 'transfer' : null,
            ]);

            ConversaTransferida::dispatch(
                conversation: $conversation,
                fromUserId: $fromUserId,
                toUserId: $target?->id,
                toRole: $role,
                noteSanitized: $this->sanitizeNote($note),
                executorId: $by->id,
            );
            ConversaAtribuidaParaInbox::dispatch($conversation, 'transfer', $target?->id, $fromUserId);

            return $conversation->fresh();
        });
    }

    /**
     * Remove atribuição, colocando conversa de volta na fila "Sem atendente".
     */
    public function unassign(Conversation $conversation, ?User $by): Conversation
    {
        return DB::transaction(function () use ($conversation): Conversation {
            /** @var Conversation $conversation */
            $conversation = Conversation::where('id', $conversation->id)->lockForUpdate()->firstOrFail();

            $this->closeCurrentAssignment($conversation);

            $conversation->update([
                'assigned_user_id' => null,
                'assigned_at' => null,
                'assignment_strategy' => null,
            ]);

            return $conversation->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Validates that the target user belongs to the same tenant as the conversation.
     * Defense in depth — Policy validates first; Service also validates here.
     *
     * @throws CrossTenantAssignmentException
     */
    private function guardCrossTenant(Conversation $conversation, User ...$users): void
    {
        foreach ($users as $user) {
            if ($user->tenant_id !== $conversation->tenant_id) {
                // Record audit before throwing
                $this->recordCrossTenantAttempt($conversation, $user);

                throw new CrossTenantAssignmentException($conversation->tenant_id, $user->id);
            }
        }
    }

    /**
     * Records a cross-tenant attempt audit log entry.
     */
    private function recordCrossTenantAttempt(Conversation $conversation, User $targetUser): void
    {
        try {
            AuditLog::create([
                'tenant_id' => $conversation->tenant_id,
                'user_id' => auth()->id(),
                'actor_type' => 'user',
                'action' => 'conversa.transferencia.cross_tenant_bloqueado',
                'auditable_type' => Conversation::class,
                'auditable_id' => $conversation->id,
                'payload' => [
                    'conversation_id' => $conversation->id,
                    'target_user_id' => $targetUser->id,
                    'target_user_tenant_id' => $targetUser->tenant_id,
                ],
            ]);
        } catch (\Throwable) {
            // Never let audit failure propagate — the 403 is the important part
        }
    }

    /**
     * Closes the current active assignment row (sets unassigned_at = now()).
     */
    private function closeCurrentAssignment(Conversation $conversation): void
    {
        ConversationAssignment::where('conversation_id', $conversation->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);
    }

    /**
     * Creates an append-only assignment history row.
     */
    private function createAssignmentRow(
        Conversation $conversation,
        ?int $userId,
        ?int $assignedBy,
        string $reason,
        ?string $note = null,
        ?string $assignmentRole = null,
    ): ConversationAssignment {
        return ConversationAssignment::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'assigned_by' => $assignedBy,
            'assignment_role' => $assignmentRole,
            'assigned_at' => now(),
            'unassigned_at' => null,
            'transfer_note' => $note,
            'reason' => $reason,
        ]);
    }

    /**
     * Picks an online user by role with capacity, ordered by ascending id (deterministic).
     */
    private function pickUserByRole(Conversation $conversation, string $role): ?User
    {
        $tenantId = $conversation->tenant_id;

        // Eligible user IDs from presence table
        $eligibleIds = UserPresence::where('tenant_id', $tenantId)
            ->where('status', 'online')
            ->where('last_seen_at', '>', now()->subMinutes(5))
            ->whereRaw('current_assigned_count < max_concurrent_conversations')
            ->pluck('user_id')
            ->toArray();

        if (empty($eligibleIds)) {
            return null;
        }

        return User::where('tenant_id', $tenantId)
            ->whereIn('id', $eligibleIds)
            ->whereHas('roles', fn ($q) => $q->where('name', $role))
            ->orderBy('id')
            ->first();
    }

    /**
     * Resolves the first active auto-assign strategy from the tenant's rules.
     * Defaults to 'round_robin' when no rules configured.
     */
    private function resolveActiveStrategy(Conversation $conversation): string
    {
        $rule = AssignmentRule::where('tenant_id', $conversation->tenant_id)
            ->where('is_active', true)
            ->where(function ($q) use ($conversation): void {
                $q->whereNull('channel_id')
                    ->orWhere('channel_id', $conversation->channel_id);
            })
            ->orderBy('priority')
            ->first();

        return $rule?->strategy ?? 'round_robin';
    }

    /**
     * Basic sanitization of transfer notes for audit payload.
     * Preserves the note but trims whitespace.
     */
    private function sanitizeNote(string $note): string
    {
        return trim($note);
    }
}
