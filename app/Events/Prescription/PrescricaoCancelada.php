<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Emitido quando uma receita é cancelada (AC-8.1.8).
 *
 * Implementa `ContainsNoClinicalData` — nenhum dado clínico exposto.
 *
 * @see specs/007-gestao-receituario/spec.md AC-8.1.8
 */
final class PrescricaoCancelada implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $prescriptionId,
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $cancelledByUserId,
        public readonly string $categoryReason,
        public readonly Carbon $cancelledAt,
    ) {}
}
