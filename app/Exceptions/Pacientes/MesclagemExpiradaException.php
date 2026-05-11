<?php

namespace App\Exceptions\Pacientes;

/**
 * Lançada pelo `MergeService::revert()` quando a janela de 30 dias
 * de reversão já expirou.
 *
 * Mapeada em `bootstrap/app.php` para HTTP 410 Gone.
 *
 * @see App\Services\Pacientes\MergeService
 * @see specs/002-crm-pacientes/spec.md US-3.1
 */
final class MesclagemExpiradaException extends \RuntimeException
{
    public function __construct(string $message = 'A janela de reversão de 30 dias expirou.')
    {
        parent::__construct($message);
    }
}
