<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T103a — Evento: paciente compareceu (clarify nº 14).
 */
class ConsultaRealizada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $markedByUserId,
    ) {}
}
