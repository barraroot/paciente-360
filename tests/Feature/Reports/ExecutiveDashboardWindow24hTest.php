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
 * **T004 (Fase 11 — Spec 011 / Gate G1)** — Suporte ao preset '24h' no
 * `ExecutiveDashboardController::resolvePeriod()`.
 *
 * Q9 da Fase 8 estabelece que janelas ≤ 24h usam queries live; este teste
 * valida que o preset `24h` (introduzido nesta spec) é aceito e produz
 * `period.start ≈ now()-24h`.
 */
class ExecutiveDashboardWindow24hTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_executive_endpoint_supports_24h_preset(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-a', 'admin-clinica');

        $beforeRequest = Carbon::now();

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive?preset=24h');

        $response->assertOk();

        $payload = $response->json('data');
        $this->assertArrayHasKey('period', $payload);
        $this->assertArrayHasKey('start', $payload['period']);
        $this->assertArrayHasKey('end', $payload['period']);

        $start = Carbon::parse($payload['period']['start']);
        $expectedStart = $beforeRequest->copy()->subHours(24);

        $diffSeconds = abs($start->diffInSeconds($expectedStart));
        $this->assertLessThanOrEqual(
            5,
            $diffSeconds,
            "Expected period.start to be within 5s of now()-24h, got {$diffSeconds}s diff."
        );
    }

    public function test_unknown_preset_falls_back_to_30d_default(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-a', 'admin-clinica');

        $beforeRequest = Carbon::now();

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/executive?preset=invalid-value');

        $response->assertOk();

        $start = Carbon::parse($response->json('data.period.start'));
        $expectedStart = $beforeRequest->copy()->subDays(30);

        $this->assertLessThanOrEqual(5, abs($start->diffInSeconds($expectedStart)));
    }
}
