<?php

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Exceção lançada quando uma operação violaria o requisito de manter
 * ao menos 1 Admin Clínica ativo por tenant. Mapeada para HTTP 409 (FR-029).
 *
 * @see App\Services\Users\UserService
 */
final class LastAdminClinicaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('users.last_admin_clinica'));
    }
}
