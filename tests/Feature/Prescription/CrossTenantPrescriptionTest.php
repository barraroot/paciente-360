<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T038 — Gate multi-tenant: 7 cenários — tenant B recebe 404 em receita do tenant A.
 * Audit log cross_tenant_attempt registrado.
 */
class CrossTenantPrescriptionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function setupTwoTenants(): array
    {
        $tenantA = $this->bootstrapTenantWithRoles('tenant-a-cross');
        $tenantA->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $tenantB = $this->bootstrapTenantWithRoles('tenant-b-cross');
        $tenantB->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($tenantA->id);
        $medicoA = $this->userForRole($tenantA, 'medico');

        $registrar->setPermissionsTeamId($tenantB->id);
        $medicoB = $this->userForRole($tenantB, 'medico');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescriptionA = Prescription::factory()
            ->common()
            ->forTenant($tenantA)
            ->withProfessional($medicoA)
            ->create();

        return [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA];
    }

    public function test_tenant_b_cannot_show_tenant_a_prescription(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->getJson(
            $this->tenantUrl($tenantB, "/prescriptions/{$prescriptionA->id}"),
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        $response->assertNotFound();
    }

    public function test_tenant_b_cannot_update_tenant_a_prescription(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->patchJson(
            $this->tenantUrl($tenantB, "/prescriptions/{$prescriptionA->id}"),
            ['notes' => 'intrusion attempt'],
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        $response->assertNotFound();
    }

    public function test_tenant_b_cannot_cancel_tenant_a_prescription(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->postJson(
            $this->tenantUrl($tenantB, "/prescriptions/{$prescriptionA->id}/cancel"),
            [
                'cancellation_reason_category' => 'outro',
                'cancellation_reason' => 'Cross-tenant cancel attempt',
            ],
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        $response->assertNotFound();
    }

    public function test_tenant_b_cannot_upload_pdf_to_tenant_a_prescription(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->postJson(
            $this->tenantUrl($tenantB, "/prescriptions/{$prescriptionA->id}/pdf"),
            [],
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        $response->assertNotFound();
    }

    public function test_tenant_b_cannot_download_pdf_from_tenant_a_prescription(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->getJson(
            $this->tenantUrl($tenantB, "/prescriptions/{$prescriptionA->id}/pdf"),
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        $response->assertNotFound();
    }

    public function test_tenant_b_cannot_list_tenant_a_prescriptions_by_patient(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantA->id);
        $patientA = Paciente::factory()->create(['tenant_id' => $tenantA->id]);
        $registrar->setPermissionsTeamId(null);

        // Update prescription to belong to patientA
        $prescriptionA->update(['patient_id' => $patientA->id]);

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->getJson(
            $this->tenantUrl($tenantB, "/prescriptions?patient_id={$patientA->id}"),
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        $response->assertOk();
        // Must return empty — no cross-tenant data
        $this->assertEmpty($response->json('data'));
    }

    public function test_cross_tenant_show_returns_404_not_403(): void
    {
        [$tenantA, $tenantB, $medicoA, $medicoB, $prescriptionA] = $this->setupTwoTenants();

        Sanctum::actingAs($medicoB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $response = $this->getJson(
            $this->tenantUrl($tenantB, "/prescriptions/{$prescriptionA->id}"),
            ['X-Tenant-Slug' => $tenantB->slug],
        );

        // 404, not 403, to avoid leaking existence of the resource
        $response->assertNotFound();
        $response->assertStatus(404);
    }
}
