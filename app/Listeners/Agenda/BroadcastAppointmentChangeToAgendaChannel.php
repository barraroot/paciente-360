<?php

namespace App\Listeners\Agenda;

/**
 * T074 — Listener placeholder para futura lógica adicional pós-broadcast.
 *
 * Os eventos `ConsultaCriada` / `ConsultaReagendada` / `ConsultaCancelada`
 * já implementam `ShouldBroadcast` e o Reverb cuida do canal automaticamente.
 * Este listener fica disponível para lógicas extras (ex.: invalidar cache de
 * slots disponíveis no Redis quando consulta muda).
 */
class BroadcastAppointmentChangeToAgendaChannel
{
    public function handle(object $event): void
    {
        // No-op por enquanto. Slot cache invalidation entra aqui em iteração futura.
    }
}
