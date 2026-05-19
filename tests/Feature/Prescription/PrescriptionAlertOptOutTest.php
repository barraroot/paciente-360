<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Preferences\PatientProfessionalPreference;
use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\ReceitaProximaDoVencimento;
use App\Listeners\Prescription\DispatchPrescriptionAlertViaMessaging;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T092 — AC-8.2.8: opt-out paciente → envio externo suprimido, skip_reason='recipient_opted_out'.
 *
 * Evento ReceitaProximaDoVencimento ainda é emitido normalmente.
 * Apenas o envio via mensageria é suprimido.
 */
class PrescriptionAlertOptOutTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_opt_out_patient_suppresses_external_send_and_sets_skip_reason(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-optout-patient', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state([
                'patient_id' => $paciente->id,
                'expires_at' => Carbon::today()->addDays(90),
            ])
            ->create();

        // Registra preferência de opt-out
        PatientProfessionalPreference::create([
            'tenant_id' => $tenant->id,
            'patient_id' => $paciente->id,
            'professional_id' => $medico->id,
            'suppress_renewal_notifications' => true,
        ]);

        // Cria alerta pending
        $alert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today(),
            ])
            ->create();

        // Dispara evento — listener deve suprimir envio externo
        $event = new ReceitaProximaDoVencimento(
            prescriptionId: $prescription->id,
            patientId: $paciente->id,
            professionalId: $medico->id,
            professionalName: $medico->name ?? 'Dr. Teste',
            daysUntilExpiry: 15,
            prescriptionType: $prescription->type,
            defaultAppointmentTypeId: null,
        );

        // Injeta alert_id no contexto — simula DispatchPrescriptionAlertJob
        $listener = app(DispatchPrescriptionAlertViaMessaging::class);
        $listener->processForAlert($event, $alert);

        // Alert deve ser skipped com motivo opt-out
        $alert->refresh();
        $this->assertSame(AlertStatus::Skipped, $alert->status);
        $this->assertSame('recipient_opted_out', $alert->skip_reason);
    }

    public function test_patient_without_opt_out_is_not_skipped(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-no-optout', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state([
                'patient_id' => $paciente->id,
                'expires_at' => Carbon::today()->addDays(90),
            ])
            ->create();

        // SEM preferência de opt-out
        $alert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days7,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today(),
            ])
            ->create();

        $event = new ReceitaProximaDoVencimento(
            prescriptionId: $prescription->id,
            patientId: $paciente->id,
            professionalId: $medico->id,
            professionalName: $medico->name ?? 'Dr. Teste',
            daysUntilExpiry: 7,
            prescriptionType: $prescription->type,
            defaultAppointmentTypeId: null,
        );

        $listener = app(DispatchPrescriptionAlertViaMessaging::class);
        $listener->processForAlert($event, $alert);

        // Alert NÃO deve ser skipped por opt-out
        $alert->refresh();
        $this->assertNotSame('recipient_opted_out', $alert->skip_reason);
    }
}
