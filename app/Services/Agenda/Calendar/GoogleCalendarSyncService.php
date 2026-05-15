<?php

namespace App\Services\Agenda\Calendar;

use App\Models\Agenda\Appointment;
use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Agenda\CalendarSyncedEvent;
use App\Services\Agenda\TimezoneResolverService;
use App\Support\Metrics\AgendaMetricsContract;

/**
 * T149 — Espelha Appointment ↔ Google Calendar (clarify nº 10/15 + FR-038).
 *
 * **GATE LGPD CRÍTICO** (Princípio I / FR-038/038a):
 *  - Título FIXO: "Consulta — {Profissional.nome}"
 *  - Descrição genérica: "Agendamento via {Tenant.nome}"
 *  - **PROIBIDO**: nome do paciente, CPF, convênio, sintomas, tipo clínico,
 *    qualquer dado clínico identificável.
 *  - Cobertura via teste GoogleEventPayloadLgpdTest (T141 — gate obrigatório).
 */
final class GoogleCalendarSyncService
{
    public function __construct(
        private readonly GoogleCalendarApiClient $client,
        private readonly TimezoneResolverService $tzResolver,
        private readonly AgendaMetricsContract $metrics,
    ) {}

    public function syncCreated(Appointment $appointment, CalendarSyncAccount $account): ?CalendarSyncedEvent
    {
        if ($account->status !== 'connected' || $account->google_calendar_id === null) {
            return null;
        }

        $start = microtime(true);

        $body = $this->buildEventBody($appointment, $account);

        try {
            $created = $this->client->insertEvent($account, $account->google_calendar_id, $body);
        } finally {
            $this->metrics->calendarSyncLatencySeconds('insert', microtime(true) - $start);
        }

        return CalendarSyncedEvent::create([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
            'calendar_sync_account_id' => $account->id,
            'external_event_id' => $created['id'],
            'last_synced_at' => now(),
            'etag' => $created['etag'] ?? null,
        ]);
    }

    public function syncUpdated(Appointment $appointment, CalendarSyncAccount $account): void
    {
        if ($account->status !== 'connected' || $account->google_calendar_id === null) {
            return;
        }

        $synced = CalendarSyncedEvent::query()
            ->where('appointment_id', $appointment->id)
            ->where('calendar_sync_account_id', $account->id)
            ->first();

        if (! $synced) {
            $this->syncCreated($appointment, $account);

            return;
        }

        $body = $this->buildEventBody($appointment, $account);
        $patched = $this->client->patchEvent($account, $account->google_calendar_id, $synced->external_event_id, $body);

        $synced->update([
            'last_synced_at' => now(),
            'etag' => $patched['etag'] ?? null,
        ]);
    }

    public function syncDeleted(Appointment $appointment, CalendarSyncAccount $account): void
    {
        if ($account->status !== 'connected' || $account->google_calendar_id === null) {
            return;
        }

        $synced = CalendarSyncedEvent::query()
            ->where('appointment_id', $appointment->id)
            ->where('calendar_sync_account_id', $account->id)
            ->first();

        if (! $synced) {
            return;
        }

        $this->client->deleteEvent($account, $account->google_calendar_id, $synced->external_event_id);
        $synced->delete();
    }

    /**
     * **GATE LGPD** — payload sem dados clínicos. Validado por
     * GoogleEventPayloadLgpdTest (T141).
     *
     * @return array{summary:string, description:string, start:array, end:array}
     */
    public function buildEventBody(Appointment $appointment, CalendarSyncAccount $account): array
    {
        $professional = $appointment->professional;
        $tenant = $appointment->tenant;

        $tz = $this->tzResolver->forProfessional($professional);

        return [
            'summary' => "Consulta — {$professional->name}",
            'description' => "Agendamento via {$tenant->name}",
            'start' => [
                'dateTime' => $appointment->starts_at->toIso8601String(),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => $appointment->ends_at->toIso8601String(),
                'timeZone' => $tz,
            ],
        ];
    }
}
