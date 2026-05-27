import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import { NAVIGATION } from '@/config/navigation.js';
import AppShell from '@/layouts/AppShell.vue';
import { i18n } from '@/i18n/index.js';

function findLabelKeyForRoute(name) {
    for (const entry of NAVIGATION) {
        if (Array.isArray(entry.children)) {
            const found = entry.children.find((c) => c.routeName === name);
            if (found) {
                return found.labelKey;
            }
        } else if (entry.routeName === name) {
            return entry.labelKey;
        }
    }
    return null;
}

/**
 * Rotas do painel autenticado — todas filhas da rota pai `/panel` que renderiza
 * o AppShell. Paths relativos (sem barra inicial). Cada rota declara
 * `meta.title` (string) usada pela topbar e por `document.title` (US-6).
 *
 * `/panel/onboarding` permanece como IRMÃ (não filha) para ficar fora do shell —
 * onboarding é tela cheia para reduzir distração durante o setup inicial.
 */
const panelChildren = [
    {
        path: '',
        name: 'panel.home',
        component: () => import('@/pages/PanelHome.vue'),
        meta: { title: 'layout.sidebar.dashboard' },
    },
    {
        path: 'billing/plans',
        name: 'billing.plans',
        component: () => import('@/pages/billing/PlansPage.vue'),
        meta: { title: 'layout.sidebar.settings.plans', ability: 'manage-billing' },
    },
    {
        path: 'billing/subscription',
        name: 'billing.subscription',
        component: () => import('@/pages/billing/SubscriptionPage.vue'),
        meta: { title: 'layout.sidebar.settings.subscription', ability: 'manage-billing' },
    },
    {
        path: 'billing/ai-usage',
        name: 'billing.ai-usage',
        component: () => import('@/pages/billing/AiUsagePage.vue'),
        meta: { title: 'layout.sidebar.settings.ai_usage', ability: 'view-ai-usage' },
    },
    {
        path: 'users',
        name: 'users.list',
        component: () => import('@/pages/users/UsersListPage.vue'),
        meta: { title: 'layout.sidebar.settings.users', ability: 'manage-users' },
    },
    {
        path: 'users/invite',
        name: 'users.invite',
        component: () => import('@/pages/users/InviteUserPage.vue'),
        meta: { title: 'layout.sidebar.settings.users', ability: 'manage-users' },
    },
    {
        path: 'audit-logs',
        name: 'audit.list',
        component: () => import('@/pages/audit/AuditLogsPage.vue'),
        meta: { title: 'layout.sidebar.settings.audit', ability: 'view-audit-logs' },
    },
    // ─── Agenda ────────────────────────────────────────────────────────────
    {
        path: 'agenda',
        name: 'agenda.index',
        component: () => import('@/pages/agenda/AgendaPage.vue'),
        meta: { title: 'layout.sidebar.agenda.calendar', ability: 'appointment.view' },
    },
    {
        path: 'agenda/lista-espera',
        name: 'agenda.waitlist',
        component: () => import('@/pages/agenda/WaitlistPage.vue'),
        meta: { title: 'layout.sidebar.agenda.waitlist', ability: 'appointment.view' },
    },
    {
        path: 'agenda/tipos',
        name: 'agenda.types.index',
        component: () => import('@/pages/agenda/AppointmentTypesPage.vue'),
        meta: { title: 'layout.sidebar.agenda.types', ability: 'appointment_type.manage' },
    },
    {
        path: 'agenda/horarios',
        name: 'agenda.schedule.index',
        component: () => import('@/pages/agenda/ScheduleConfigPage.vue'),
        meta: { title: 'layout.sidebar.agenda.schedule', ability: 'schedule.configure' },
    },
    {
        path: 'agenda/sincronizacao',
        name: 'agenda.sync.index',
        component: () => import('@/pages/agenda/CalendarSyncPage.vue'),
        meta: { title: 'layout.sidebar.agenda.sync', ability: 'calendar_sync.configure' },
    },
    // ─── IA Matricial ──────────────────────────────────────────────────────
    {
        path: 'ia/personas',
        name: 'ia.personas.index',
        component: () => import('@/pages/Ia/PersonasIndex.vue'),
        meta: { title: 'layout.sidebar.ia.personas', ability: 'ai.persona.view' },
    },
    {
        path: 'ia/personas/nova',
        name: 'ia.personas.new',
        component: () => import('@/pages/Ia/PersonaForm.vue'),
        meta: { title: 'layout.sidebar.ia.personas', ability: 'ai.persona.manage' },
    },
    {
        path: 'ia/personas/:id/editar',
        name: 'ia.personas.edit',
        component: () => import('@/pages/Ia/PersonaForm.vue'),
        meta: { title: 'layout.sidebar.ia.personas', ability: 'ai.persona.manage' },
    },
    {
        path: 'ia/matriz',
        name: 'ia.matriz.index',
        component: () => import('@/pages/Ia/MatrizCanais.vue'),
        meta: { title: 'layout.sidebar.ia.matriz', ability: 'ai.matrix.manage' },
    },
    {
        path: 'ia/contexto-trabalho',
        name: 'ia.work-context.index',
        component: () => import('@/pages/Ia/WorkContextPage.vue'),
        meta: { title: 'layout.sidebar.ia.workContext', ability: 'ai.work-context.view' },
    },
    {
        path: 'ia/bases',
        name: 'ia.bases.index',
        component: () => import('@/pages/Ia/BasesIndex.vue'),
        meta: { title: 'layout.sidebar.ia.bases', ability: 'ai.knowledge.view' },
    },
    {
        path: 'ia/bases/nova',
        name: 'ia.bases.new',
        component: () => import('@/pages/Ia/BaseForm.vue'),
        meta: { title: 'layout.sidebar.ia.bases', ability: 'ai.knowledge.manage' },
    },
    {
        path: 'ia/bases/:id/editar',
        name: 'ia.bases.edit',
        component: () => import('@/pages/Ia/BaseForm.vue'),
        meta: { title: 'layout.sidebar.ia.bases', ability: 'ai.knowledge.manage' },
    },
    {
        path: 'ia/guardrails',
        name: 'ia.guardrails.index',
        component: () => import('@/pages/Ia/GuardrailsIndex.vue'),
        meta: { title: 'layout.sidebar.ia.guardrails', ability: 'ai.guardrail.view' },
    },
    {
        path: 'ia/guardrails/novo',
        name: 'ia.guardrails.new',
        component: () => import('@/pages/Ia/GuardrailForm.vue'),
        meta: { title: 'layout.sidebar.ia.guardrails', ability: 'ai.guardrail.manage' },
    },
    {
        path: 'ia/guardrails/:id/editar',
        name: 'ia.guardrails.edit',
        component: () => import('@/pages/Ia/GuardrailForm.vue'),
        meta: { title: 'layout.sidebar.ia.guardrails', ability: 'ai.guardrail.manage' },
    },
    {
        path: 'ia/logs',
        name: 'ia.logs.index',
        component: () => import('@/pages/Ia/LogsIndex.vue'),
        meta: { title: 'layout.sidebar.ia.logs', ability: 'ai.log.view' },
    },
    // ─── Pacientes ─────────────────────────────────────────────────────────
    {
        path: 'pacientes',
        name: 'pacientes.list',
        component: () => import('@/pages/pacientes/PacientesListPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.list', ability: 'paciente.view' },
    },
    {
        path: 'pacientes/novo',
        name: 'pacientes.create',
        component: () => import('@/pages/pacientes/PacienteFormPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.list', ability: 'paciente.create' },
    },
    {
        path: 'pacientes/mesclagem',
        name: 'pacientes.mesclagem',
        component: () => import('@/pages/pacientes/MesclagemPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.mesclagem', ability: 'paciente.merge' },
    },
    {
        path: 'pacientes/funil',
        name: 'pacientes.funil.kanban',
        component: () => import('@/pages/pacientes/FunilKanbanPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.funil', ability: 'paciente.view' },
    },
    {
        path: 'pacientes/funil/config',
        name: 'pacientes.funil.config',
        component: () => import('@/pages/pacientes/FunilConfigPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.funil', ability: 'paciente.update' },
    },
    {
        path: 'pacientes/importar',
        name: 'pacientes.import.upload',
        component: () => import('@/pages/pacientes/ImportacaoPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.import', ability: 'paciente.import' },
    },
    {
        path: 'pacientes/importacao/:id',
        name: 'pacientes.import.status',
        component: () => import('@/pages/pacientes/ImportacaoStatusPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { title: 'layout.sidebar.pacientes.import', ability: 'paciente.import' },
    },
    {
        path: 'pacientes/:id',
        name: 'pacientes.show',
        component: () => import('@/pages/pacientes/PacienteShowPage.vue'),
        meta: { title: 'layout.sidebar.pacientes.list', ability: 'paciente.view' },
    },
    {
        path: 'pacientes/:id/editar',
        name: 'pacientes.edit',
        component: () => import('@/pages/pacientes/PacienteFormPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'layout.sidebar.pacientes.list', ability: 'paciente.update' },
    },
    // ─── Configurações ─────────────────────────────────────────────────────
    {
        path: 'configuracoes/sessoes',
        name: 'auth.tokens',
        component: () => import('@/pages/auth/TokensPage.vue'),
        meta: { title: 'layout.sidebar.settings.sessions' },
    },
    {
        path: 'profissionais',
        name: 'professionals.list',
        component: () => import('@/pages/Professionals/ProfessionalsListPage.vue'),
        meta: { title: 'layout.sidebar.settings.professionals', ability: 'professional.manage' },
    },
    // ─── Canais ────────────────────────────────────────────────────────────
    {
        path: 'canais',
        name: 'canais.index',
        component: () => import('@/pages/Canais/Index.vue'),
        meta: { title: 'layout.sidebar.inbox.channels', ability: 'inbox.view' },
    },
    {
        path: 'canais/conectar-whatsapp',
        name: 'canais.conectar_whatsapp',
        component: () => import('@/pages/Canais/ConectarWhatsApp.vue'),
        meta: { title: 'layout.sidebar.inbox.channels', ability: 'channel.connect' },
    },
    {
        path: 'canais/conectar-instagram',
        name: 'canais.conectar_instagram',
        component: () => import('@/pages/Canais/ConectarInstagram.vue'),
        meta: { title: 'layout.sidebar.inbox.channels', ability: 'channel.connect' },
    },
    {
        path: 'canais/:id',
        name: 'canais.show',
        component: () => import('@/pages/Canais/Detalhe.vue'),
        props: true,
        meta: { title: 'layout.sidebar.inbox.channels', ability: 'inbox.view' },
    },
    // ─── Receituários ──────────────────────────────────────────────────────
    {
        path: 'receituarios',
        name: 'prescriptions.index',
        component: () => import('@/pages/prescriptions/PrescriptionsListPage.vue'),
        meta: { title: 'layout.sidebar.prescriptions', ability: 'prescription.view' },
    },
    {
        path: 'receituarios/novo',
        name: 'prescriptions.create',
        component: () => import('@/pages/prescriptions/PrescriptionCreatePage.vue'),
        meta: { title: 'layout.sidebar.prescriptions', ability: 'prescription.create' },
    },
    {
        path: 'receituarios/relatorio',
        name: 'prescriptions.report',
        component: () => import('@/pages/prescriptions/PrescriptionsReportPage.vue'),
        meta: { title: 'layout.sidebar.prescriptions', ability: 'prescription.view' },
    },
    {
        path: 'receituarios/:id',
        name: 'prescriptions.show',
        component: () => import('@/pages/prescriptions/PrescriptionShowPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { title: 'layout.sidebar.prescriptions', ability: 'prescription.view' },
    },
    {
        path: 'receituarios/:id/renovar',
        name: 'prescriptions.renew',
        component: () => import('@/pages/prescriptions/PrescriptionRenewPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { title: 'layout.sidebar.prescriptions', ability: 'prescription.create' },
    },
    // ─── Inbox ─────────────────────────────────────────────────────────────
    {
        path: 'inbox',
        name: 'inbox.index',
        component: () => import('@/pages/Inbox/Index.vue'),
        meta: { title: 'layout.sidebar.inbox.conversations', ability: 'inbox.view' },
    },
    {
        path: 'inbox/conversa/:id',
        name: 'inbox.conversation',
        component: () => import('@/pages/Inbox/Index.vue'),
        props: true,
        meta: { title: 'layout.sidebar.inbox.conversations', ability: 'inbox.view' },
    },
    {
        path: 'inbox/regras-atribuicao',
        name: 'inbox.regras_atribuicao',
        component: () => import('@/pages/Inbox/RegrasAtribuicao.vue'),
        meta: { title: 'layout.sidebar.inbox.assignment_rules', ability: 'inbox.assign' },
    },
    {
        path: 'inbox/respostas-rapidas',
        name: 'inbox.respostas_rapidas',
        component: () => import('@/pages/Inbox/RespostasRapidas.vue'),
        meta: { title: 'layout.sidebar.inbox.quick_replies', ability: 'inbox.view' },
    },
    // ─── Campanhas ─────────────────────────────────────────────────────────
    {
        path: 'campanhas',
        name: 'campaigns.index',
        component: () => import('@/pages/Campaigns/CampaignsIndexPage.vue'),
        meta: { title: 'layout.sidebar.campaigns', ability: 'campaign.create' },
    },
    {
        path: 'campanhas/nova',
        name: 'campaigns.create',
        component: () => import('@/pages/Campaigns/CampaignCreatePage.vue'),
        meta: { title: 'layout.sidebar.campaigns', ability: 'campaign.create' },
    },
    {
        path: 'campanhas/:id',
        name: 'campaigns.show',
        component: () => import('@/pages/Campaigns/CampaignShowPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { title: 'layout.sidebar.campaigns', ability: 'campaign.create' },
    },
    {
        path: 'campanhas/:id/relatorio',
        name: 'campaigns.report',
        component: () => import('@/pages/Campaigns/CampaignReportPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { title: 'layout.sidebar.campaigns', ability: 'campaign.create' },
    },
    // ─── Privacidade ───────────────────────────────────────────────────────
    {
        path: 'privacidade/consentimentos',
        name: 'privacy.consents',
        component: () => import('@/pages/Privacy/ConsentsPage.vue'),
        meta: { title: 'layout.sidebar.privacy.consents', ability: 'privacy.view' },
    },
    {
        path: 'privacidade/esquecimento',
        name: 'privacy.forgetting',
        component: () => import('@/pages/Privacy/ForgettingPage.vue'),
        meta: { title: 'layout.sidebar.privacy.forgetting', ability: 'privacy.view' },
    },
    {
        path: 'privacidade/portabilidade',
        name: 'privacy.portability',
        component: () => import('@/pages/Privacy/PortabilityPage.vue'),
        meta: { title: 'layout.sidebar.privacy.portability', ability: 'privacy.view' },
    },
    // ─── Integrações ───────────────────────────────────────────────────────
    {
        path: 'integracoes/webhooks',
        name: 'integrations.webhooks',
        component: () => import('@/pages/Integrations/WebhooksSettingsPage.vue'),
        meta: { title: 'layout.sidebar.integrations.webhooks', ability: 'webhook.manage' },
    },
    {
        path: 'integracoes/webhooks/dlq',
        name: 'integrations.webhooks.dlq',
        component: () => import('@/pages/Integrations/WebhookDeliveriesPage.vue'),
        meta: { title: 'layout.sidebar.integrations.dlq', ability: 'webhook.manage' },
    },
    {
        path: 'integracoes/api-tokens',
        name: 'integrations.api_tokens',
        component: () => import('@/pages/Integrations/ApiTokensSettingsPage.vue'),
        meta: { title: 'layout.sidebar.integrations.api_tokens', ability: 'api_token.manage' },
    },
    // ─── Relatórios ────────────────────────────────────────────────────────
    {
        path: 'relatorios/executivo',
        name: 'reports.executive',
        component: () => import('@/pages/Reports/ExecutiveDashboardPage.vue'),
        meta: { title: 'layout.sidebar.reports.executive', ability: 'report.view' },
    },
    {
        path: 'relatorios/operacional',
        name: 'reports.operational',
        component: () => import('@/pages/Reports/OperationalReportPage.vue'),
        meta: { title: 'layout.sidebar.reports.operational', ability: 'report.view' },
    },
    {
        path: 'relatorios/clinico',
        name: 'reports.clinical',
        component: () => import('@/pages/Reports/ClinicalReportPage.vue'),
        meta: { title: 'layout.sidebar.reports.clinical', ability: 'report.view' },
    },
    // Catch-all dentro do shell
    {
        path: ':pathMatch(.*)*',
        name: 'panel.catchAll',
        component: () => import('@/pages/PanelHome.vue'),
    },
];

const routes = [
    {
        path: '/',
        redirect: '/panel',
    },

    // ─── Autenticação ───────────────────────────────────────────────────────────
    {
        path: '/login',
        name: 'auth.login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/forgot-password',
        name: 'auth.forgot',
        component: () => import('@/pages/auth/ForgotPasswordPage.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/reset-password/:token',
        name: 'auth.reset',
        component: () => import('@/pages/auth/ResetPasswordPage.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/register-clinic',
        name: 'tenant.register',
        component: () => import('@/pages/tenant-register/RegisterTenantPage.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/accept-invitation',
        name: 'users.accept',
        component: () => import('@/pages/invitations/AcceptInvitationPage.vue'),
        meta: { requiresGuest: false },
    },

    // ─── Onboarding (FULLSCREEN — fora do shell, decisão deliberada) ───────────
    {
        path: '/panel/onboarding',
        name: 'panel.onboarding',
        component: () => import('@/pages/onboarding/OnboardingWizardPage.vue'),
        meta: { requiresAuth: true },
    },

    // ─── Painel autenticado (envolto por AppShell) ─────────────────────────────
    {
        path: '/panel',
        component: AppShell,
        meta: { requiresAuth: true },
        children: panelChildren,
    },

    // ─── Pública (LGPD) ────────────────────────────────────────────────────────
    {
        path: '/privacidade/esquecimento/publico',
        name: 'privacy.public_forgetting',
        component: () => import('@/pages/Privacy/PublicForgettingRequestPage.vue'),
        meta: { requiresGuest: false, title: 'Solicitar Esquecimento' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

/**
 * Guard global de autenticação.
 *
 * - requiresAuth sem sessão local: tenta fetchMe (rehidrata sessão via cookie).
 *   Se o cookie ainda é válido, o usuário segue para a rota.
 *   Se fetchMe falhar (401), redireciona para /login com ?redirect=<destino>.
 *
 * - requiresGuest com sessão ativa: redireciona para /panel
 *   (evita que usuário logado acesse /login).
 */
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        try {
            await auth.fetchMe();
        } catch {
            return { name: 'auth.login', query: { redirect: to.fullPath } };
        }
    }

    if (to.meta.requiresGuest && auth.isAuthenticated) {
        return { path: '/panel' };
    }

    // Enforce de permissão em rotas gated (`meta.ability` / `meta.anyOf`).
    // Sem isto, rotas gated eram alcançáveis por URL direta — só a sidebar
    // escondia o link. O backend ainda devolve 403, mas a página renderizava
    // com empty state enganoso. Redireciona para a home do painel.
    if (auth.isAuthenticated) {
        const { ability, anyOf } = to.meta ?? {};
        const lacksAbility = ability && !auth.hasPermission(ability);
        const lacksAnyOf =
            Array.isArray(anyOf) &&
            anyOf.length > 0 &&
            !anyOf.some((permission) => auth.hasPermission(permission));

        if (lacksAbility || lacksAnyOf) {
            return { name: 'panel.home' };
        }
    }

    // Onboarding é gerenciável apenas por admin-clinica / super-admin
    // (OnboardingPolicy::manage). Demais perfis (ex.: médico) não devem ser
    // redirecionados nem alcançar a tela — senão tomam 403 em /onboarding/state.
    const canManageOnboarding =
        auth.isAuthenticated && (auth.hasRole('admin-clinica') || auth.hasRole('super-admin'));

    // Auto-redirect para onboarding quando tenant ainda não concluiu o setup.
    if (
        to.name === 'panel.home' &&
        canManageOnboarding &&
        auth.tenant?.onboarding_completed === false
    ) {
        return { name: 'panel.onboarding' };
    }

    // Não-gestores que tentam abrir o wizard por URL direta voltam à home.
    if (to.name === 'panel.onboarding' && auth.isAuthenticated && !canManageOnboarding) {
        return { name: 'panel.home' };
    }
});

/**
 * Atualiza `document.title` com `{tenantName} — {pageTitle}` baseado em
 * `to.meta.title` (i18n key ou string literal) — cumpre US-6 / FR-011.
 */
router.afterEach((to) => {
    if (typeof document === 'undefined') {
        return;
    }
    const auth = useAuthStore();
    const tenantName = auth.tenant?.name ?? 'Paciente360';
    let pageTitle = '';

    const metaTitle = to.meta?.title;
    if (typeof metaTitle === 'function') {
        try {
            pageTitle = metaTitle(to) ?? '';
        } catch {
            pageTitle = '';
        }
    } else if (typeof metaTitle === 'string') {
        // Pode ser uma chave i18n (ex.: 'layout.sidebar.agenda.calendar')
        // ou uma string literal.
        if (metaTitle.includes('.')) {
            try {
                const translated = i18n.global.t(metaTitle);
                pageTitle = translated === metaTitle ? metaTitle : translated;
            } catch {
                pageTitle = metaTitle;
            }
        } else {
            pageTitle = metaTitle;
        }
    } else {
        // Fallback: lookup pela árvore de navegação (static, sem Vue setup context).
        const key = findLabelKeyForRoute(to.name);
        if (key) {
            try {
                pageTitle = i18n.global.t(key);
            } catch {
                pageTitle = '';
            }
        }
    }

    document.title = pageTitle ? `${tenantName} — ${pageTitle}` : tenantName;
});

export default router;
