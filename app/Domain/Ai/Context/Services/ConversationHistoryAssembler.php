<?php

declare(strict_types=1);

namespace App\Domain\Ai\Context\Services;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Support\Lgpd\PiiScrubber;
use Laravel\Ai\Messages\Message as AiMessage;
use Laravel\Ai\Messages\MessageRole;

/**
 * Feature 017 (US1) — monta a janela verbatim MÍNIMA do histórico da conversa.
 *
 * Retorna as últimas N mensagens (config `ai.matricial.history.window_messages`)
 * ANTERIORES à mensagem atual, em ordem cronológica, como mensagens do laravel/ai
 * (`user` para inbound do paciente, `assistant` para saídas da IA/atendente).
 * Cada conteúdo é PSEUDONIMIZADO antes de sair (Princípios I/III) — nenhum PII
 * bruto vai ao provedor. Mensagens de sistema e sem corpo são ignoradas.
 */
final class ConversationHistoryAssembler
{
    /**
     * @return list<AiMessage>
     */
    public function assemble(Conversation $conversation, ?int $beforeMessageId = null): array
    {
        $window = (int) config('ai.matricial.history.window_messages', 6);

        if ($window <= 0) {
            return [];
        }

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('sender_type', ['patient', 'ai', 'user'])
            ->where('content_type', 'text')
            ->whereNotNull('body');

        if ($beforeMessageId !== null) {
            $query->where('id', '<', $beforeMessageId);
        }

        $recent = $query->latest('id')->limit($window)->get()->reverse();

        $messages = [];

        foreach ($recent as $message) {
            $body = trim((string) $message->body);

            if ($body === '') {
                continue;
            }

            $role = $message->direction === 'in'
                ? MessageRole::User
                : MessageRole::Assistant;

            $messages[] = new AiMessage($role, (string) PiiScrubber::scrub($body));
        }

        return $messages;
    }
}
