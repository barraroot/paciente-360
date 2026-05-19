<?php

namespace App\Domain\Prescription\Prescription\Exceptions;

use RuntimeException;

/**
 * Lançada quando regras regulatórias da Portaria SVS/MS 344/98 são violadas.
 * Exemplo: receita controlada com mais de 1 item.
 */
final class ControlledPrescriptionRulesException extends RuntimeException
{
    public static function tooManyItems(): self
    {
        return new self('Controlled prescriptions must have exactly 1 item (Portaria 344/98).');
    }
}
