<?php

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Exceção lançada quando uma operação de assinatura é tentada em um
 * tenant sem assinatura ativa (T202 — retorna HTTP 404).
 */
final class NoActiveSubscriptionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Nenhuma assinatura ativa encontrada para este tenant.');
    }
}
