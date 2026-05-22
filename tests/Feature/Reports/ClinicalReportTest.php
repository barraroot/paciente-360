<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T276 (Fase 8 — Lote E US-10.3)** — Clinical Report E2E.
 */
class ClinicalReportTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_clinica_obtains_tenant_scope_envelope(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinic-clin-a', 'admin-clinica');

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/clinical')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'occupancy_by_professional',
                    'top_procedure_types',
                ],
            ]);
    }
}
