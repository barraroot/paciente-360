<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\ConsultaCriada;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T060** — Happy paths US-6.3 (criação de consulta).
 *
 * Cenários: criação OK, idempotency replay, professional_schedule_not_configured,
 * funil mover card automaticamente.
 *
 * **Débito técnico**: cobertura mínima. Expandir para todos os ACs (6.3.1..6.3.7),
 * incluir teste de override_block + push notification, busca trgm de paciente.
 */
class AppointmentCreationTest extends TestCase
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

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-create', 'admin-clinica');

        $this->professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $this->type = AppointmentType::factory()->for($this->tenant)->create(['duration_minutes' => 30, 'buffer_minutes' => 5]);

        ProfessionalSchedule::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::today()->toDateString(),
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_creates_appointment_via_painel(): void
    {
        Event::fake([ConsultaCriada::class]);

        $paciente = Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();

        $response = $this->postJson('/api/v1/agenda/consultas', [
            'paciente_id' => $paciente->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => Carbon::tomorrow()->setTime(9, 0)->toIso8601String(),
            'channel_origin' => 'painel',
        ], ['X-Tenant-Slug' => $this->tenant->slug]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'scheduled');
        $response->assertJsonPath('data.channel_origin', 'painel');
        $response->assertJsonPath('idempotent_replay', false);

        $this->assertDatabaseHas('appointments', [
            'paciente_id' => $paciente->id,
            'professional_id' => $this->professional->id,
            'status' => 'scheduled',
        ]);

        Event::assertDispatched(ConsultaCriada::class);
    }

    public function test_idempotency_key_replays_returning_existing(): void
    {
        $paciente = Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $key = (string) Str::uuid();

        $first = $this->postJson('/api/v1/agenda/consultas', [
            'idempotency_key' => $key,
            'paciente_id' => $paciente->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => Carbon::tomorrow()->setTime(11, 0)->toIso8601String(),
            'channel_origin' => 'ia',
        ], ['X-Tenant-Slug' => $this->tenant->slug])->assertCreated();

        $appointmentId = $first->json('data.id');

        $replay = $this->postJson('/api/v1/agenda/consultas', [
            'idempotency_key' => $key,
            'paciente_id' => $paciente->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => Carbon::tomorrow()->setTime(11, 0)->toIso8601String(),
            'channel_origin' => 'ia',
        ], ['X-Tenant-Slug' => $this->tenant->slug]);

        $replay->assertOk();
        $replay->assertJsonPath('idempotent_replay', true);
        $replay->assertJsonPath('data.id', $appointmentId);

        $this->assertSame(1, Appointment::query()->where('idempotency_key', $key)->count());
    }

    public function test_rejects_when_professional_has_no_schedule_configured(): void
    {
        $paciente = Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $unscheduled = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();

        $response = $this->postJson('/api/v1/agenda/consultas', [
            'paciente_id' => $paciente->id,
            'professional_id' => $unscheduled->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => Carbon::tomorrow()->setTime(9, 0)->toIso8601String(),
            'channel_origin' => 'painel',
        ], ['X-Tenant-Slug' => $this->tenant->slug]);

        $response->assertStatus(422);
        $this->assertSame('professional_schedule_not_configured', $response->json('error'));
    }

    public function test_listener_moves_paciente_card_to_agendado_column(): void
    {
        $coluna = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Agendado',
            'slug' => 'agendado',
            'cor' => '#10B981',
            'posicao' => 5,
        ]);

        $paciente = Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();

        $this->postJson('/api/v1/agenda/consultas', [
            'paciente_id' => $paciente->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
            'channel_origin' => 'painel',
        ], ['X-Tenant-Slug' => $this->tenant->slug])->assertCreated();

        $this->assertSame($coluna->id, $paciente->fresh()->funil_coluna_atual_id);
    }
}
