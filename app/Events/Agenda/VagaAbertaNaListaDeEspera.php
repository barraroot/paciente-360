<?php

namespace App\Events\Agenda;

use App\Models\Agenda\WaitlistEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T129 — Evento: vaga aberta na lista de espera (clarify nº 8 — sequencial K=1).
 *
 * Consumido pela Fase 3 que envia mensagem ao paciente notificado.
 */
class VagaAbertaNaListaDeEspera
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly int $notificationWindowMinutes,
    ) {}
}
