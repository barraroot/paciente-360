<?php

namespace Tests\Feature\Fase0\Auth;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests para Logout e endpoint /me (T105).
 *
 * @see specs/001-fundacao-multitenant/tasks.md — T105
 */
class LogoutMeTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    /** @test */
    public function test_me_returns_user_with_permissions_and_tenant(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-me', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, ['status' => 'active']);

        // Roles no team mode do Spatie são escopadas por tenant_id.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        $role = Role::create(['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user->assignRole($role);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user);

        $response = $this->getJson('http://clinica-me.lvh.me/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'status',
                'roles',
                'permissions',
                'last_login_at',
                'created_at',
                'tenant' => ['id', 'slug', 'name', 'status'],
            ],
        ]);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.tenant.slug', 'clinica-me');
    }

    /** @test */
    public function test_logout_returns_204_and_audits(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-logout', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, ['status' => 'active']);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user);

        $response = $this->postJson('http://clinica-logout.lvh.me/api/v1/auth/logout');

        $response->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.logout.succeeded',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function test_me_returns_401_when_unauthenticated(): void
    {
        $this->createTenant(['slug' => 'clinica-unauth', 'status' => 'active']);

        $response = $this->getJson('http://clinica-unauth.lvh.me/api/v1/auth/me');

        $response->assertUnauthorized();
    }
}
