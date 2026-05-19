<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T123 — AC-8.3.1: GET /ai/prescriptions/{id}/context retorna payload
 * com exatamente 7 campos da allowlist (gate LGPD Princípio I).
 *
 * NUNCA deve conter: medication_name, posology, notes — mesmo para receita `common`.
 *
 * @see specs/007-gestao-receituario/spec.md Q5
 * @see specs/007-gestao-receituario/contracts/openapi.yaml (PrescriptionForAi schema)
 */
class PrescriptionAiContextEndpointTest extends TestCase
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
     * AC-8.3.1 — Payload tem exatamente os 7 campos da allowlist.
     *
     * Para receita `common` próxima do vencimento.
     */
    public function test_ai_context_endpoint_returns_exactly_seven_allowed_fields(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-ai-ctx-1', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Cria token com ability prescription.ai_context
        $token = $medico->createToken('ai-system', ['prescription.ai_context'])->plainTextToken;
        Sanctum::actingAs($medico, ['prescription.ai_context']);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7), // dentro da janela D-7
                'status' => 'active',
            ]);

        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $data = $response->json('data');

        // Gate LGPD — exatamente 7 campos
        $this->assertCount(7, $data, 'Payload deve ter exatamente 7 campos.');

        $allowedKeys = [
            'prescription_id',
            'patient_id',
            'professional_id',
            'professional_name',
            'days_until_expiry',
            'prescription_type',
            'default_appointment_type_id',
        ];

        foreach ($allowedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Chave '{$key}' deve estar no payload.");
        }

        // Campos PROIBIDOS — mesmo para receita common
        $forbiddenKeys = ['medication_name', 'posology', 'notes', 'concentration', 'pharmaceutical_form'];
        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $data, "Chave '{$key}' NÃO deve estar no payload (gate LGPD).");
        }
    }

    /**
     * AC-8.3.1 — prescription_type correto no payload.
     */
    public function test_ai_context_prescription_type_reflects_actual_type(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-ai-ctx-2', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($medico, ['prescription.ai_context']);

        $prescription = Prescription::factory()
            ->special()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(15),
                'expires_at' => Carbon::today()->addDays(15),
                'status' => 'active',
            ]);

        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertSame('special', $response->json('data.prescription_type'));
    }

    /**
     * AC-8.3.1 — days_until_expiry é signed (negativo se vencida na janela).
     */
    public function test_ai_context_days_until_expiry_is_correct(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-ai-ctx-3', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($medico, ['prescription.ai_context']);

        $expiresAt = Carbon::today()->addDays(7);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(83),
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertSame(7, $response->json('data.days_until_expiry'));
    }

    /**
     * Gate: receita fora da janela de renovação retorna 422.
     */
    public function test_ai_context_returns_422_when_outside_renewal_window(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-ai-ctx-4', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($medico, ['prescription.ai_context']);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today(),
                'expires_at' => Carbon::today()->addDays(90), // fora da janela D-30
                'status' => 'active',
            ]);

        $this->app->instance('tenant', $tenant);

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/ai/prescriptions/{$prescription->id}/context"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame('prescription_outside_ai_context_window', $response->json('error'));
    }
}
