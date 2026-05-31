<?php

declare(strict_types=1);

namespace App\Listeners\Ai;

use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;

/**
 * Fecha um buraco da Fase 15: o `HumanTakeoverService::resumeAi()` (usado pelo
 * botão "Reativar IA" da UI e pelo `ExpireAiPausesJob` de timeout) só zera
 * `conversation.ai_paused_until`, mas a escalação anterior também deixou
 * `ai_conversation_assignments.status='paused'`. Sem reativar o assignment, o
 * `AiMessageProcessor::process()` faz early return em `existing->isPaused()` e
 * a IA fica eternamente muda — exatamente o sintoma observado.
 *
 * Auto-discovered (Laravel 11+). Síncrono, idempotente, sem reemissão de
 * evento (não há recursão).
 */
final class ReactivateAssignmentOnAiResume
{
    public function __construct(private readonly AiConversationAssignmentService $assignments) {}

    public function handle(ConversaRetomadaPelaIA $event): void
    {
        $this->assignments->markAssignmentResumed($event->conversation->id);
    }
}
