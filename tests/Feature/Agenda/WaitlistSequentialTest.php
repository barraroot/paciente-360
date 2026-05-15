<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\VagaAbertaNaListaDeEspera;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Agenda\WaitlistEntry;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Agenda\AppointmentService;
use App\Services\Agenda\WaitlistService;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T127** — US-6.6 lista de espera sequencial K=1 (clarify nº 8).
 *
 * Cobertura: enroll FIFO, cancelamento abre vaga e notifica APENAS o 1º,
 * expiração re-notifica o próximo, accept atomicamente cria appointment.
 *
 * **Débito técnico**: faltam: múltiplas listas simultâneas (AC-6.6.5),
 * relatório agregado (AC-6.6.6).
 */
class WaitlistSequentialTest extends TestCase
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

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-waitlist', 'admin-clinica');

        $this->professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $this->type = AppointmentType::factory()->for($this->tenant)->create();

        ProfessionalSchedule::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::today()->toDateString(),
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_enroll_assigns_sequential_positions_fifo(): void
    {
        $service = app(WaitlistService::class);

        $p1 = $this->createPaciente();
        $p2 = $this->createPaciente();
        $p3 = $this->createPaciente();

        $e1 = $service->enroll($p1, $this->professional, $this->type);
        $e2 = $service->enroll($p2, $this->professional, $this->type);
        $e3 = $service->enroll($p3, $this->professional, $this->type);

        $this->assertSame(1, $e1->position);
        $this->assertSame(2, $e2->position);
        $this->assertSame(3, $e3->position);
    }

    public function test_cancellation_notifies_only_the_first_in_queue(): void
    {
        Event::fake([VagaAbertaNaListaDeEspera::class]);

        $service = app(WaitlistService::class);

        $p1 = $this->createPaciente();
        $p2 = $this->createPaciente();
        $p3 = $this->createPaciente();

        $service->enroll($p1, $this->professional, $this->type);
        $service->enroll($p2, $this->professional, $this->type);
        $service->enroll($p3, $this->professional, $this->type);

        // Cria appointment futuro e cancela — listener OpenWaitlistOnCancellation roda
        $appointment = Appointment::factory()->state([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'paciente_id' => $this->createPaciente()->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(10, 30),
            'status' => 'scheduled',
            'channel_origin' => 'painel',
        ])->create();

        // Cancel via service (emite ConsultaCancelada uma vez; listener roda 1x)
        app(AppointmentService::class)->cancel(
            $appointment,
            ['quem_cancelou' => 'atendente', 'motivo' => 'paciente_solicitou'],
            $this->admin,
        );

        // Estado real é o que importa — apenas p1 ficou notified (clarify nº 8 — sequencial K=1)
        Event::assertDispatched(VagaAbertaNaListaDeEspera::class);
        $this->assertSame('notified', WaitlistEntry::query()->where('paciente_id', $p1->id)->first()->status);
        $this->assertSame('waiting', WaitlistEntry::query()->where('paciente_id', $p2->id)->first()->status);
        $this->assertSame('waiting', WaitlistEntry::query()->where('paciente_id', $p3->id)->first()->status);

        // Apenas 1 entry foi notified no DB (não há paralelismo)
        $this->assertSame(1, WaitlistEntry::query()->where('status', 'notified')->count());
    }

    public function test_expire_notifications_re_notifies_next(): void
    {
        $service = app(WaitlistService::class);

        $p1 = $this->createPaciente();
        $p2 = $this->createPaciente();
        $service->enroll($p1, $this->professional, $this->type);
        $service->enroll($p2, $this->professional, $this->type);

        // Notifica p1 e força expirado
        $entry1 = $service->notifyNext($this->tenant->id, $this->professional->id, $this->type->id, Carbon::tomorrow()->setTime(15, 0));
        $entry1->update(['expires_at' => now()->subMinute()]);

        $expired = $service->expireNotifications();
        $this->assertSame(1, $expired);

        $this->assertSame('expired', $entry1->fresh()->status);
        $this->assertSame('notified', WaitlistEntry::query()->where('paciente_id', $p2->id)->first()->status);
    }

    public function test_accept_creates_appointment_atomically(): void
    {
        $service = app(WaitlistService::class);

        $paciente = $this->createPaciente();
        $entry = $service->enroll($paciente, $this->professional, $this->type);
        $service->notifyNext($this->tenant->id, $this->professional->id, $this->type->id, Carbon::tomorrow()->setTime(11, 0));

        $entry = $entry->fresh();
        $this->assertSame('notified', $entry->status);

        $appointment = $service->accept($entry, [], $this->admin);

        $this->assertSame($paciente->id, $appointment->paciente_id);
        $this->assertSame('autoatendimento', $appointment->channel_origin);
        $this->assertSame('accepted', $entry->fresh()->status);
        $this->assertSame($appointment->id, $entry->fresh()->accepted_appointment_id);
    }

    private function createPaciente(): Paciente
    {
        return Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();
    }
}
