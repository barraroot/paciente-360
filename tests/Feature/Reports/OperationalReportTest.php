<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T271 (Fase 8 — Lote E US-10.2)** — Operational Report E2E.
 */
class OperationalReportTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_returns_envelope_with_required_sections(): void
    {
        [$tenant, $user] = $this->tenantAndUserForRole('clinic-ops', 'admin-clinica');

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/reports/operational')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'first_response_time',
                    'resolution_time',
                    'volume_per_attendant',
                    'ai_performance',
                ],
            ]);
    }
}
