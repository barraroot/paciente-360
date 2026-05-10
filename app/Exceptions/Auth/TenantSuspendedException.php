<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Exceção lançada quando o tenant está suspenso e um usuário tenta autenticar.
 * Mapeada para HTTP 403 no Handler com payload `{error: 'tenant_suspended'}`.
 *
 * @see App\Services\Auth\AuthenticationService
 * @see App\Http\Middleware\EnsureTenantNotSuspended
 */
final class TenantSuspendedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tenant suspended.');
    }
}
