<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Models\ConsentRecord;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T038 (Fase 8 — Lote A US-13.1)** — Gate Multi-tenancy (Gate 2 da Constituição).
 *
 * Princípio II NON-NEGOTIABLE: tenant A NUNCA pode ler/escrever consentimentos
 * de tenant B, mesmo via manipulação de parâmetro de URL ou IDs diretos.
 *
 * Validações:
 *   1. Tenant A não vê consents do tenant B na listagem (global scope).
 *   2. Tenant A não consegue ler consent específico do tenant B (404 — Q AC-8.4.9 pattern).
 *   3. Criação de consent referenciando paciente de outro tenant é rejeitada (validation).
 */
class CrossTenantConsentTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_tenant_a_cannot_list_tenant_b_consents(): void
    {
        // Tenant A com seu próprio consent.
        [$tenantA] = $this->tenantAndUserForRole('clinica-a', 'admin-clinica');
        $patientA = Paciente::factory()->state(['tenant_id' => $tenantA->id])->create();
        ConsentRecord::factory()
            ->marketing()
            ->state(['tenant_id' => $tenantA->id, 'patient_id' => $patientA->id])
            ->create();

        // Tenant B em separado (sem auth).
        $tenantB = $this->bootstrapTenantWithRoles('clinica-b');
        // 3 consents do tenant B em pacientes distintos (PARTIAL UNIQUE por
        // patient_id+finalidade impede grants duplicados no mesmo paciente).
        Paciente::factory()->count(3)->state(['tenant_id' => $tenantB->id])->create()
            ->each(fn (Paciente $patientB) => ConsentRecord::factory()
                ->marketing()
                ->state(['tenant_id' => $tenantB->id, 'patient_id' => $patientB->id])
                ->create());

        // Auth como tenant A — lista deve mostrar APENAS 1 consent (do tenant A).
        $response = $this->getJson(
            $this->tenantUrl($tenantA, '/privacy/consents'),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame($tenantA->id, $response->json('data.0.patient_id') === $patientA->id ? $tenantA->id : null);
    }

    public function test_tenant_a_gets_404_when_reading_tenant_b_consent_by_id(): void
    {
        // Tenant A logado.
        [$tenantA] = $this->tenantAndUserForRole('clinica-a-show', 'admin-clinica');

        // Consent existe no tenant B.
        $tenantB = $this->bootstrapTenantWithRoles('clinica-b-show');
        $patientB = Paciente::factory()->state(['tenant_id' => $tenantB->id])->create();
        $consentB = ConsentRecord::factory()
            ->marketing()
            ->state(['tenant_id' => $tenantB->id, 'patient_id' => $patientB->id])
            ->create();

        // Tenant A tenta acessar pelo ID — TenantScope esconde, route model binding 404.
        $response = $this->getJson(
            $this->tenantUrl($tenantA, "/privacy/consents/{$consentB->id}"),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertNotFound();
    }

    public function test_creating_consent_for_other_tenant_patient_is_rejected(): void
    {
        [$tenantA] = $this->tenantAndUserForRole('clinica-a-create', 'admin-clinica');

        $tenantB = $this->bootstrapTenantWithRoles('clinica-b-create');
        $patientB = Paciente::factory()->state(['tenant_id' => $tenantB->id])->create();

        // Tenant A tenta criar consent referenciando paciente do tenant B.
        $response = $this->postJson(
            $this->tenantUrl($tenantA, '/privacy/consents'),
            [
                'patient_id' => $patientB->id, // paciente de outro tenant
                'channel' => 'manual',
                'finalidade' => 'marketing',
                'state' => 'granted',
            ],
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        // exists:pacientes,id é filtrado por TenantScope → validation fail 422.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_id');
    }
}
