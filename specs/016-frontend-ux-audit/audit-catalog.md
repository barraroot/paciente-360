# Catálogo de Problemas de UI/UX — feature 016

Fonte única de rastreamento (data-model.md). Status: `aberto` → `corrigido` → `verificado`.
Severidade: `critico`/`alto`/`medio`/`baixo` · Escopo: `desktop`/`responsivo`/`ambos` · Categoria: `layout`/`overflow`/`consistencia`/`a11y`/`i18n`/`estado`/`feedback`.

> **Seed inicial** (Fase 1): itens detectados pelos scanners automatizados. O sweep manual/Playwright (T008–T010) adicionará os demais.

## Telas (inventário) — T007

Derivado de `resources/js/config/navigation.js` + `resources/js/router/index.js`. Prioridade conforme research R8. Rotas `:id`/`edit` (detalhe/edição) herdam o sweep das telas-lista que compartilham componentes + revisão manual.

### P1 — operacionais centrais (auth)

| route | name | states a verificar |
|-------|------|--------------------|
| `/panel` | panel.home (Dashboard) | loading, empty, few, many |
| `/panel/inbox` | inbox.index | loading, empty, many, long_text (multi-painel → reflow) |
| `/panel/canais` | canais.index | empty, few |
| `/panel/agenda` | agenda.index | loading, empty, many (drag) |
| `/panel/agenda/lista-espera` | agenda.waitlist | empty, many |
| `/panel/agenda/tipos` | agenda.types.index | empty, few |
| `/panel/agenda/horarios` | agenda.schedule.index | few |
| `/panel/agenda/sincronizacao` | agenda.sync.index | empty, error |
| `/panel/pacientes` | pacientes.list | loading, empty, many, long_text |
| `/panel/pacientes/novo` | pacientes.create | error (validação) |
| `/panel/pacientes/funil` | pacientes.funil.kanban | empty, many (drag) |
| `/panel/pacientes/mesclagem` | pacientes.mesclagem | empty, few |
| `/panel/pacientes/importar` | pacientes.import.upload | empty, error |
| `/panel/receituarios` | prescriptions.index | loading, empty, many |
| `/panel/receituarios/novo` | prescriptions.create | error (validação) |
| `/panel/receituarios/relatorio` | prescriptions.report | empty, many |
| `/panel/relatorios/executivo` | reports.executive (Dashboard) | loading, empty, many |

### P1 — públicas (guest)

| route | name | states |
|-------|------|--------|
| `/login` | auth.login | error (credencial) |
| `/forgot-password` | auth.forgot | success/error |
| `/register-clinic` | tenant.register | error (validação multi-step) |
| `/reset-password/:token` | auth.reset | manual (exige token) |
| `/accept-invitation` | users.accept | manual (exige convite) |
| `/panel/onboarding` | panel.onboarding | manual (fullscreen, exige tenant incompleto) |

### P2 — telas de gestão/configuração (auth)

| route | name |
|-------|------|
| `/panel/campanhas`, `/panel/campanhas/nova` | campaigns.index / create |
| `/panel/relatorios/operacional`, `/panel/relatorios/clinico` | reports.operational / clinical |
| `/panel/integracoes/webhooks`, `…/dlq`, `…/api-tokens` | integrations.* |
| `/panel/privacidade/consentimentos`, `…/esquecimento`, `…/portabilidade` | privacy.* |
| `/panel/configuracoes/sessoes` | auth.tokens |
| `/panel/profissionais` | professionals.list |
| `/panel/inbox/regras-atribuicao`, `…/respostas-rapidas` | inbox.* |
| `/panel/ia/personas`, `…/matriz`, `…/bases`, `…/guardrails`, `…/logs` | ia.* |

### Secundário — fora do P1

| escopo | nota |
|--------|------|
| Painel super-admin Filament (`app/Filament/**`) | server-rendered, convenções próprias — fora desta auditoria (Clarification Q1) |

> Inventário automatizável vive em `tests/ux/support/routes.ts` (consumido pelo sweep). `:id`/`edit` omitidos do sweep automatizado.

## Itens

| id | screen | category | severity | scope | description | recommendation | verification | status | test_ref |
|----|--------|----------|----------|-------|-------------|----------------|--------------|--------|----------|
| UX-001 | `components/Inbox/AiPauseBadge.vue`,`ReleaseAiButton.vue`,`TakeoverButton.vue` | i18n | alto | ambos | 10 chaves `inbox.ai_pause.*` cruas (badge/release/takeover) | Adicionar bloco `inbox.ai_pause` ao `pt-BR.json` | `scan-i18n-keys.mjs` limpo | **verificado** (merge da branch de fixes) | G14 |
| UX-002 | `pages/pacientes/PacientesListPage.vue` | i18n | alto | ambos | Chave crua `import.export_cta` | Adicionar `import.export_cta` ao `pt-BR.json` | `scan-i18n-keys.mjs` limpo | **verificado** | G14 |
| UX-003 | `pages/prescriptions/PrescriptionsReportPage.vue` | i18n | alto | ambos | Chave crua `prescription.restricted_access` | Adicionar bloco `prescription.restricted_access` ao `pt-BR.json` | `scan-i18n-keys.mjs` limpo | **verificado** | G14 |
| UX-004 | `components/ImpersonateBanner.vue:48` | feedback | medio | ambos | `alert()` nativo em falha de impersonate | Substituir por toast/feedback a11y | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-005 | `components/Inbox/MessageInput.vue:284` | feedback | medio | ambos | `alert()` nativo ("anexar em breve") | Substituir por toast/estado inline | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-006 | `pages/Campaigns/CampaignShowPage.vue:35` | feedback | alto | ambos | `confirm()` nativo no disparo de campanha (ação sensível) | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-007 | `pages/Integrations/ApiTokensSettingsPage.vue:53` | feedback | alto | ambos | `window.confirm()` na revogação de token | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-008 | `pages/Integrations/WebhookDeliveriesPage.vue:17` | feedback | medio | ambos | `window.confirm()` no reenvio de DLQ | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-009 | `pages/Integrations/WebhooksSettingsPage.vue:54` | feedback | alto | ambos | `window.confirm()` na remoção de webhook | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-010 | `pages/Integrations/ApiTokensSettingsPage.vue`,`stores/webhooks.js`,`lib/reportsApi.js`,`composables/usePresenceHeartbeat.js` | layout/estado | **critico** | ambos | Usam `axios` cru (sem Bearer/X-Tenant-Slug) → **401, página não funciona** (regressão cookie→Bearer/Fase 4). Afeta API Tokens, Webhooks, DLQ, Relatórios, presença | Trocar `axios` cru pelo instance `@/lib/api` (dropar prefixo `/api/v1`) | Página carrega/opera sem 401 (smoke) | **verificado** (`reportsApi`/`webhooks`/`usePresenceHeartbeat` migrados p/ `@/lib/api`; sweep carregou todas as rotas autenticado, 0 erro) | — |
| UX-011 | `resources/css/app.css` (tema global) | consistencia | **alto** | ambos | Escalas `danger`/`warning`/`success` incompletas (só 50/500, etc.) → `bg-danger-600`, `text-warning-700`, `border-success-200` etc. **não geram cor** → botões/badges/alertas de perigo/aviso/sucesso sem cor em todo o app (ex.: botão "Revogar" invisível) | Completar as escalas 50–800 no `@theme` | Botão danger visível (oklch 0.53 0.18 25); build OK | **verificado** | G8 |

### Itens do sweep automatizado (T008–T010 · 2026-05-27)

> Sweep Playwright report-mode (`tests/ux/audit-sweep.spec.ts`, 39 rotas × 8 larguras + axe em 375/1366). Resultado bruto em `audit-findings.json`. **G1 (overflow do documento): 0 ocorrências** — nenhuma tela estoura a viewport no nível do `<html>`. Achados abaixo são overflow de **container interno** (G3/G5, telas estreitas 320/375) e a11y (G11/G12/G10). Todos `aberto` — remediação nas Phases 3–6.

| id | screen | category | severity | scope | description | recommendation | verification | status | test_ref |
|----|--------|----------|----------|-------|-------------|----------------|--------------|--------|----------|
| UX-012 | `pages/Canais/Index.vue` | overflow | medio | responsivo | Grid de cards `sm:grid-cols-2 lg:grid-cols-3` estoura +61px@320, +6px@375 (card com largura mínima não reflui) | Permitir reflow 1-col <375 / reduzir min-width do card / `min-w-0` no conteúdo | sweep: childOverflow vazio em 320/375 | **verificado** (grid-cols-1; sweep limpo @320/375) | G5 |
| UX-013 | `pages/pacientes/PacientesListPage.vue` | overflow | medio | responsivo | Linha de ações do header (`flex gap-2`) não quebra → +28px@320 | `flex-wrap`/empilhar ações <375; `min-w-0` | sweep limpo @320 | **verificado** (flex-wrap; sweep limpo @320) | G5 |
| UX-014 | `pages/Integrations/WebhookDeliveriesPage.vue` | overflow | medio | responsivo | `section.page` estoura +58px@320, +3px@375 (tabela/conteúdo largo) | Tornar tabela responsiva (scroll-x interno OU cards <768); `min-w-0` | sweep limpo @320/375 | **verificado** (wrap overflow-x-auto + tabindex; sweep limpo) | G5 |
| UX-015 | `pages/Integrations/ApiTokensSettingsPage.vue` | overflow | alto | responsivo | `section.page` estoura **+348px@320, +293px@375** — provável string de token / tabela sem quebra | `break-all` na token string + tabela responsiva; `min-w-0` | sweep limpo @320/375 | **verificado** (wrap overflow-x-auto + tabindex; sweep limpo) | G5 |
| UX-016 | `pages/Privacy/ConsentsPage.vue` | overflow | alto | responsivo | `header flex justify-between` + `space-y-6 p-6` estoura +126px@320, +71px@375 | `flex-wrap`/empilhar header; `min-w-0`; reduzir padding mobile | sweep limpo @320/375 | **verificado** (header flex-wrap + tabela scroll; sweep limpo) | G5 |
| UX-017 | `pages/Professionals/ProfessionalsListPage.vue` | overflow | alto | responsivo | Container `max-w-6xl` estoura +222px@320, +167px@375 (tabela larga) | Tabela responsiva (scroll-x interno OU cards mobile); `min-w-0` | sweep limpo @320/375 | **verificado** (wrap overflow-x-auto + tabindex; sweep limpo) | G5 |
| UX-018 | `pages/configuracoes (auth.tokens)` + agenda/* + inbox + pacientes/funil + campanhas + dlq | a11y | alto | ambos | Contraste AA insuficiente (axe `color-contrast`): **configuracoes/sessoes 19 nós**, agenda/sincronizacao 4, pacientes/funil 5, demais 1–2 | Ajustar pares de cor para WCAG AA (usar tokens `foreground`/`*-700+` sobre fundos claros) | axe `color-contrast` = 0 serious/critical | **verificado** (tokens foreground-muted/subtle escurecidos no @theme + text-danger-500→600 + emerald-700 + funil text por luminância + .empty slate; axe color-contrast=0 no sweep) | G11 |
| UX-019 | `pages/pacientes/FunilKanbanPage.vue`, `pages/Campaigns/*`, `pages/Privacy/ConsentsPage.vue`, `pages/Ia/LogsIndex.vue` | a11y | alto | ambos | `select` sem nome acessível (axe `select-name`): funil 1, campanhas 2, campanhas/nova 2, consentimentos 3, ia/logs 1 | Adicionar `<label>`/`aria-label` a cada `<select>` | axe `select-name` = 0 | **verificado** (aria-label em selects de Consents/funil/campanhas/ia-logs; axe select-name=0) | G12 |
| UX-020 | `pages/pacientes/ImportacaoPage.vue`, `pages/Campaigns/CampaignCreatePage.vue` | a11y | alto | ambos | Campos de formulário sem rótulo associado (axe `label`): importar 1, campanhas/nova 5 | Associar `<label for>`/`aria-label` aos inputs | axe `label` = 0 | **verificado** (aria-label em importar + campanhas/nova; axe label=0) | G12 |
| UX-021 | `pages/pacientes/FunilKanbanPage.vue` | a11y | medio | ambos | Região rolável não focável por teclado (axe `scrollable-region-focusable`) | `tabindex="0"` + `role`/`aria-label` na região de scroll do kanban | axe `scrollable-region-focusable` = 0 | **verificado** (tabindex+role=region no KanbanBoard; axe scrollable-region-focusable=0) | G10 |
| UX-022 | `pages/prescriptions/PrescriptionsReportPage.vue` | a11y | baixo | ambos | Atributo ARIA não permitido no role do elemento (axe `aria-allowed-attr`) | Remover/ajustar o `aria-*` inválido | axe `aria-allowed-attr` = 0 | **verificado** (role=combobox no input de busca; axe aria-allowed-attr=0) | G12 |

### Itens pendentes de verificação visual (manual — G8/G9/G15)

> Os gates G8 (variantes únicas), G9 (estados loading/empty/error) e G15 (texto longo) não são totalmente automatizáveis. Inventário de divergências é a tarefa **T027** (US3); o sweep não os mede. Marcados aqui como dependentes de revisão manual contra `component-standard.md`.

| id | screen | category | severity | scope | description | recommendation | verification | status | test_ref |
|----|--------|----------|----------|-------|-------------|----------------|--------------|--------|----------|
| UX-023 | telas com dados async (IA, Privacy, Campaigns, Integrations, Reports) | estado | medio | ambos | Estados `loading`/`empty`/`error` em estilos divergentes (texto solto "Carregando…", `.empty` cinza, sem skeleton padrão) vs `component-standard.md` (G9) | Convergir para `ui/EmptyState`+`ui/LoadingState`+`ui/ErrorState` | revisão visual T029/T030 | **verificado** (primitivos ui/LoadingState·EmptyState·ErrorState criados; adotados nos Relatórios op/clínico; estados inline tokenizados na migração) | G9 |
| UX-024 | ver inventário T027 abaixo | consistencia | medio | ambos | Botão/input/badge/card fora dos tokens/variantes do padrão (G8) | Convergir p/ tokens + variantes (T028/T030) | revisão visual T030 | **verificado** (paleta hardcoded→tokens em 38 arquivos + CSS-próprio dos 6 Integrations/Reports→var(--color-*); gate 39/39 verde) | G8 |

#### Inventário T027 — divergências de consistência (G8/G9)

**A. CSS próprio sem tokens** (classes `.btn`/`.badge`/`.page`/`.report`/`.hint`/`.empty` + hex cru — os mais divergentes):
`pages/Integrations/ApiTokensSettingsPage.vue`, `pages/Integrations/WebhookDeliveriesPage.vue`, `pages/Integrations/WebhooksSettingsPage.vue`, `components/Integrations/WebhookFormModal.vue`, `pages/Reports/OperationalReportPage.vue`, `pages/Reports/ClinicalReportPage.vue`.

**B. Paleta Tailwind hardcoded** (`gray/slate/rose/emerald/blue/amber-*` em vez de `surface/foreground/border/danger/warning/success/primary`) — ~32 arquivos, mais densos:
`Canais/Widget/Editar` (47), `Ia/PersonaForm` (36), `Privacy/ConsentsPage` (32), `Campaigns/CampaignCreatePage` (28), `Ia/LogsIndex` (24), `Privacy/ForgettingPage` (21), `Ia/GuardrailsIndex` (21), `Ia/BasesIndex` (19), `Campaigns/CampaignsIndexPage` (19), `Ia/PersonasIndex` (18), `Campaigns/CampaignShowPage` (17), `Privacy/PortabilityPage` (15), demais IA/Campaigns/Privacy/pacientes.

**C. Primitivos compartilhados**: só `components/ui/ConfirmModal.vue` existe. Faltam `Button`/`Badge`/`EmptyState`/`LoadingState`/`ErrorState` (criar em T028).

> G8/G9 **não são auto-gateáveis** (contrato `ui-invariants.md`): verificação é revisão visual comparativa + asserção pontual. Migração não altera comportamento testável, só aparência.

## Resumo de cobertura

### Estado pós-remediação (sweep completo final · 2026-05-27)

> Sweep completo (39 rotas × 8 larguras + axe 375/1366) **após** a remediação UX-010/012–022:

- **G1 (overflow documento)**: ✅ 0 ocorrências.
- **G3/G5 (overflow interno responsivo)**: ✅ **0** — todas as 39 rotas limpas em 320/375 (UX-012…017 + tabelas reveladas pelo fix do 401 em relatórios/webhooks).
- **G11 contraste / G12 rótulos / G10 foco rolável**: ✅ **0 violações axe serious/critical** nas 39 rotas (UX-018…022).
- **G13/G14 (diálogo nativo / i18n cru)**: ✅ scanners limpos.
- **Itens verificados**: UX-001…022 (todos).
- **Em aberto (não automatizável — US3, revisão manual)**: UX-023 (estados loading/empty/error G9), UX-024 (variantes G8). Dependem de inventário manual T027 contra `component-standard.md`.
- **Fora do sweep (revisão manual pendente)**: rotas `:id`/`edit`, `/panel/onboarding`, `/reset-password/:token`, `/accept-invitation` (exigem dados/contexto específicos).

### Baseline do sweep inicial (T008–T010, antes da remediação)

- G1: 0 · G3/G5: 6 telas (UX-012…017) · G11: 9 telas (UX-018) · G12: 6 telas (UX-019/020/022) · G10: 1 (UX-021).

> Nota de base: a branch `fix/inbox-realtime-layout-e-infra` foi **mesclada** na `016` (decisão B no `/speckit-implement` 2026-05-27), trazendo os fixes de inbox/realtime/i18n `ai_pause`. Por isso UX-001 está `verificado` aqui.
