<?php

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Exceção lançada quando o tenant atingiu o limite de usuários do plano.
 * Mapeada para HTTP 409 no Handler (FR-027).
 *
 * @see App\Services\Users\InvitationService
 */
final class PlanLimitReachedException extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
    ) {
        parent::__construct(__('users.plan_limit_reached', ['limit' => $limit]));
    }
}
