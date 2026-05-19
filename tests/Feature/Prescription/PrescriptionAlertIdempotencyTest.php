<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Alert\PrescriptionAlertIdempotencyKey;
use App\Domain\Prescription\Prescription\Prescription;
use App\Jobs\Prescription\ProcessPrescriptionAlertsJob;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T086 — Gate de idempotência: ProcessPrescriptionAlertsJob rodado 2x
 * deve marcar apenas 1 alerta como dispatched (Redis lock + DB UNIQUE).
 */
class PrescriptionAlertIdempotencyTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_process_alerts_job_is_idempotent_via_redis_lock(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-idem-redis');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        // Cria alerta pending com scheduled_for = hoje
        $alert = PrescriptionAlert::factory()
            ->for(
                Prescription::factory()->forTenant($tenant),
                'prescription',
            )
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days15,
                'status' => AlertStatus::Pending,
                'scheduled_for' => CarbonImmutable::today(),
            ])
            ->create();

        // Simula que a chave Redis já está setada (alerta já processado hoje)
        $key = PrescriptionAlertIdempotencyKey::for(
            $alert->prescription_id,
            AlertType::Days15,
            CarbonImmutable::today(),
        );
        Redis::set($key, 1, 'EX', 90000);

        // Roda o job — deve pular por causa do Redis lock
        app()->call([new ProcessPrescriptionAlertsJob($tenant), 'handle']);

        // O alerta ainda deve estar pending (skip por idempotência)
        $alert->refresh();
        $this->assertSame(AlertStatus::Pending, $alert->status,
            'Alerta deve permanecer pending quando Redis lock já existe.',
        );
    }

    public function test_process_alerts_job_sets_redis_lock_on_first_run(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-idem-lock');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $alert = PrescriptionAlert::factory()
            ->for(
                Prescription::factory()->forTenant($tenant),
                'prescription',
            )
            ->state([
                'tenant_id' => $tenant->id,
                'alert_type' => AlertType::Days7,
                'status' => AlertStatus::Pending,
                'scheduled_for' => CarbonImmutable::today(),
            ])
            ->create();

        $key = PrescriptionAlertIdempotencyKey::for(
            $alert->prescription_id,
            AlertType::Days7,
            CarbonImmutable::today(),
        );

        // Garante que a chave não existe antes
        Redis::del($key);
        $this->assertNull(Redis::get($key));

        // Roda o job (em modo sync para testes)
        app()->call([new ProcessPrescriptionAlertsJob($tenant), 'handle']);

        // Após o primeiro run, a chave Redis deve existir
        $this->assertNotNull(Redis::get($key),
            'Redis lock deve ser setado após o primeiro processamento.',
        );
    }

    public function test_db_unique_constraint_prevents_duplicate_alerts(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-idem-db');

        $prescription = Prescription::factory()
            ->forTenant($tenant)
            ->create();

        // Cria o primeiro alerta
        PrescriptionAlert::create([
            'prescription_id' => $prescription->id,
            'tenant_id' => $tenant->id,
            'alert_type' => AlertType::Days15,
            'status' => AlertStatus::Pending,
            'scheduled_for' => CarbonImmutable::today(),
        ]);

        // Tenta inserir duplicata — deve falhar por UNIQUE constraint
        $this->expectException(UniqueConstraintViolationException::class);

        PrescriptionAlert::create([
            'prescription_id' => $prescription->id,
            'tenant_id' => $tenant->id,
            'alert_type' => AlertType::Days15,
            'status' => AlertStatus::Pending,
            'scheduled_for' => CarbonImmutable::today()->addDay(),
        ]);
    }
}
