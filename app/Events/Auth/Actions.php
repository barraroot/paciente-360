<?php

namespace App\Events\Auth;

/**
 * Constantes de ação para eventos de autenticação (Princípio I — auditabilidade LGPD).
 *
 * Centraliza as strings de ação para evitar typos e facilitar refatoração.
 *
 * @see App\Events\Auth\LoginSucceeded
 * @see App\Events\Auth\LoginFailed
 * @see App\Events\Auth\LogoutSucceeded
 */
final class Actions
{
    public const USER_LOGIN_SUCCEEDED = 'user.login.succeeded';

    public const USER_LOGIN_FAILED = 'user.login.failed';

    public const USER_LOGIN_LOCKED = 'user.login.locked';

    public const USER_LOGOUT_SUCCEEDED = 'user.logout.succeeded';

    public const USER_PASSWORD_FORGOT_REQUESTED = 'user.password.forgot.requested';

    public const USER_PASSWORD_RESET = 'user.password.reset';
}
