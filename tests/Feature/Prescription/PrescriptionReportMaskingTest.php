<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T152 — AC-8.4.2 — Mascaramento de receitas controladas no relatório.
 *
 * Atendente / Recepcionista / médico não-emissor veem linha mascarada.
 * Médico emissor e Admin Clínica com view_controlled veem completo.
 *
 * Nota: controlled: (expires_at - issued_at) = 30 (Portaria 344/98).
 */
class PrescriptionReportMaskingTest extends TestCase
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
     * Cria receita controlada dentro da janela de 30d.
     * issued = hoje - 20d, expires = hoje + 10d.
     *
     * @return array<string, mixed>
     */
    private function controlledAttrs(int $tenantId, int $patientId, int $professionalId): array
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
            'expires_at' => $issuedAt->copy()->addDays(30), // +10d from today
            'alert_disabled' => false,
        ];
    }

    public function test_atendente_sees_controlled_prescription_masked_in_report(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('mask-atendente');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $medico = $this->userForRole($tenant, 'medico');
        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create($this->controlledAttrs($tenant->id, $patient->id, $medico->id));

        $atendente = $this->userForRole($tenant, 'atendente');
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($atendente, ['*']);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.masked'));
        $this->assertEquals('controlled_access_restricted', $response->json('data.0.masked_reason'));
        $this->assertArrayNotHasKey('notes', $response->json('data.0'));
    }

    public function test_medico_non_emissor_sees_controlled_prescription_masked(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('mask-non-emissor');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $emissor = $this->userForRole($tenant, 'medico');
        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create($this->controlledAttrs($tenant->id, $patient->id, $emissor->id));

        $otherMedico = $this->userForRole($tenant, 'medico');
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($otherMedico, ['*']);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.masked'));
    }

    public function test_emissor_sees_own_controlled_prescription_unmasked(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('mask-emissor');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $medico = $this->userForRole($tenant, 'medico');
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($medico, ['*']);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create($this->controlledAttrs($tenant->id, $patient->id, $medico->id));

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.masked'));
    }

    public function test_admin_clinica_with_view_controlled_sees_unmasked(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('mask-admin');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $medico = $this->userForRole($tenant, 'medico');
        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create($this->controlledAttrs($tenant->id, $patient->id, $medico->id));

        $admin = $this->userForRole($tenant, 'admin-clinica');
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.masked'));
    }
}
