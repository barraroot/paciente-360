<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T118 — Evento: paciente/IA tentou cancelar fora do prazo (clarify nº 3).
 *
 * Fase 3 cria handoff/note na inbox para atendente decidir caso a caso.
 */
class CancelamentoSolicitadoForaDoPrazo
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $requestedBy,        // paciente | ia
        public readonly int $windowHours,
        public readonly float $currentHoursUntilAppt,
    ) {}
}
