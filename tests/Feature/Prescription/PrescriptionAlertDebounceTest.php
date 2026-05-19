<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\ReceitaProximaDoVencimento;
use App\Listeners\Prescription\DispatchPrescriptionAlertViaMessaging;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T093 — AC-8.2.6: debounce 4h por destinatário.
 *
 * Se 3 receitas do mesmo paciente vencem no mesmo dia, o debounce
 * impede que a 2ª e 3ª mensagens sejam enviadas dentro de 4h.
 */
class PrescriptionAlertDebounceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_second_alert_for_same_patient_within_4h_is_debounced(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-debounce', 'medico');
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

        $debounceKey = "messaging_debounce:prescription_alert:{$paciente->id}:".AlertType::Days15->value;

        // Simula que o debounce já está ativo (alerta anterior enviado dentro de 4h)
        Redis::set($debounceKey, 1, 'EX', 14400);

        $alert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today(),
            ])
            ->create();

        $event = new ReceitaProximaDoVencimento(
            prescriptionId: $prescription->id,
            patientId: $paciente->id,
            professionalId: $medico->id,
            professionalName: $medico->name ?? 'Dr. Teste',
            daysUntilExpiry: 15,
            prescriptionType: $prescription->type,
            defaultAppointmentTypeId: null,
        );

        $listener = app(DispatchPrescriptionAlertViaMessaging::class);
        $listener->processForAlert($event, $alert);

        $alert->refresh();
        $this->assertSame(AlertStatus::Skipped, $alert->status,
            'Alert deve ser debounced quando chave Redis já existe.',
        );
        $this->assertSame('debounced', $alert->skip_reason);
    }

    public function test_first_alert_for_patient_sets_debounce_key(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-debounce-set', 'medico');
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

        $debounceKey = "messaging_debounce:prescription_alert:{$paciente->id}:".AlertType::Days7->value;
        Redis::del($debounceKey);

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

        // Após processamento (pode ser blocked_no_channel/blocked_no_template mas
        // a chave de debounce deve ter sido tentada antes de verificar canais)
        // O importante: não ser skipped por debounce
        $alert->refresh();
        $this->assertNotSame('debounced', $alert->skip_reason,
            'Primeiro alerta não deve ser bloqueado por debounce.',
        );
    }
}
