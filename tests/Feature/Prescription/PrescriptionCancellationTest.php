<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescricaoCancelada;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T036 — AC-8.1.8:
 *  - Cancelamento exige category + texto.
 *  - É irreversível.
 *  - Emite PrescricaoCancelada.
 */
class PrescriptionCancellationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_emissor_can_cancel_prescription(): void
    {
        Event::fake([PrescricaoCancelada::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-ok', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'erro_emissao',
                'cancellation_reason' => 'Medicamento trocado por engano.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => 'cancelled',
            'cancellation_reason_category' => 'erro_emissao',
        ]);

        Event::assertDispatched(PrescricaoCancelada::class, function (PrescricaoCancelada $event) use ($prescription): bool {
            return $event->prescriptionId === $prescription->id;
        });
    }

    public function test_admin_clinica_can_cancel_prescription(): void
    {
        Event::fake([PrescricaoCancelada::class]);

        [$tenant] = $this->tenantAndUserForRole('clinica-cancel-admin', 'admin-clinica');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $medico = $this->createUserForTenant($tenant);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'substituicao',
                'cancellation_reason' => 'Nova receita emitida com dosagem correta.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        Event::assertDispatched(PrescricaoCancelada::class);
    }

    public function test_cancellation_requires_category_and_reason(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-missing', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['cancellation_reason_category', 'cancellation_reason']);
    }

    public function test_cancellation_requires_valid_category(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-cat', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'motivo_invalido',
                'cancellation_reason' => 'Texto válido.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['cancellation_reason_category']);
    }

    public function test_cancellation_reason_cannot_exceed_500_chars(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-long', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'outro',
                'cancellation_reason' => str_repeat('x', 501),
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['cancellation_reason']);
    }

    public function test_cancellation_is_irreversible(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-irrev', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        // First cancel
        $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'desistencia_paciente',
                'cancellation_reason' => 'Paciente desistiu.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        // Second cancel attempt — idempotent (returns 200, no-op)
        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'outro',
                'cancellation_reason' => 'Tentativa de recancelar.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancellation_records_audit_log(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-cancel-auditlog', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($medico)
            ->create();

        $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'erro_emissao',
                'cancellation_reason' => 'Erro de dosagem.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'prescription.cancelled',
            'auditable_id' => $prescription->id,
        ]);
    }

    public function test_non_emissor_non_admin_cannot_cancel(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-cancel-notemisor', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $otherMedico = $this->createUserForTenant($tenant);

        $prescription = Prescription::factory()
            ->common()
            ->forTenant($tenant)
            ->withProfessional($otherMedico)
            ->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/cancel"),
            [
                'cancellation_reason_category' => 'outro',
                'cancellation_reason' => 'Cancelamento indevido.',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }
}
