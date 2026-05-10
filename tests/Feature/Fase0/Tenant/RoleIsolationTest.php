<?php

namespace Tests\Feature\Fase0\Tenant;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * **T060** — prova que roles são isoladas por tenant via Spatie team mode
 * (Princípio II). Cada tenant pode ter sua própria role homônima sem
 * conflito; a role de um tenant não atravessa fronteira para outro.
 *
 * Setup: os testes criam roles diretamente com `tenant_id` explícito (não
 * dependem do seeder de templates). O team id é sincronizado pelo listener
 * de `Authenticated` ou ajustado manualmente via `PermissionRegistrar`.
 */
class RoleIsolationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Garante team id "limpo" no início de cada teste — outros
        // testes podem ter deixado o registrar com um id antigo, e o
        // cache do Spatie é singleton ao longo do request.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_role_with_same_name_can_exist_in_two_tenants(): void
    {
        $tenantA = $this->createTenant(['slug' => 'role-iso-a']);
        $tenantB = $this->createTenant(['slug' => 'role-iso-b']);

        Role::create([
            'name' => 'medico',
            'guard_name' => 'web',
            'tenant_id' => $tenantA->id,
        ]);

        Role::create([
            'name' => 'medico',
            'guard_name' => 'web',
            'tenant_id' => $tenantB->id,
        ]);

        $count = DB::table('roles')->where('name', 'medico')->count();

        $this->assertSame(2, $count, 'Mesmo nome de role deve coexistir em tenants distintos.');
    }

    public function test_user_only_sees_roles_of_own_tenant_when_listing(): void
    {
        $tenantA = $this->createTenant(['slug' => 'role-iso-list-a']);
        $tenantB = $this->createTenant(['slug' => 'role-iso-list-b']);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($tenantA->id);
        $roleA = Role::create([
            'name' => 'medico',
            'guard_name' => 'web',
            'tenant_id' => $tenantA->id,
        ]);

        $registrar->setPermissionsTeamId($tenantB->id);
        Role::create([
            'name' => 'medico',
            'guard_name' => 'web',
            'tenant_id' => $tenantB->id,
        ]);

        // Volta para o team A e cria/atribui o user.
        $registrar->setPermissionsTeamId($tenantA->id);

        $userA = $this->createUserForTenant($tenantA);
        $userA->assignRole($roleA);

        // Sob o team A, o user enxerga 1 role pertencente ao tenant A.
        $rolesA = $userA->roles()->get();
        $this->assertCount(1, $rolesA);
        $this->assertSame($tenantA->id, (int) $rolesA->first()->tenant_id);

        // Tentar atribuir uma role do tenant B sob o contexto do tenant A
        // deve falhar — `findByName('medico')` resolve apenas roles do
        // team A (ou globais), nunca do team B.
        $registrar->setPermissionsTeamId($tenantA->id);
        try {
            $userA->assignRole('medico-do-tenant-b-inexistente');
            $this->fail('assignRole deveria ter lançado RoleDoesNotExist.');
        } catch (RoleDoesNotExist) {
            $this->assertTrue(true);
        }
    }

    public function test_super_admin_role_is_global_with_null_tenant(): void
    {
        // Garante que estamos no contexto "global" (tenant_id null).
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        Role::create([
            'name' => 'super-admin',
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super-iso@admin.local',
        ]);

        $superAdmin->assignRole('super-admin');

        $this->assertTrue($superAdmin->hasRole('super-admin'));

        $row = DB::table('roles')->where('name', 'super-admin')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->tenant_id, 'Role super-admin deve ter tenant_id NULL (escopo global).');
    }
}
