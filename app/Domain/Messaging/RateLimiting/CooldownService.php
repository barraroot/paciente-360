<?php

declare(strict_types=1);

namespace App\Domain\Messaging\RateLimiting;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Events\Messaging\RateLimiting\ConversationCooldownEnded;
use App\Events\Messaging\RateLimiting\ConversationCooldownStarted;
use App\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * **T201 (Fase 18 — Polish, FR-008b/c)** — gerencia o cooldown auditável
 * disparado quando o rate limit anti-abuso é excedido.
 *
 * Efeitos de `startFor`:
 *   1. seta `cooldown_until` = now + `messaging.cooldown.minutes` (default 15);
 *   2. seta `cooldown_reason` curto (motivo legível, ex.: `rate_limit_per_conversation`);
 *   3. eleva `priority='alta'` para chamar atenção do operador na inbox;
 *   4. posta uma `Message` sender=system descrevendo o motivo (sem PII);
 *   5. emite `ConversationCooldownStarted` (auditado, sem dado clínico).
 *
 * Durante o cooldown:
 *   - `IsConversationOnCooldownChecker::check()` bloqueia ProcessAiResponseJob,
 *     KanbanCurationService, AudioSynthesisService, McpToolBridge (FR-008c).
 *   - Mensagens inbound continuam sendo PERSISTIDAS (não descartadas) — só não
 *     disparam IA nem efeitos colaterais. Operador segue podendo responder
 *     manualmente.
 *
 * `endBy` é chamado por:
 *   - operador (`messaging.cooldown.manage` action) — `endedBy='operator'`;
 *   - expiração natural (lazy, detectada pelo checker quando `cooldown_until`
 *     já passou) — `endedBy='expired'`.
 */
final class CooldownService
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function startFor(
        Conversation $conversation,
        string $reason,
        string $limiterKey,
        ?string $burstLabel = null,
    ): Conversation {
        $minutes = (int) config('messaging.cooldown.minutes', 15);

        // Idempotência: se já estiver em cooldown ativo, estende a janela para
        // o maior entre o atual e o novo (sem alterar o motivo original).
        $newUntil = Carbon::now()->addMinutes($minutes);
        if ($conversation->cooldown_until !== null && $conversation->cooldown_until->isFuture()) {
            $newUntil = $conversation->cooldown_until->greaterThan($newUntil)
                ? $conversation->cooldown_until
                : $newUntil;
        }

        $conversation->forceFill([
            'cooldown_until' => $newUntil,
            'cooldown_reason' => $reason,
            'priority' => 'alta',
        ])->save();

        $this->postSystemNotice($conversation, $reason, $minutes);

        $this->events->dispatch(new ConversationCooldownStarted(
            conversation: $conversation,
            reason: $reason,
            limiterKey: $limiterKey,
            cooldownMinutes: $minutes,
            burstLabel: $burstLabel,
        ));

        return $conversation;
    }

    public function endBy(Conversation $conversation, ?User $actor): Conversation
    {
        if ($conversation->cooldown_until === null) {
            return $conversation;
        }

        $conversation->forceFill([
            'cooldown_until' => null,
            'cooldown_reason' => null,
        ])->save();

        $this->events->dispatch(new ConversationCooldownEnded(
            conversation: $conversation,
            endedBy: $actor !== null ? 'operator' : 'expired',
            actorUserId: $actor?->id,
        ));

        return $conversation;
    }

    private function postSystemNotice(Conversation $conversation, string $reason, int $minutes): void
    {
        $body = sprintf(
            'Conversa em cooldown automático (%d min) — %s. Respostas da IA pausadas. Você pode responder manualmente.',
            $minutes,
            $this->humanizeReason($reason),
        );

        Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'system',
            'body' => $body,
            'body_searchable' => mb_strtolower($body),
            'body_preview' => mb_substr($body, 0, 140),
            'content_type' => 'text',
            'status' => 'sent',
        ]);
    }

    private function humanizeReason(string $reason): string
    {
        return match ($reason) {
            'rate_limit_per_conversation' => 'volume excessivo de mensagens nesta conversa',
            'rate_limit_per_identifier' => 'volume excessivo de mensagens deste contato',
            default => 'excesso de mensagens em janela curta',
        };
    }
}
