<?php

namespace App\Domain\Messaging\Infrastructure\Listeners;

use App\Events\Paciente\PacienteAnonimizado;
use App\Jobs\Messaging\AnonimizaMensagensDoPacienteJob;

/**
 * T255 — Listener que despacha o job de anonimização de mensagens
 * quando um paciente é anonimizado (LGPD FR-018, NC-14.b).
 *
 * Subscreve `App\Events\Paciente\PacienteAnonimizado` (Fase 2).
 * O job realiza a anonimização granular (recebidas zeradas, enviadas preservadas)
 * de forma assíncrona para não bloquear a request de anonimização do paciente.
 *
 * Registrado em `EventServiceProvider::boot()`.
 */
final class AnonimizaMensagensOnPacienteAnonimizadoListener
{
    public function handle(PacienteAnonimizado $event): void
    {
        AnonimizaMensagensDoPacienteJob::dispatch(
            pacienteId: $event->paciente->id,
            tenantId: $event->paciente->tenant_id,
        );
    }
}
