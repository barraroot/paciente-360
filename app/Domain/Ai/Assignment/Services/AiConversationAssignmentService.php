<?php

declare(strict_types=1);

namespace App\Domain\Ai\Assignment\Services;

use App\Domain\Ai\Assignment\Events\IAEscalouParaHumano;
use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Distribution\Services\AiPersonaSelectorService;
use App\Domain\Ai\Persona\Events\PersonaAtribuidaAConversa;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Conversation\Models\Conversation;
use Illuminate\Support\Carbon;

/**
 * Cria/consulta atribuições de persona à conversa existente (FR-015..FR-017).
 *
 * Continuidade: uma conversa já atribuída mantém a MESMA persona até encerrar,
 * pausar, transferir ou reatribuição explícita (G4). A UNIQUE parcial garante
 * uma única atribuição não-encerrada por conversa.
 */
final class AiConversationAssignmentService
{
    public function __construct(private readonly AiPersonaSelectorService $selector) {}

    /**
     * Sentinela de pausa INDEFINIDA da IA Matricial (R5): sem auto-resume
     * temporizado — só reativação explícita limpa o pause.
     */
    public static function indefinitePauseUntil(): Carbon
    {
        return now()->addCentury();
    }

    public function findActive(int $conversationId): ?AiConversationAssignment
    {
        return AiConversationAssignment::query()
            ->where('conversation_id', $conversationId)
            ->where('status', '!=', AiConversationAssignment::STATUS_CLOSED)
            ->first();
    }

    /**
     * Resolve a persona da conversa: reusa a atribuição ativa (continuidade)
     * ou cria uma nova via round-robin. Retorna null se o canal não tiver
     * persona ativa (IA não atende).
     */
    public function resolveForConversation(Conversation $conversation): ?AiPersona
    {
        $existing = $this->findActive($conversation->id);
        if ($existing !== null) {
            return $existing->persona;
        }

        $channelType = $conversation->channel?->type
            ?? $conversation->loadMissing('channel')->channel?->type;

        if ($channelType === null) {
            return null;
        }

        $persona = $this->selector->selectForNewConversation($conversation->tenant_id, $channelType);
        if ($persona === null) {
            return null;
        }

        $this->assign($conversation, $persona, $channelType);

        return $persona;
    }

    public function assign(Conversation $conversation, AiPersona $persona, string $channelType): AiConversationAssignment
    {
        return AiConversationAssignment::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'channel_type' => $channelType,
            'ai_persona_id' => $persona->id,
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'metadata' => [],
        ]);
    }

    public function close(AiConversationAssignment $assignment): void
    {
        $assignment->update([
            'status' => AiConversationAssignment::STATUS_CLOSED,
            'unassigned_at' => now(),
        ]);
    }

    /**
     * Pausa manual e INDEFINIDA da IA na conversa (FR-016/SC-007): seta o pause
     * na conversa (sentinela far-future) e marca a atribuição ativa como `paused`.
     */
    public function pauseIndefinitely(Conversation $conversation, ?int $userId): void
    {
        $conversation->update([
            'ai_paused_until' => self::indefinitePauseUntil(),
            'ai_pause_set_by' => $userId,
        ]);

        $this->markAssignmentPaused($conversation->id, $userId);
    }

    /**
     * Inverso de `markAssignmentPaused`: devolve a atribuição ativa a `assigned`
     * (sem tocar na conversa). Usado pelo listener de resume da IA, onde a
     * conversa já teve `ai_paused_until` zerado pelo `HumanTakeoverService`.
     * Idempotente — seguro chamar quando não há atribuição ou já está ativa.
     */
    public function markAssignmentResumed(int $conversationId): void
    {
        $assignment = $this->findActive($conversationId);

        if ($assignment === null || ! $assignment->isPaused()) {
            return;
        }

        $assignment->update([
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
            'paused_by' => null,
            'paused_at' => null,
        ]);
    }

    /**
     * Marca a atribuição ativa como pausada (sem tocar na conversa). Usado pelo
     * listener de takeover humano, onde a conversa já teve o pause setado.
     */
    public function markAssignmentPaused(int $conversationId, ?int $userId): void
    {
        $assignment = $this->findActive($conversationId);

        if ($assignment === null || $assignment->isPaused()) {
            return;
        }

        $assignment->update([
            'status' => AiConversationAssignment::STATUS_PAUSED,
            'paused_by' => $userId,
            'paused_at' => now(),
        ]);
    }

    /**
     * Reativa a IA na conversa: limpa o pause e devolve a atribuição a `assigned`.
     * Idempotente. Não reativa conversa encerrada (verificado no controller).
     */
    public function resume(Conversation $conversation, ?int $userId): void
    {
        $conversation->update([
            'ai_paused_until' => null,
            'ai_pause_set_by' => null,
        ]);

        $assignment = $this->findActive($conversation->id);
        if ($assignment !== null && $assignment->isPaused()) {
            $assignment->update([
                'status' => AiConversationAssignment::STATUS_ASSIGNED,
                'paused_by' => null,
                'paused_at' => null,
            ]);
        }

        event(new ConversaRetomadaPelaIA(conversation: $conversation->refresh(), mode: 'manual'));
    }

    /**
     * Reatribui conversas ativas que apontam para uma persona que saiu de
     * operação (desativada ou removida do canal) — FR-016a/G10b.
     *
     * Para cada atribuição: tenta repontar para outra persona ATIVA do mesmo
     * canal via round-robin; se não houver, pausa a IA e escala para humano.
     */
    public function reassignAwayFromPersona(AiPersona $persona): void
    {
        $assignments = AiConversationAssignment::query()
            ->where('ai_persona_id', $persona->id)
            ->whereNotIn('status', [AiConversationAssignment::STATUS_CLOSED])
            ->get();

        foreach ($assignments as $assignment) {
            $replacement = $this->selector->selectForNewConversation(
                $assignment->tenant_id,
                $assignment->channel_type,
            );

            if ($replacement !== null) {
                $assignment->update([
                    'ai_persona_id' => $replacement->id,
                    'status' => AiConversationAssignment::STATUS_ASSIGNED,
                ]);

                event(new PersonaAtribuidaAConversa(
                    $assignment->tenant_id,
                    $assignment->conversation_id,
                    $replacement->id,
                    $assignment->channel_type,
                ));

                continue;
            }

            // Sem persona ativa no canal → pausa + handoff humano.
            $assignment->update([
                'status' => AiConversationAssignment::STATUS_PAUSED,
                'paused_at' => now(),
            ]);

            Conversation::query()
                ->whereKey($assignment->conversation_id)
                ->update(['ai_paused_until' => self::indefinitePauseUntil()]);

            event(new IAEscalouParaHumano(
                $assignment->tenant_id,
                $assignment->conversation_id,
                $persona->id,
                'persona_deactivated_no_replacement',
            ));
        }
    }
}
