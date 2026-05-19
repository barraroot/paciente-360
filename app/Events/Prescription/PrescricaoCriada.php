<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Emitido quando uma receita é criada com sucesso (AC-8.1.1).
 *
 * Implementa `ContainsNoClinicalData` — gate LGPD que garante que nenhum
 * dado clínico (medicamento, posologia) sai no payload do evento.
 *
 * @see specs/007-gestao-receituario/spec.md AC-8.1.1
 */
final class PrescricaoCriada implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $prescriptionId,
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $professionalId,
        public readonly string $type,
        public readonly Carbon $issuedAt,
        public readonly Carbon $expiresAt,
    ) {}
}
