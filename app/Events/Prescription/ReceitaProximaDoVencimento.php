<?php

namespace App\Events\Prescription;

use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido quando uma receita está próxima do vencimento (AC-8.2.1).
 *
 * Implementa `ContainsNoClinicalData` — auditado e validado para
 * não expor dados clínicos (research §3 + gate T087).
 *
 * ATENÇÃO: Esta lista de 7 props é gate constitucional (T087).
 * NÃO adicionar medication_name, posology, notes ou qualquer dado clínico
 * sem revisão LGPD obrigatória (PrescriptionEventPayloadLgpdTest).
 *
 * @see specs/007-gestao-receituario/research.md §3
 * @see tests/Feature/Prescription/PrescriptionEventPayloadLgpdTest.php
 */
final class ReceitaProximaDoVencimento implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $prescriptionId,
        public readonly int $patientId,
        public readonly int $professionalId,
        public readonly string $professionalName,
        public readonly int $daysUntilExpiry,
        public readonly PrescriptionType $prescriptionType,
        public readonly ?int $defaultAppointmentTypeId,
    ) {}
}
