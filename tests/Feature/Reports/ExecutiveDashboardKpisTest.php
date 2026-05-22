<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T260 (Fase 8 — Lote E US-10.1)** — AC-10.1.1 / AC-10.1.2.
 *
 * Valida que GET /api/v1/reports/executive retorna 5 KPI cards com chaves
 * estáveis quando o user tem `report.view`, e 403 quando não tem.
 */
class ExecutiveDashboardKpisTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_clinica_obtains_kpis_envelope(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-a', 'admin-clinica');

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive?preset=30d');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'leads_by_channel' => ['value', 'delta_percent'],
                'conversion_rate' => ['value', 'delta_percent'],
                'no_show_rate' => ['value', 'delta_percent'],
                'estimated_revenue' => ['value', 'delta_percent'],
                'response_time_first_p95' => ['value'],
            ],
        ]);
    }

    public function test_user_without_report_view_gets_403(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-b', 'atendente');

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive')
            ->assertForbidden();
    }
}
