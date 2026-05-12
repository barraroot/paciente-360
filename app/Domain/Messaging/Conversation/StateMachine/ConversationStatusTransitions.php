<?php

namespace App\Domain\Messaging\Conversation\StateMachine;

use App\Domain\Messaging\Conversation\Exceptions\InvalidConversationTransitionException;

/**
 * T104 — State machine de conversa (NC-2).
 *
 * Estados válidos: aberta, pendente, resolvida, reaberta.
 * Transições válidas definidas abaixo.
 *
 * @see specs/003-omnichannel-inbox/spec.md § NC-2
 */
final class ConversationStatusTransitions
{
    /**
     * Transições permitidas: from_status => [to_status, ...]
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'aberta' => ['pendente', 'resolvida'],
        'pendente' => ['resolvida', 'aberta'],
        'resolvida' => ['reaberta', 'aberta'],  // reaberta: manual; aberta: nova msg inbound (NC-2)
        'reaberta' => ['aberta'],
    ];

    /**
     * Verifica se a transição é válida.
     *
     * @param string|null $from Status atual (null = novo)
     */
    public static function canTransitionTo(?string $from, string $to): bool
    {
        if ($from === null) {
            return in_array($to, ['aberta'], true);
        }

        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Aplica a transição e retorna o novo status.
     *
     * @throws InvalidConversationTransitionException Se a transição for inválida.
     */
    public static function transition(string $from, string $to): string
    {
        if (! self::canTransitionTo($from, $to)) {
            throw new InvalidConversationTransitionException($from, $to);
        }

        return $to;
    }
}
