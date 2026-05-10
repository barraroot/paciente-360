<?php

namespace App\Exceptions\Auth;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Exceção lançada quando a conta está bloqueada por excesso de tentativas
 * de login. Mapeada para HTTP 423 no Handler.
 *
 * Inclui o timestamp de expiração do lock para o cliente exibir
 * feedback útil sem revelar por que o bloqueio foi acionado.
 *
 * @see App\Services\Auth\AuthenticationService
 */
final class AccountLockedException extends RuntimeException
{
    public function __construct(
        public readonly Carbon $lockedUntil,
    ) {
        parent::__construct(__('auth.login.locked', ['minutes' => 15]));
    }
}
