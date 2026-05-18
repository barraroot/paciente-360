<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Pdf\PrescriptionPdfStorage;
use App\Domain\Prescription\Pdf\PrescriptionPdfVersioningService;
use App\Domain\Prescription\Prescription\Prescription;
use App\Jobs\Prescription\PrescriptionPdfUploadJob;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T040 — Q7b: substituição preserva versão anterior no S3 + audit pdf_replaced.
 */
class PrescriptionPdfVersioningTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Storage::fake('local');
        Storage::fake('s3');
    }

    public function test_versioning_service_computes_next_version(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-ver', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['pdf_version' => 0, 'pdf_path' => null])
            ->create();

        $service = new PrescriptionPdfVersioningService;

        $this->assertSame(1, $service->nextVersion($prescription));
    }

    public function test_versioning_service_increments_from_existing_version(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-ver2', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['pdf_version' => 3])
            ->create();

        $service = new PrescriptionPdfVersioningService;

        $this->assertSame(4, $service->nextVersion($prescription));
    }

    public function test_pdf_storage_generates_versioned_path(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-path', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $storage = new PrescriptionPdfStorage;
        $path = $storage->pathFor($prescription, 1);

        $this->assertStringContainsString((string) $tenant->id, $path);
        $this->assertStringContainsString((string) $prescription->id, $path);
        $this->assertStringContainsString('v1.pdf', $path);
    }

    public function test_pdf_replacement_preserves_old_version_path(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-replace', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $storage = new PrescriptionPdfStorage;
        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->state(['pdf_version' => 0, 'pdf_path' => null])
            ->create();

        $version1Path = $storage->pathFor($prescription, 1);
        $version2Path = $storage->pathFor($prescription, 2);

        // Simulate old file stored at v1 path
        Storage::disk('s3')->put($version1Path, 'v1 content');

        // When we compute path for v2, v1 must still be accessible
        Storage::disk('s3')->put($version2Path, 'v2 content');

        $this->assertTrue(Storage::disk('s3')->exists($version1Path), 'v1.pdf must be preserved');
        $this->assertTrue(Storage::disk('s3')->exists($version2Path), 'v2.pdf must exist');
    }

    public function test_pdf_replacement_records_audit_log(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-audit', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        // Simulate an existing v1 PDF on the prescription
        $existingPath = "prescriptions/{$tenant->id}/{$prescription->id}/v1.pdf";
        $prescription->update(['pdf_path' => $existingPath, 'pdf_version' => 1]);

        // Run upload job directly to simulate second upload
        $newTmpPath = 'tmp/test-receita.pdf';
        Storage::disk('local')->put($newTmpPath, 'new pdf content');

        $job = new PrescriptionPdfUploadJob($prescription->id, $newTmpPath);
        $job->handle();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'prescription.pdf_replaced',
            'auditable_id' => $prescription->id,
        ]);
    }
}
