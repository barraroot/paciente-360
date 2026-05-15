<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T102 — Evento: confirmação não respondida + escalada manual
 * (clarify nº 6 — T-15min sem resposta OU paciente sem canal).
 *
 * **Importante (analyze A1)**: NÃO altera Appointment.status. Apenas registra
 * ConfirmationDispatch.status='pending_manual' e Fase 3 cria task na inbox.
 */
class ConsultaPendenteContatoManual
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        /** @var list<string> */
        public readonly array $attempts,    // ex.: ['24h', 'retry_30min']
        public readonly string $reason,     // 'no_response' | 'no_channel'
    ) {}
}
