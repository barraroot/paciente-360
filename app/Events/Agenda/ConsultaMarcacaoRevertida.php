<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T103c — Evento: reversão de marcação de comparecimento (clarify nº 14).
 *
 * Dentro de 48h: ability `appointment.update`. Após 48h: ability dedicada
 * `appointment.revert_attendance_marking` (default: Admin Clínica) + audit warning.
 */
class ConsultaMarcacaoRevertida
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $revertedByUserId,
        public readonly string $previousStatus,
    ) {}
}
