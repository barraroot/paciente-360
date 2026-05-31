<?php

declare(strict_types=1);

namespace App\Domain\Messaging\RateLimiting;

use App\Domain\Ai\Mcp\Client\McpToolBridge;
use App\Domain\Crm\Kanban\Services\KanbanCurationService;
use App\Domain\Messaging\Audio\Outbound\Services\AudioSynthesisService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Messaging\RateLimiting\ConversationCooldownEnded;
use App\Jobs\Ai\ProcessAiResponseJob;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * **T202 (Fase 18 — Polish, FR-008c)** — checker injetável usado por
 * {@see ProcessAiResponseJob}, {@see KanbanCurationService},
 * {@see AudioSynthesisService} e
 * {@see McpToolBridge} antes de operar.
 *
 * `check()` retorna `true` se a conversa AINDA está em cooldown. Faz lazy
 * end (limpa `cooldown_until` + emite `ConversationCooldownEnded(endedBy=expired)`)
 * quando detecta que a janela já expirou — não exige cron para limpar.
 */
final class IsConversationOnCooldownChecker
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function check(Conversation $conversation): bool
    {
        $until = $conversation->cooldown_until;
        if ($until === null) {
            return false;
        }

        if ($until->isFuture()) {
            return true;
        }

        // Lazy end — expirou naturalmente.
        $conversation->forceFill([
            'cooldown_until' => null,
            'cooldown_reason' => null,
        ])->save();

        $this->events->dispatch(new ConversationCooldownEnded(
            conversation: $conversation,
            endedBy: 'expired',
            actorUserId: null,
        ));

        return false;
    }
}
