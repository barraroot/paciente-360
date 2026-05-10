<?php

namespace Tests\Feature\Fase0\Users;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests do "Last Admin Guard" (T242 — FR-029).
 *
 * Garante que o sistema nunca permite deixar um tenant sem nenhum
 * admin-clinica ativo — nem via delete nem via change-role.
 */
class LastAdminGuardTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usersUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/users";
    }

    /**
     * Monta tenant com roles e dois tipos de admin configuráveis.
     *
     * @return array{tenant: Tenant, admin1: User, admin2?: User}
     */
    private function createTenantWithAdmins(string $slug, int $adminCount = 1): array
    {
        $tenant = $this->createTenant(['slug' => $slug]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        Role::firstOrCreate(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $admin1 = $this->createUserForTenant($tenant, ['status' => 'active']);
        $admin1->assignRole('admin-clinica');

        $result = ['tenant' => $tenant, 'admin1' => $admin1];

        if ($adminCount >= 2) {
            $admin2 = $this->createUserForTenant($tenant, ['status' => 'active']);
            $admin2->assignRole('admin-clinica');
            $result['admin2'] = $admin2;
        }

        return $result;
    }

    /** @test */
    public function test_cannot_delete_last_admin_clinica(): void
    {
        ['tenant' => $tenant, 'admin1' => $admin] = $this->createTenantWithAdmins('clinica-last-del');
        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->deleteJson("{$this->usersUrl('clinica-last-del')}/{$admin->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'last_admin_clinica');
    }

    /** @test */
    public function test_can_delete_admin_when_two_exist(): void
    {
        ['tenant' => $tenant, 'admin1' => $admin1, 'admin2' => $admin2]
            = $this->createTenantWithAdmins('clinica-two-admins', 2);

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin1)
            ->deleteJson("{$this->usersUrl('clinica-two-admins')}/{$admin2->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('users', [
            'id' => $admin2->id,
            'status' => 'disabled',
        ]);
    }

    /** @test */
    public function test_cannot_change_last_admin_role(): void
    {
        ['tenant' => $tenant, 'admin1' => $admin] = $this->createTenantWithAdmins('clinica-last-role');
        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->patchJson(
                "{$this->usersUrl('clinica-last-role')}/{$admin->id}",
                ['roles' => ['medico']]
            );

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'last_admin_clinica');
    }

    /** @test */
    public function test_self_admin_cannot_remove_self_role(): void
    {
        ['tenant' => $tenant, 'admin1' => $admin] = $this->createTenantWithAdmins('clinica-self-role');
        $this->app->instance('tenant', $tenant);

        // Admin tenta remover sua própria role admin-clinica quando é o único.
        $response = $this->actingAs($admin)
            ->patchJson(
                "{$this->usersUrl('clinica-self-role')}/{$admin->id}",
                ['roles' => ['medico']]
            );

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'last_admin_clinica');
    }
}
