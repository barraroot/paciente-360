<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\ProfissionalAgendaConfigurada;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ScheduleException;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T032** — Happy paths US-6.1 (config agenda + bloqueios).
 *
 * **Débito técnico**: cobertura mínima — apenas 3 cenários core.
 * Expandir nas próximas iterações para cobrir todos os ACs (6.1.1..6.1.6),
 * cenários de override de bloqueio (clarify nº 5), wizard "copiar de outro
 * profissional" e edge case de prof sem agenda.
 */
class ProfessionalScheduleTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-1', 'admin-clinica');
    }

    public function test_admin_clinica_configures_recurring_schedule_and_emits_event(): void
    {
        Event::fake([ProfissionalAgendaConfigurada::class]);

        $professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();
        $type = AppointmentType::factory()->for($this->tenant)->create();

        $payload = [
            'timezone' => 'America/Sao_Paulo',
            'schedules' => [
                ['day_of_week' => 1, 'blocks' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '18:00']]],
                ['day_of_week' => 2, 'blocks' => [['start' => '08:00', 'end' => '18:00']]],
            ],
            'accepted_appointment_type_ids' => [$type->id],
        ];

        $response = $this->putJson("/api/v1/agenda/professionals/{$professional->id}/schedules", $payload, [
            'X-Tenant-Slug' => $this->tenant->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.timezone_resolved', 'America/Sao_Paulo');
        $response->assertJsonCount(2, 'data.schedules');

        $this->assertDatabaseCount('professional_schedules', 2);
        $this->assertDatabaseHas('appointment_type_professional', [
            'appointment_type_id' => $type->id,
            'professional_id' => $professional->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Event::assertDispatched(ProfissionalAgendaConfigurada::class);
    }

    public function test_admin_creates_schedule_exception_with_audit(): void
    {
        $professional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();

        $response = $this->postJson("/api/v1/agenda/professionals/{$professional->id}/schedule-exceptions", [
            'starts_at' => '2026-06-10T00:00:00-03:00',
            'ends_at' => '2026-06-20T23:59:59-03:00',
            'reason' => 'Férias',
        ], ['X-Tenant-Slug' => $this->tenant->slug]);

        $response->assertCreated();
        $response->assertJsonPath('data.reason', 'Férias');
        $response->assertJsonPath('data.created_by_user_id', $this->admin->id);
        $response->assertJsonStructure(['data' => ['id', 'starts_at', 'ends_at'], 'cascaded_cancellations']);

        $this->assertSame(1, ScheduleException::count());
    }

    public function test_medico_can_only_edit_own_schedule(): void
    {
        $medicoUser = $this->userForRole($this->tenant, 'medico');
        $ownProfessional = Professional::factory()->state([
            'tenant_id' => $this->tenant->id,
            'user_id' => $medicoUser->id,
        ])->create();
        $otherProfessional = Professional::factory()->state(['tenant_id' => $this->tenant->id])->create();

        Sanctum::actingAs($medicoUser, ['*']);

        // Próprio: deve permitir
        $own = $this->putJson("/api/v1/agenda/professionals/{$ownProfessional->id}/schedules", [
            'schedules' => [['day_of_week' => 1, 'blocks' => [['start' => '09:00', 'end' => '12:00']]]],
        ], ['X-Tenant-Slug' => $this->tenant->slug]);
        $own->assertOk();

        // Outro profissional: deve negar
        $other = $this->putJson("/api/v1/agenda/professionals/{$otherProfessional->id}/schedules", [
            'schedules' => [['day_of_week' => 1, 'blocks' => [['start' => '09:00', 'end' => '12:00']]]],
        ], ['X-Tenant-Slug' => $this->tenant->slug]);
        $other->assertForbidden();
    }
}
