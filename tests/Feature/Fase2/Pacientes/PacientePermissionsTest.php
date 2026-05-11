<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * T030 — Verifica que os abilities CRM (§ 2.4 do spec) são corretamente
 * gateados por perfil em modo team do Spatie.
 *
 * Estratégia:
 *   1. Roda `RolesSeeder` (Lote 0) + nova porção do RolesSeeder (T031)
 *      que adiciona 12 abilities globais (`tenant_id = NULL`) atribuídas
 *      às roles template.
 *   2. Para cada tenant criado, clona as roles template (já feito pelo
 *      DevSeeder; aqui replicamos via helper local) para `tenant_id`
 *      específico.
 *   3. Cria um usuário por role e dispara `$user->can($ability)`.
 *
 * Os abilities testados:
 *  - 8 abilities principais (`paciente.view/create/update/delete/import/
 *    export/merge/note.write`)
 *  - 4 sub-abilities (`paciente.note.view:{geral|clinica|comportamental|
 *    financeira}`)
 *
 * @see specs/002-crm-pacientes/spec.md § 2.4
 */
class PacientePermissionsTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    /**
     * Matrix completa de § 2.4 — fonte de verdade para os asserts.
     *
     * @var array<string, array<string, bool>>
     */
    private const MATRIX = [
        'admin-clinica' => [
            'paciente.view' => true,
            'paciente.create' => true,
            'paciente.update' => true,
            'paciente.delete' => true,
            'paciente.import' => true,
            'paciente.export' => true,
            'paciente.merge' => true,
            'paciente.note.write' => true,
            'paciente.note.view:geral' => true,
            'paciente.note.view:clinica' => true,
            'paciente.note.view:comportamental' => true,
            'paciente.note.view:financeira' => true,
        ],
        'medico' => [
            'paciente.view' => true,
            'paciente.create' => true,
            'paciente.update' => true,
            'paciente.delete' => false,
            'paciente.import' => false,
            'paciente.export' => false,
            'paciente.merge' => false,
            'paciente.note.write' => true,
            'paciente.note.view:geral' => true,
            'paciente.note.view:clinica' => true,
            'paciente.note.view:comportamental' => true,
            'paciente.note.view:financeira' => false,
        ],
        'atendente' => [
            'paciente.view' => true,
            'paciente.create' => true,
            'paciente.update' => true,
            'paciente.delete' => false,
            'paciente.import' => false,
            'paciente.export' => false,
            'paciente.merge' => false,
            'paciente.note.write' => true,
            'paciente.note.view:geral' => true,
            'paciente.note.view:clinica' => false,
            'paciente.note.view:comportamental' => true,
            'paciente.note.view:financeira' => false,
        ],
        'recepcionista' => [
            'paciente.view' => true,
            'paciente.create' => true,
            'paciente.update' => true,
            'paciente.delete' => false,
            'paciente.import' => false,
            'paciente.export' => false,
            'paciente.merge' => false,
            'paciente.note.write' => true,
            'paciente.note.view:geral' => true,
            'paciente.note.view:clinica' => false,
            'paciente.note.view:comportamental' => true,
            'paciente.note.view:financeira' => false,
        ],
        'financeiro' => [
            'paciente.view' => false,
            'paciente.create' => false,
            'paciente.update' => false,
            'paciente.delete' => false,
            'paciente.import' => false,
            'paciente.export' => false,
            'paciente.merge' => false,
            'paciente.note.write' => false,
            'paciente.note.view:geral' => false,
            'paciente.note.view:clinica' => false,
            'paciente.note.view:comportamental' => false,
            'paciente.note.view:financeira' => false,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Seedar roles + permissions (incluindo as novas de Fase 2 — T031).
        $this->seed(RolesSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cria um tenant, clona as 5 roles de domínio (sem super-admin) com
     * `tenant_id` específico e retorna o tenant. As permissions ficam
     * globais (tenant_id NULL) — mesmo padrão do DevSeeder/Fase 0.
     */
    private function bootstrapTenantWithRoles(string $slug): Tenant
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
     * Cria usuário no tenant + role + ativa o team id do registrar.
     */
    private function userForRole(Tenant $tenant, string $role): User
    {
        $user = $this->createUserForTenant($tenant);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        return $user;
    }

    /** @test */
    public function test_admin_clinica_has_all_paciente_abilities(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('alfa-admin');
        $user = $this->userForRole($tenant, 'admin-clinica');

        foreach (self::MATRIX['admin-clinica'] as $ability => $expected) {
            $this->assertTrue(
                $user->can($ability),
                "admin-clinica deveria ter ability {$ability}"
            );
        }
    }

    /** @test */
    public function test_medico_can_view_create_update_but_not_delete_import_export(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('alfa-medico');
        $user = $this->userForRole($tenant, 'medico');

        foreach (self::MATRIX['medico'] as $ability => $expected) {
            $this->assertSame(
                $expected,
                $user->can($ability),
                "medico ability {$ability} esperado=".($expected ? 'true' : 'false')
            );
        }
    }

    /** @test */
    public function test_atendente_and_recepcionista_have_same_set_minus_clinical_notes(): void
    {
        foreach (['atendente', 'recepcionista'] as $roleName) {
            $tenant = $this->bootstrapTenantWithRoles("alfa-{$roleName}");
            $user = $this->userForRole($tenant, $roleName);

            foreach (self::MATRIX[$roleName] as $ability => $expected) {
                $this->assertSame(
                    $expected,
                    $user->can($ability),
                    "{$roleName} ability {$ability} esperado=".($expected ? 'true' : 'false')
                );
            }
        }
    }

    /** @test */
    public function test_financeiro_has_no_paciente_abilities(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('alfa-fin');
        $user = $this->userForRole($tenant, 'financeiro');

        foreach (self::MATRIX['financeiro'] as $ability => $expected) {
            $this->assertFalse(
                $user->can($ability),
                "financeiro nao deveria ter ability {$ability}"
            );
        }
    }

    /** @test */
    public function test_note_view_clinica_restricted_to_medico_and_admin(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('alfa-nota-clinica');

        foreach (['admin-clinica' => true, 'medico' => true, 'atendente' => false, 'recepcionista' => false, 'financeiro' => false] as $role => $expected) {
            $user = $this->userForRole($tenant, $role);

            $this->assertSame(
                $expected,
                $user->can('paciente.note.view:clinica'),
                "{$role} ability paciente.note.view:clinica esperado=".($expected ? 'true' : 'false')
            );
        }
    }

    /** @test */
    public function test_note_view_financeira_restricted_to_admin(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('alfa-nota-fin');

        foreach (['admin-clinica' => true, 'medico' => false, 'atendente' => false, 'recepcionista' => false, 'financeiro' => false] as $role => $expected) {
            $user = $this->userForRole($tenant, $role);

            $this->assertSame(
                $expected,
                $user->can('paciente.note.view:financeira'),
                "{$role} ability paciente.note.view:financeira esperado=".($expected ? 'true' : 'false')
            );
        }
    }

    /**
     * @test
     *
     * Super Admin deve receber 403 explícito em endpoints de paciente —
     * mesmo que o Gate::before global retorne `true` para outras abilities,
     * a PacientePolicy::before sobrescreve esse bypass (FR-038).
     */
    public function test_super_admin_returns_403_on_paciente_endpoints(): void
    {
        // Super admin é template global (tenant_id NULL); criado pelo seeder.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin);

        $response = $this->getJson('http://localhost/api/v1/pacientes');

        $response->assertForbidden();
    }

    /**
     * @test
     *
     * Financeiro autenticado em tenant retorna 403 ao bater na rota
     * de pacientes (a policy nega antes do controller stub responder).
     */
    public function test_financeiro_returns_403_on_paciente_endpoints(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('alfa-fin-endpoint');
        $financeiro = $this->userForRole($tenant, 'financeiro');

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($financeiro)
            ->getJson('http://alfa-fin-endpoint.lvh.me/api/v1/pacientes');

        $response->assertForbidden();
    }
}
