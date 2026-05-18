<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T035 — AC-8.1.7: PATCH aceita apenas `notes`; tipo/items/expires_at imutáveis.
 */
class PrescriptionUpdateImmutabilityTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_emissor_can_update_notes_only(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-upd-notes', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['notes' => 'Observação atualizada pelo médico.'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'prescription.updated',
            'auditable_id' => $prescription->id,
        ]);
    }

    public function test_update_ignores_type_change_attempts(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-upd-type', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['notes' => 'updated', 'type' => 'controlled'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        // Type must remain common
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'type' => 'common',
        ]);
    }

    public function test_update_ignores_expires_at_change_attempts(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-upd-exp', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $originalExpiresAt = $prescription->expires_at->toDateString();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['notes' => 'updating', 'expires_at' => Carbon::today()->addDays(180)->toDateString()],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'expires_at' => $originalExpiresAt,
        ]);
    }

    public function test_non_emissor_cannot_update_prescription(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-upd-nonemisor', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $otherMedico = $this->createUserForTenant($tenant);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($otherMedico) // different professional
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['notes' => 'tentativa indevida'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }

    public function test_cancelled_prescription_cannot_be_updated(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-upd-cancelled', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->cancelled()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->patchJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}"),
            ['notes' => 'tentativa em cancelada'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }
}
