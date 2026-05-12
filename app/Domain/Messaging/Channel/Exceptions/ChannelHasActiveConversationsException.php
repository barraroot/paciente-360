<?php

namespace App\Domain\Messaging\Channel\Exceptions;

use RuntimeException;

/**
 * Lançada pelo ChannelService quando se tenta desconectar um canal
 * que possui conversas ativas sem o flag `force=true`.
 */
final class ChannelHasActiveConversationsException extends RuntimeException
{
    public function __construct(string $message = 'O canal possui conversas ativas. Use force=true para forçar a desconexão.')
    {
        parent::__construct($message);
    }
}
