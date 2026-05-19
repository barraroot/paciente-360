<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'prescription.create',
        'prescription.view',
        'prescription.update',
        'prescription.cancel',
        'prescription.view_controlled',
        'prescription.export',
        'prescription_alert.configure',
        // T141 — Ability exclusiva para tokens de sistema/IA.
        // NÃO atribuída a nenhuma role default — apenas via createToken('ai-system', ['prescription.ai_context']).
        'prescription.ai_context',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin-clinica' => [
            'prescription.view',
            'prescription.cancel',
            'prescription.view_controlled',
            'prescription.export',
            'prescription_alert.configure',
        ],
        'medico' => [
            'prescription.create',
            'prescription.view',
            'prescription.update',
            'prescription.cancel',
            'prescription.view_controlled',
            'prescription.export',
            'prescription_alert.configure',
        ],
        'atendente' => [
            'prescription.view',
        ],
        'recepcionista' => [
            'prescription.view',
        ],
    ];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $this->ensurePermissionsForTeam(null);
        $this->syncRolesForTeam(null);

        Tenant::query()->each(function (Tenant $tenant) use ($registrar): void {
            $registrar->setPermissionsTeamId($tenant->id);
            $registrar->forgetCachedPermissions();

            $this->ensurePermissionsForTeam($tenant->id);
            $this->syncRolesForTeam($tenant->id);
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', self::GUARD)
            ->delete();

        $registrar->forgetCachedPermissions();
    }

    private function ensurePermissionsForTeam(?int $tenantId): void
    {
        foreach (self::PERMISSIONS as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => self::GUARD,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    private function syncRolesForTeam(?int $tenantId): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->when($tenantId === null, fn ($query) => $query->whereNull('tenant_id'))
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->where('guard_name', self::GUARD)
                ->when($tenantId === null, fn ($query) => $query->whereNull('tenant_id'))
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->pluck('id')
                ->all();

            $existingIds = $role->permissions()->pluck('permissions.id')->all();
            $role->permissions()->sync(array_values(array_unique(array_merge($existingIds, $permissionIds))));
        }
    }
};
