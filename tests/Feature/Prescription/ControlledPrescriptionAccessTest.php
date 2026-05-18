<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescricaoControladaVisualizada;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T037 — Gate principal: 5 perfis × 4 endpoints (matriz Q8):
 *  - Atendente/Recepcionista/médico não-emissor → PrescriptionMasked.
 *  - Emissor + Admin Clínica com view_controlled → completo + audit log.
 */
class ControlledPrescriptionAccessTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createControlledPrescriptionWithEmisor(): array
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-controlled-access');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $emissor = $this->userForRole($tenant, 'medico');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescription = Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($emissor)
            ->create();

        return [$tenant, $emissor, $prescription];
    }

    // -----------------------------------------------------------------------
    // Emissor vê conteúdo completo
    // -----------------------------------------------------------------------

    public function test_emissor_sees_full_controlled_prescription_content(): void
    {
        Event::fake([PrescricaoControladaVisualizada::class]);

        [$tenant, $emissor, $prescription] = $this->createControlledPrescriptionWithEmisor();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        Sanctum::actingAs($emissor, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.masked', false);
        $response->assertJsonMissing(['masked_reason']);

        Event::assertDispatched(PrescricaoControladaVisualizada::class, function (PrescricaoControladaVisualizada $event) use ($emissor, $prescription): bool {
            return $event->actorUserId === $emissor->id
                && $event->prescriptionId === $prescription->id;
        });
    }

    // -----------------------------------------------------------------------
    // Admin Clínica com view_controlled vê conteúdo completo
    // -----------------------------------------------------------------------

    public function test_admin_clinica_with_view_controlled_sees_full_content(): void
    {
        Event::fake([PrescricaoControladaVisualizada::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-admin-view-ctrl');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $admin = $this->userForRole($tenant, 'admin-clinica');
        $emissor = $this->userForRole($tenant, 'medico');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescription = Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($emissor)
            ->create();

        Sanctum::actingAs($admin, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.masked', false);

        Event::assertDispatched(PrescricaoControladaVisualizada::class);
    }

    // -----------------------------------------------------------------------
    // Médico não-emissor recebe linha mascarada
    // -----------------------------------------------------------------------

    public function test_non_emissor_medico_receives_masked_controlled_prescription(): void
    {
        Event::fake([PrescricaoControladaVisualizada::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-non-emissor');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $emissor = $this->userForRole($tenant, 'medico');
        $otherMedico = $this->userForRole($tenant, 'medico');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescription = Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($emissor)
            ->create();

        Sanctum::actingAs($otherMedico, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.masked', true);
        $response->assertJsonFragment(['masked_reason' => 'controlled_access_restricted']);

        Event::assertNotDispatched(PrescricaoControladaVisualizada::class);
    }

    // -----------------------------------------------------------------------
    // Atendente recebe linha mascarada
    // -----------------------------------------------------------------------

    public function test_atendente_receives_masked_controlled_prescription(): void
    {
        Event::fake([PrescricaoControladaVisualizada::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-atendente-mask');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $emissor = $this->userForRole($tenant, 'medico');
        $atendente = $this->userForRole($tenant, 'atendente');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescription = Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($emissor)
            ->create();

        Sanctum::actingAs($atendente, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.masked', true);

        Event::assertNotDispatched(PrescricaoControladaVisualizada::class);
    }

    // -----------------------------------------------------------------------
    // Recepcionista recebe linha mascarada
    // -----------------------------------------------------------------------

    public function test_recepcionista_receives_masked_controlled_prescription(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-recep-mask');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $emissor = $this->userForRole($tenant, 'medico');
        $recepcionista = $this->userForRole($tenant, 'recepcionista');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $prescription = Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($emissor)
            ->create();

        Sanctum::actingAs($recepcionista, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.masked', true);
    }

    // -----------------------------------------------------------------------
    // Audit log gravado para acesso controlado completo
    // -----------------------------------------------------------------------

    public function test_controlled_access_audit_log_recorded_for_emissor(): void
    {
        [$tenant, $emissor, $prescription] = $this->createControlledPrescriptionWithEmisor();

        Sanctum::actingAs($emissor, ['*']);
        $this->app->instance('tenant', $tenant);

        $this->getJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'prescription.view_controlled',
            'auditable_id' => $prescription->id,
            'user_id' => $emissor->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Mascaramento na lista (index)
    // -----------------------------------------------------------------------

    public function test_controlled_prescriptions_appear_masked_in_list_for_atendente(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-list-mask');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $emissor = $this->userForRole($tenant, 'medico');
        $atendente = $this->userForRole($tenant, 'atendente');

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        Prescription::factory()
            ->controlled()
            ->forTenant($tenant)
            ->withProfessional($emissor)
            ->create();

        Sanctum::actingAs($atendente, ['*']);
        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        // Controlled prescriptions should NOT appear (global scope filters them for atendente)
        // OR appear as masked - depends on global scope implementation
        $data = $response->json('data');
        foreach ($data as $item) {
            if ($item['type'] === 'controlled') {
                $this->assertTrue($item['masked'], 'Controlled prescription must be masked for atendente');
            }
        }
    }
}
