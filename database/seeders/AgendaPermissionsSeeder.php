<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T021** — Adiciona as 11 abilities da Fase 5 (Agendamento) aos templates globais
 * de roles (tenant_id = NULL) E sincroniza para roles dos tenants existentes.
 *
 * Idempotente — `firstOrCreate` + `syncPermissions` são safe em reseed.
 *
 * Atribuição por role (spec.md § Contratos Herdados / Fase 0):
 *  - admin-clinica   → todas as 11 (incluindo override_block + revert_attendance_marking)
 *  - medico          → view, create, update, cancel, manage_own_schedule, calendar_sync.configure
 *  - atendente       → view, create, update, cancel, waitlist.manage
 *  - recepcionista   → view, create, update, cancel, waitlist.manage
 *  - financeiro      → view (somente leitura — Princípio I LGPD)
 *
 * `super-admin` continua bypass via Gate `before` (sem atribuição explícita).
 */
class AgendaPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'appointment.view',
        'appointment.create',
        'appointment.update',
        'appointment.cancel',
        'appointment.override_block',
        'appointment.manage_own_schedule',
        'appointment.revert_attendance_marking',
        'schedule.configure',
        'appointment_type.manage',
        'waitlist.manage',
        'calendar_sync.configure',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin-clinica' => [
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.cancel',
            'appointment.override_block',
            'appointment.manage_own_schedule',
            'appointment.revert_attendance_marking',
            'schedule.configure',
            'appointment_type.manage',
            'waitlist.manage',
            'calendar_sync.configure',
        ],
        'medico' => [
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.cancel',
            'appointment.manage_own_schedule',
            'calendar_sync.configure',
        ],
        'atendente' => [
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.cancel',
            'waitlist.manage',
        ],
        'recepcionista' => [
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.cancel',
            'waitlist.manage',
        ],
        'financeiro' => [
            'appointment.view',
        ],
    ];

    public function run(): void
    {
        // 1) Cria as permissions como TEMPLATES GLOBAIS (tenant_id = NULL)
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => self::GUARD,
                'tenant_id' => null,
            ]);
        }

        // 2) Atribui aos templates globais (tenant_id = NULL)
        foreach (self::ROLE_PERMISSIONS as $roleName => $permNames) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->whereNull('tenant_id')
                ->first();

            if (! $role) {
                continue;
            }

            $permissions = Permission::whereIn('name', $permNames)
                ->where('guard_name', self::GUARD)
                ->whereNull('tenant_id')
                ->get();

            // syncWithoutDetaching evita remover permissions de outras fases
            $existingIds = $role->permissions()->pluck('permissions.id')->toArray();
            $newIds = $permissions->pluck('id')->toArray();
            $merged = array_unique(array_merge($existingIds, $newIds));
            $role->permissions()->sync($merged);
        }

        // 3) Sincroniza para tenants EXISTENTES (clonando permissions para
        //    tenant_id != NULL). Idempotente — só adiciona se faltar.
        Tenant::query()->each(function (Tenant $tenant): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // Cria permissions per tenant (espelhando templates)
            foreach (self::PERMISSIONS as $name) {
                Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => self::GUARD,
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Atribui aos roles do tenant
            foreach (self::ROLE_PERMISSIONS as $roleName => $permNames) {
                $role = Role::where('name', $roleName)
                    ->where('guard_name', self::GUARD)
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if (! $role) {
                    continue;
                }

                $permissions = Permission::whereIn('name', $permNames)
                    ->where('guard_name', self::GUARD)
                    ->where('tenant_id', $tenant->id)
                    ->get();

                $existingIds = $role->permissions()->pluck('permissions.id')->toArray();
                $newIds = $permissions->pluck('id')->toArray();
                $merged = array_unique(array_merge($existingIds, $newIds));
                $role->permissions()->sync($merged);
            }
        });

        // Reset registrar para não vazar contexto
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
