<?php

namespace App\Domain\Messaging\Channel\Exceptions;

use RuntimeException;

/**
 * Lançada pelo ChannelService quando o tenant tenta conectar
 * um canal que já existe (mesmo messaging_service_sid).
 */
final class ChannelAlreadyConnectedException extends RuntimeException
{
    public function __construct(string $message = 'Este canal já está conectado ao tenant.')
    {
        parent::__construct($message);
    }
}
