<?php

declare(strict_types=1);

namespace App\Listeners\Ai;

use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Message\Models\Message;
use App\Jobs\Ai\ProcessAiResponseJob;
use Illuminate\Support\Facades\Cache;

/**
 * Ao retomar a IA (manual via TakeoverController OU timeout via
 * ExpireAiPausesJob), reprocessa a conversa se a última mensagem for uma
 * inbound do paciente ainda sem resposta — evita deixar o paciente no escuro
 * quando o que disparou o pause (escalonamento por tool sem dado, takeover
 * humano etc.) foi resolvido depois.
 *
 * Invariante: só dispatch se a ÚLTIMA mensagem da conversa for `direction=in`
 * e `sender_type=patient`. Se a última for outbound (IA ou humano respondeu
 * durante o pause, ou o paciente nem mandou nada novo), nada a reprocessar.
 *
 * Auto-discovered (Laravel 11+). Reusa o debounce do trigger de inbound
 * (`ai:debounce:{conversation_id}`) p/ não brigar com burst simultâneo.
 */
final class ReprocessLastInboundOnAiResume
{
    public function __construct(private readonly AiMatrixService $matrix) {}

    public function handle(ConversaRetomadaPelaIA $event): void
    {
        $conversation = $event->conversation;

        // Defesa: resumeAi deveria ter zerado, mas se algo no meio repausou,
        // não enfileiramos (o próprio processor já tem early return, mas
        // economiza o round-trip).
        if ($conversation->ai_paused_until !== null && $conversation->ai_paused_until->isFuture()) {
            return;
        }

        // Invariante: existe inbound do paciente pendente de resposta.
        $last = Message::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        if ($last === null || $last->direction !== 'in' || $last->sender_type !== 'patient') {
            return;
        }

        $conversation->loadMissing('channel');
        $channelType = $conversation->channel?->type;
        if ($channelType === null) {
            return;
        }

        if (! $this->matrix->isChannelAiEnabled($conversation->tenant_id, $channelType)) {
            return;
        }

        // Reusa a chave do trigger inbound — se já houver job agendado
        // para esta conversa, não duplicamos.
        $debounce = (int) config('ai.matricial.debounce_seconds', 8);
        $key = "ai:debounce:{$conversation->id}";

        if (! Cache::add($key, true, $debounce)) {
            return;
        }

        ProcessAiResponseJob::dispatch($conversation->id, $conversation->tenant_id)
            ->delay(now()->addSeconds($debounce));
    }
}
