<?php

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Exceção lançada quando um tenant já possui uma assinatura ativa
 * e tenta iniciar um novo checkout (T185 — retorna HTTP 409).
 */
final class AlreadySubscribedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este tenant já possui uma assinatura ativa.');
    }
}
