<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\ConsultaMarcacaoRevertida;
use App\Events\Agenda\ConsultaNaoRealizada;
use App\Events\Agenda\ConsultaRealizada;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T097** — Happy paths US-6.x (marcação comparecimento — clarify nº 14).
 *
 * Cobertura: marca realizada, marca no-show, revert dentro de 48h.
 *
 * **Débito técnico**: faltam testes de revert APÓS 48h com ability dedicada,
 * cron auto-close stale, auto-flag T+30min via job.
 */
class AttendanceMarkingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Professional $professional;

    private AppointmentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-att', 'admin-clinica');

        $this->professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $this->type = AppointmentType::factory()->for($this->tenant)->create();

        ProfessionalSchedule::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'day_of_week' => Carbon::yesterday()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::yesterday()->toDateString(),
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_marks_appointment_as_realizada(): void
    {
        Event::fake([ConsultaRealizada::class]);

        $appointment = $this->createPastAppointment();

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/marcar-comparecimento",
            ['status' => 'realizada'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertOk();
        $this->assertSame('realizada', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->attendance_marked_at);
        Event::assertDispatched(ConsultaRealizada::class);
    }

    public function test_admin_marks_no_show(): void
    {
        Event::fake([ConsultaNaoRealizada::class]);

        $appointment = $this->createPastAppointment();

        $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/marcar-comparecimento",
            ['status' => 'nao_realizada', 'attendance_motivo' => 'Não compareceu sem aviso'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        )->assertOk();

        $this->assertSame('nao_realizada', $appointment->fresh()->status);
        Event::assertDispatched(ConsultaNaoRealizada::class);
    }

    public function test_revert_within_48h_works(): void
    {
        Event::fake([ConsultaMarcacaoRevertida::class]);

        $appointment = $this->createPastAppointment();
        $appointment->update([
            'status' => 'realizada',
            'attendance_marked_at' => now()->subHours(2), // dentro 48h
            'attendance_marked_by_user_id' => $this->admin->id,
        ]);

        $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/reverter-comparecimento",
            [],
            ['X-Tenant-Slug' => $this->tenant->slug],
        )->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('scheduled', $fresh->status);
        $this->assertNull($fresh->attendance_marked_at);
        Event::assertDispatched(ConsultaMarcacaoRevertida::class);
    }

    public function test_marking_outside_7d_window_is_rejected(): void
    {
        // Appointment 8 dias atrás (fora janela de marcação)
        $appointment = Appointment::factory()->state([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'paciente_id' => Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create()->id,
            'starts_at' => Carbon::now()->subDays(8),
            'ends_at' => Carbon::now()->subDays(8)->addMinutes(30),
            'status' => 'scheduled',
            'channel_origin' => 'painel',
        ])->create();

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/marcar-comparecimento",
            ['status' => 'realizada'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame('appointment_too_old_for_marking', $response->json('error'));
    }

    private function createPastAppointment(): Appointment
    {
        return Appointment::factory()->state([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'paciente_id' => Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create()->id,
            'starts_at' => Carbon::yesterday()->setTime(14, 0),
            'ends_at' => Carbon::yesterday()->setTime(14, 30),
            'status' => 'scheduled',
            'channel_origin' => 'painel',
        ])->create();
    }
}
