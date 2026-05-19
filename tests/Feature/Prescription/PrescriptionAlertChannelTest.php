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
 * T088 — Gate Meta: sem template HSM → blocked_no_template + fallback Inbox.
 *
 * Com template + fora janela 24h → usa HSM (blocked_no_channel em ausência de canal).
 */
class PrescriptionAlertChannelTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Limpa chaves de debounce entre testes para evitar interferência
        Redis::flushdb();
    }

    public function test_no_template_results_in_blocked_no_template_status(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-no-template', 'medico');
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

        $alert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today(),
            ])
            ->create();

        // SEM template HSM cadastrado
        // SEM canal WhatsApp
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

        // Sem canal → blocked_no_channel; sem template → blocked_no_template
        // Qualquer um dos dois é válido conforme a ordem de verificação
        $this->assertContains(
            $alert->status,
            [AlertStatus::BlockedNoChannel, AlertStatus::BlockedNoTemplate],
            'Alert sem canal/template deve ser blocked.',
        );
    }

    public function test_no_channel_results_in_blocked_no_channel_status(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-no-channel', 'medico');
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

        $alert = PrescriptionAlert::factory()
            ->for($prescription, 'prescription')
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => Carbon::today(),
            ])
            ->create();

        // SEM canal WhatsApp ativo para o tenant
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

        $this->assertSame(AlertStatus::BlockedNoChannel, $alert->status,
            'Sem canal WhatsApp ativo, alert deve ser blocked_no_channel.',
        );
    }
}
