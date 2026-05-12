<?php

namespace App\Domain\Messaging\Conversation\Contracts;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;

/**
 * Contrato para o mecanismo de pausa/retomada da IA em conversas.
 *
 * CONTRATO CONGELADO — Fase 4 (IA matricial) depende desta interface.
 * Qualquer mudança de assinatura ou payload de eventos exige amendment
 * explícito da constitution (Princípio III).
 *
 * @see specs/003-omnichannel-inbox/spec.md § US-4.6
 * @see tests/Unit/Messaging/ConversaIATogglingContractTest.php (gate constitucional)
 */
interface ConversaIATogglingContract
{
    /**
     * Pausa a IA na conversa por $minutes minutos.
     *
     * Se já pausada, ESTENDE o timer (não acumula):
     * ai_paused_until = now() + $minutes (reinicia).
     *
     * $minutes é clamped ao range [5, 240].
     *
     * @param string $reason 'manual_click' | 'mensagem_enviada' | 'reprise'
     */
    public function pauseAi(Conversation $conv, int $minutes, ?User $byUser, string $reason = 'manual_click'): Conversation;

    /**
     * Libera a IA imediatamente. Idempotente — não lança exceção se já liberada.
     *
     * @param string $mode 'manual' | 'timeout'
     */
    public function resumeAi(Conversation $conv, string $mode = 'manual'): Conversation;

    /**
     * Retorna true se a pausa está ativa (ai_paused_until no futuro).
     */
    public function isPaused(Conversation $conv): bool;

    /**
     * Segundos restantes até retomada. Null se não pausada.
     */
    public function secondsUntilResume(Conversation $conv): ?int;
}
