<?php

namespace App\Services\Agenda\Calendar;

use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Tenant;

/**
 * T148 — Gerencia sub-calendário tenant-scoped (clarify nº 15).
 *
 * Cria sub-calendário automaticamente no fluxo de connect:
 * "Paciente360 — {Tenant.nome}" — isola eventos cross-tenant da mesma conta Google.
 */
final class GoogleSubCalendarManager
{
    public function __construct(private readonly GoogleCalendarApiClient $client) {}

    /**
     * Cria sub-calendário e persiste google_calendar_id em CalendarSyncAccount.
     *
     * @throws \DomainException calendar_subcalendar_creation_failed
     */
    public function createSubCalendar(CalendarSyncAccount $account, Tenant $tenant): string
    {
        $summary = "Paciente360 — {$tenant->name}";

        try {
            $created = $this->client->createSubCalendar($account, $summary);
        } catch (\Throwable $e) {
            throw new \DomainException('calendar_subcalendar_creation_failed', previous: $e);
        }

        $account->update([
            'google_calendar_id' => $created['id'],
            'google_calendar_name_seen' => $created['summary'],
        ]);

        return $created['id'];
    }
}
