<?php

namespace App\Exceptions\Pacientes;

/**
 * Lançada pelo `MergeService::revert()` quando a mesclagem já foi
 * revertida anteriormente.
 *
 * Mapeada em `bootstrap/app.php` para HTTP 422.
 *
 * @see App\Services\Pacientes\MergeService
 * @see specs/002-crm-pacientes/spec.md US-3.1
 */
final class MesclagemJaRevertidaException extends \RuntimeException
{
    public function __construct(string $message = 'Esta mesclagem já foi revertida.')
    {
        parent::__construct($message);
    }
}
