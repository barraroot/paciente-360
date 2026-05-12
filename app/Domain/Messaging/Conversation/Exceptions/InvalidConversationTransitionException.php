<?php

namespace App\Domain\Messaging\Conversation\Exceptions;

use InvalidArgumentException;

/**
 * T109 — Lançada quando uma transição de estado inválida é tentada em uma conversa.
 *
 * Mapeada para HTTP 409 Conflict no ConversationsController.
 */
final class InvalidConversationTransitionException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {
        parent::__construct(
            "Transição inválida de '{$fromStatus}' para '{$toStatus}'."
        );
    }
}
