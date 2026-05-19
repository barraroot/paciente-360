<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Paciente;
use App\Support\Metrics\PrescriptionMetricsContract;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T128 — Quickstart cenário 1 passo 3: Atendente sem ability
 * `prescription.ai_context` → 403 + métrica incrementa.
 *
 * NOTA: Os testes de negação usam token escoped sem `prescription.ai_context`
 * para simular tokens de usuário comuns (sem a ability de sistema/IA).
 * `Sanctum::actingAs($user, ['*'])` concede todas as abilities ao token —
 * portanto os testes de 403 usam `Sanctum::actingAs($user, ['prescription.view'])`.
 */
class PrescriptionAiContextAuthorizationTest extends TestCase
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
     * Atendente com token sem `prescription.ai_context` → 403.
     */
    public function test_attendant_gets_403_on_ai_context_endpoint(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-auth-1');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $atendente = $this->userForRole($tenant, 'atendente');
        $medico = $this->userForRole($tenant, 'medico');
        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Token escoped sem prescription.ai_context — simula token de usuário comum
        Sanctum::actingAs($atendente, ['prescription.view']);

        $this->app->instance('tenant', $tenant);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }

    /**
     * Atendente → 403 + métrica `prescription_controlled_access_denied_total` incrementada.
     */
    public function test_denied_access_increments_metric(): void
    {
        $metricsCalled = false;
        $mockMetrics = $this->createMock(PrescriptionMetricsContract::class);
        $mockMetrics->expects($this->once())
            ->method('controlledAccessDeniedTotal')
            ->willReturnCallback(function () use (&$metricsCalled): void {
                $metricsCalled = true;
            });

        $this->app->instance(PrescriptionMetricsContract::class, $mockMetrics);

        $tenant = $this->bootstrapTenantWithRoles('clinica-auth-2');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $atendente = $this->userForRole($tenant, 'atendente');
        $medico = $this->userForRole($tenant, 'medico');
        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Token escoped sem prescription.ai_context
        Sanctum::actingAs($atendente, ['prescription.view']);

        $this->app->instance('tenant', $tenant);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $this->assertTrue($metricsCalled, 'Métrica deve ser incrementada ao negar acesso.');
    }

    /**
     * Médico com token de ability `prescription.ai_context` → acesso permitido.
     */
    public function test_token_with_ai_context_ability_gets_access(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-auth-3', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        // Token com ability exclusiva de IA
        Sanctum::actingAs($medico, ['prescription.ai_context']);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
    }
}
