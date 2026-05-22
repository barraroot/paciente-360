<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Reports\Services\ClinicalReportService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T263 (Fase 8 — Lote E US-10.3)** — Q13 — Escopo por perfil.
 *
 * Service unitário: médico-só-médico vê apenas seu professional_id;
 * admin-clinica vê todo o tenant.
 */
class ExecutiveDashboardScopeByRoleTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_medico_only_sees_own_scope(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinic-scope-1', 'medico');

        $service = app(ClinicalReportService::class);
        $start = Carbon::now()->subDays(30);
        $end = Carbon::now();

        $data = $service->build($tenant, $start, $end, $medico);

        $this->assertIsArray($data);
        $this->assertSame($medico->id, $data['scoped_professional_id'] ?? null);
    }

    public function test_admin_clinica_sees_tenant_scope(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinic-scope-2', 'admin-clinica');

        $service = app(ClinicalReportService::class);
        $data = $service->build($tenant, Carbon::now()->subDays(30), Carbon::now(), $admin);

        $this->assertNull($data['scoped_professional_id'] ?? null);
    }
}
