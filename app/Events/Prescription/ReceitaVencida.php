<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido quando uma receita ativa é marcada como vencida (AC-8.2.7).
 *
 * Implementa `ContainsNoClinicalData` — nenhum dado clínico exposto.
 *
 * @see specs/007-gestao-receituario/spec.md AC-8.2.7
 */
final class ReceitaVencida implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $prescriptionId,
        public readonly int $patientId,
        public readonly CarbonImmutable|string $expiredAt,
    ) {}
}
