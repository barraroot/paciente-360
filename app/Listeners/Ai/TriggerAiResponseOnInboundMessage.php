<?php

declare(strict_types=1);

namespace App\Listeners\Ai;

use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Ai\Coalescing\Services\PassiveDebounceScheduler;
use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Domain\Messaging\Message\Events\MensagemRecebida;

/**
 * Conecta a IA Matricial ao fluxo existente: ao receber uma mensagem inbound
 * do paciente, se a IA estiver habilitada para o canal, despacha a
 * coalescência híbrida (Fase 18, US1 — FR-001) que:
 *
 *  1. Adiciona a mensagem ao TURNO ATUAL via {@see ConversationTurnCoordinator}
 *     (INCR atômico de versão + push na lista de message_ids).
 *  2. Agenda/re-agenda o flush passivo via {@see PassiveDebounceScheduler}
 *     (delay = `ai.matricial.coalesce.passive_debounce_s`, default 4s).
 *  3. Se nova mensagem chegar dentro da janela → versão incrementa → o flush
 *     pendente vira no-op (superseded) e um novo flush é agendado.
 *
 * **Substitui** o debounce simples da Fase 17 (boolean lock Cache::add) que
 * apenas IGNORAVA mensagens subsequentes — agora elas ENTRAM no turno.
 *
 * Auto-discovered (Laravel 11+). Não bloqueia o webhook.
 */
final class TriggerAiResponseOnInboundMessage
{
    public function __construct(
        private readonly AiMatrixService $matrix,
        private readonly ConversationTurnCoordinator $coordinator,
        private readonly PassiveDebounceScheduler $scheduler,
    ) {}

    public function handle(MensagemRecebida $event): void
    {
        $message = $event->message;
        $conversation = $event->conversation;

        // Apenas mensagens recebidas do paciente.
        if ($message->direction !== 'in' || $message->sender_type !== 'patient') {
            return;
        }

        // Mensagens sandbox NÃO disparam coalescência (sessão de teste — US6 trata).
        if ((bool) ($message->sandbox ?? false)) {
            return;
        }

        // IA pausada na conversa → não responde (FR-033 da Fase 17 / FR-006 desta US).
        if ($conversation->ai_paused_until !== null && $conversation->ai_paused_until->isFuture()) {
            return;
        }

        $conversation->loadMissing('channel');
        $channelType = $conversation->channel?->type;
        if ($channelType === null) {
            return;
        }

        // Habilitação derivada: precisa de ≥1 persona ativa no canal (FR-011a Fase 17).
        if (! $this->matrix->isChannelAiEnabled($conversation->tenant_id, $channelType)) {
            return;
        }

        // 1) entra no turno (atômico) e ganha versão N.
        $state = $this->coordinator->joinOrStartTurn(
            conversationId: $conversation->id,
            messageId: $message->id,
        );

        // 2) (re)agenda o flush passivo com delay — versão N será verificada
        // pelo FlushCoalescedTurnJob; se N != current quando o job rodar,
        // outro flush mais novo já foi agendado e este é no-op (superseded).
        $this->scheduler->scheduleFlush(
            conversationId: $conversation->id,
            tenantId: $conversation->tenant_id,
            turnVersion: $state->version,
        );
    }
}
