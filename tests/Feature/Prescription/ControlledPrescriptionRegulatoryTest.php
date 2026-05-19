<?php

namespace Tests\Feature\Prescription;

use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T034 — Gate Portaria 344/98:
 *  - Controlada com >1 item → 422.
 *  - Tentativa de override expires_at rejeitada server-side.
 */
class ControlledPrescriptionRegulatoryTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_controlled_prescription_with_multiple_items_returns_422(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-reg-multi-item', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => Carbon::today()->toDateString(),
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                    ['medication_name' => 'Clonazepam 0.5mg', 'posology' => '1 comp ao dormir'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_controlled_prescription_with_exactly_one_item_succeeds(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-reg-one-item', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => Carbon::today()->toDateString(),
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
    }

    public function test_controlled_prescription_expires_at_is_always_30_days_server_side(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-reg-expires', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $issuedAt = Carbon::today()->toDateString();

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => $issuedAt,
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.expires_at', Carbon::today()->addDays(30)->toDateString());
    }

    public function test_controlled_prescription_status_cannot_be_overridden_in_request(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-reg-status', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => Carbon::today()->toDateString(),
                'status' => 'cancelled', // attempt to override server-side field
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        // Status must always be active on creation
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_controlled_prescription_professional_id_cannot_be_overridden(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-reg-prof', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $otherMedico = $this->createUserForTenant($tenant);
        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => Carbon::today()->toDateString(),
                'professional_id' => $otherMedico->id, // attempt to override
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        // Must be forced to authenticated user
        $response->assertJsonPath('data.professional_id', $medico->id);
    }

    public function test_controlled_prescription_alert_disabled_is_rejected(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-reg-alert', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => Carbon::today()->toDateString(),
                'alert_disabled' => true, // not allowed for controlled
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['alert_disabled']);
    }

    public function test_special_prescription_alert_disabled_is_rejected(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-reg-alert-special', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'special',
                'issued_at' => Carbon::today()->toDateString(),
                'alert_disabled' => true, // not allowed for special
                'items' => [
                    ['medication_name' => 'Clonazepam', 'posology' => '1 comp ao dormir'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['alert_disabled']);
    }
}
