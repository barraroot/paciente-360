<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T062** — Seeder de roles e permissions default (templates globais).
 *
 * Cria 6 roles e 6 permissions com `tenant_id = NULL` (templates).
 * `super-admin` é a única role de fato global; as demais são clonadas
 * por `TenantService` ao criar um tenant (mecanismo descrito em
 * `data-model.md` § 5.4).
 *
 * **Idempotente**: usa `firstOrCreate`. Reseed não duplica.
 *
 * Atribuição de permissions (data-model § 5.4):
 *  - admin-clinica: todas (6)
 *  - financeiro: view-billing, view-ai-usage, view-audit-logs (3)
 *  - medico, atendente, recepcionista: somente login (0 permissions)
 *  - super-admin: bypass via Gate (sem permissions atribuídas
 *    explicitamente — gate `before` no AppServiceProvider).
 */
class RolesSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * @var list<string>
     */
    private const ROLES = [
        'super-admin',
        'admin-clinica',
        'medico',
        'atendente',
        'recepcionista',
        'financeiro',
    ];

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        // Fase 0 — gestão da clínica
        'manage-users',
        'manage-billing',
        'manage-onboarding',
        'view-audit-logs',
        'view-billing',
        'view-ai-usage',

        // Fase 2 — CRM de Pacientes (§ 2.4 do spec)
        'paciente.view',
        'paciente.create',
        'paciente.update',
        'paciente.delete',
        'paciente.import',
        'paciente.export',
        'paciente.merge',
        'paciente.note.write',
        'paciente.note.view:geral',
        'paciente.note.view:clinica',
        'paciente.note.view:comportamental',
        'paciente.note.view:financeira',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin-clinica' => [
            // Fase 0
            'manage-users',
            'manage-billing',
            'manage-onboarding',
            'view-audit-logs',
            'view-billing',
            'view-ai-usage',
            // Fase 2 — admin clínica tem TODAS as abilities CRM.
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.delete',
            'paciente.import',
            'paciente.export',
            'paciente.merge',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:clinica',
            'paciente.note.view:comportamental',
            'paciente.note.view:financeira',
        ],
        'medico' => [
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:clinica',
            'paciente.note.view:comportamental',
        ],
        'atendente' => [
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:comportamental',
        ],
        'recepcionista' => [
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:comportamental',
        ],
        'financeiro' => [
            // Fase 0
            'view-billing',
            'view-ai-usage',
            'view-audit-logs',
            // Fase 2 — financeiro NÃO recebe abilities de paciente.
        ],
    ];

    public function run(): void
    {
        // Garante team id "global" (NULL) ao criar templates — o Spatie
        // honra esse contexto ao gravar `tenant_id` nas roles. Sem isso,
        // um seeder rodando depois de um teste poderia herdar o team id
        // anterior do registrar.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => self::GUARD,
                'tenant_id' => null,
            ]);
        }

        foreach (self::ROLES as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => self::GUARD,
                'tenant_id' => null,
            ]);

            $permissionNames = self::ROLE_PERMISSIONS[$roleName] ?? [];

            if ($permissionNames === []) {
                continue;
            }

            $permissions = Permission::query()
                ->whereNull('tenant_id')
                ->whereIn('name', $permissionNames)
                ->get();

            // syncPermissions é idempotente — mantém a role com exatamente
            // este conjunto de permissions, sem duplicar attaches.
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
