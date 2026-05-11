<?php

namespace Tests\Feature\Fase0\Seeders;

use App\Models\Role;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **T062** — verifica `RolesSeeder`: 6 roles default (templates globais),
 * idempotência, atribuição de permissions ao `admin-clinica`.
 */
class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_six_default_roles(): void
    {
        Artisan::call('db:seed', [
            '--class' => RolesSeeder::class,
            '--force' => true,
        ]);

        $count = DB::table('roles')->whereNull('tenant_id')->count();
        $this->assertSame(6, $count, 'Deve existir 6 roles template (tenant_id NULL).');

        foreach (['super-admin', 'admin-clinica', 'medico', 'atendente', 'recepcionista', 'financeiro'] as $name) {
            $exists = DB::table('roles')
                ->whereNull('tenant_id')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            $this->assertTrue($exists, "Role template '{$name}' deve existir com guard_name=web.");
        }
    }

    public function test_seeding_is_idempotent(): void
    {
        Artisan::call('db:seed', [
            '--class' => RolesSeeder::class,
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => RolesSeeder::class,
            '--force' => true,
        ]);

        $rolesCount = DB::table('roles')->whereNull('tenant_id')->count();
        $permissionsCount = DB::table('permissions')->whereNull('tenant_id')->count();

        // Após Fase 2 (T031), o RolesSeeder cria 18 permissions globais:
        //   6 da Fase 0 (manage-users, manage-billing, manage-onboarding,
        //   view-audit-logs, view-billing, view-ai-usage) + 12 do CRM de
        //   pacientes (paciente.view/create/update/delete/import/export/merge/
        //   note.write + 4 sub-abilities paciente.note.view:{tipo}).
        $this->assertSame(6, $rolesCount, 'Reseed não deve duplicar roles.');
        $this->assertSame(18, $permissionsCount, 'Reseed não deve duplicar permissions.');
    }

    public function test_admin_clinica_has_all_permissions(): void
    {
        Artisan::call('db:seed', [
            '--class' => RolesSeeder::class,
            '--force' => true,
        ]);

        $admin = Role::query()
            ->whereNull('tenant_id')
            ->where('name', 'admin-clinica')
            ->where('guard_name', 'web')
            ->firstOrFail();

        // Após Fase 2 (T031), admin-clinica recebe todas as 18 permissions
        // (6 Fase 0 + 12 CRM pacientes — § 2.4 do spec da Fase 2).
        $this->assertCount(
            18,
            $admin->permissions,
            'admin-clinica deve ter as 18 permissions default (6 Fase 0 + 12 CRM pacientes).'
        );
    }

    public function test_financeiro_has_three_permissions(): void
    {
        Artisan::call('db:seed', [
            '--class' => RolesSeeder::class,
            '--force' => true,
        ]);

        $financeiro = Role::query()
            ->whereNull('tenant_id')
            ->where('name', 'financeiro')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $names = $financeiro->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame(
            ['view-ai-usage', 'view-audit-logs', 'view-billing'],
            $names,
            'financeiro deve ter exatamente view-ai-usage, view-audit-logs, view-billing.'
        );
    }
}
