<?php

namespace App\Domain\Messaging\Message\Exceptions;

use RuntimeException;

/**
 * T109 — Placeholder Fase 4: canal degradado (quality rating baixo).
 *
 * Disparo automático bloqueado quando o canal está em estado degradado.
 */
final class ChannelDegradedException extends RuntimeException
{
    public function __construct(string $channelName = 'canal')
    {
        parent::__construct(
            "Canal '{$channelName}' está degradado; envio automático bloqueado."
        );
    }
}
