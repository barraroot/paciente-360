<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T062** — Seeder de roles e permissions default (templates globais).
 *
 * Cria 6 roles e 33 permissions com `tenant_id = NULL` (templates).
 * `super-admin` é a única role de fato global; as demais são clonadas
 * por `TenantService` ao criar um tenant (mecanismo descrito em
 * `data-model.md` § 5.4).
 *
 * **Idempotente**: usa `firstOrCreate`. Reseed não duplica.
 *
 * Atribuição de permissions por fase:
 *  - admin-clinica: abilities administrativas + CRM + inbox + receituário operacional
 *  - financeiro: view-billing, view-ai-usage, view-audit-logs (3)
 *  - medico: CRM + inbox próprio + abilities completas de receituário
 *  - atendente, recepcionista: CRM + inbox + `prescription.view`
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

        // Fase 3 — Omnichannel Inbox (§ 2.3 do spec — tabela de abilities)
        // Financeiro e Super Admin NÃO recebem essas abilities (Princípio I LGPD — minimização de acesso a mensagens).
        'inbox.view',
        'inbox.respond',
        'inbox.assign',
        'inbox.transfer',
        'inbox.takeover_ai',
        'channel.connect',
        'channel.disconnect',
        'quick_reply.manage',

        // Fase 7 — Receituários
        'prescription.create',
        'prescription.view',
        'prescription.update',
        'prescription.cancel',
        'prescription.view_controlled',
        'prescription.export',
        'prescription_alert.configure',
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
            // Fase 3 — admin clínica tem TODAS as abilities de inbox + canal.
            'inbox.view',
            'inbox.respond',
            'inbox.assign',
            'inbox.transfer',
            'inbox.takeover_ai',
            'channel.connect',
            'channel.disconnect',
            'quick_reply.manage',
            // Fase 7 — admin clínica não cria/edita receita, mas visualiza, cancela e exporta.
            'prescription.view',
            'prescription.cancel',
            'prescription.view_controlled',
            'prescription.export',
            'prescription_alert.configure',
        ],
        'medico' => [
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:clinica',
            'paciente.note.view:comportamental',
            // Fase 3 — médico: inbox (escopo "próprio + designado" imposto na policy).
            // channel.connect / channel.disconnect: apenas admin-clinica.
            'inbox.view',
            'inbox.respond',
            'inbox.takeover_ai',
            'quick_reply.manage',
            // Fase 7 — médico emissor.
            'prescription.create',
            'prescription.view',
            'prescription.update',
            'prescription.cancel',
            'prescription.view_controlled',
            'prescription.export',
            'prescription_alert.configure',
        ],
        'atendente' => [
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:comportamental',
            // Fase 3 — atendente: inbox completa, sem channel.connect/disconnect.
            'inbox.view',
            'inbox.respond',
            'inbox.assign',
            'inbox.transfer',
            'inbox.takeover_ai',
            'quick_reply.manage',
            // Fase 7 — sem acesso ao conteúdo clínico controlado.
            'prescription.view',
        ],
        'recepcionista' => [
            'paciente.view',
            'paciente.create',
            'paciente.update',
            'paciente.note.write',
            'paciente.note.view:geral',
            'paciente.note.view:comportamental',
            // Fase 3 — recepcionista: inbox completa, sem channel.connect/disconnect.
            'inbox.view',
            'inbox.respond',
            'inbox.assign',
            'inbox.transfer',
            'inbox.takeover_ai',
            'quick_reply.manage',
            // Fase 7 — sem acesso ao conteúdo clínico controlado.
            'prescription.view',
        ],
        'financeiro' => [
            // Fase 0
            'view-billing',
            'view-ai-usage',
            'view-audit-logs',
            // Fase 2 — financeiro NÃO recebe abilities de paciente.
            // Fase 3 — financeiro NÃO recebe abilities de inbox (Princípio I LGPD — minimização).
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
