<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G1 (Spec 012)** — Isolamento multi-tenant em /professionals (Princípio II).
 */
final class ProfessionalsCrossTenantTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Professional $professionalB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenantA] = $this->tenantAndUserForRole('cross-tenant-a', 'admin-clinica');
        $this->tenantB = $this->bootstrapTenantWithRoles('cross-tenant-b');

        $userB = $this->createUserForTenant($this->tenantB);
        $this->professionalB = Professional::factory()
            ->forTenant($this->tenantB)
            ->create(['user_id' => $userB->id]);

        // Reautentica como tenant A (bootstrapTenantWithRoles mexe no contexto).
        [$this->tenantA] = $this->tenantAndUserForRole('cross-tenant-a-2', 'admin-clinica');
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenantA->slug];
    }

    public function test_list_does_not_leak_other_tenants_professionals(): void
    {
        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenantA, '/professionals?is_active=all')
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains(
            $this->professionalB->id,
            $ids,
            'Professional do tenant B vazou na listagem do tenant A (violação Princípio II).'
        );
    }

    public function test_show_other_tenants_professional_returns_404(): void
    {
        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenantA, "/professionals/{$this->professionalB->id}")
        );

        $response->assertNotFound();
    }

    public function test_update_other_tenants_professional_returns_404(): void
    {
        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenantA, "/professionals/{$this->professionalB->id}"),
            ['name' => 'Tentativa Cross-Tenant']
        );

        $response->assertNotFound();
    }

    public function test_delete_other_tenants_professional_returns_404(): void
    {
        $response = $this->withHeaders($this->headers())->deleteJson(
            $this->tenantUrl($this->tenantA, "/professionals/{$this->professionalB->id}")
        );

        $response->assertNotFound();
    }
}
