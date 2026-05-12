<?php

namespace App\Domain\Messaging\Infrastructure\Listeners;

use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Models\Tenant;
use App\Models\User;

/**
 * T173 — Listener para pausa implícita da IA quando um humano envia mensagem.
 *
 * Subscreve ao evento MensagemEnviada. Filtra apenas mensagens com
 * sender_type === 'user' (atendente humano) — ignora 'system' e 'ai'.
 *
 * Implementa AC-4.6.2: "mensagem manual pausa IA implicitamente (sem clique)".
 *
 * @see App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract
 */
final class SetAiPausedOnOutboundMessageListener
{
    public function __construct(
        private readonly ConversaIATogglingContract $takeoverService,
    ) {}

    public function handle(MensagemEnviada $event): void
    {
        $message = $event->message;

        // Only react when a human user sends — not system or ai messages
        if ($message->sender_type !== 'user') {
            return;
        }

        $conversation = $event->conversation;
        $sender = $message->sender_id !== null
            ? User::withoutGlobalScopes()->find($message->sender_id)
            : null;

        // Read pause duration from tenant settings, then config fallback
        $minutes = $this->resolvePauseMinutes($conversation->tenant_id);

        // Determine reason: 'reprise' if already paused, else 'mensagem_enviada'
        $reason = $this->takeoverService->isPaused($conversation)
            ? 'reprise'
            : 'mensagem_enviada';

        $this->takeoverService->pauseAi($conversation, $minutes, $sender, $reason);
    }

    private function resolvePauseMinutes(int $tenantId): int
    {
        // T181: reads from tenant.settings.inbox.ai_pause_minutes with fallback to global config
        $tenant = app()->bound('tenant') ? app('tenant') : Tenant::withoutGlobalScopes()->find($tenantId);

        if ($tenant instanceof Tenant) {
            return $tenant->getInboxAiPauseMinutes();
        }

        return (int) config('messaging.ai_pause.minutes', 30);
    }
}
