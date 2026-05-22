<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Reports\Models\MetricAggregation;
use App\Domain\Reports\Services\ExecutiveDashboardService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T264 (Fase 8 — Lote E US-10.1)** — R-8-5 — Alerta de agregação stale.
 *
 * Quando a última agregação está com lag > 2h o envelope retorna
 * `aggregation_lag_seconds` adequado para o front renderizar banner.
 */
class HourlyAggregationStaleAlertTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_stale_aggregation_exposes_lag_in_envelope(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinic-stale', 'admin-clinica');

        // Cria 1 aggregation com computed_at antigo (3h atrás).
        MetricAggregation::query()->create([
            'tenant_id' => $tenant->id,
            'metric_name' => 'leads_by_channel',
            'period' => 'day',
            'period_start' => Carbon::now()->startOfDay(),
            'period_end' => Carbon::now()->endOfDay(),
            'dimensions' => [],
            'value_numeric' => 0,
            'computed_at' => Carbon::now()->subHours(3),
        ]);

        $service = app(ExecutiveDashboardService::class);
        $data = $service->getKpis($tenant, Carbon::now()->subDays(7), Carbon::now(), $admin);

        $this->assertArrayHasKey('aggregation_lag_seconds', $data);
        $this->assertGreaterThan(7200, $data['aggregation_lag_seconds']);
    }
}
