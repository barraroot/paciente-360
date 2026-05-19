import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const PanelPlaceholder = {
    template: `
        <main class="flex min-h-screen items-center justify-center bg-surface text-foreground">
            <div class="text-center">
                <h1 class="text-2xl font-semibold">Paciente 360</h1>
                <p class="mt-2 text-foreground-muted">Painel em construção.</p>
            </div>
        </main>
    `,
};

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

    // ─── Painel (requer autenticação) ───────────────────────────────────────────
    {
        path: '/panel',
        name: 'panel.home',
        component: PanelPlaceholder,
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/onboarding',
        name: 'panel.onboarding',
        component: () => import('@/pages/onboarding/OnboardingWizardPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/billing/plans',
        name: 'billing.plans',
        component: () => import('@/pages/billing/PlansPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/billing/subscription',
        name: 'billing.subscription',
        component: () => import('@/pages/billing/SubscriptionPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/billing/ai-usage',
        name: 'billing.ai-usage',
        component: () => import('@/pages/billing/AiUsagePage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/users',
        name: 'users.list',
        component: () => import('@/pages/users/UsersListPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/users/invite',
        name: 'users.invite',
        component: () => import('@/pages/users/InviteUserPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/accept-invitation',
        name: 'users.accept',
        component: () => import('@/pages/invitations/AcceptInvitationPage.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/panel/audit-logs',
        name: 'audit.list',
        component: () => import('@/pages/audit/AuditLogsPage.vue'),
        meta: { requiresAuth: true },
    },
    // ─── Agenda (US-6.3 / US-6.4 / US-6.6) ────────────────────────────────────
    {
        path: '/panel/agenda',
        name: 'agenda.index',
        component: () => import('@/pages/agenda/AgendaPage.vue'),
        meta: { requiresAuth: true, title: 'Agenda' },
    },
    {
        path: '/panel/agenda/lista-espera',
        name: 'agenda.waitlist',
        component: () => import('@/pages/agenda/WaitlistPage.vue'),
        meta: { requiresAuth: true, title: 'Lista de Espera' },
    },

    // ─── Pacientes ──────────────────────────────────────────────────────────────
    {
        path: '/panel/pacientes',
        name: 'pacientes.list',
        component: () => import('@/pages/pacientes/PacientesListPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/pacientes/novo',
        name: 'pacientes.create',
        component: () => import('@/pages/pacientes/PacienteFormPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/pacientes/mesclagem',
        name: 'pacientes.mesclagem',
        component: () => import('@/pages/pacientes/MesclagemPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/pacientes/:id',
        name: 'pacientes.show',
        component: () => import('@/pages/pacientes/PacienteShowPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/pacientes/:id/editar',
        name: 'pacientes.edit',
        component: () => import('@/pages/pacientes/PacienteFormPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { requiresAuth: true },
    },
    // US4 — Funil Kanban
    {
        path: '/panel/pacientes/funil',
        name: 'pacientes.funil.kanban',
        component: () => import('@/pages/pacientes/FunilKanbanPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/pacientes/funil/config',
        name: 'pacientes.funil.config',
        component: () => import('@/pages/pacientes/FunilConfigPage.vue'),
        meta: { requiresAuth: true },
    },
    // US3 — Importação em massa
    {
        path: '/panel/pacientes/importar',
        name: 'pacientes.import.upload',
        component: () => import('@/pages/pacientes/ImportacaoPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/panel/pacientes/importacao/:id',
        name: 'pacientes.import.status',
        component: () => import('@/pages/pacientes/ImportacaoStatusPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { requiresAuth: true },
    },

    // ─── Configurações → Sessões (US2 — Bearer token management) ──────────────
    {
        path: '/panel/configuracoes/sessoes',
        name: 'auth.tokens',
        component: () => import('@/pages/auth/TokensPage.vue'),
        meta: { requiresAuth: true },
    },

    // ─── Canais (Omnichannel Inbox — US1) ──────────────────────────────────────
    {
        path: '/panel/canais',
        name: 'canais.index',
        component: () => import('@/pages/Canais/Index.vue'),
        meta: { requiresAuth: true, ability: 'inbox.view', title: 'Canais' },
    },
    {
        path: '/panel/canais/conectar-whatsapp',
        name: 'canais.conectar_whatsapp',
        component: () => import('@/pages/Canais/ConectarWhatsApp.vue'),
        meta: {
            requiresAuth: true,
            ability: 'channel.connect',
            title: 'Conectar WhatsApp',
        },
    },
    {
        path: '/panel/canais/conectar-instagram',
        name: 'canais.conectar_instagram',
        component: () => import('@/pages/Canais/ConectarInstagram.vue'),
        meta: {
            requiresAuth: true,
            ability: 'channel.connect',
            title: 'Conectar Instagram',
        },
    },
    {
        path: '/panel/canais/:id',
        name: 'canais.show',
        component: () => import('@/pages/Canais/Detalhe.vue'),
        props: true,
        meta: {
            requiresAuth: true,
            ability: 'inbox.view',
            title: 'Detalhes do canal',
        },
    },

    // ─── Receituários (US-8.1 — Fase 7) ───────────────────────────────────────────────
    {
        path: '/panel/receituarios',
        name: 'prescriptions.index',
        component: () => import('@/pages/prescriptions/PrescriptionsListPage.vue'),
        meta: { requiresAuth: true, ability: 'prescription.view', title: 'Receituários' },
    },
    {
        path: '/panel/receituarios/novo',
        name: 'prescriptions.create',
        component: () => import('@/pages/prescriptions/PrescriptionCreatePage.vue'),
        meta: { requiresAuth: true, ability: 'prescription.create', title: 'Nova Receita' },
    },
    {
        path: '/panel/receituarios/:id',
        name: 'prescriptions.show',
        component: () => import('@/pages/prescriptions/PrescriptionShowPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { requiresAuth: true, ability: 'prescription.view', title: 'Detalhe da Receita' },
    },
    {
        path: '/panel/receituarios/:id/renovar',
        name: 'prescriptions.renew',
        component: () => import('@/pages/prescriptions/PrescriptionRenewPage.vue'),
        props: (route) => ({ id: route.params.id }),
        meta: { requiresAuth: true, ability: 'prescription.create', title: 'Renovar Receita' },
    },

    // ─── Inbox Unificada (Omnichannel — US4) ───────────────────────────────────
    {
        path: '/panel/inbox',
        name: 'inbox.index',
        component: () => import('@/pages/Inbox/Index.vue'),
        meta: { requiresAuth: true, ability: 'inbox.view', title: 'Inbox' },
    },
    {
        path: '/panel/inbox/conversa/:id',
        name: 'inbox.conversation',
        component: () => import('@/pages/Inbox/Index.vue'),
        props: true,
        meta: { requiresAuth: true, ability: 'inbox.view', title: 'Conversa' },
    },
    // US-4.5 — Regras de atribuição automática
    {
        path: '/panel/inbox/regras-atribuicao',
        name: 'inbox.regras_atribuicao',
        component: () => import('@/pages/Inbox/RegrasAtribuicao.vue'),
        meta: {
            requiresAuth: true,
            ability: 'inbox.assign',
            title: 'Regras de Atribuição',
        },
    },
    // US-4.7 — Respostas Rápidas
    {
        path: '/panel/inbox/respostas-rapidas',
        name: 'inbox.respostas_rapidas',
        component: () => import('@/pages/Inbox/RespostasRapidas.vue'),
        meta: {
            requiresAuth: true,
            ability: 'inbox.view',
            title: 'Respostas Rápidas',
        },
    },

    {
        path: '/panel/:pathMatch(.*)*',
        name: 'panel.catchAll',
        component: PanelPlaceholder,
        meta: { requiresAuth: true },
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

    // Auto-redirect para onboarding quando tenant ainda não concluiu o setup.
    // Só aplica em /panel (exato) para não criar loops — /panel/onboarding e
    // demais sub-rotas do painel ficam isentas.
    if (
        to.name === 'panel.home' &&
        auth.isAuthenticated &&
        auth.tenant?.onboarding_completed === false
    ) {
        return { name: 'panel.onboarding' };
    }
});

export default router;
