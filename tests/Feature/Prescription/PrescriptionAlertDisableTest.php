<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T091 — AC-8.2.2: PATCH alert_disabled=true em special/controlled → 422.
 *
 * Em receita `common`, o disable deve funcionar e cancelar alerts pending.
 * Re-enable deve reagendar checkpoints futuros.
 */
class PrescriptionAlertDisableTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_disabling_alerts_on_special_prescription_returns_422(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-disable-special', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->special()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/alert-config"),
            ['alert_disabled' => true],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['alert_disabled']);
    }

    public function test_disabling_alerts_on_controlled_prescription_returns_422(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-disable-controlled', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/alert-config"),
            ['alert_disabled' => true],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['alert_disabled']);
    }

    public function test_disabling_alerts_on_common_prescription_succeeds(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-disable-common', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id, 'alert_disabled' => false])
            ->create();

        // Cria alerta pending para ser cancelado
        PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today()->addDays(75),
            ])
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/alert-config"),
            ['alert_disabled' => true],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        // Prescription deve ter alert_disabled = true
        $prescription->refresh();
        $this->assertTrue($prescription->alert_disabled);

        // Alerts pending devem estar cancelled
        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescription->id)
            ->where('status', AlertStatus::Pending)
            ->count();

        $this->assertSame(0, $alerts, 'Todos os alerts pending devem ter sido cancelados.');
    }

    public function test_reenabling_alerts_schedules_future_checkpoints(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-reenable-alert', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Prescription com alert_disabled=true e expires_at no futuro distante
        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state([
                'patient_id' => $paciente->id,
                'alert_disabled' => true,
                'expires_at' => Carbon::today()->addDays(90),
            ])
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/alert-config"),
            ['alert_disabled' => false],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $prescription->refresh();
        $this->assertFalse($prescription->alert_disabled);

        // Deve ter criado novos alerts pending para checkpoints futuros
        $pendingAlerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescription->id)
            ->where('status', AlertStatus::Pending)
            ->count();

        $this->assertGreaterThan(0, $pendingAlerts,
            'Re-enable deve criar novos alerts pending para checkpoints futuros.',
        );
    }

    public function test_get_alerts_for_prescription(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-get-alerts', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today()->addDays(75),
            ])
            ->create();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/alerts"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.alert_type', 'days15');
    }
}
