<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T261 (Fase 8 — Lote E US-10.1)** — AC-10.1.4 — Drill-down.
 *
 * Valida endpoint GET /api/v1/reports/executive/drill/{metric}.
 */
class ExecutiveDashboardDrillDownTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_drill_down_returns_structured_payload(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-d', 'admin-clinica');

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive/drill/leads_by_channel?preset=30d')
            ->assertOk()
            ->assertJsonStructure(['data' => ['metric', 'period_start', 'period_end']]);
    }
}
