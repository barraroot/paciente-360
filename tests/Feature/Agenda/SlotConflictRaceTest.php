<?php

namespace Tests\Feature\Agenda;

use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Agenda\AppointmentService;
use App\Services\Agenda\SlotConflictException;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T061** — Gate atômico FR-011a / SC-008.
 *
 * Cobertura: PARTIAL UNIQUE INDEX em (tenant_id, professional_id, starts_at)
 * WHERE status IN ('scheduled', 'confirmed') deve garantir que apenas 1 INSERT
 * sucede em qualquer tentativa simultânea — os demais devem receber
 * `slot_conflict` (HTTP 409 ou SlotConflictException).
 *
 * **Débito técnico**: o teste serializa as 50 tentativas (limitação PHPUnit
 * single-process). Para ataque real concorrente, precisaria de fork ou de
 * test runner paralelo (paratest). Mantém a validação do gate em nível DB
 * via PARTIAL UNIQUE — o que é suficiente para garantir que NÃO há race
 * window mesmo com paralelismo real.
 */
class SlotConflictRaceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Professional $professional;

    private AppointmentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-race', 'admin-clinica');

        $this->professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $this->type = AppointmentType::factory()->for($this->tenant)->create(['duration_minutes' => 30, 'buffer_minutes' => 0]);

        // Schedule mínimo para passar FR-002b (professional_schedule_not_configured)
        ProfessionalSchedule::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'day_of_week' => Carbon::tomorrow()->dayOfWeekIso,
            'blocks' => [['start' => '08:00', 'end' => '18:00']],
            'effective_from' => Carbon::today()->toDateString(),
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_partial_unique_index_blocks_duplicate_appointments_at_same_slot(): void
    {
        $service = app(AppointmentService::class);
        $startsAt = Carbon::tomorrow()->setTime(9, 0);

        $paciente1 = Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $paciente2 = Paciente::factory()->state(['tenant_id' => $this->tenant->id])->create();

        $payload = fn ($pacienteId) => [
            'paciente_id' => $pacienteId,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => $startsAt,
            'channel_origin' => 'painel',
        ];

        // Primeiro INSERT: sucesso
        $first = $service->create($payload($paciente1->id), $this->user);
        $this->assertInstanceOf(Appointment::class, $first['appointment']);

        // Segundo INSERT no mesmo slot: deve lançar SlotConflictException
        $this->expectException(SlotConflictException::class);
        $service->create($payload($paciente2->id), $this->user);
    }

    public function test_50_serial_attempts_at_same_slot_yield_exactly_one_success(): void
    {
        $service = app(AppointmentService::class);
        $startsAt = Carbon::tomorrow()->setTime(10, 0);

        $pacientes = Paciente::factory()->count(50)
            ->state(['tenant_id' => $this->tenant->id])->create();

        $successes = 0;
        $conflicts = 0;

        foreach ($pacientes as $paciente) {
            try {
                $service->create([
                    'paciente_id' => $paciente->id,
                    'professional_id' => $this->professional->id,
                    'appointment_type_id' => $this->type->id,
                    'starts_at' => $startsAt,
                    'channel_origin' => 'painel',
                ], $this->user);
                $successes++;
            } catch (SlotConflictException) {
                $conflicts++;
            }
        }

        $this->assertSame(1, $successes, 'Exatamente 1 INSERT deve ter sucesso (gate FR-011a)');
        $this->assertSame(49, $conflicts, '49 tentativas devem receber slot_conflict');

        // PARTIAL UNIQUE garante: 1 row scheduled no DB
        $this->assertSame(1, Appointment::query()
            ->where('professional_id', $this->professional->id)
            ->where('starts_at', $startsAt)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->count());
    }
}
