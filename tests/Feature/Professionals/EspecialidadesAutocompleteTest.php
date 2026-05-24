<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G7 (Spec 012)** — Autocomplete de especialidade (Q1 / FR-001 / R7).
 *
 * DISTINCT por tenant, ILIKE case-insensitive, isolamento multi-tenant
 * (Princípio II), e exclusão de profissionais soft-deletados.
 */
final class EspecialidadesAutocompleteTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant] = $this->tenantAndUserForRole('autocomplete-admin', 'admin-clinica');
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_returns_distinct_especialidades_from_tenant(): void
    {
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Cardiologia']);
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Cardiologia']);
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Pediatria']);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals/especialidades')
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEqualsCanonicalizing(['Cardiologia', 'Pediatria'], $data);
    }

    public function test_filter_by_query_is_case_insensitive(): void
    {
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Cardiologia']);
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Pediatria']);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals/especialidades?q=CARD')
        );

        $response->assertOk();
        $this->assertSame(['Cardiologia'], $response->json('data'));
    }

    public function test_does_not_leak_other_tenants_especialidades(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('autocomplete-b');
        Professional::factory()->forTenant($tenantB)->create(['especialidade' => 'Neurologia']);

        // Reautentica como tenant atual.
        [$this->tenant] = $this->tenantAndUserForRole('autocomplete-admin-2', 'admin-clinica');
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Cardiologia']);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals/especialidades')
        );

        $response->assertOk();
        $this->assertNotContains('Neurologia', $response->json('data'));
        $this->assertContains('Cardiologia', $response->json('data'));
    }

    public function test_excludes_soft_deleted_professionals(): void
    {
        Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Cardiologia']);
        $deleted = Professional::factory()->forTenant($this->tenant)->create(['especialidade' => 'Ortopedia']);
        $deleted->delete();

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals/especialidades')
        );

        $response->assertOk();
        $this->assertNotContains('Ortopedia', $response->json('data'));
        $this->assertContains('Cardiologia', $response->json('data'));
    }
}
