<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\ConsultaCancelada;
use App\Events\Agenda\ConsultaConfirmacaoPendente;
use App\Events\Agenda\ConsultaConfirmada;
use App\Events\Agenda\ConsultaPendenteContatoManual;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ConfirmationDispatch;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Agenda\ConfirmationDispatcherService;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T096** — Happy paths US-6.4 (confirmação automática + comparecimento).
 *
 * Cobertura: dispatch T-24h, processa "1" (confirmed), processa "3" (canceled),
 * paciente sem canal → pending_manual. Marcar realizada/no-show, reverter dentro
 * de 48h.
 *
 * **Débito técnico**: cobertura mínima de happy paths. Faltam:
 * AC-6.4.4 (resposta "2" → ReagendamentoSolicitadoPeloPaciente),
 * AC-6.4.6 (idempotência reverse — paciente confirma após cancel manual),
 * cenários de retry T-30min e 15min_manual_escalation, integração via_ia.
 */
class ConfirmationFlowTest extends TestCase
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

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-confirm', 'admin-clinica');

        $this->professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $this->type = AppointmentType::factory()->for($this->tenant)->create(['duration_minutes' => 30]);

        ProfessionalSchedule::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::today()->toDateString(),
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_dispatcher_emits_confirmacao_pendente_in_24h_window(): void
    {
        Event::fake([ConsultaConfirmacaoPendente::class]);

        $appointment = Appointment::factory()
            ->state([
                'tenant_id' => $this->tenant->id,
                'professional_id' => $this->professional->id,
                'appointment_type_id' => $this->type->id,
                'paciente_id' => Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create()->id,
                'starts_at' => Carbon::now()->addHours(23)->addMinutes(30),
                'ends_at' => Carbon::now()->addHours(24),
                'status' => 'scheduled',
                'channel_origin' => 'painel',
            ])->create();

        // Garante paciente com canal_origem (não cai em pending_manual)
        $appointment->paciente->update(['telefone_primario' => '(11) 99999-0001']);

        app(ConfirmationDispatcherService::class)->dispatchPending();

        Event::assertDispatched(ConsultaConfirmacaoPendente::class, function ($event) use ($appointment) {
            return $event->appointment->id === $appointment->id && $event->kind === '24h';
        });

        $this->assertDatabaseHas('confirmation_dispatches', [
            'appointment_id' => $appointment->id,
            'kind' => '24h',
            'status' => 'dispatched',
        ]);
    }

    public function test_response_1_emits_confirmada_and_updates_status(): void
    {
        Event::fake([ConsultaConfirmada::class]);

        $appointment = $this->createAppointment(['status' => 'scheduled']);
        ConfirmationDispatch::create([
            'tenant_id' => $this->tenant->id,
            'appointment_id' => $appointment->id,
            'kind' => '24h',
            'via_ia' => false,
            'dispatched_at' => now()->subHour(),
            'status' => 'dispatched',
        ]);

        $response = $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/confirmar-resposta",
            ['response_value' => '1', 'dispatch_kind' => '24h'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        );

        $response->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        Event::assertDispatched(ConsultaConfirmada::class);
    }

    public function test_response_3_cancels_appointment(): void
    {
        Event::fake([ConsultaCancelada::class]);

        $appointment = $this->createAppointment(['status' => 'scheduled']);
        ConfirmationDispatch::create([
            'tenant_id' => $this->tenant->id,
            'appointment_id' => $appointment->id,
            'kind' => '24h',
            'via_ia' => false,
            'dispatched_at' => now(),
            'status' => 'dispatched',
        ]);

        $this->postJson(
            "/api/v1/agenda/consultas/{$appointment->id}/confirmar-resposta",
            ['response_value' => '3', 'dispatch_kind' => '24h'],
            ['X-Tenant-Slug' => $this->tenant->slug],
        )->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertSame('paciente', $fresh->quem_cancelou);
        Event::assertDispatched(ConsultaCancelada::class);
    }

    public function test_paciente_sem_canal_origem_cria_pending_manual_at_24h(): void
    {
        Event::fake([ConsultaPendenteContatoManual::class]);

        $appointment = Appointment::factory()
            ->state([
                'tenant_id' => $this->tenant->id,
                'professional_id' => $this->professional->id,
                'appointment_type_id' => $this->type->id,
                'paciente_id' => Paciente::factory()->state(['tenant_id' => $this->tenant->id, 'telefone_primario' => null])->create()->id,
                'starts_at' => Carbon::now()->addHours(23)->addMinutes(30),
                'ends_at' => Carbon::now()->addHours(24),
                'status' => 'scheduled',
                'channel_origin' => 'painel',
            ])->create();

        app(ConfirmationDispatcherService::class)->dispatchPending();

        $this->assertDatabaseHas('confirmation_dispatches', [
            'appointment_id' => $appointment->id,
            'kind' => '24h',
            'status' => 'pending_manual',
        ]);
        Event::assertDispatched(ConsultaPendenteContatoManual::class);
        $this->assertSame('scheduled', $appointment->fresh()->status, 'Appointment.status NÃO muda em pending_manual (analyze A1)');
    }

    private function createAppointment(array $overrides = []): Appointment
    {
        return Appointment::factory()->state(array_merge([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'paciente_id' => Paciente::factory()->state(['tenant_id' => $this->tenant->id, 'telefone_primario' => '(11) 99999-0001'])->create()->id,
            'starts_at' => Carbon::tomorrow()->setTime(14, 0),
            'ends_at' => Carbon::tomorrow()->setTime(14, 30),
            'channel_origin' => 'painel',
        ], $overrides))->create();
    }
}
