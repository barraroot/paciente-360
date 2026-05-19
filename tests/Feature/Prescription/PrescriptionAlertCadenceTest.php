<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Events\Prescription\ReceitaProximaDoVencimento;
use App\Listeners\Prescription\ProjectPrescriptionToPatientTimeline;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T085 — AC-8.2.1: 3 checkpoints D-15/D-7/D-1 materializados na criação.
 *
 * Verifica que ao criar uma receita via API, o scheduler cria
 * 3 PrescriptionAlert com alert_type = days15/days7/days1.
 */
class PrescriptionAlertCadenceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_creating_prescription_schedules_three_alert_checkpoints(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-alert-cadence', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $issuedAt = Carbon::today();

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => $issuedAt->toDateString(),
                'duration_days' => 90,
                'items' => [
                    ['medication_name' => 'Sinvastatina 20mg', 'posology' => '1 cp à noite'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $prescriptionId = $response->json('data.id');

        // Deve ter 3 alertas criados (D-15, D-7, D-1)
        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->orderBy('scheduled_for')
            ->get();

        $this->assertCount(3, $alerts, 'Devem existir exatamente 3 alertas.');

        $alertTypes = $alerts->pluck('alert_type')->map(fn ($t) => $t->value)->toArray();
        $this->assertEqualsCanonicalizing(
            ['days15', 'days7', 'days1'],
            $alertTypes,
        );
    }

    public function test_alert_scheduled_for_dates_match_expires_at_minus_days(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-alert-dates', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $issuedAt = Carbon::today();
        $duration = 90;

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => $issuedAt->toDateString(),
                'duration_days' => $duration,
                'items' => [
                    ['medication_name' => 'Atorvastatina 40mg', 'posology' => '1 cp à noite'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $prescriptionId = $response->json('data.id');
        $expiresAt = $issuedAt->copy()->addDays($duration);

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->get()
            ->keyBy(fn ($a) => $a->alert_type->value);

        $this->assertTrue($alerts['days15']->scheduled_for->isSameDay($expiresAt->copy()->subDays(15)));
        $this->assertTrue($alerts['days7']->scheduled_for->isSameDay($expiresAt->copy()->subDays(7)));
        $this->assertTrue($alerts['days1']->scheduled_for->isSameDay($expiresAt->copy()->subDays(1)));
    }

    public function test_alerts_have_correct_initial_status_pending(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-alert-status', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 90,
                'items' => [
                    ['medication_name' => 'Metformina 500mg', 'posology' => '1 cp após refeição'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->get();

        foreach ($alerts as $alert) {
            $this->assertSame(
                AlertStatus::Pending,
                $alert->status,
                "Alerta {$alert->alert_type->value} deveria ser pending.",
            );
        }
    }

    public function test_alert_disabled_true_on_common_creates_no_alerts(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-alert-disabled', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 90,
                'alert_disabled' => true,
                'items' => [
                    ['medication_name' => 'Vitamina D3', 'posology' => '1 cp por dia'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        $count = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->count();

        $this->assertSame(0, $count, 'Nenhum alerta deve ser criado quando alert_disabled=true.');
    }

    public function test_alerts_have_correct_tenant_id(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-alert-tenant', 'medico');
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
                    ['medication_name' => 'Dipirona', 'posology' => '1 cp 6/6h'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $prescriptionId)
            ->get();

        foreach ($alerts as $alert) {
            $this->assertSame($tenant->id, $alert->tenant_id);
        }
    }

    /**
     * T122 — ReceitaProximaDoVencimento NÃO vira item em eventos_timeline (Q11).
     *
     * ProjectPrescriptionToPatientTimeline escuta apenas PrescricaoCriada
     * e PrescricaoCancelada — não ReceitaProximaDoVencimento/ReceitaVencida.
     */
    public function test_receita_proxima_do_vencimento_does_not_create_timeline_entry(): void
    {
        // Verificação via reflection: ProjectPrescriptionToPatientTimeline
        // não tem método handle() para ReceitaProximaDoVencimento.
        $listenerClass = ProjectPrescriptionToPatientTimeline::class;
        $reflection = new \ReflectionClass($listenerClass);
        $methods = collect($reflection->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->map(fn ($m) => $m->getName())
            ->toArray();

        // Listener auto-discovery: métodos que começam com "handle" são descobertos.
        // Apenas handleCriada e handleCancelada devem existir.
        $handleMethods = array_filter($methods, fn ($m) => str_starts_with($m, 'handle'));

        $this->assertNotContains('handle', $handleMethods,
            'Não deve ter método handle() genérico para ReceitaProximaDoVencimento.',
        );

        // Confirma que os métodos de timeline existentes não ouvem ReceitaProximaDoVencimento
        foreach ($handleMethods as $method) {
            $refMethod = $reflection->getMethod($method);
            $params = $refMethod->getParameters();
            if (count($params) > 0) {
                $paramType = $params[0]->getType();
                if ($paramType instanceof \ReflectionNamedType) {
                    $this->assertNotSame(
                        ReceitaProximaDoVencimento::class,
                        $paramType->getName(),
                        "Método {$method} não deve ouvir ReceitaProximaDoVencimento.",
                    );
                }
            }
        }
    }
}
