<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Events\Prescription\ReceitaRenovada;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T127 — Nova receita com `renewed_from_id`:
 *  - Emite ReceitaRenovada.
 *  - Original transita para `status=superseded`.
 *  - Alerts pending da original viram `cancelled` (CancelAlertScheduleOnRenewal).
 */
class PrescriptionRenewedChainTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * T127 — ReceitaRenovada é emitida quando nova receita usa renewed_from_id.
     */
    public function test_creating_renewal_prescription_emits_receita_renovada_event(): void
    {
        Event::fake([ReceitaRenovada::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-chain-1', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $original = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        // Cria renewal pendente (initiate já feito antes)
        $renewal = PrescriptionRenewal::factory()->create([
            'tenant_id' => $tenant->id,
            'original_prescription_id' => $original->id,
            'renewed_prescription_id' => null,
            'initiated_by' => 'ai',
        ]);

        // POST /prescriptions com renewed_from_id
        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 90,
                'items' => [
                    ['medication_name' => 'Sinvastatina 20mg', 'posology' => '1 cp à noite'],
                ],
                'renewed_from_id' => $original->id,
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        Event::assertDispatched(ReceitaRenovada::class, function ($event) use ($original, $response) {
            return $event->oldPrescriptionId === $original->id
                && $event->newPrescriptionId === $response->json('data.id');
        });
    }

    /**
     * T127 — Original transita para `superseded` após renovação.
     */
    public function test_original_prescription_transitions_to_superseded_on_renewal(): void
    {
        Event::fake([ReceitaRenovada::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-chain-2', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $original = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        $renewal = PrescriptionRenewal::factory()->create([
            'tenant_id' => $tenant->id,
            'original_prescription_id' => $original->id,
            'renewed_prescription_id' => null,
            'initiated_by' => 'professional',
        ]);

        $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 90,
                'items' => [
                    ['medication_name' => 'Sinvastatina 20mg', 'posology' => '1 cp à noite'],
                ],
                'renewed_from_id' => $original->id,
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $original->refresh();
        $this->assertSame(
            PrescriptionStatus::Superseded,
            $original->status,
            'Original deve transitar para superseded.',
        );
    }

    /**
     * T127 — Alerts pending da original viram cancelled (CancelAlertScheduleOnRenewal).
     *
     * Não faz Event::fake para que o listener CancelAlertScheduleOnRenewal
     * possa rodar quando ReceitaRenovada é despachado.
     */
    public function test_pending_alerts_of_original_are_cancelled_on_renewal(): void
    {
        // Sem Event::fake — precisamos que CancelAlertScheduleOnRenewal execute.

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-chain-3', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $original = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        // Cria alert pending manualmente
        $alert = PrescriptionAlert::factory()->create([
            'prescription_id' => $original->id,
            'tenant_id' => $tenant->id,
            'status' => AlertStatus::Pending->value,
        ]);

        $renewal = PrescriptionRenewal::factory()->create([
            'tenant_id' => $tenant->id,
            'original_prescription_id' => $original->id,
            'renewed_prescription_id' => null,
            'initiated_by' => 'professional',
        ]);

        $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 90,
                'items' => [
                    ['medication_name' => 'Sinvastatina 20mg', 'posology' => '1 cp à noite'],
                ],
                'renewed_from_id' => $original->id,
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $alert->refresh();
        $this->assertSame(
            AlertStatus::Cancelled,
            $alert->status,
            'Alert pending da original deve ser cancelado.',
        );
    }
}
