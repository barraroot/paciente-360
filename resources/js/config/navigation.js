/**
 * Árvore canônica de navegação do App Shell.
 *
 * Definição estática conforme `specs/009-app-shell/contracts/navigation-tree.md`.
 * Filtragem por permissão acontece em runtime via `useNavigation()` composable.
 *
 * Cada entry pode ser:
 *   - NavItem  (link único)  → tem `routeName`
 *   - NavGroup (expansível)  → tem `children: NavItem[]`
 *
 * Campos:
 *   key        — ID único (usado como persistência de expanded state)
 *   labelKey   — chave i18n
 *   icon       — nome do ícone (componente em components/layout/icons/)
 *   routeName  — nome de rota Vue Router (apenas para items)
 *   ability    — permission requerida (Spatie name); ausente = sem gate além de auth
 *   anyOf      — array de abilities; visível se user tem QUALQUER uma
 *   children   — sub-items (apenas para grupos)
 */
export const NAVIGATION = [
    {
        key: 'dashboard',
        labelKey: 'layout.sidebar.dashboard',
        icon: 'home',
        routeName: 'panel.home',
    },
    {
        key: 'agenda',
        labelKey: 'layout.sidebar.agenda.title',
        icon: 'calendar',
        children: [
            {
                key: 'agenda.calendar',
                labelKey: 'layout.sidebar.agenda.calendar',
                routeName: 'agenda.index',
                ability: 'appointment.view',
            },
            {
                key: 'agenda.waitlist',
                labelKey: 'layout.sidebar.agenda.waitlist',
                routeName: 'agenda.waitlist',
                ability: 'appointment.view',
            },
            {
                key: 'agenda.types',
                labelKey: 'layout.sidebar.agenda.types',
                routeName: 'agenda.types.index',
                ability: 'appointment_type.manage',
            },
            {
                key: 'agenda.schedule',
                labelKey: 'layout.sidebar.agenda.schedule',
                routeName: 'agenda.schedule.index',
                ability: 'schedule.configure',
            },
            {
                key: 'agenda.sync',
                labelKey: 'layout.sidebar.agenda.sync',
                routeName: 'agenda.sync.index',
                ability: 'calendar_sync.configure',
            },
        ],
    },
    {
        key: 'ia',
        labelKey: 'layout.sidebar.ia.title',
        icon: 'cog',
        children: [
            {
                key: 'ia.personas',
                labelKey: 'layout.sidebar.ia.personas',
                routeName: 'ia.personas.index',
                ability: 'ai.persona.view',
            },
            {
                key: 'ia.matriz',
                labelKey: 'layout.sidebar.ia.matriz',
                routeName: 'ia.matriz.index',
                ability: 'ai.matrix.manage',
            },
        ],
    },
    {
        key: 'pacientes',
        labelKey: 'layout.sidebar.pacientes.title',
        icon: 'users',
        children: [
            {
                key: 'pacientes.list',
                labelKey: 'layout.sidebar.pacientes.list',
                routeName: 'pacientes.list',
                ability: 'paciente.view',
            },
            {
                key: 'pacientes.funil',
                labelKey: 'layout.sidebar.pacientes.funil',
                routeName: 'pacientes.funil.kanban',
                ability: 'paciente.view',
            },
            {
                key: 'pacientes.import',
                labelKey: 'layout.sidebar.pacientes.import',
                routeName: 'pacientes.import.upload',
                ability: 'paciente.import',
            },
            {
                key: 'pacientes.mesclagem',
                labelKey: 'layout.sidebar.pacientes.mesclagem',
                routeName: 'pacientes.mesclagem',
                ability: 'paciente.merge',
            },
        ],
    },
    {
        key: 'inbox',
        labelKey: 'layout.sidebar.inbox.title',
        icon: 'inbox',
        children: [
            {
                key: 'inbox.conversations',
                labelKey: 'layout.sidebar.inbox.conversations',
                routeName: 'inbox.index',
                ability: 'inbox.view',
            },
            {
                key: 'inbox.channels',
                labelKey: 'layout.sidebar.inbox.channels',
                routeName: 'canais.index',
                ability: 'channel.connect',
            },
            {
                key: 'inbox.assignment_rules',
                labelKey: 'layout.sidebar.inbox.assignment_rules',
                routeName: 'inbox.regras_atribuicao',
                ability: 'inbox.assign',
            },
            {
                key: 'inbox.quick_replies',
                labelKey: 'layout.sidebar.inbox.quick_replies',
                routeName: 'inbox.respostas_rapidas',
                ability: 'inbox.view',
            },
        ],
    },
    {
        key: 'prescriptions',
        labelKey: 'layout.sidebar.prescriptions',
        icon: 'clipboard',
        routeName: 'prescriptions.index',
        ability: 'prescription.view',
    },
    {
        key: 'campaigns',
        labelKey: 'layout.sidebar.campaigns',
        icon: 'megaphone',
        routeName: 'campaigns.index',
        ability: 'campaign.create',
    },
    {
        key: 'reports',
        labelKey: 'layout.sidebar.reports.title',
        icon: 'chart',
        children: [
            {
                key: 'reports.executive',
                labelKey: 'layout.sidebar.reports.executive',
                routeName: 'reports.executive',
                ability: 'report.view',
            },
            {
                key: 'reports.operational',
                labelKey: 'layout.sidebar.reports.operational',
                routeName: 'reports.operational',
                ability: 'report.view',
            },
            {
                key: 'reports.clinical',
                labelKey: 'layout.sidebar.reports.clinical',
                routeName: 'reports.clinical',
                ability: 'report.view',
            },
        ],
    },
    {
        key: 'integrations',
        labelKey: 'layout.sidebar.integrations.title',
        icon: 'plug',
        children: [
            {
                key: 'integrations.webhooks',
                labelKey: 'layout.sidebar.integrations.webhooks',
                routeName: 'integrations.webhooks',
                ability: 'webhook.manage',
            },
            {
                key: 'integrations.dlq',
                labelKey: 'layout.sidebar.integrations.dlq',
                routeName: 'integrations.webhooks.dlq',
                ability: 'webhook.manage',
            },
            {
                key: 'integrations.api_tokens',
                labelKey: 'layout.sidebar.integrations.api_tokens',
                routeName: 'integrations.api_tokens',
                ability: 'api_token.manage',
            },
        ],
    },
    {
        key: 'privacy',
        labelKey: 'layout.sidebar.privacy.title',
        icon: 'shield',
        children: [
            {
                key: 'privacy.consents',
                labelKey: 'layout.sidebar.privacy.consents',
                routeName: 'privacy.consents',
                ability: 'privacy.view',
            },
            {
                key: 'privacy.forgetting',
                labelKey: 'layout.sidebar.privacy.forgetting',
                routeName: 'privacy.forgetting',
                ability: 'privacy.view',
            },
            {
                key: 'privacy.portability',
                labelKey: 'layout.sidebar.privacy.portability',
                routeName: 'privacy.portability',
                ability: 'privacy.view',
            },
        ],
    },
    {
        key: 'settings',
        labelKey: 'layout.sidebar.settings.title',
        icon: 'cog',
        children: [
            {
                key: 'settings.sessions',
                labelKey: 'layout.sidebar.settings.sessions',
                routeName: 'auth.tokens',
            },
            {
                key: 'settings.professionals',
                labelKey: 'layout.sidebar.settings.professionals',
                routeName: 'professionals.list',
                ability: 'professional.manage',
            },
            {
                key: 'settings.users',
                labelKey: 'layout.sidebar.settings.users',
                routeName: 'users.list',
                ability: 'manage-users',
            },
            {
                key: 'settings.audit',
                labelKey: 'layout.sidebar.settings.audit',
                routeName: 'audit.list',
                ability: 'view-audit-logs',
            },
            {
                key: 'settings.plans',
                labelKey: 'layout.sidebar.settings.plans',
                routeName: 'billing.plans',
                ability: 'manage-billing',
            },
            {
                key: 'settings.subscription',
                labelKey: 'layout.sidebar.settings.subscription',
                routeName: 'billing.subscription',
                ability: 'manage-billing',
            },
            {
                key: 'settings.ai_usage',
                labelKey: 'layout.sidebar.settings.ai_usage',
                routeName: 'billing.ai-usage',
                ability: 'view-billing',
            },
        ],
    },
];
