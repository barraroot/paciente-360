<?php

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Exceção lançada quando um token de convite é inválido, expirado ou já
 * foi utilizado. Mapeada para HTTP 410 Gone no Handler (FR-025).
 *
 * @see App\Services\Users\InvitationService
 */
final class InvalidInvitationException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message ?: __('users.invitation.invalid'));
    }
}
