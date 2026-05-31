<?php

declare(strict_types=1);

namespace App\Domain\Ai\Coalescing\Services;

use App\Jobs\Ai\FlushCoalescedTurnJob;
use Illuminate\Support\Facades\Redis;

/**
 * **T067 (Fase 18 — US1, FR-001 fase i — debounce passivo)** — agenda o flush
 * do turno coalescido com `delay = passive_debounce_s` e reseta a janela a
 * cada nova mensagem (key-fantasma `ai:turn:{conv}:debounce` com EX <s>).
 *
 * Diferente do debounce simples da Fase 17 (`ai:debounce:{conv}` boolean
 * lock que ignorava bursts subsequentes), aqui:
 *  - Cada nova mensagem ENTRA no turno (via ConversationTurnCoordinator);
 *  - O flush é re-agendado com novo `delay` — a janela "anda" enquanto o
 *    paciente continua falando;
 *  - O cap absoluto `max_turn_s` (FR-003) impede espera indefinida.
 */
final class PassiveDebounceScheduler
{
    public function scheduleFlush(int $conversationId, int $tenantId, int $turnVersion): void
    {
        $debounceS = (int) config('ai.matricial.coalesce.passive_debounce_s', 4);

        // Atualiza a chave-fantasma de debounce para sinalizar "esperando mais
        // input" (audit-friendly, não bloqueia nada).
        Redis::set("ai:turn:{$conversationId}:debounce", '1', 'EX', $debounceS);

        FlushCoalescedTurnJob::dispatch($conversationId, $tenantId, $turnVersion)
            ->delay(now()->addSeconds($debounceS));
    }
}
