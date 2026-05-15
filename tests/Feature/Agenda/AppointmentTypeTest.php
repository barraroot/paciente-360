<?php

namespace Tests\Feature\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T050** — Happy paths US-6.2 (CRUD tipos de atendimento).
 *
 * **Débito técnico**: cobertura mínima — 3 cenários. Expandir para cobrir todos
 * os ACs (6.2.1..6.2.5) incl. cor color picker, intent_ia, valor_convenio_default
 * com NULL/preenchido, multi-tenant naming colision.
 */
class AppointmentTypeTest extends TestCase
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

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-2', 'admin-clinica');
    }

    public function test_admin_creates_appointment_type(): void
    {
        $response = $this->postJson('/api/v1/agenda/appointment-types', [
            'nome' => 'Consulta',
            'duration_minutes' => 30,
            'buffer_minutes' => 5,
            'valor_particular' => 200.00,
            'cor' => '#3B82F6',
        ], ['X-Tenant-Slug' => $this->tenant->slug]);

        $response->assertCreated();
        $response->assertJsonPath('data.nome', 'Consulta');
        $response->assertJsonPath('data.slug', 'consulta');
        $response->assertJsonPath('data.duration_minutes', 30);

        $this->assertDatabaseHas('appointment_types', [
            'tenant_id' => $this->tenant->id,
            'nome' => 'Consulta',
            'is_active' => true,
        ]);
    }

    public function test_destroy_does_soft_inactivate_preserving_history(): void
    {
        $type = AppointmentType::factory()->for($this->tenant)->create();

        $response = $this->deleteJson("/api/v1/agenda/appointment-types/{$type->id}", [], [
            'X-Tenant-Slug' => $this->tenant->slug,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'is_active' => false,
        ]);
    }

    public function test_index_excludes_inactive_by_default(): void
    {
        AppointmentType::factory()->count(2)->for($this->tenant)->create();
        AppointmentType::factory()->for($this->tenant)->inactive()->create();

        $response = $this->getJson('/api/v1/agenda/appointment-types', [
            'X-Tenant-Slug' => $this->tenant->slug,
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        // include_inactive=true devolve todos
        $all = $this->getJson('/api/v1/agenda/appointment-types?include_inactive=1', [
            'X-Tenant-Slug' => $this->tenant->slug,
        ]);
        $this->assertCount(3, $all->json('data'));
    }
}
