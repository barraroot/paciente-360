<?php

namespace App\Domain\Messaging\Channel\Exceptions;

use RuntimeException;

/**
 * Lançada pelo ChannelService quando as credenciais fornecidas
 * são rejeitadas pelo provider externo (Twilio, Meta, etc.).
 */
final class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'As credenciais fornecidas são inválidas.')
    {
        parent::__construct($message);
    }
}
