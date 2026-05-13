<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helper reutilizável para testes que precisam de tenant com roles/permissions
 * já configuradas (equivalente ao bootstrap em `PacientePermissionsTest`).
 */
trait CreatesTenantWithRoles
{
    use CreatesTenants;

    /**
     * Roda o RolesSeeder e esquece o cache do Spatie.
     */
    protected function seedRoles(): void
    {
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cria um tenant e clona todas as roles template (exceto super-admin)
     * com o `tenant_id` específico, preservando as permissions.
     */
    protected function bootstrapTenantWithRoles(string $slug): Tenant
    {
        $tenant = $this->createTenant(['slug' => $slug]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $templates = Role::query()
            ->whereNull('tenant_id')
            ->where('name', '!=', 'super-admin')
            ->with('permissions')
            ->get();

        foreach ($templates as $template) {
            $registrar->setPermissionsTeamId($tenant->id);

            $tenantRole = Role::firstOrCreate([
                'name' => $template->name,
                'guard_name' => $template->guard_name,
                'tenant_id' => $tenant->id,
            ]);

            $tenantRole->syncPermissions($template->permissions);
        }

        $registrar->setPermissionsTeamId(null);

        return $tenant;
    }

    /**
     * Cria um usuário no tenant com a role especificada e retorna-o
     * autenticado (via `actingAs`).
     */
    protected function userForRole(Tenant $tenant, string $role): User
    {
        $user = $this->createUserForTenant($tenant);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        $user->assignRole($role);

        return $user;
    }

    /**
     * Cria tenant com roles, usuário com role especificada e autentica.
     * Injeta o tenant no container para simular ResolveTenant.
     *
     * @return array{0: Tenant, 1: User}
     */
    protected function tenantAndUserForRole(string $slug, string $role): array
    {
        $tenant = $this->bootstrapTenantWithRoles($slug);
        $user = $this->userForRole($tenant, $role);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user, ['*']);

        return [$tenant, $user];
    }

    /**
     * URL base para o tenant (usa subdomínio lvh.me padrão).
     */
    protected function tenantUrl(Tenant $tenant, string $path = ''): string
    {
        $suffix = config('tenancy.subdomain_suffix', 'lvh.me');

        return "http://{$tenant->slug}.{$suffix}/api/v1{$path}";
    }
}
