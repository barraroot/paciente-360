<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T089 — AC-8.2.4: receita com validade curta → checkpoints passados viram `skipped`.
 *
 * Ex.: receita com duration_days=5 → expires_at=hoje+5d.
 * D-15 = hoje-10d (passado) → skipped.
 * D-7  = hoje-2d  (passado) → skipped.
 * D-1  = hoje+4d  (futuro)  → pending.
 */
class PrescriptionAlertSkipTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_checkpoints_past_at_creation_are_skipped(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-skip-past', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Receita com 5 dias → expires_at = hoje + 5d
        // D-15 = hoje - 10d (passado) → deve ser skipped
        // D-7  = hoje - 2d  (passado) → deve ser skipped
        // D-1  = hoje + 4d  (futuro)  → deve ser pending
        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->subDays(25)->toDateString(), // issued 25d atrás
                'duration_days' => 30,
                // expires_at = issued - 25 + 30 = hoje + 5d
                'items' => [
                    ['medication_name' => 'Ibuprofeno 400mg', 'posology' => '1 cp 8/8h'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->get()
            ->keyBy(fn ($a) => $a->alert_type->value);

        // D-15 → skipped (scheduled_for seria hoje - 10d)
        $this->assertSame(AlertStatus::Skipped, $alerts['days15']->status);
        $this->assertSame('checkpoint_past_at_creation', $alerts['days15']->skip_reason);

        // D-7 → skipped (scheduled_for seria hoje - 2d)
        $this->assertSame(AlertStatus::Skipped, $alerts['days7']->status);
        $this->assertSame('checkpoint_past_at_creation', $alerts['days7']->skip_reason);

        // D-1 → pending (scheduled_for = hoje + 4d)
        $this->assertSame(AlertStatus::Pending, $alerts['days1']->status);
        $this->assertNull($alerts['days1']->skip_reason);
    }

    public function test_all_checkpoints_skipped_when_prescription_expires_in_zero_days(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-skip-all', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // expires_at = hoje → todos D-15/D-7/D-1 são passados → todos skipped
        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->subDays(30)->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Amoxicilina 500mg', 'posology' => '1 cp 8/8h'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->get();

        $this->assertCount(3, $alerts);

        foreach ($alerts as $alert) {
            $this->assertSame(AlertStatus::Skipped, $alert->status,
                "Alerta {$alert->alert_type->value} deveria ser skipped.",
            );
            $this->assertSame('checkpoint_past_at_creation', $alert->skip_reason);
        }
    }

    public function test_all_checkpoints_pending_when_prescription_expires_far_in_future(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-skip-none', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // expires_at = hoje + 180d → todos checkpoints no futuro → todos pending
        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 180,
                'items' => [
                    ['medication_name' => 'Omeprazol 20mg', 'posology' => '1 cp antes do café'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->get();

        $this->assertCount(3, $alerts);

        foreach ($alerts as $alert) {
            $this->assertSame(AlertStatus::Pending, $alert->status,
                "Alerta {$alert->alert_type->value} deveria ser pending.",
            );
        }
    }
}
