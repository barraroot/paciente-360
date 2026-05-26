<?php

declare(strict_types=1);

namespace App\Domain\Ai\Assignment\Services;

use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Distribution\Services\AiPersonaSelectorService;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Models\Conversation;

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
}
