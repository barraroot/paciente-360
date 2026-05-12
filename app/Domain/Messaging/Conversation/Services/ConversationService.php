<?php

namespace App\Domain\Messaging\Conversation\Services;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaCriada;
use App\Domain\Messaging\Conversation\Events\ConversaReaberta;
use App\Domain\Messaging\Conversation\Events\ConversaResolvida;
use App\Domain\Messaging\Conversation\Events\ConversaVinculadaAPaciente;
use App\Domain\Messaging\Conversation\Exceptions\InvalidConversationTransitionException;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\StateMachine\ConversationStatusTransitions;
use App\Models\Paciente;
use App\Models\User;

/**
 * T105 — Serviço de domínio para operações em conversas.
 *
 * Aplica state machine, dispara eventos auditáveis e mantém consistência
 * dos campos de status da Conversation.
 *
 * @see specs/003-omnichannel-inbox/spec.md § NC-2 + FR-010–FR-014
 */
final class ConversationService
{
    /**
     * Encontra ou cria conversa para mensagem inbound.
     *
     * Se a conversa existente estiver `resolvida`, reabre com motivo `nova_msg`.
     * Se não existir, cria com status `aberta`.
     */
    public function findOrCreateForInbound(
        Channel $channel,
        string $externalThreadId,
        ?int $patientId = null,
    ): Conversation {
        $externalThreadIdNormalized = strtolower(trim($externalThreadId));

        /** @var Conversation|null $conversation */
        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $channel->tenant_id)
            ->where('channel_id', $channel->id)
            ->where('external_thread_id', $externalThreadId)
            ->first();

        if ($conversation !== null) {
            if ($conversation->status === 'resolvida') {
                $conversation = $this->reopen($conversation, 'nova_msg');
            }

            return $conversation;
        }

        $conversation = Conversation::create([
            'tenant_id' => $channel->tenant_id,
            'channel_id' => $channel->id,
            'patient_id' => $patientId,
            'external_thread_id' => $externalThreadId,
            'status' => 'aberta',
            'opened_at' => now(),
            'priority' => 'normal',
            'received_outside_hours' => false,
            'unread_count' => 0,
        ]);

        event(new ConversaCriada($conversation));

        return $conversation;
    }

    /**
     * Resolve uma conversa (manual ou automático).
     *
     * @throws InvalidConversationTransitionException
     */
    public function resolve(
        Conversation $conv,
        string $mode = 'manual',
        ?int $resolutorId = null,
    ): Conversation {
        ConversationStatusTransitions::transition($conv->status, 'resolvida');

        $conv->update([
            'status' => 'resolvida',
            'resolved_at' => now(),
            'resolution_mode' => $mode,
        ]);

        event(new ConversaResolvida($conv, $mode, $resolutorId));

        return $conv->fresh() ?? $conv;
    }

    /**
     * Reabre uma conversa resolvida.
     *
     * Status final = `aberta` (NC-2: "status volta a aberta, dispara ConversaReaberta").
     *
     * @throws InvalidConversationTransitionException
     */
    public function reopen(Conversation $conv, string $motivo = 'manual'): Conversation
    {
        ConversationStatusTransitions::transition($conv->status, 'aberta');

        $conv->update([
            'status' => 'aberta',
            'resolved_at' => null,
            'resolution_mode' => null,
        ]);

        event(new ConversaReaberta($conv, $motivo));

        return $conv->fresh() ?? $conv;
    }

    /**
     * Vincula conversa a um paciente.
     */
    public function linkToPatient(
        Conversation $conv,
        Paciente $patient,
        ?int $executorId,
        string $modo = 'manual',
    ): Conversation {
        $conv->update(['patient_id' => $patient->id]);

        event(new ConversaVinculadaAPaciente($conv, $patient->id, $modo));

        return $conv->fresh() ?? $conv;
    }

    /**
     * Marca conversa como lida (unread_count = 0).
     * Também marca como lidas as mensagens inbound recebidas antes do momento atual.
     */
    public function markAsRead(Conversation $conv, User $user): void
    {
        $now = now();

        $conv->update(['unread_count' => 0]);

        $conv->messages()
            ->where('direction', 'in')
            ->whereNull('read_at')
            ->where('created_at', '<=', $now)
            ->update(['read_at' => $now]);
    }
}
