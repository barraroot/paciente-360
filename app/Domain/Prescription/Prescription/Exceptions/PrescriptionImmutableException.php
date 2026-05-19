<?php

namespace App\Domain\Prescription\Prescription\Exceptions;

use RuntimeException;

/**
 * Lançada quando se tenta alterar campo imutável de uma receita (AC-8.1.7).
 * Após save, apenas `notes` é editável; tipo, validade, medicamentos e posologia
 * são imutáveis — corrija via cancelamento (categoria `erro_emissao`) + nova receita.
 */
final class PrescriptionImmutableException extends RuntimeException
{
    public static function immutableField(string $field): self
    {
        return new self("Field '{$field}' is immutable after prescription creation.");
    }

    public static function prescriptionCancelled(): self
    {
        return new self('Cannot modify a cancelled prescription.');
    }
}
