<?php

namespace Tests\Unit\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Domain\Prescription\Report\ControlledPrescriptionMaskingService;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T042 — Masking service em isolamento.
 */
class ControlledPrescriptionMaskingServiceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private ControlledPrescriptionMaskingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ControlledPrescriptionMaskingService;
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_common_prescription_is_never_masked(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-mask-common', 'medico');

        $prescription = new Prescription;
        $prescription->type = PrescriptionType::Common;
        $prescription->professional_id = 999;

        $result = $this->service->mask($prescription, $medico);

        $this->assertFalse($result['masked']);
    }

    public function test_controlled_prescription_masked_for_non_emissor_medico(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-mask-nonemissor');

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $emissor = $this->userForRole($tenant, 'medico');
        $otherMedico = $this->userForRole($tenant, 'medico');

        $registrar->setPermissionsTeamId(null);

        $prescription = new Prescription;
        $prescription->type = PrescriptionType::Controlled;
        $prescription->professional_id = $emissor->id; // emissor is different user

        $result = $this->service->mask($prescription, $otherMedico);

        $this->assertTrue($result['masked']);
        $this->assertSame('controlled_access_restricted', $result['masked_reason']);
        $this->assertArrayNotHasKey('notes', $result);
    }

    public function test_controlled_prescription_not_masked_for_emissor(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-mask-emissor', 'medico');

        $prescription = new Prescription;
        $prescription->type = PrescriptionType::Controlled;
        $prescription->professional_id = $medico->id; // same as viewer

        $result = $this->service->mask($prescription, $medico);

        $this->assertFalse($result['masked']);
    }

    public function test_controlled_prescription_not_masked_for_admin_clinica(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-mask-admin', 'admin-clinica');

        $prescription = new Prescription;
        $prescription->type = PrescriptionType::Controlled;
        $prescription->professional_id = 9999; // different emissor

        $result = $this->service->mask($prescription, $admin);

        $this->assertFalse($result['masked']);
    }

    public function test_masked_result_includes_safe_fields(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-mask-safefields');

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $atendente = $this->userForRole($tenant, 'atendente');
        $registrar->setPermissionsTeamId(null);

        $prescription = new Prescription;
        $prescription->type = PrescriptionType::Controlled;
        $prescription->professional_id = 9999;
        $prescription->id = 1;

        $result = $this->service->mask($prescription, $atendente);

        $this->assertTrue($result['masked']);
        $this->assertSame('controlled_access_restricted', $result['masked_reason']);
        // Must include type info for operational coordination
        $this->assertArrayHasKey('type', $result);
    }
}
