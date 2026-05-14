<?php

namespace App\Jobs\Agenda;

use App\Jobs\TenantAwareJob;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarSyncService;

/**
 * T152 — Sync Appointment para Google Calendar (US-6.7).
 *
 * Action: 'create' | 'update' | 'delete'
 */
final class SyncAppointmentToGoogleCalendarJob extends TenantAwareJob
{
    public function __construct(
        public readonly int $appointmentId,
        public readonly string $action,
    ) {
        parent::__construct();
    }

    protected function run(): void
    {
        $appointment = Appointment::find($this->appointmentId);
        if (! $appointment) {
            return;
        }

        $account = CalendarSyncAccount::query()
            ->where('professional_id', $appointment->professional_id)
            ->where('status', 'connected')
            ->first();

        if (! $account) {
            return;
        }

        $service = app(GoogleCalendarSyncService::class);

        match ($this->action) {
            'create' => $service->syncCreated($appointment, $account),
            'update' => $service->syncUpdated($appointment, $account),
            'delete' => $service->syncDeleted($appointment, $account),
            default => null,
        };
    }
}
