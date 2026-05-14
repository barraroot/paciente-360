<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\ConsultaCancelada;
use App\Events\Agenda\ConsultaCriada;
use App\Events\Agenda\ConsultaReagendada;
use App\Jobs\Agenda\SyncAppointmentToGoogleCalendarJob;
use App\Models\Agenda\CalendarSyncAccount;

/**
 * T157 — Listener: dispatcha sync para Google quando appointment muda (US-6.7).
 *
 * Escuta 3 eventos via método handle dispatchAs (auto-discovered via type).
 */
class SyncAppointmentToGoogleCalendar
{
    public function handleCreated(ConsultaCriada $event): void
    {
        $this->dispatchIfSynced($event->appointment->id, $event->appointment->professional_id, 'create');
    }

    public function handleUpdated(ConsultaReagendada $event): void
    {
        $this->dispatchIfSynced($event->appointment->id, $event->appointment->professional_id, 'update');
    }

    public function handleCanceled(ConsultaCancelada $event): void
    {
        $this->dispatchIfSynced($event->appointment->id, $event->appointment->professional_id, 'delete');
    }

    private function dispatchIfSynced(int $appointmentId, int $professionalId, string $action): void
    {
        $hasSync = CalendarSyncAccount::query()
            ->where('professional_id', $professionalId)
            ->where('status', 'connected')
            ->exists();

        if ($hasSync) {
            SyncAppointmentToGoogleCalendarJob::dispatch($appointmentId, $action);
        }
    }
}
