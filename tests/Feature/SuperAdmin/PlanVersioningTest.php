<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Events\PlanoCriado;
use App\Domain\SuperAdmin\Events\PlanoEditado;
use App\Domain\SuperAdmin\Models\PlanVersion;
use App\Domain\SuperAdmin\Models\TenantPlanBinding;
use App\Domain\SuperAdmin\Services\PlanVersioningService;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T123 (Fase 8 — Lote B US-12.2)** — AC-12.2.2 / Q12.2.2 (snapshot versioning).
 *
 * Valida que:
 *   1. Primeira chamada a `createVersion()` em plano sem versão emite `PlanoCriado`.
 *   2. Segunda chamada cria versão 2 + fecha versão 1 + emite `PlanoEditado`.
 *   3. Tenants existentes vinculados à v1 permanecem inalterados.
 *   4. `activeVersionFor()` retorna a versão vigente.
 */
class PlanVersioningTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_first_version_emits_plano_criado(): void
    {
        Event::fake([PlanoCriado::class]);

        [, $admin] = $this->tenantAndUserForRole('clinica-pv-first', 'admin-clinica');

        $plan = Plan::factory()->create();

        /** @var PlanVersioningService $svc */
        $svc = app(PlanVersioningService::class);
        $v1 = $svc->createVersion($plan, [
            'name' => 'Básico',
            'daily_campaign_limit' => 200,
            'api_rate_limit_per_minute' => 100,
            'webhook_max_endpoints' => 5,
        ], createdBy: $admin);

        $this->assertSame(1, $v1->version);
        $this->assertNull($v1->valid_to);
        $this->assertTrue($v1->isActive());

        Event::assertDispatched(PlanoCriado::class);
    }

    public function test_second_call_creates_v2_closes_v1_and_emits_plano_editado(): void
    {
        Event::fake([PlanoCriado::class, PlanoEditado::class]);

        [, $admin] = $this->tenantAndUserForRole('clinica-pv-edit', 'admin-clinica');
        $plan = Plan::factory()->create();

        /** @var PlanVersioningService $svc */
        $svc = app(PlanVersioningService::class);

        $v1 = $svc->createVersion($plan, ['name' => 'Básico v1'], createdBy: $admin);
        $v2 = $svc->createVersion($plan, ['name' => 'Básico v2', 'daily_campaign_limit' => 500], createdBy: $admin);

        $v1->refresh();

        $this->assertSame(2, $v2->version);
        $this->assertNotNull($v1->valid_to, 'V1 deve ter sido fechada com valid_to.');
        $this->assertNull($v2->valid_to);

        // Apenas 1 versão ativa por PARTIAL UNIQUE.
        $this->assertSame(1, PlanVersion::query()->forPlan($plan->id)->active()->count());

        Event::assertDispatchedTimes(PlanoCriado::class, 1); // Apenas na primeira
        Event::assertDispatchedTimes(PlanoEditado::class, 1);
    }

    public function test_existing_tenant_remains_on_old_version_after_edit(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-pv-tenant-pin', 'admin-clinica');

        // Setup — tenant vinculado a v1 via factory direta (simula seed inicial).
        $plan = Plan::factory()->create();

        /** @var PlanVersioningService $svc */
        $svc = app(PlanVersioningService::class);
        $v1 = $svc->createVersion($plan, ['name' => 'Pro v1'], createdBy: $admin);

        // Cria binding manual ligando tenant→v1.
        TenantPlanBinding::factory()->state([
            'tenant_id' => $tenant->id,
            'plan_version_id' => $v1->id,
        ])->active()->create();

        // Admin edita o plano — cria v2.
        $svc->createVersion($plan, ['name' => 'Pro v2'], createdBy: $admin);

        // Tenant continua na v1.
        $binding = TenantPlanBinding::query()
            ->forTenant($tenant->id)
            ->active()
            ->first();

        $this->assertNotNull($binding);
        $this->assertSame($v1->id, $binding->plan_version_id, 'Tenant existente deve permanecer em v1 (Q12.2.2).');
    }

    public function test_active_version_for_returns_current(): void
    {
        [, $admin] = $this->tenantAndUserForRole('clinica-pv-active', 'admin-clinica');

        $plan = Plan::factory()->create();

        /** @var PlanVersioningService $svc */
        $svc = app(PlanVersioningService::class);

        $v1 = $svc->createVersion($plan, ['name' => 'v1'], createdBy: $admin);
        $v2 = $svc->createVersion($plan, ['name' => 'v2'], createdBy: $admin);

        $active = $svc->activeVersionFor($plan);

        $this->assertNotNull($active);
        $this->assertSame($v2->id, $active->id);
        $this->assertSame(2, $active->version);
    }
}
