<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * T103b — Evento: no-show (clarify nº 14).
 *
 * Consumido por Fase 6 (cadência retorno opcional), métricas no-show.
 * Sem mensagem automática "sentimos sua falta" no MVP (clarify nº 14).
 */
class ConsultaNaoRealizada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $markedByUserId,
        public readonly ?Carbon $autoFlaggedAt = null,
    ) {}
}
