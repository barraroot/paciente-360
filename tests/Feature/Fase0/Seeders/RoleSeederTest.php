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

        // Total global de permissions cresceu com as fases seguintes (Agenda,
        // Receituário, Fase 8 Privacy/SuperAdmin/Campaigns/Integrations/Reports,
        // Spec 012 professional.manage e Fase 15 IA Matricial — ai.persona/knowledge/
        // guardrail/matrix/log). O foco do teste é idempotência (reseed não duplica),
        // não o valor absoluto.
        $this->assertSame(6, $rolesCount, 'Reseed não deve duplicar roles.');
        $this->assertSame(59, $permissionsCount, 'Reseed não deve duplicar permissions.');
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

        // admin-clinica recebe o conjunto operacional (cresce a cada fase;
        // hoje 53 incluindo report.view/export, professional.manage da Spec 012 e
        // as abilities da Fase 15 IA Matricial — ai.persona/knowledge/guardrail/matrix/log).
        $this->assertCount(
            53,
            $admin->permissions,
            'admin-clinica deve ter o conjunto default de permissions operacionais.'
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
