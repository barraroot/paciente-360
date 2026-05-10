<?php

namespace Tests\Feature\Fase0\Users;

use App\Jobs\Email\SendInvitationEmailJob;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests de limite de usuários por plano (T241 — FR-027).
 *
 * Verifica que o limite `plan.max_users` é respeitado ao criar convites,
 * e que usuários desativados e convites revogados não contam para o limite.
 */
class UserLimitTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function invitationsUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/users/invitations";
    }

    /**
     * Cria tenant com plano de max_users limitado e um admin-clinica.
     *
     * @return array{tenant: Tenant, admin: User, plan: Plan}
     */
    private function createTenantWithPlan(string $slug, int $maxUsers): array
    {
        $plan = Plan::factory()->create(['max_users' => $maxUsers]);
        $tenant = $this->createTenant(['slug' => $slug, 'plan_id' => $plan->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        Role::firstOrCreate(
            ['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        Role::firstOrCreate(
            ['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        $admin = $this->createUserForTenant($tenant, ['status' => 'active']);
        $admin->assignRole('admin-clinica');

        return ['tenant' => $tenant, 'admin' => $admin, 'plan' => $plan];
    }

    /** @test */
    public function test_invitation_blocked_when_plan_limit_reached(): void
    {
        Bus::fake([SendInvitationEmailJob::class]);

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithPlan('clinica-limit', 3);

        // 1 admin já existe (active). Cria mais 1 active + 1 pending = 3 total.
        $this->createUserForTenant($tenant, ['status' => 'active']);

        Invitation::factory()
            ->forTenant($tenant, $admin)
            ->create(['email' => 'pending1@example.com']);

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->postJson(
                $this->invitationsUrl('clinica-limit'),
                ['email' => 'novo@example.com', 'intended_role' => 'medico']
            );

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'plan_limit_reached');
        $response->assertJsonPath('limit', 3);
    }

    /** @test */
    public function test_revoked_invitation_does_not_count(): void
    {
        Bus::fake([SendInvitationEmailJob::class]);

        // max_users=3; admin=1 ativo + 1 outro ativo = 2 ativos, 0 pending.
        // Convite revogado não entra na conta → total que conta=2 < 3 → deve passar.
        // Se contasse: 2+1=3 >= 3 → bloquearia.
        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithPlan('clinica-revoked-count', 3);

        $this->createUserForTenant($tenant, ['status' => 'active']);

        // Convite revogado NÃO deve contar para o limite.
        Invitation::factory()
            ->forTenant($tenant, $admin)
            ->revoked()
            ->create(['email' => 'revogado@example.com']);

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->postJson(
                $this->invitationsUrl('clinica-revoked-count'),
                ['email' => 'novo@example.com', 'intended_role' => 'medico']
            );

        $response->assertStatus(201);
    }

    /** @test */
    public function test_disabled_users_do_not_count(): void
    {
        Bus::fake([SendInvitationEmailJob::class]);

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithPlan('clinica-disabled-count', 3);

        // 1 admin ativo + 1 user ativo = 2 ativos.
        $this->createUserForTenant($tenant, ['status' => 'active']);
        // User disabled NÃO conta.
        $this->createUserForTenant($tenant, ['status' => 'disabled']);

        $this->app->instance('tenant', $tenant);

        // Com max=3, 2 ativos + 0 pending < 3 → deve passar.
        $response = $this->actingAs($admin)
            ->postJson(
                $this->invitationsUrl('clinica-disabled-count'),
                ['email' => 'novo@example.com', 'intended_role' => 'medico']
            );

        $response->assertStatus(201);
    }

    /** @test */
    public function test_trial_without_plan_uses_default_5(): void
    {
        Bus::fake([SendInvitationEmailJob::class]);

        // Tenant trial sem plano (plan_id = null).
        $tenant = $this->createTenant(['slug' => 'clinica-trial-limit', 'plan_id' => null, 'status' => 'trial']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        Role::firstOrCreate(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $admin = $this->createUserForTenant($tenant, ['status' => 'active']);
        $admin->assignRole('admin-clinica');

        // Cria 4 users ativos adicionais = 5 total (admin + 4).
        $this->createUserForTenant($tenant, ['status' => 'active']);
        $this->createUserForTenant($tenant, ['status' => 'active']);
        $this->createUserForTenant($tenant, ['status' => 'active']);
        $this->createUserForTenant($tenant, ['status' => 'active']);

        $this->app->instance('tenant', $tenant);

        // 6º convite → 409 (default trial limit = 5).
        $response = $this->actingAs($admin)
            ->postJson(
                $this->invitationsUrl('clinica-trial-limit'),
                ['email' => 'sexto@example.com', 'intended_role' => 'medico']
            );

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'plan_limit_reached');
        $response->assertJsonPath('limit', 5);
    }
}
