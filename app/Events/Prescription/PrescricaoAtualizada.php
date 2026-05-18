<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido quando campos de uma receita são atualizados (AC-8.1.7).
 *
 * `changedFields` lista os campos que foram alterados (ex: `['notes']`, `['pdf_path']`).
 * Nunca inclui o conteúdo dos campos — apenas a lista de nomes.
 *
 * @see specs/007-gestao-receituario/spec.md AC-8.1.7
 */
final class PrescricaoAtualizada implements ContainsNoClinicalData
{
    use Dispatchable;

    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public readonly int $prescriptionId,
        public readonly array $changedFields,
    ) {}
}
