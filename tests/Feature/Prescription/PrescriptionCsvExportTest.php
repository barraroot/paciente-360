<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\AuditLog;
use App\Models\Paciente;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T153 — AC-8.4.5/7 — Exportação CSV de receitas.
 *
 * Cobre:
 *  - AC-8.4.5: CSV respeita ability `prescription.export`.
 *  - AC-8.4.7: prefixo CONFIDENCIAL_ quando ≥1 controlada no resultado.
 *  - Audit log registra lista de IDs exportados.
 *
 * Nota: constraints de DB:
 *  - common: (expires_at - issued_at) IN (30, 60, 90, 180)
 *  - controlled: (expires_at - issued_at) = 30
 */
class PrescriptionCsvExportTest extends TestCase
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
    private function commonAttrsInWindow(int $tenantId, int $patientId, int $professionalId): array
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

    /**
     * @return array<string, mixed>
     */
    private function controlledAttrsInWindow(int $tenantId, int $patientId, int $professionalId): array
    {
        $issuedAt = Carbon::today()->subDays(20);

        return [
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'professional_id' => $professionalId,
            'type' => PrescriptionType::Controlled,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
            'alert_disabled' => false,
        ];
    }

    public function test_export_requires_prescription_export_ability(): void
    {
        // Cria um tenant com roles mas usa um usuário sem nenhuma role
        // (um user sem role não tem prescription.export por default)
        $tenant = $this->bootstrapTenantWithRoles('csv-auth');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        // Usuário sem roles — não tem prescription.export
        $userSemExport = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($userSemExport, ['*']);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }

    public function test_export_returns_csv_download(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('csv-download', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create(
            $this->commonAttrsInWindow($tenant->id, $patient->id, $medico->id)
        );

        $response = $this->get(
            $this->tenantUrl($tenant, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_filename_has_confidencial_prefix_when_controlled_present(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('csv-confidencial', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create(
            $this->controlledAttrsInWindow($tenant->id, $patient->id, $medico->id)
        );

        $response = $this->get(
            $this->tenantUrl($tenant, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('CONFIDENCIAL_', $contentDisposition);
    }

    public function test_export_filename_has_no_confidencial_prefix_when_no_controlled(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('csv-no-confidencial', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create(
            $this->commonAttrsInWindow($tenant->id, $patient->id, $medico->id)
        );

        $response = $this->get(
            $this->tenantUrl($tenant, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('CONFIDENCIAL_', $contentDisposition);
        $this->assertStringContainsString('receituarios_', $contentDisposition);
    }

    public function test_export_creates_audit_log_with_ids(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('csv-audit', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()->create(
            $this->commonAttrsInWindow($tenant->id, $patient->id, $medico->id)
        );

        $this->get(
            $this->tenantUrl($tenant, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        $auditLog = AuditLog::where('action', 'prescription.csv_exported')
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log for CSV export should have been created.');
        $this->assertArrayHasKey('ids', $auditLog->payload);
        $this->assertContains($prescription->id, $auditLog->payload['ids']);
        $this->assertArrayHasKey('count', $auditLog->payload);
        $this->assertArrayHasKey('contained_controlled', $auditLog->payload);
        $this->assertFalse($auditLog->payload['contained_controlled']);
    }

    public function test_export_audit_log_marks_contained_controlled_true(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('csv-audit-ctrl', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create(
            $this->controlledAttrsInWindow($tenant->id, $patient->id, $medico->id)
        );

        $this->get(
            $this->tenantUrl($tenant, '/prescription-reports/export'),
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        $auditLog = AuditLog::where('action', 'prescription.csv_exported')
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertTrue($auditLog->payload['contained_controlled']);
    }
}
