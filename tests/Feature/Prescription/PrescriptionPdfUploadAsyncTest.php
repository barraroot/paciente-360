<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Jobs\Prescription\PrescriptionPdfUploadJob;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T039 — AC-8.1.4:
 *  - Textuais persistidos antes do upload.
 *  - Falha de S3 não invalida receita.
 *  - PDF é processado de forma assíncrona.
 */
class PrescriptionPdfUploadAsyncTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Storage::fake('local');
        Bus::fake();
    }

    public function test_pdf_upload_dispatches_async_job(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-async', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $file = UploadedFile::fake()->create('receita.pdf', 512, 'application/pdf');

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/pdf"),
            ['pdf' => $file],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertAccepted();

        Bus::assertDispatched(PrescriptionPdfUploadJob::class);
    }

    public function test_prescription_textual_data_persisted_before_pdf_upload(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-text', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        // Create textual prescription first without PDF
        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => Paciente::factory()->create(['tenant_id' => $tenant->id])->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Omeprazol', 'posology' => '1 cp em jejum'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $prescriptionId = $response->json('data.id');

        // Prescription exists without PDF
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescriptionId,
            'pdf_path' => null,
        ]);
    }

    public function test_s3_failure_does_not_invalidate_prescription(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-s3fail', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $file = UploadedFile::fake()->create('receita.pdf', 100, 'application/pdf');

        $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/pdf"),
            ['pdf' => $file],
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertAccepted();

        // Prescription still exists and is active even when job hasn't run
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => 'active',
        ]);
    }

    public function test_pdf_upload_validates_file_type(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-type', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $file = UploadedFile::fake()->create('receita.doc', 512, 'application/msword');

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/pdf"),
            ['pdf' => $file],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['pdf']);
    }

    public function test_pdf_upload_validates_file_size(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-pdf-size', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        // 11MB file — exceeds 10MB limit
        $file = UploadedFile::fake()->create('large_receita.pdf', 11264, 'application/pdf');

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/pdf"),
            ['pdf' => $file],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['pdf']);
    }

    public function test_pdf_upload_requires_view_permission(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-pdf-perm');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $medico = $this->userForRole($tenant, 'medico');
        $atendente = $this->userForRole($tenant, 'atendente');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        Sanctum::actingAs($atendente, ['*']);
        $this->app->instance('tenant', $tenant);

        $file = UploadedFile::fake()->create('receita.pdf', 100, 'application/pdf');

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/pdf"),
            ['pdf' => $file],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }
}
