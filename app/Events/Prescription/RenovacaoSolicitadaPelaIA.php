<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T131 — Emitido quando a IA solicita renovação de uma receita (AC-8.3.2).
 *
 * Implementa `ContainsNoClinicalData` — gate constitucional LGPD.
 * Props contêm SOMENTE referências (IDs), sem dados clínicos.
 * Mesmo padrão de ReceitaProximaDoVencimento (gate T087).
 *
 * ATENÇÃO: NÃO adicionar medication_name, posology, notes sem revisão LGPD.
 *
 * @see specs/007-gestao-receituario/spec.md Q5
 * @see app/Support/Lgpd/ContainsNoClinicalData.php
 */
final class RenovacaoSolicitadaPelaIA implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $prescriptionId,
        public readonly int $patientId,
        public readonly int $professionalId,
        public readonly ?int $appointmentId,
    ) {}
}
