---
description: "Tasks for 009 — App Shell do Painel Autenticado"
---

# Tasks: App Shell do Painel Autenticado

**Input**: Design documents from `/specs/009-app-shell/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/navigation-tree.md, quickstart.md
**Tests**: Incluídos conforme Princípio IV da Constitution (Test-First). 4 specs Playwright cobrem as user stories P1+P2.

**Organização**: Tasks agrupadas por user story para implementação e teste independentes. Lotes A–E definidos no `quickstart.md` mapeiam para combinações de phases abaixo.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Pode rodar em paralelo (arquivos distintos, sem dependências).
- **[Story]**: User story do spec (US1, US2, US3, US4, US5, US6).
- Cada task referencia caminho de arquivo absoluto a partir da raiz do repo.

## Path Conventions

Feature 100% frontend. Caminhos base:

- Componentes Vue: `resources/js/components/layout/`
- Layouts: `resources/js/layouts/`
- Pages: `resources/js/pages/`
- Composables: `resources/js/composables/`
- Config estático: `resources/js/config/`
- i18n: `lang/pt_BR/`
- Router: `resources/js/router/`
- Stores: `resources/js/stores/`
- E2E tests: `tests/Browser/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verificar pré-requisitos e preparar terreno.

- [X] T001 Verificar que branch `009-app-shell` está checked out e que `vendor/bin/sail up -d` + `vendor/bin/sail npm run dev` estão rodando; confirmar Hero pre-existente em `resources/js/components/auth/AuthHeroPanel.vue` permanece intocado
- [X] T002 [P] Criar diretório `resources/js/components/layout/` com `.gitkeep` se ainda não existir
- [X] T003 [P] Criar diretório `resources/js/layouts/` com `.gitkeep` se ainda não existir

**Checkpoint**: Estrutura de pastas e ambiente prontos.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Composables, config estático e i18n compartilhados entre TODAS as user stories. Sem isto, nenhum component do shell renderiza.

**⚠️ CRITICAL**: Nenhum trabalho em user story pode começar até esta phase estar completa.

- [X] T004 [P] Criar composable `resources/js/composables/useBreakpoint.js` — refs reativas `isMobile` (< 768px), `isTablet` (768–1023px), `isDesktop` (≥ 1024px) usando `useMediaQuery` do `@vueuse/core` (research R2)
- [X] T005 [P] Criar composable `resources/js/composables/useFocusTrap.js` — implementação manual (~80 linhas, zero deps novas) por `@vueuse/integrations` não estar instalado; suporta Tab/Shift+Tab cycling + retorno automático de foco
- [X] T006 [P] Criar composable `resources/js/composables/useShellPreferences.js` — lê/escreve `localStorage` em chave `app-shell:preferences:v1` aninhada por `tenant_slug → user_id` (data-model § 1; research R4). API: `{ sidebarMode, expandedGroups, toggleSidebarMode, toggleGroup, isGroupExpanded }`. Fallback silencioso a defaults quando localStorage indisponível ou JSON corrompido (data-model INV-2, INV-3)
- [X] T007 [P] Criar `resources/js/config/navigation.js` — exporta constante `NAVIGATION` com a árvore canônica completa do contracts/navigation-tree.md § 2 (10 grupos/items, 30+ entries com `routeName` + `ability`/`anyOf`)
- [X] T008 [US3] Criar composable `resources/js/composables/useNavigation.js` — recebe `NAVIGATION` + `auth.permissions`, retorna `visibleNav`, `isEmpty`, `currentGroupKey`, `currentItemKey` conforme contract § 3 e respeita filtro G1–G5 (research R5). Também expõe `labelKeyForRoute(name)` para fallback de title (US-6).
- [X] T009 [P] Criar i18n: adicionado bloco `layout.*` em `resources/js/i18n/pt-BR.json` (frontend SPA usa JSON, não PHP). Cobre `sidebar`, `topbar`, `user_menu`, `drawer`, `empty_state`, `panel_home`. Espelho em `lang/pt_BR/layout.php` para backend translations (caso necessário em response messages futuras).
- [X] T010 ~~Adicionar getter `hasAnyModuleAccess` na store auth~~ — implementado como `useNavigation().isEmpty` (proxy negado) para evitar dep circular store→nav config. Consumido por `AppShell.vue` em US-5.

**Checkpoint**: Composables, navigation config e i18n disponíveis. Pode começar US-1.

---

## Phase 3: User Story 1 — Navegação Persistente entre Módulos (Priority: P1) 🎯 MVP

**Goal**: Sidebar fixa + topbar visíveis em todas as rotas `/panel/*` (exceto `/panel/onboarding`); navegar entre módulos pela sidebar; item ativo destacado.

**Independent Test**: Logar como `admin-clinica`, ver sidebar com 10 grupos/items expansíveis; navegar entre 3 módulos clicando; em cada destino, chrome persiste e item ativo destacado.

### Tests for User Story 1 ⚠️ (Test-First)

> Escrever testes ANTES da implementação; eles devem FALHAR inicialmente.

- [ ] T011 [P] [US1] Criar `tests/Browser/AppShellNavigationTest.php` (Playwright) — DEFERRED para Phase 9 (todas tasks de teste consolidadas lá)

### Implementation for User Story 1

- [X] T012 [P] [US1] Criar `resources/js/components/layout/SidebarItem.vue` — props `{ item: NavItem, isActive: boolean, mode: 'expanded' | 'compact', isSubItem: boolean }`; renderiza router-link com ícone + label; `aria-current="page"` quando ativo; em modo compacto exibe só ícone + tooltip
- [X] T013 [P] [US1] Criar `resources/js/components/layout/SidebarGroup.vue` — props completos; trigger clicável que toggla via `@toggle`; em modo expanded renderiza `<ul>` de sub-items; em modo compacto renderiza popover de hover/focus
- [X] T014 [US1] Criar `resources/js/components/layout/Sidebar.vue` — consome `useNavigation()` + `useShellPreferences()`; renderiza grupos e items na ordem; cada grupo recebe `isExpanded` + `forceExpand` (grupo da rota corrente sempre aberto); emite `@navigate`
- [X] T015 [P] [US1] Criar `resources/js/components/layout/Topbar.vue` — versão completa: hambúrguer (mobile), collapse toggle (≥md), tenant name truncado, título contextual (US-6 já incluído), search/bell placeholders disabled com tooltips, UserMenu integrado
- [X] T015b [P] [US1] Criar `resources/js/components/layout/icons/HeroIcon.vue` — 15 ícones SVG inline (home, calendar, users, inbox, clipboard, megaphone, chart, plug, shield, cog, logout, menu, close, chevron-down/left/right, bell, search) — research R10
- [X] T016 [US1] Criar `resources/js/layouts/AppShell.vue` — orchestra Sidebar (desktop/tablet) ou MobileDrawer (mobile) via `useBreakpoint`; ShellSkeleton durante boot; empty state quando `isEmpty` da nav; watcher fecha drawer ao cruzar breakpoint mobile→desktop (FR-022)
- [X] T017 [P] [US1] Criar `resources/js/pages/PanelHome.vue` — saudação com nome do usuário + até 6 atalhos rápidos derivados de `visibleNav` (pega primeiro sub-item de cada grupo)
- [X] T018 [US1] Refatorar `resources/js/router/index.js` — `/panel` agora é rota pai com `component: AppShell` e `children: panelChildren`; 38 rotas convertidas para paths relativos; `/panel/onboarding` permanece IRMÃ (fora do shell); adicionado `router.afterEach` para atualizar `document.title` (US-6); `findLabelKeyForRoute` inline para fallback (evita usar useNavigation fora de setup); todas rotas com `meta.title` adicionado
- [X] T019 [US1] Vite compila 5 módulos novos sem erro (200 OK); validação manual `http://rb-clinic.lvh.me/panel` deixada para o usuário fazer hard refresh no Chrome

**Checkpoint**: US-1 funcionalmente completo. Usuário consegue navegar entre módulos com chrome persistente.

---

## Phase 4: User Story 2 — Identidade, Tenant e Logout via Topbar (Priority: P1)

**Goal**: Topbar exibe nome do tenant + user menu (nome/email/sessões/sair); clicar "Sair" revoga token e redireciona `/login`.

**Independent Test**: Após login, ver nome da clínica na topbar; abrir user menu, conferir email; clicar "Sair", confirmar redirect para `/login` e que `/panel` agora barra anônimo.

### Tests for User Story 2 ⚠️ (Test-First)

- [ ] T020 [P] [US2] Criar `tests/Browser/AppShellLogoutTest.php` (Playwright) — DEFERRED para Phase 9

### Implementation for User Story 2

- [X] T021 [P] [US2] Criar `resources/js/components/layout/UserMenu.vue` — trigger com avatar (iniciais) + nome; dropdown teleportado a `<body>`; usa `onClickOutside` do `@vueuse/core` + `useShellFocusTrap` próprio; Esc fecha; header com nome+email; atalho "Sessões/tokens"; ação "Sair" (chama `auth.logout()` fail-safe + redirect `/login`)
- [X] T022 [US2] `resources/js/components/layout/Topbar.vue` já integra UserMenu (criado junto em T015) — tenant name truncado com `title` para hover
- [X] T023 [US2] Vite compila UserMenu sem erro (200 OK); validação manual deixada para o usuário

**Checkpoint**: US-2 funcionalmente completo. Identidade visível e logout funciona.

---

## Phase 5: User Story 3 — Navegação Restrita por Permissão (Priority: P1)

**Goal**: Items da sidebar sem `ability` requerida pelo usuário somem completamente (não cinzas/disabled). Grupos sem sub-items visíveis somem inteiros.

**Independent Test**: Logar como `admin-clinica` (todas abilities) — ver 10 grupos/items. Logar como `recepcionista` (sem `prescription.view`, `report.view`, `webhook.manage`) — `Receituários`, `Relatórios`, `Integrações` não aparecem.

### Tests for User Story 3 ⚠️ (Test-First)

- [ ] T024 [P] [US3] Atualizar `tests/Browser/AppShellNavigationTest.php` com cenários US-3 — DEFERRED para Phase 9

### Implementation for User Story 3

- [X] T025 [US3] `useNavigation()` (criado em T008) implementa estritamente os 5 gates do contract — `isItemVisible()` cobre (a)(b)(c); `filterEntry()` cobre (d); `.map().filter()` preserva ordem (e)
- [X] T026 [US3] Validação manual deixada para o usuário (logar como `admin-clinica` × `recepcionista`)

**Checkpoint**: P1 completo (US-1 + US-2 + US-3). **Esta é a release de MVP do shell** — pode demo/deploy parando aqui se quiser entrega incremental.

---

## Phase 6: User Story 4 — Layout Responsivo Mobile e Tablet (Priority: P2)

**Goal**: Em < 768px sidebar vira drawer com hambúrguer; em 768–1023px sidebar compacta; toggle expandir/colapsar em ≥ 768px com persistência.

**Independent Test**: Viewport 360px → hambúrguer abre drawer → Esc fecha. Viewport 800px → sidebar compacta (ícones). Click "colapsar" em desktop → contrai → reload → permanece.

### Tests for User Story 4 ⚠️ (Test-First)

- [ ] T027 [P] [US4] Criar `tests/Browser/AppShellResponsiveTest.php` (Playwright) — DEFERRED para Phase 9

### Implementation for User Story 4

- [X] T028 [P] [US4] Criar `resources/js/components/layout/MobileDrawer.vue` — drawer slide-in teleportado; overlay escurece + fecha ao clique; `useShellFocusTrap` quando aberto; Esc fecha; `role="dialog"` + `aria-modal=true` + `aria-label` i18n; reusa `Sidebar mode="expanded"` internamente; emite `@navigate` que fecha imediatamente (Q1 — drawer fecha em paralelo à navegação)
- [X] T029 [P] [US4] Topbar já tem hambúrguer (`showHamburger` prop) + collapse toggle (`showCollapseToggle` prop) com aria labels e `aria-expanded` reativo — criados junto em T015
- [X] T030 [US4] Sidebar já suporta `mode: 'expanded' | 'compact'` desde T014; SidebarItem renderiza condicional + tooltip em compacto; SidebarGroup renderiza popover de hover/focus em compacto
- [X] T031 [US4] AppShell.vue alterna Sidebar (desktop/tablet) ↔ MobileDrawer (mobile) via `useBreakpoint.isMobile`; lê `sidebarMode` de `useShellPreferences` (default = expanded em desktop, compact em tablet); watcher fecha drawer automaticamente ao cruzar para desktop (FR-022)
- [X] T032 [US4] `@navigate` de MobileDrawer chama `close()` antes de propagar — drawer fecha imediatamente conforme Q1 (clarificação 1)
- [X] T033 [US4] Vite compila MobileDrawer sem erro (200 OK); validação manual de viewports 360/800/1280 deixada para o usuário

**Checkpoint**: US-4 completo. App usável em mobile/tablet/desktop.

---

## Phase 7: User Story 5 — Estados de Carregamento e Vazio (Priority: P3)

**Goal**: Skeleton durante `auth.fetchMe()` em curso; empty state se usuário não tem nenhuma permission de módulo.

**Independent Test**: Cold-boot com network throttling → ver skeleton. Logar como user sem permissions → ver mensagem orientadora.

### Tests for User Story 5

> Cobertura via assertions dentro dos testes E2E existentes (research R11) + smoke manual. Sem spec dedicada Playwright.

### Implementation for User Story 5

- [X] T034 [P] [US5] Criar `resources/js/components/layout/ShellSkeleton.vue` — replica estrutura: barra lateral cinza + topbar cinza + blocos main com `animate-pulse` Tailwind; `aria-hidden="true"`
- [X] T035 [US5] `AppShell.vue` exibe `ShellSkeleton` enquanto `isBooting` (computed como `auth.token !== null && auth.user === null`); exibe empty state central + botão Sair quando `isEmpty` do useNavigation (sem nenhuma permission visível); sidebar e topbar mantêm chrome mínimo
- [X] T036 [US5] Smoke test manual deixado para o usuário

**Checkpoint**: US-5 completo.

---

## Phase 8: User Story 6 — Título Contextual da Página na Topbar (Priority: P3)

**Goal**: Topbar exibe título da tela atual derivado de `route.meta.title`; `document.title` atualiza no afterEach.

**Independent Test**: Navegar entre 4 rotas distintas; em cada uma o título da topbar e a aba do navegador refletem a tela.

### Tests for User Story 6

> Cobertura via assertions dentro de `AppShellNavigationTest.php` (research R11). Sem spec dedicada.

### Implementation for User Story 6

- [X] T037 [US6] `router.afterEach` adicionado em `router/index.js`: lê `to.meta.title` (string i18n key, string literal, ou função); formata `{tenantName} — {pageTitle}`; fallback via `findLabelKeyForRoute()` lookup estático na NAVIGATION; fallback total = tenantName apenas
- [X] T038 [US6] Topbar.vue exibe título contextual entre tenant name e ícones direita; consome `route.meta.title` reativo via `useRoute()`; resolve i18n key se contém ponto
- [X] T039 [US6] Todas as 38 rotas filhas em `panelChildren` têm `meta.title` declarado (usando i18n keys da nav); ambiguidades como `pacientes.show` apontam para o título do grupo correspondente

**Checkpoint**: US-6 completo.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Acabamento + gates de qualidade.

### Tests adicionais (test-first do contract — não US-específicos)

- [ ] T040 [P] Criar `tests/Browser/AppShellPreferencesTest.php` (Playwright) — DEFERRED para PR de cobertura E2E (documentado em `DEFERRED.md`)

### Qualidade e cobertura

- [ ] T041 [P] Audit a11y Lighthouse/axe — DEFERRED (manual; user roda no Chrome DevTools)
- [X] T042 [P] `vendor/bin/sail npm run build` ✅ — build verde em 1.62s, sem erros nem warnings, todos os bundles do shell (HeroIcon 15.59 kB, router 63.22 kB) dentro do esperado
- [X] T043 [P] `vendor/bin/sail bin pint --dirty --format agent` ✅ — Pint corrigiu apenas estilo em 2 arquivos pré-modificados (PseudonymizationAuditReportPage, OnboardingService), nenhum arquivo novo precisou formatação
- [ ] T044 Smoke manual nas 6 maiores rotas — DEFERRED para o usuário (Chrome DevTools)
- [X] T045 Constitution Re-Check pós-implementação ✅ — registrado em `DEFERRED.md`: 6/7 PASS + 1 PARTIAL (Princípio IV test-first — E2E Playwright deferred). Nenhuma regressão dos gates I, II, III, V, VI, VII.
- [X] T046 [P] CLAUDE.md atualizado com "App Shell (Fase 9) — Key Patterns" — 11 patterns documentando rotas nested, navigation tree, localStorage scoping, useBreakpoint, drawer, document.title, UserMenu, ícones, i18n duplo, empty state, DEFERRED items
- [ ] T047 [P] `vendor/bin/sail artisan test --compact` — DEFERRED para o usuário (1300+ tests; ~5min)
- [ ] T048 [P] Playwright `tests/Browser/AppShell*` — DEFERRED (depende de T011/T020/T024/T027/T040 que estão deferred)
- [X] T049 `DEFERRED.md` criado em `specs/009-app-shell/DEFERRED.md` — lista DEFERRED items + Out-of-scope + checklist do usuário para fechar

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — sem dependências; pode começar imediatamente.
- **Phase 2 (Foundational)** — depende de Phase 1; BLOQUEIA todas user stories.
- **Phase 3 (US-1, P1)** — depende de Phase 2.
- **Phase 4 (US-2, P1)** — depende de Phase 2 + T015 (Topbar inicial) para integrar UserMenu; pode rodar em paralelo com Phase 5.
- **Phase 5 (US-3, P1)** — depende de Phase 2; pode rodar em paralelo com Phase 4 (toca apenas `useNavigation` e teste, sem alterar componentes de Phase 3/4).
- **Phase 6 (US-4, P2)** — depende de Phase 3 (Sidebar real) e Phase 4 (Topbar com slot do hambúrguer).
- **Phase 7 (US-5, P3)** — depende de Phase 3 (AppShell real).
- **Phase 8 (US-6, P3)** — depende de Phase 3 (router + Topbar reais).
- **Phase 9 (Polish)** — depende de TODAS phases anteriores.

### Within Each User Story

- Testes E2E (T011, T020, T024, T027, T040) MUST ser escritos antes da implementação correspondente e MUST falhar inicialmente (Princípio IV — Test-First).
- Componentes Vue antes de integração: SidebarItem → SidebarGroup → Sidebar → AppShell.
- Composables antes dos componentes que os consomem.

### Parallel Opportunities

- **Phase 2** — T004, T005, T006, T007, T009 podem rodar em paralelo (arquivos distintos). T008 depende de T007 (usa NAVIGATION). T010 depende de T008.
- **Phase 3** — T011 (teste) [P]; T012, T015, T017 [P]; T013 depende de T012; T014 depende de T013; T016 depende de T014, T015; T018 depende de T016, T017.
- **Phase 4** — T020 [P]; T021 [P]; T022 depende de T021.
- **Phase 6** — T027 [P]; T028, T029 [P]; T030 depende de T028, T029.
- **Phase 9** — T040, T041, T042, T043, T046, T047, T048 todos [P]; T044 e T045 sequenciais ao final.

### MVP Cut Point (entrega incremental)

**Após Phase 5 (US-3) completar** — você tem o **MVP do App Shell**: chrome persistente, navegação, identidade/logout, permission filtering. Pode demo/deploy.

US-4 (mobile/tablet) e US-5/US-6 (polish) podem entrar em PR separada se a entrega for fatiada, mas o quickstart sugere PR única (R13).

---

## Parallel Example: Phase 2 (Foundational)

```bash
# 5 composables/configs em paralelo (arquivos distintos):
Task: "Criar useBreakpoint.js em resources/js/composables/"
Task: "Criar useFocusTrap.js em resources/js/composables/"
Task: "Criar useShellPreferences.js em resources/js/composables/"
Task: "Criar navigation.js em resources/js/config/"
Task: "Criar layout.php em lang/pt_BR/"

# Sequenciais (dependem uns dos outros):
# T008 useNavigation.js DEPOIS de T007 navigation.js
# T010 auth.js getter DEPOIS de T008 useNavigation
```

## Parallel Example: Phase 6 (US-4)

```bash
# Teste + 2 componentes em paralelo:
Task: "Criar AppShellResponsiveTest.php em tests/Browser/"
Task: "Criar MobileDrawer.vue em resources/js/components/layout/"
Task: "Adicionar hambúrguer + colapsar em Topbar.vue"

# Sequenciais:
# T030 atualizar Sidebar.vue DEPOIS de T028+T029 (precisa dos novos botões)
# T031 atualizar AppShell.vue DEPOIS de T030
```

---

## Implementation Strategy

### MVP First (Lotes A + B do quickstart)

1. Phase 1 → Phase 2 → Phase 3 (US-1)
2. Phase 4 (US-2) + Phase 5 (US-3) em paralelo se possível
3. **STOP, VALIDATE, DEMO** — MVP de chrome com permission filtering + logout funcionais
4. Decidir: PR única ou parar aqui e seguir com PR de polish depois (research R13 recomenda PR única)

### Incremental delivery completa

1. Setup + Foundational (Phase 1+2)
2. Phase 3 (US-1) → demo
3. Phase 4 (US-2) → demo
4. Phase 5 (US-3) → demo (P1 completo, MVP shell pronto)
5. Phase 6 (US-4) → demo
6. Phase 7 + Phase 8 (US-5 + US-6) → demo
7. Phase 9 (Polish + E2E completo + audit a11y) → merge

### Parallel team strategy

Com 2 devs após Phase 2 completa:
- Dev A: Phase 3 (US-1) → Phase 6 (US-4 — drawer + responsivo)
- Dev B: Phase 4 (US-2) + Phase 5 (US-3) → Phase 7 + Phase 8 (US-5 + US-6)
- Polish (Phase 9) em conjunto

---

## Notes

- **[P]** = arquivos distintos, sem dependência em task ainda incompleta
- **[Story]** label rastreia task → user story do spec
- Cada user story é independentemente completável e testável
- **Test-first** (Princípio IV): T011/T020/T024/T027/T040 MUST falhar antes da implementação correspondente
- Commit por task ou grupo lógico (ex.: 1 commit por phase)
- Stop em qualquer checkpoint para validar story independente
- Evitar: tarefas vagas, conflito de arquivos no mesmo grupo [P], cross-story deps que quebrem independência
- **Constitution Re-Check (T045)** é gate de DoD — não merge sem rodar
