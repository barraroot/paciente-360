<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T065** — Seeder de desenvolvimento. Cria 2 tenants navegáveis em
 *   - http://clinica-alfa.lvh.me  (active, plano starter "contratado")
 *   - http://clinica-beta.lvh.me  (trial, sem assinatura)
 *
 * Senha padrão para todos os usuários: `password123`.
 *
 * Pré-requisitos: `RolesSeeder`, `PlansSeeder`, `SuperAdminSeeder` rodados
 * antes (chamados aqui caso ainda não tenham rodado para garantir
 * idempotência ponta-a-ponta).
 *
 * **Bloqueado em produção**: `RuntimeException` se `APP_ENV=production`
 * (Princípio VII).
 *
 * Ao criar cada tenant, clona as roles template (tenant_id NULL) para
 * `tenant_id = X` e copia as permissions atribuídas a cada role template.
 */
class DevSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevSeeder não pode rodar em produção.');
        }

        // Garante baseline: roles, planos, super admin.
        $this->call([
            RolesSeeder::class,
            PlansSeeder::class,
            SuperAdminSeeder::class,
        ]);

        $this->seedTenantAlfa();
        $this->seedTenantBeta();

        if (isset($this->command)) {
            $this->command->info('DevSeeder: tenants navegáveis em http://clinica-alfa.lvh.me e http://clinica-beta.lvh.me');
            $this->command->info('Senha padrão de todos os usuários de dev: '.self::PASSWORD);
        }
    }

    /**
     * Clínica Alfa — tenant ativo com plano starter contratado em modo
     * dev (subscription Stripe fictícia). 5 usuários (um por papel) +
     * 1 profissional vinculado ao usuário `medico`.
     */
    private function seedTenantAlfa(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'clinica-alfa'],
            [
                'name' => 'Clínica Alfa',
                'cnpj' => '12345678000100',
                'responsible_name' => 'Alfa Responsável',
                'responsible_email' => 'admin@clinica-alfa.test',
                'responsible_phone' => '+5511999990001',
                'status' => 'active',
                'trial_ends_at' => null,
                'overdue_since' => null,
                'restrictions_applied_at' => null,
                'plan_id' => null,
                'stripe_customer_id' => null,
                'subdomain_custom' => null,
                'terms_accepted_at' => now(),
                'terms_version' => '2026-05-01',
                'onboarding_state' => json_encode((object) []),
            ]
        );

        $this->cloneTemplateRolesForTenant($tenant->id);

        $starter = DB::table('plans')->where('code', 'starter')->first();

        if ($starter !== null) {
            // Vincula o plano ao tenant para refletir "contratado".
            $tenant->forceFill(['plan_id' => $starter->id])->save();

            DB::table('subscriptions')->updateOrInsert(
                ['stripe_id' => 'sub_dev_alfa'],
                [
                    'tenant_id' => $tenant->id,
                    'type' => 'default',
                    'stripe_status' => 'active',
                    'stripe_price' => $starter->stripe_price_id_base,
                    'quantity' => 1,
                    'plan_id' => $starter->id,
                    'plan_snapshot' => json_encode([
                        'code' => $starter->code,
                        'base_price_cents' => $starter->base_price_cents,
                        'included_professionals' => $starter->included_professionals,
                        'included_ai_messages' => $starter->included_ai_messages,
                        'overage_price_cents' => $starter->overage_price_cents,
                        'max_users' => $starter->max_users,
                        'max_channels' => $starter->max_channels,
                    ]),
                    'professionals_quantity' => 1,
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->startOfMonth()->addMonth(),
                    'trial_ends_at' => null,
                    'ends_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $usersByRole = [
            'admin-clinica' => ['Alfa Admin', 'admin@clinica-alfa.test'],
            'medico' => ['Alfa Médico', 'medico@clinica-alfa.test'],
            'atendente' => ['Alfa Atendente', 'atendente@clinica-alfa.test'],
            'recepcionista' => ['Alfa Recepcionista', 'recepcionista@clinica-alfa.test'],
            'financeiro' => ['Alfa Financeiro', 'financeiro@clinica-alfa.test'],
        ];

        $createdUsers = [];

        foreach ($usersByRole as $roleName => [$name, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );

            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }

            $createdUsers[$roleName] = $user;
        }

        // Profissional vinculado ao médico (esqueleto — fase 4 traz o
        // domínio completo de agenda).
        $medico = $createdUsers['medico'];

        DB::table('professionals')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'user_id' => $medico->id],
            [
                'name' => $medico->name,
                'council_type' => 'CRM',
                'council_number' => '12345',
                'council_state' => 'SP',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );

        $registrar->setPermissionsTeamId(null);
    }

    /**
     * Clínica Beta — tenant em trial, sem assinatura, com 1 admin user.
     */
    private function seedTenantBeta(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'clinica-beta'],
            [
                'name' => 'Clínica Beta',
                'cnpj' => '98765432000111',
                'responsible_name' => 'Beta Responsável',
                'responsible_email' => 'admin@clinica-beta.test',
                'responsible_phone' => '+5511999990002',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'overdue_since' => null,
                'restrictions_applied_at' => null,
                'plan_id' => null,
                'stripe_customer_id' => null,
                'subdomain_custom' => null,
                'terms_accepted_at' => now(),
                'terms_version' => '2026-05-01',
                'onboarding_state' => json_encode((object) []),
            ]
        );

        $this->cloneTemplateRolesForTenant($tenant->id);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $admin = User::firstOrCreate(
            ['email' => 'admin@clinica-beta.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Beta Admin',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $admin->hasRole('admin-clinica')) {
            $admin->assignRole('admin-clinica');
        }

        $registrar->setPermissionsTeamId(null);
    }

    /**
     * Clona as roles e permissions template (tenant_id NULL, exceto
     * `super-admin`) para o tenant indicado, preservando a relação
     * role→permissions.
     *
     * Idempotente: `firstOrCreate` em roles/permissions; `syncPermissions`
     * para o set de permissions.
     */
    private function cloneTemplateRolesForTenant(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $templates = Role::query()
            ->whereNull('tenant_id')
            ->where('name', '!=', 'super-admin')
            ->with('permissions')
            ->get();

        foreach ($templates as $template) {
            $registrar->setPermissionsTeamId($tenantId);

            $tenantRole = Role::firstOrCreate([
                'name' => $template->name,
                'guard_name' => $template->guard_name,
                'tenant_id' => $tenantId,
            ]);

            // Permissions ficam globais (tenant_id NULL) nesta fase —
            // basta vincular as mesmas instâncias do template.
            $permissionIds = $template->permissions->pluck('id')->all();

            if ($permissionIds === []) {
                continue;
            }

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $tenantRole->syncPermissions($permissions);
        }

        $registrar->setPermissionsTeamId(null);
    }
}
