<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Suporte ao preset "Hoje" (`1d`) no
 * `ExecutiveDashboardController::resolvePeriod()`.
 *
 * Diferente do preset `24h` (últimas 24h a partir de agora), o preset `1d`
 * representa o dia corrente a partir de `00:00` (início do dia local), e o
 * período anterior dos deltas é o dia anterior completo.
 */
class ExecutiveDashboardWindow1dTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_executive_endpoint_supports_1d_preset_starting_at_midnight(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-a', 'admin-clinica');

        $beforeRequest = Carbon::now();

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive?preset=1d');

        $response->assertOk();

        $payload = $response->json('data');
        $this->assertArrayHasKey('period', $payload);

        $start = Carbon::parse($payload['period']['start']);
        $end = Carbon::parse($payload['period']['end']);

        // start = início do dia corrente (00:00), não now()-24h.
        $expectedStart = $beforeRequest->copy()->startOfDay();
        $this->assertLessThanOrEqual(
            5,
            abs($start->diffInSeconds($expectedStart)),
            "Expected period.start to be the start of the current day, got {$start->toIso8601String()}."
        );
        $this->assertSame(0, $start->hour);
        $this->assertSame(0, $start->minute);

        // end ≈ agora.
        $this->assertLessThanOrEqual(5, abs($end->diffInSeconds($beforeRequest)));
    }

    public function test_1d_preset_returns_delta_percent_shape_for_kpis(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-a', 'admin-clinica');

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive?preset=1d');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'leads_by_channel' => ['value', 'delta_percent'],
                    'conversion_rate' => ['value', 'delta_percent'],
                    'no_show_rate' => ['value', 'delta_percent'],
                    'estimated_revenue' => ['value', 'delta_percent'],
                ],
            ]);
    }
}
