<?php

namespace App\Domain\Messaging\Message\Exceptions;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * T109 — Lançada quando se tenta enviar mensagem não-template fora da janela de 24h.
 *
 * Mapeada para HTTP 422 no MessagesController com schema WindowExpiredErrorResponse.
 *
 * @see specs/003-omnichannel-inbox/spec.md § FR-019 + AC-4.4.5
 */
final class WindowExpiredWithoutTemplateException extends RuntimeException
{
    public function __construct(
        public readonly ?Carbon $lastInboundMessageAt,
        public readonly float $hoursSinceLastInbound,
        public readonly int $availableTemplatesCount,
    ) {
        parent::__construct(
            'Janela de 24h expirada; selecione um template aprovado para retomar contato.'
        );
    }
}
