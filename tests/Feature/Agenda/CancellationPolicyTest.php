<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\CancelamentoSolicitadoForaDoPrazo;
use App\Events\Agenda\ConsultaCancelada;
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
 * **T117** — US-6.5 política de cancelamento (clarify nº 3).
 *
 * Cobertura: paciente fora do prazo → 422 + escalated; profissional irrestrito;
 * override por type.min_cancellation_hours.
 *
 * **Débito técnico**: faltam: cascade cancel via ScheduleException (FR-028c),
 * cancel idempotente, médico próprio cancela.
 */
class CancellationPolicyTest extends TestCase
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

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-cancel', 'admin-clinica');

        $this->professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $this->type = AppointmentType::factory()->for($this->tenant)->create([
            'duration_minutes' => 30,
            'min_cancellation_hours' => null, // herda do tenant (default 4)
        ]);

        ProfessionalSchedule::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::today()->toDateString(),
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_paciente_within_window_can_cancel(): void
    {
        Event::fake([ConsultaCancelada::class]);

        $appointment = $this->createFutureAppointment(Carbon::now()->addHours(10));

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/cancelar",
            ['quem_cancelou' => 'paciente', 'motivo' => 'imprevisto'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertOk();
        $this->assertSame('canceled', $appointment->fresh()->status);
        Event::assertDispatched(ConsultaCancelada::class);
    }

    public function test_paciente_outside_window_returns_422_and_escalates(): void
    {
        Event::fake([CancelamentoSolicitadoForaDoPrazo::class]);

        // 2h até a consulta — janela é 4h (default tenant)
        $appointment = $this->createFutureAppointment(Carbon::now()->addHours(2));

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/cancelar",
            ['quem_cancelou' => 'paciente', 'motivo' => 'imprevisto'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'cancellation_outside_window');
        $response->assertJsonPath('escalated_to_inbox', true);
        $this->assertSame(4, $response->json('window_hours'));

        $this->assertSame('scheduled', $appointment->fresh()->status);
        Event::assertDispatched(CancelamentoSolicitadoForaDoPrazo::class);
    }

    public function test_profissional_can_cancel_irrespective_of_window(): void
    {
        // 1h antes — paciente não conseguiria; profissional consegue
        $appointment = $this->createFutureAppointment(Carbon::now()->addHour());

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/cancelar",
            ['quem_cancelou' => 'profissional', 'motivo' => 'emergência médica'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertOk();
        $this->assertSame('canceled', $appointment->fresh()->status);
    }

    public function test_type_override_min_cancellation_hours_takes_precedence(): void
    {
        $cirurgia = AppointmentType::factory()->for($this->tenant)->create([
            'min_cancellation_hours' => 48,
        ]);

        // 24h antes — tenant default (4h) permitiria, mas tipo Cirurgia=48h bloqueia
        $appointment = $this->createFutureAppointment(Carbon::now()->addHours(24), ['appointment_type_id' => $cirurgia->id]);

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/cancelar",
            ['quem_cancelou' => 'paciente', 'motivo' => 'mudança'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame(48, $response->json('window_hours'));
    }

    private function createFutureAppointment(Carbon $startsAt, array $overrides = []): Appointment
    {
        return Appointment::factory()->state(array_merge([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'paciente_id' => Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create()->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'status' => 'scheduled',
            'channel_origin' => 'painel',
        ], $overrides))->create();
    }
}
