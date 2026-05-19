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
 * T155 — AC-8.4.9 — Proteção cross-tenant no relatório.
 *
 * Um usuário de um tenant não deve ver receitas de outro tenant.
 */
class PrescriptionReportCrossTenantTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function validAttrs(int $tenantId, int $patientId, int $professionalId): array
    {
        // issued = 20d atrás, expires = issued + 30d = daqui 10d
        $issuedAt = Carbon::today()->subDays(20);

        return [
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'professional_id' => $professionalId,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
        ];
    }

    public function test_report_does_not_expose_other_tenant_prescriptions(): void
    {
        // Tenant A: autenticado
        [$tenantA, $medicoA] = $this->tenantAndUserForRole('cross-tenant-a', 'medico');
        $tenantA->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        // Tenant B: prescriptions que NÃO devem aparecer
        $tenantB = $this->bootstrapTenantWithRoles('cross-tenant-b');
        $tenantB->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);
        $medicoB = $this->userForRole($tenantB, 'medico');

        $patientA = Paciente::factory()->create(['tenant_id' => $tenantA->id]);
        $patientB = Paciente::factory()->create(['tenant_id' => $tenantB->id]);

        Prescription::factory()->create($this->validAttrs($tenantA->id, $patientA->id, $medicoA->id));

        $prescriptionB = Prescription::factory()->create($this->validAttrs($tenantB->id, $patientB->id, $medicoB->id));

        $response = $this->getJson(
            $this->tenantUrl($tenantA, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($prescriptionB->id, $ids, 'Tenant B prescription must not appear in Tenant A report.');
    }

    public function test_report_is_scoped_to_authenticated_user_tenant(): void
    {
        [$tenantA, $medicoA] = $this->tenantAndUserForRole('scope-a', 'medico');
        $tenantA->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $tenantB = $this->bootstrapTenantWithRoles('scope-b');
        $tenantB->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);
        $medicoB = $this->userForRole($tenantB, 'medico');

        $patientA = Paciente::factory()->create(['tenant_id' => $tenantA->id]);
        $patientB = Paciente::factory()->create(['tenant_id' => $tenantB->id]);

        $prescA = Prescription::factory()->create($this->validAttrs($tenantA->id, $patientA->id, $medicoA->id));

        Prescription::factory()->create($this->validAttrs($tenantB->id, $patientB->id, $medicoB->id));

        $response = $this->getJson(
            $this->tenantUrl($tenantA, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($prescA->id, $ids);
        $this->assertCount(1, $ids, 'Only Tenant A prescriptions should be visible.');
    }

    public function test_csv_export_cross_tenant_isolation(): void
    {
        [$tenantA, $medicoA] = $this->tenantAndUserForRole('csv-cross-a', 'medico');
        $tenantA->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $tenantB = $this->bootstrapTenantWithRoles('csv-cross-b');
        $tenantB->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);
        $medicoB = $this->userForRole($tenantB, 'medico');

        $patientA = Paciente::factory()->create(['tenant_id' => $tenantA->id]);
        $patientB = Paciente::factory()->create(['tenant_id' => $tenantB->id]);

        $prescA = Prescription::factory()->create($this->validAttrs($tenantA->id, $patientA->id, $medicoA->id));
        $prescB = Prescription::factory()->create($this->validAttrs($tenantB->id, $patientB->id, $medicoB->id));

        $response = $this->get(
            $this->tenantUrl($tenantA, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertOk();

        $csv = $response->streamedContent();

        // O ID do prescription B não deve aparecer no CSV
        $this->assertStringNotContainsString((string) $prescB->id, $csv, 'Tenant B prescription ID must not appear in Tenant A CSV export.');
    }
}
