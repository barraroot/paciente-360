<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T120 — Evento: paciente respondeu "2" (remarca) — Fase 5 emite, futura IA Matricial consume.
 */
class ReagendamentoSolicitadoPeloPaciente
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}
}
