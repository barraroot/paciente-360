<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescricaoCancelada;
use App\Events\Prescription\PrescricaoCriada;
use App\Listeners\Prescription\ProjectPrescriptionToPatientTimeline;
use App\Models\EventoTimeline;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T044 — AC-8.1.10: listener ProjectPrescriptionToPatientTimeline
 * projeta PrescricaoCriada/Cancelada; alertas não poluem timeline.
 */
class PrescriptionTimelineProjectionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_prescription_creation_projects_to_patient_timeline(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-timeline-create', 'medico');
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
                    ['medication_name' => 'Aspirina', 'posology' => '100mg ao dia'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $tenant->id,
            'paciente_id' => $paciente->id,
            'tipo' => 'receita.criada',
        ]);
    }

    public function test_prescription_cancellation_projects_to_patient_timeline(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-timeline-cancel', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'erro_emissao',
                'cancellation_reason' => 'Medicamento errado.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $tenant->id,
            'paciente_id' => $paciente->id,
            'tipo' => 'receita.cancelada',
        ]);
    }

    public function test_listener_handles_prescricao_criada_event(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-timeline-listener', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        $listener = new ProjectPrescriptionToPatientTimeline;

        $event = new PrescricaoCriada(
            prescriptionId: $prescription->id,
            tenantId: $tenant->id,
            patientId: $paciente->id,
            professionalId: $medico->id,
            type: 'common',
            issuedAt: Carbon::today(),
            expiresAt: Carbon::today()->addDays(30),
        );

        $listener->handleCriada($event);

        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $paciente->id,
            'tipo' => 'receita.criada',
            'referencia_tipo' => Prescription::class,
            'referencia_id' => $prescription->id,
        ]);
    }

    public function test_listener_handles_prescricao_cancelada_event(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-timeline-cancellistener', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        $prescription = Prescription::factory()
            ->cancelled()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        $listener = new ProjectPrescriptionToPatientTimeline;

        $event = new PrescricaoCancelada(
            prescriptionId: $prescription->id,
            tenantId: $tenant->id,
            patientId: $paciente->id,
            cancelledByUserId: $medico->id,
            categoryReason: 'erro_emissao',
            cancelledAt: Carbon::now(),
        );

        $listener->handleCancelada($event);

        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $paciente->id,
            'tipo' => 'receita.cancelada',
        ]);
    }

    public function test_cancelled_prescription_remains_visible_in_timeline(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-timeline-visible', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['patient_id' => $paciente->id])
            ->create();

        // Create
        $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Dipirona', 'posology' => '1 cp 6/6h'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        // Cancel
        $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'erro_emissao',
                'cancellation_reason' => 'Erro no medicamento.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $criada = EventoTimeline::where('tipo', 'receita.criada')
            ->where('tenant_id', $tenant->id)
            ->count();

        $cancelada = EventoTimeline::where('tipo', 'receita.cancelada')
            ->where('tenant_id', $tenant->id)
            ->count();

        $this->assertGreaterThan(0, $cancelada, 'Cancelled prescription must remain in timeline');
    }
}
