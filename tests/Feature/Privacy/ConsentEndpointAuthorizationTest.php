<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Models\ConsentRecord;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T037 (Fase 8 — Lote A US-13.1)** — Cobre AC-13.1.5 e AC-13.1.6.
 *
 * Valida que o painel Admin Clínica acessa os consentimentos via endpoint
 * indexado e que perfis sem `privacy.view` recebem 403 (Princípio I —
 * menor exposição).
 */
class ConsentEndpointAuthorizationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_clinica_can_list_consents(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-list-ok', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        ConsentRecord::factory()
            ->forFinalidade(\App\Domain\Privacy\Models\ConsentFinalidade::Marketing)
            ->state([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
            ])
            ->count(3)
            ->create();

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/privacy/consents'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_atendente_without_privacy_view_gets_403(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-list-no-perm', 'atendente');
        // Atendente não tem privacy.view por padrão (RolesSeeder Fase 8).

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/privacy/consents'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }

    public function test_filtering_by_finalidade_and_state_works(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-filter', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        ConsentRecord::factory()
            ->marketing()
            ->granted()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->count(2)
            ->create();

        ConsentRecord::factory()
            ->pesquisa()
            ->refused()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->count(1)
            ->create();

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/privacy/consents?finalidade=marketing&state=granted'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        foreach ($response->json('data') as $row) {
            $this->assertSame('marketing', $row['finalidade']);
            $this->assertSame('granted', $row['state']);
        }
    }
}
