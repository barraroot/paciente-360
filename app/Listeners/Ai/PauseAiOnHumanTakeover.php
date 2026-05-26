<?php

declare(strict_types=1);

namespace App\Listeners\Ai;

use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;

/**
 * US6 / FR-016 — quando um humano assume a conversa, a atribuição de IA é
 * marcada como `paused`. O pause na conversa (`ai_paused_until`) já é setado
 * pelo fluxo de takeover (HumanTakeoverService/TakeoverController); aqui apenas
 * sincronizamos o estado da atribuição para refletir a supervisão humana.
 *
 * Auto-discovered (Laravel 11+).
 */
final class PauseAiOnHumanTakeover
{
    public function __construct(private readonly AiConversationAssignmentService $assignments) {}

    public function handle(ConversaAssumidaPorHumano $event): void
    {
        $this->assignments->markAssignmentPaused(
            $event->conversation->id,
            $event->executorId,
        );
    }
}
