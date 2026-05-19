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
 * T090 — AC-8.2.3: cancelar receita → alerts pending viram `cancelled`.
 *
 * Alerts com status `dispatched` devem ser preservados (disparo já saiu).
 */
class PrescriptionAlertCancellationOnCancelTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pending_alerts_are_cancelled_when_prescription_is_cancelled(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-alerts', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state([
                'patient_id' => $paciente->id,
                'issued_at' => Carbon::today(),
                'expires_at' => Carbon::today()->addDays(90),
            ])
            ->create();

        // Cria alertas pending
        $pendingAlert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today()->addDays(75),
            ])
            ->create();

        $pendingAlert2 = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days7,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today()->addDays(83),
            ])
            ->create();

        // Cancela a receita via API
        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'erro_emissao',
                'cancellation_reason' => 'Cancelamento de teste.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        // Alerts pending devem virar cancelled
        $pendingAlert->refresh();
        $this->assertSame(AlertStatus::Cancelled, $pendingAlert->status);

        $pendingAlert2->refresh();
        $this->assertSame(AlertStatus::Cancelled, $pendingAlert2->status);
    }

    public function test_dispatched_alerts_are_preserved_when_prescription_is_cancelled(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-keep-dispatched', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state([
                'patient_id' => $paciente->id,
                'issued_at' => Carbon::today(),
                'expires_at' => Carbon::today()->addDays(90),
            ])
            ->create();

        // Cria alerta já despachado — deve ser preservado
        $dispatchedAlert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->dispatched()
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
            ])
            ->create();

        // Cria alerta pending — deve virar cancelled
        $pendingAlert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days7,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today()->addDays(83),
            ])
            ->create();

        $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'erro_emissao',
                'cancellation_reason' => 'Cancelamento de teste.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        // Dispatched deve permanecer dispatched
        $dispatchedAlert->refresh();
        $this->assertSame(AlertStatus::Dispatched, $dispatchedAlert->status,
            'Alert dispatched deve ser preservado após cancelamento da receita.',
        );

        // Pending deve virar cancelled
        $pendingAlert->refresh();
        $this->assertSame(AlertStatus::Cancelled, $pendingAlert->status);
    }
}
