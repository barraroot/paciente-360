<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Exceção lançada quando credenciais são inválidas (senha errada, usuário
 * desabilitado ou e-mail inexistente). Mapeada para HTTP 401 no Handler.
 *
 * A mensagem genérica previne enumeração de usuários (FR-032 / Princípio VII).
 *
 * @see App\Services\Auth\AuthenticationService
 */
final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.failed'));
    }
}
