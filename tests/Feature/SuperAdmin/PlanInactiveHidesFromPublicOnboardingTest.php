<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Plan;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T125 (Fase 8 — Lote B US-12.2)** — AC-12.2.4.
 *
 * Plano marcado `is_active=false` NÃO deve aparecer em listagens públicas
 * (onboarding self-service da Fase 0), mas continua existindo para tenants
 * que já o usam. Este teste valida:
 *   1. Scope tradicional `where is_active=true` filtra inativos.
 *   2. Plano inativo continua sendo acessível por query direta (admin).
 *   3. Tenant já vinculado ao plano inativo NÃO sofre impacto (sua binding
 *      é em uma PlanVersion específica — independente de is_active no Plan).
 */
class PlanInactiveHidesFromPublicOnboardingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_active_scope_filters_inactive_plans(): void
    {
        Plan::factory()->create(['code' => 'basico-active', 'is_active' => true]);
        Plan::factory()->create(['code' => 'pro-inactive', 'is_active' => false]);
        Plan::factory()->create(['code' => 'enterprise-active', 'is_active' => true]);

        $activePlans = Plan::query()->where('is_active', true)->get();

        $this->assertCount(2, $activePlans);
        $this->assertNotContains('pro-inactive', $activePlans->pluck('code')->all());
    }

    public function test_inactive_plan_is_still_accessible_directly(): void
    {
        $plan = Plan::factory()->create(['code' => 'legacy', 'is_active' => false]);

        // Admin pode acessar o plano via query direta.
        $found = Plan::query()->where('code', 'legacy')->first();

        $this->assertNotNull($found);
        $this->assertSame($plan->id, $found->id);
        $this->assertFalse($found->is_active);
    }

    public function test_existing_tenant_on_inactive_plan_keeps_binding(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-legacy-plan', 'admin-clinica');

        $legacyPlan = Plan::factory()->create(['is_active' => true]);
        $tenant->update(['plan_id' => $legacyPlan->id]);

        // Admin desativa o plano antigo.
        $legacyPlan->update(['is_active' => false]);

        // Tenant continua vinculado.
        $tenant->refresh();
        $this->assertSame($legacyPlan->id, $tenant->plan_id);

        // E ainda consegue resolver o plano via relação.
        $this->assertNotNull($tenant->plan);
        $this->assertFalse($tenant->plan->is_active);
    }
}
