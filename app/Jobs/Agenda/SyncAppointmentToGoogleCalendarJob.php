<?php

namespace App\Jobs\Agenda;

use App\Jobs\TenantAwareJob;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarSyncService;
use Sentry\State\Scope;

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

        // T172 — Sentry tags para rastreamento de falhas Google sync
        if (function_exists('Sentry\\configureScope')) {
            \Sentry\configureScope(function (Scope $scope) use ($account): void {
                $scope->setTag('agenda.sync.provider', $account->provider);
                $scope->setTag('agenda.sync.account_id', (string) $account->id);
                $scope->setTag('agenda.sync.action', $this->action);
                $scope->setTag('agenda.appointment_id', (string) $this->appointmentId);
            });
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
