<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Events\PlanoAlteradoPeloSuperAdmin;
use App\Domain\SuperAdmin\Models\PlanVersion;
use App\Domain\SuperAdmin\Models\TenantPlanBinding;
use App\Domain\SuperAdmin\Services\PlanVersioningService;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T124 (Fase 8 — Lote B US-12.2)** — AC-12.2.3 (proration Stripe via Cashier — stub).
 *
 * Valida:
 *   1. `migrateTenantToPlanVersion()` fecha binding atual + cria novo + emite evento.
 *   2. Motivo < 10 chars dispara InvalidArgumentException.
 *   3. `tenants.plan_id` é atualizado para o novo plano (compat com Fase 0).
 *   4. Histórico fica preservado — bindings antigas continuam na tabela com effective_to.
 */
class PlanChangeProrationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_migration_creates_new_binding_and_closes_previous(): void
    {
        Event::fake([PlanoAlteradoPeloSuperAdmin::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-mig-basic', 'admin-clinica');

        $basicPlan = Plan::factory()->create(['code' => 'basico-test', 'name' => 'Básico']);
        $proPlan = Plan::factory()->create(['code' => 'pro-test', 'name' => 'Pro']);

        /** @var PlanVersioningService $svc */
        $svc = app(PlanVersioningService::class);
        $basicV1 = $svc->createVersion($basicPlan, ['name' => 'Básico'], createdBy: $admin);
        $proV1 = $svc->createVersion($proPlan, ['name' => 'Pro'], createdBy: $admin);

        // Vincula tenant ao plano Básico inicialmente.
        TenantPlanBinding::factory()->state([
            'tenant_id' => $tenant->id,
            'plan_version_id' => $basicV1->id,
        ])->active()->create();

        // Super Admin migra tenant para Pro.
        $newBinding = $svc->migrateTenantToPlanVersion(
            tenant: $tenant,
            newPlanVersion: $proV1,
            changedBy: $admin,
            reason: 'Upgrade comercial fechado em 2026-05-22',
        );

        // Novo binding ativo.
        $this->assertSame($proV1->id, $newBinding->plan_version_id);
        $this->assertNull($newBinding->effective_to);
        $this->assertSame($admin->id, $newBinding->changed_by_user_id);

        // Binding antigo fechado.
        $oldBinding = TenantPlanBinding::query()
            ->forTenant($tenant->id)
            ->where('plan_version_id', $basicV1->id)
            ->first();
        $this->assertNotNull($oldBinding);
        $this->assertNotNull($oldBinding->effective_to);

        // Apenas 1 binding ativo (PARTIAL UNIQUE).
        $this->assertSame(1, TenantPlanBinding::query()->forTenant($tenant->id)->active()->count());

        // tenants.plan_id atualizado.
        $tenant->refresh();
        $this->assertSame($proPlan->id, $tenant->plan_id);

        Event::assertDispatched(
            PlanoAlteradoPeloSuperAdmin::class,
            fn (PlanoAlteradoPeloSuperAdmin $e): bool => $e->tenantId === $tenant->id
                && $e->oldPlanVersionId === $basicV1->id
                && $e->newPlanVersionId === $proV1->id,
        );
    }

    public function test_short_reason_throws_invalid_argument(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-mig-short-reason', 'admin-clinica');
        $plan = Plan::factory()->create();
        $pv = PlanVersion::factory()->state(['plan_id' => $plan->id])->create();

        /** @var PlanVersioningService $svc */
        $svc = app(PlanVersioningService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Motivo da alteração deve ter no mínimo 10 caracteres.');

        $svc->migrateTenantToPlanVersion(
            tenant: $tenant,
            newPlanVersion: $pv,
            changedBy: $admin,
            reason: 'curto',
        );
    }
}
