<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Exceção lançada quando o token de recuperação de senha é inválido,
 * expirado ou já foi utilizado.
 *
 * Mapeada para HTTP 410 (Gone) em `bootstrap/app.php`. A mensagem genérica
 * previne enumeração via canais oblíquos (FR-032 — Princípio VII).
 *
 * @see App\Services\Auth\PasswordResetService
 */
final class InvalidPasswordResetTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.password_reset.token_invalid'));
    }
}
