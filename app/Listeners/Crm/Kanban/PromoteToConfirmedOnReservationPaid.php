<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Events\Agenda\ConsultaConfirmada;
use App\Models\Paciente;
use App\Models\Tenant;

/**
 * **T102 (Fase 18 — US3, FR-018)** — quando a consulta é confirmada
 * (ConsultaConfirmada da Fase 5), promove o card do paciente para 'confirmado'.
 *
 * Listener auto-discovered (Laravel 11+).
 */
final class PromoteToConfirmedOnReservationPaid
{
    public function __construct(
        private readonly KanbanAutoTransitionService $transitions,
    ) {}

    public function handle(ConsultaConfirmada $event): void
    {
        $appointment = $event->appointment;
        if ($appointment->paciente_id === null) {
            return;
        }

        $tenant = Tenant::find($appointment->tenant_id);
        if ($tenant === null) {
            return;
        }
        app()->instance('tenant', $tenant);

        $paciente = Paciente::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->find($appointment->paciente_id);

        if ($paciente === null) {
            return;
        }

        $this->transitions->apply($paciente, 'reservation_confirmed', [
            'reason' => "Consulta confirmada (via {$event->via}) — appointment #{$appointment->id}.",
        ]);
    }
}
