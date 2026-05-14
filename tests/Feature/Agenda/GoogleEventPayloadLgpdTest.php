<?php

namespace Tests\Feature\Agenda;

use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Services\Agenda\Calendar\GoogleCalendarSyncService;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T141** — GATE PRINCÍPIO I (LGPD) / FR-038/038a.
 *
 * Payload enviado ao Google Calendar **NÃO PODE** conter dados clínicos:
 *  - SEM nome do paciente
 *  - SEM CPF
 *  - SEM telefone
 *  - SEM convênio
 *  - SEM tipo de atendimento (clínico)
 *  - SEM diagnóstico, sintomas, observações
 *
 * Apenas: título fixo "Consulta — {Profissional}", descrição "Agendamento via {Tenant}".
 */
class GoogleEventPayloadLgpdTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    public function test_event_payload_contains_no_pii_or_clinical_data(): void
    {
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-lgpd', 'admin-clinica');

        $professional = Professional::factory()->state([
            'tenant_id' => $tenant->id,
            'name' => 'Dr. Silva',
        ])->create();

        $type = AppointmentType::factory()->for($tenant)->create([
            'nome' => 'Cirurgia Cardíaca Urgente',  // dado clínico que NÃO pode vazar
        ]);

        ProfessionalSchedule::create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::today()->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        $paciente = Paciente::factory()->state([
            'tenant_id' => $tenant->id,
            'nome' => 'Maria Souza',
            'cpf' => '12345678901',
            'telefone_primario' => '(11) 98765-4321',
        ])->create();

        $appointment = Appointment::factory()->state([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'appointment_type_id' => $type->id,
            'paciente_id' => $paciente->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0),
            'channel_origin' => 'painel',
            'notes' => 'Sintomas relatados: dor no peito',
        ])->create();

        $account = CalendarSyncAccount::factory()
            ->state(['tenant_id' => $tenant->id, 'professional_id' => $professional->id])
            ->connected()
            ->create([
                'google_calendar_id' => 'cal_test@group.calendar.google.com',
            ]);

        $service = app(GoogleCalendarSyncService::class);
        $payload = $service->buildEventBody($appointment->fresh(), $account->fresh());

        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

        // GATE: nada de PII de paciente
        $this->assertStringNotContainsString('Maria Souza', $serialized, 'Nome do paciente NÃO pode aparecer');
        $this->assertStringNotContainsString('12345678901', $serialized, 'CPF NÃO pode aparecer');
        $this->assertStringNotContainsString('98765-4321', $serialized, 'Telefone NÃO pode aparecer');

        // GATE: nada de dado clínico do tipo
        $this->assertStringNotContainsString('Cirurgia', $serialized, 'Tipo clínico NÃO pode aparecer');
        $this->assertStringNotContainsString('Cardíaca', $serialized, 'Tipo clínico NÃO pode aparecer');
        $this->assertStringNotContainsString('dor no peito', $serialized, 'Notes clínicas NÃO podem aparecer');

        // Whitelist: apenas dados não-clínicos permitidos
        $this->assertSame('Consulta — Dr. Silva', $payload['summary']);
        $this->assertStringContainsString('Agendamento via', $payload['description']);
        $this->assertArrayHasKey('start', $payload);
        $this->assertArrayHasKey('end', $payload);
        $this->assertArrayHasKey('timeZone', $payload['start']);
    }
}
