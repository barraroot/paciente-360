<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T132 — Emitido quando uma renovação é concluída (nova receita gerada).
 *
 * Implementa `ContainsNoClinicalData` — apenas referências por ID.
 * `renewedAt` em ISO 8601 UTC para consumo cross-service.
 *
 * @see specs/007-gestao-receituario/spec.md Q3, Q6a
 */
final class ReceitaRenovada implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $oldPrescriptionId,
        public readonly int $newPrescriptionId,
        public readonly string $renewedAt,
    ) {}
}
