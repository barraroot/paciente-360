<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T151 — Criticidade no relatório: verde / amarelo / vermelho.
 *
 * - green: expires_at > hoje + 15d
 * - yellow: hoje < expires_at <= hoje + 15d
 * - red: expires_at < hoje (vencida)
 *
 * Nota: check constraint exige (expires_at - issued_at) IN (30, 60, 90, 180) para common.
 * Usamos issued_at no passado para posicionar expires_at onde precisamos.
 */
class PrescriptionReportCriticalityTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_report_row_criticality_green_when_expires_after_15_days(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('crit-green', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // expires_at = hoje + 16d: issued = hoje - 14d, duration = 30d
        $issuedAt = Carbon::today()->subDays(14);
        Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30), // +16d from today
        ]);

        $expiresAfter = Carbon::today()->toDateString();
        $expiresBefore = Carbon::today()->addDays(30)->toDateString();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescription-reports?expires_after={$expiresAfter}&expires_before={$expiresBefore}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('green', $response->json('data.0.criticality'));
    }

    public function test_report_row_criticality_yellow_when_expires_within_15_days(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('crit-yellow', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // expires_at = hoje + 10d: issued = hoje - 20d, duration = 30d
        $issuedAt = Carbon::today()->subDays(20);
        Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30), // +10d from today
        ]);

        $expiresAfter = Carbon::today()->toDateString();
        $expiresBefore = Carbon::today()->addDays(30)->toDateString();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescription-reports?expires_after={$expiresAfter}&expires_before={$expiresBefore}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('yellow', $response->json('data.0.criticality'));
    }

    public function test_report_row_criticality_yellow_on_exactly_15_days(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('crit-yellow-exact', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // expires_at = hoje + 15d: issued = hoje - 15d, duration = 30d
        $issuedAt = Carbon::today()->subDays(15);
        Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30), // exactly +15d from today
        ]);

        $expiresAfter = Carbon::today()->toDateString();
        $expiresBefore = Carbon::today()->addDays(15)->toDateString();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescription-reports?expires_after={$expiresAfter}&expires_before={$expiresBefore}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('yellow', $response->json('data.0.criticality'));
    }

    public function test_report_row_criticality_red_when_expired(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('crit-red', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // expires_at = ontem: issued = 31d atrás, duration = 30d
        $issuedAt = Carbon::today()->subDays(31);
        Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30), // -1d from today
        ]);

        // Filtro custom para incluir expiradas (fora do window padrão)
        $expiresAfter = Carbon::today()->subDays(5)->toDateString();
        $expiresBefore = Carbon::today()->subDay()->toDateString();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescription-reports?expires_after={$expiresAfter}&expires_before={$expiresBefore}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('red', $response->json('data.0.criticality'));
    }
}
