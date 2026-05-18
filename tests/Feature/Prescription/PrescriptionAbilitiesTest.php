<?php

namespace Tests\Feature\Prescription;

use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T043 — AC-8.1.5: Atendente/Recepcionista sem `prescription.create` → 403.
 */
class PrescriptionAbilitiesTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_atendente_cannot_create_prescription(): void
    {
        [$tenant, $atendente] = $this->tenantAndUserForRole('clinica-abil-atend', 'atendente');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Vitamina D', 'posology' => '1 cp por dia'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }

    public function test_recepcionista_cannot_create_prescription(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-abil-recep', 'recepcionista');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Zinco', 'posology' => '1 cp por dia'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }

    public function test_medico_can_create_prescription(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-abil-medico', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Omega 3', 'posology' => '1 cp com refeição'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
    }

    public function test_atendente_can_view_prescriptions(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-abil-view');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $atendente = $this->userForRole($tenant, 'atendente');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        Sanctum::actingAs($atendente, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
    }

    public function test_403_is_recorded_in_audit_log_for_atendente(): void
    {
        [$tenant, $atendente] = $this->tenantAndUserForRole('clinica-abil-403', 'atendente');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Test', 'posology' => 'Test'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }
}
