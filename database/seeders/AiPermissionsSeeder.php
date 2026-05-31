<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 15 (IA Matricial) — abilities da camada de IA + sync para tenants existentes.
 * Idempotente (firstOrCreate + sync). Espelha o padrão de AgendaPermissionsSeeder.
 *
 * Atribuição por role:
 *  - admin-clinica → todas (gestão completa da IA)
 *  - medico        → views (persona/knowledge/guardrail/log)
 *  - atendente     → ai.persona.view + ai.log.view (pausar/reativar usa inbox.respond)
 *  - recepcionista → ai.persona.view
 *  - financeiro    → nenhuma
 *
 * super-admin: bypass via Gate::before.
 */
class AiPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    /** @var list<string> */
    private const PERMISSIONS = [
        'ai.persona.view',
        'ai.persona.manage',
        'ai.knowledge.view',
        'ai.knowledge.manage',
        'ai.guardrail.view',
        'ai.guardrail.manage',
        'ai.matrix.manage',
        'ai.log.view',
        // Feature 017 (US2) — Contexto de Trabalho.
        'ai.work-context.view',
        'ai.work-context.manage',
        // Feature 018 (US6, T036, FR-044) — chat de teste de Persona em sandbox.
        'ai.persona.test',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'admin-clinica' => [
            'ai.persona.view', 'ai.persona.manage',
            'ai.knowledge.view', 'ai.knowledge.manage',
            'ai.guardrail.view', 'ai.guardrail.manage',
            'ai.matrix.manage', 'ai.log.view',
            'ai.work-context.view', 'ai.work-context.manage',
            'ai.persona.test',
        ],
        'medico' => [
            'ai.persona.view', 'ai.knowledge.view', 'ai.guardrail.view', 'ai.log.view',
            'ai.work-context.view',
        ],
        'atendente' => [
            'ai.persona.view', 'ai.log.view',
        ],
        'recepcionista' => [
            'ai.persona.view',
        ],
    ];

    public function run(): void
    {
        // 1) Templates globais (tenant_id = NULL).
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => self::GUARD,
                'tenant_id' => null,
            ]);
        }

        $this->assignToRoles(null);

        // 2) Sincroniza para tenants existentes.
        Tenant::query()->each(function (Tenant $tenant): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (self::PERMISSIONS as $name) {
                Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => self::GUARD,
                    'tenant_id' => $tenant->id,
                ]);
            }

            $this->assignToRoles($tenant->id);
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignToRoles(?int $tenantId): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permNames) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $role) {
                continue;
            }

            $permissions = Permission::whereIn('name', $permNames)
                ->where('guard_name', self::GUARD)
                ->where('tenant_id', $tenantId)
                ->get();

            $existingIds = $role->permissions()->pluck('permissions.id')->toArray();
            $newIds = $permissions->pluck('id')->toArray();
            $merged = array_unique(array_merge($existingIds, $newIds));
            $role->permissions()->sync($merged);
        }
    }
}
