/**
 * Inventário de telas auditáveis (feature 016 · T007).
 *
 * Derivado de `resources/js/router/index.js` + `resources/js/config/navigation.js`.
 * Rotas `:id`/`edit` que exigem registro específico ficam fora do sweep automatizado
 * (cobertas pelas list pages que compartilham os mesmos componentes + revisão manual).
 */
export type Auditable = {
    path: string;
    name: string;
    priority: 'P1' | 'P2';
    auth: boolean;
};

export const PUBLIC_ROUTES: Auditable[] = [
    { path: '/login', name: 'auth.login', priority: 'P1', auth: false },
    { path: '/forgot-password', name: 'auth.forgot', priority: 'P1', auth: false },
    { path: '/register-clinic', name: 'tenant.register', priority: 'P1', auth: false },
];

export const P1_ROUTES: Auditable[] = [
    { path: '/panel', name: 'panel.home', priority: 'P1', auth: true },
    { path: '/panel/inbox', name: 'inbox.index', priority: 'P1', auth: true },
    { path: '/panel/canais', name: 'canais.index', priority: 'P1', auth: true },
    { path: '/panel/agenda', name: 'agenda.index', priority: 'P1', auth: true },
    { path: '/panel/agenda/lista-espera', name: 'agenda.waitlist', priority: 'P1', auth: true },
    { path: '/panel/agenda/tipos', name: 'agenda.types.index', priority: 'P1', auth: true },
    { path: '/panel/agenda/horarios', name: 'agenda.schedule.index', priority: 'P1', auth: true },
    { path: '/panel/agenda/sincronizacao', name: 'agenda.sync.index', priority: 'P1', auth: true },
    { path: '/panel/pacientes', name: 'pacientes.list', priority: 'P1', auth: true },
    { path: '/panel/pacientes/novo', name: 'pacientes.create', priority: 'P1', auth: true },
    { path: '/panel/pacientes/funil', name: 'pacientes.funil.kanban', priority: 'P1', auth: true },
    { path: '/panel/pacientes/mesclagem', name: 'pacientes.mesclagem', priority: 'P1', auth: true },
    { path: '/panel/pacientes/importar', name: 'pacientes.import.upload', priority: 'P1', auth: true },
    { path: '/panel/receituarios', name: 'prescriptions.index', priority: 'P1', auth: true },
    { path: '/panel/receituarios/novo', name: 'prescriptions.create', priority: 'P1', auth: true },
    { path: '/panel/receituarios/relatorio', name: 'prescriptions.report', priority: 'P1', auth: true },
    { path: '/panel/relatorios/executivo', name: 'reports.executive', priority: 'P1', auth: true },
];

export const P2_ROUTES: Auditable[] = [
    { path: '/panel/campanhas', name: 'campaigns.index', priority: 'P2', auth: true },
    { path: '/panel/campanhas/nova', name: 'campaigns.create', priority: 'P2', auth: true },
    { path: '/panel/relatorios/operacional', name: 'reports.operational', priority: 'P2', auth: true },
    { path: '/panel/relatorios/clinico', name: 'reports.clinical', priority: 'P2', auth: true },
    { path: '/panel/integracoes/webhooks', name: 'integrations.webhooks', priority: 'P2', auth: true },
    { path: '/panel/integracoes/webhooks/dlq', name: 'integrations.webhooks.dlq', priority: 'P2', auth: true },
    { path: '/panel/integracoes/api-tokens', name: 'integrations.api_tokens', priority: 'P2', auth: true },
    { path: '/panel/privacidade/consentimentos', name: 'privacy.consents', priority: 'P2', auth: true },
    { path: '/panel/privacidade/esquecimento', name: 'privacy.forgetting', priority: 'P2', auth: true },
    { path: '/panel/privacidade/portabilidade', name: 'privacy.portability', priority: 'P2', auth: true },
    { path: '/panel/configuracoes/sessoes', name: 'auth.tokens', priority: 'P2', auth: true },
    { path: '/panel/profissionais', name: 'professionals.list', priority: 'P2', auth: true },
    { path: '/panel/inbox/regras-atribuicao', name: 'inbox.regras_atribuicao', priority: 'P2', auth: true },
    { path: '/panel/inbox/respostas-rapidas', name: 'inbox.respostas_rapidas', priority: 'P2', auth: true },
    { path: '/panel/ia/personas', name: 'ia.personas.index', priority: 'P2', auth: true },
    { path: '/panel/ia/matriz', name: 'ia.matriz.index', priority: 'P2', auth: true },
    { path: '/panel/ia/bases', name: 'ia.bases.index', priority: 'P2', auth: true },
    { path: '/panel/ia/guardrails', name: 'ia.guardrails.index', priority: 'P2', auth: true },
    { path: '/panel/ia/logs', name: 'ia.logs.index', priority: 'P2', auth: true },
];

export const ALL_ROUTES: Auditable[] = [...PUBLIC_ROUTES, ...P1_ROUTES, ...P2_ROUTES];
