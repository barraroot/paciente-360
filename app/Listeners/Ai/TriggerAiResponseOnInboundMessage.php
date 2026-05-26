<?php

declare(strict_types=1);

namespace App\Listeners\Ai;

use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Jobs\Ai\ProcessAiResponseJob;
use Illuminate\Support\Facades\Cache;

/**
 * Conecta a IA Matricial ao fluxo existente: ao receber uma mensagem inbound
 * do paciente, se a IA estiver habilitada para o canal, despacha o
 * processamento assíncrono (com debounce para agrupar rajadas — FR-030b).
 *
 * Auto-discovered (Laravel 11+). Não bloqueia o webhook: o trabalho de IA
 * roda na fila `ai`.
 */
final class TriggerAiResponseOnInboundMessage
{
    public function __construct(private readonly AiMatrixService $matrix) {}

    public function handle(MensagemRecebida $event): void
    {
        $message = $event->message;
        $conversation = $event->conversation;

        // Apenas mensagens recebidas do paciente.
        if ($message->direction !== 'in' || $message->sender_type !== 'patient') {
            return;
        }

        // IA pausada na conversa → não responde (FR-033).
        if ($conversation->ai_paused_until !== null && $conversation->ai_paused_until->isFuture()) {
            return;
        }

        $conversation->loadMissing('channel');
        $channelType = $conversation->channel?->type;
        if ($channelType === null) {
            return;
        }

        // Habilitação derivada: precisa de ≥1 persona ativa no canal (FR-011a).
        if (! $this->matrix->isChannelAiEnabled($conversation->tenant_id, $channelType)) {
            return;
        }

        // Debounce: coalesce rajada em um único job dentro da janela.
        $debounce = (int) config('ai.matricial.debounce_seconds', 8);
        $key = "ai:debounce:{$conversation->id}";

        if (! Cache::add($key, true, $debounce)) {
            return; // já há um processamento agendado para esta conversa
        }

        ProcessAiResponseJob::dispatch($conversation->id, $conversation->tenant_id)
            ->delay(now()->addSeconds($debounce));
    }
}
