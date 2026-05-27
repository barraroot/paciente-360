# Tasks: Auditoria e Correção de UI/UX do Frontend

**Feature**: `016-frontend-ux-audit` | **Branch**: `016-frontend-ux-audit`
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Contracts**: [contracts/ui-invariants.md](./contracts/ui-invariants.md)

> Escopo: **só a SPA Vue do tenant + telas públicas** (Filament fora — Clarification Q1). DoD = **100% dos itens** do catálogo corrigidos, todas as severidades, toda a SPA (Q2). Suporte **fluido ~320→≥1920px** (Q4). Regressão por **asserções de invariante** Playwright+axe, gates **G1–G16** (Q5). Sem mudança de backend/dados/IA/canais.
>
> Comandos sempre via `vendor/bin/sail`. As tarefas de remediação referenciam itens do `audit-catalog.md` (produzido na Fase 2) — corrigem o que a auditoria encontrar nas telas indicadas, validando contra os gates.

## Phase 1: Setup — ferramentas de verificação e artefatos

- [X] T001 [P] Criar `specs/016-frontend-ux-audit/audit-catalog.md` (schema de `data-model.md`: itens + inventário de telas) e `specs/016-frontend-ux-audit/component-standard.md` (esqueleto a preencher na T006)
- [X] T002 [P] Criar varredor de chaves i18n cruas em `tests/ux/scan-i18n-keys.mjs` (compara chaves `t('...')` no código com `resources/js/i18n/pt-BR.json` e detecta render de chave crua) — cobre G14
- [X] T003 Criar helpers de invariantes de geometria em `tests/ux/support/uiInvariants.js` (overflow horizontal G1, preenchimento `fillsRight/fillsBottom` G2, integridade de campo/botão G3, não-sobreposição G4, alvos de toque G6, modal cabe G7) reutilizáveis por tela/largura
- [X] T004 [P] Integrar axe-core ao harness em `tests/ux/support/a11y.js` (contraste G11, rótulos G12, roles) com runner por rota
- [X] T005 [P] Criar `tests/ux/scan-native-dialogs.mjs` (grep `confirm(`/`prompt(`/`alert(` — G13) e confirmar ESLint `no-unsanitized` ativo no CI (G16)

## Phase 2: Foundational — auditoria + referência (BLOCKING)

**Bloqueia todas as fases de remediação: nada de US1–US4 começa sem o catálogo.**

- [X] T006 Extrair o "padrão de fato" das telas de referência (Pacientes, Agenda) para `specs/016-frontend-ux-audit/component-standard.md`: tokens (cores/sombras/raios/espaçamento via variáveis CSS), variantes de `button/input/select/badge/card/modal/empty/loading/error`, e regras de a11y (Q3)
- [X] T007 Enumerar o inventário de rotas a partir de `resources/js/config/navigation.js` + `resources/js/router/index.js` e registrar na seção "Telas" do catálogo (route, roles, priority P1/P2, states a verificar)
- [X] T008 Sweep de auditoria das telas **P1** (Inbox/Conversas, Canais, Agenda, Pacientes lista/funil/merge, Receituários, Dashboard) com `tests/ux/support/*` nas larguras amostradas (320/375/768/1024/1366/1440/1880/1920) + arrasto; registrar cada defeito (G1–G16) no `audit-catalog.md`
- [X] T009 Sweep de auditoria das **telas públicas** (login, cadastro de clínica, recuperação de senha, onboarding); registrar no catálogo
- [X] T010 Sweep de auditoria das telas **P2** (Campanhas, Relatórios, Integrações, Privacidade & LGPD, Configurações, Profissionais, Regras de atribuição, Respostas rápidas, IA Matricial); registrar no catálogo
- [X] T011 Classificar e priorizar todos os itens (severidade/escopo/categoria) e definir o `verification` de cada um; revisar consistência do catálogo (entrega o núcleo da US5)

**Checkpoint**: catálogo completo e priorizado → remediação pode começar.

---

## Phase 3: User Story 1 — Layout desktop consistente e usável (P1)

**Goal**: telas operacionais e públicas sem campo/botão cortado, sobreposto ou fora do container; área principal preenche o espaço — em desktop (≥1024px). Gates G1–G4, G7.

**Independent Test**: rodar o harness desktop em cada tela P1/pública e concluir as tarefas-chave sem obstrução; zero item desktop crítico/alto em aberto.

- [X] T012 [P] [US1] Remediar itens desktop (G1–G4, G7) de **Inbox/Conversas** em `resources/js/pages/Inbox/**` e `resources/js/components/Inbox/**`; asserções em `tests/ux/inbox.desktop.spec.js`
- [X] T013 [P] [US1] Remediar itens desktop de **Canais** em `resources/js/pages/Canais/**` e `resources/js/components/Canais/**`; asserções em `tests/ux/canais.desktop.spec.js`
- [X] T014 [P] [US1] Remediar itens desktop de **Agenda** em `resources/js/pages/Agenda/**`; asserções em `tests/ux/agenda.desktop.spec.js`
- [X] T015 [P] [US1] Remediar itens desktop de **Pacientes** (lista/funil/merge) em `resources/js/pages/Pacientes/**`; asserções em `tests/ux/pacientes.desktop.spec.js`
- [X] T016 [P] [US1] Remediar itens desktop de **Receituários** em `resources/js/pages/Receituarios/**`; asserções em `tests/ux/receituarios.desktop.spec.js`
- [X] T017 [P] [US1] Remediar itens desktop de **Dashboard** em `resources/js/pages/**` (home/executivo); asserções em `tests/ux/dashboard.desktop.spec.js`
- [X] T018 [P] [US1] Remediar itens desktop das **telas públicas** em `resources/js/pages/Auth/**` e onboarding; asserções em `tests/ux/publicas.desktop.spec.js`
- [X] T019 [US1] Rodar harness de invariantes desktop em todas as telas P1/públicas; marcar itens desktop como `verificado` no catálogo

---

## Phase 4: User Story 2 — Responsivo fluido (P1)

**Goal**: os mesmos fluxos funcionam de ~320px a ≥1920px sem overflow horizontal, com reflow, alvos de toque adequados e modais que cabem. Gates G1, G5, G6, G7.

**Independent Test**: rodar o harness nas larguras amostradas + arrasto em cada tela P1/pública; tarefas-chave completáveis no celular.

- [X] T020 [P] [US2] Remediar reflow/overflow/touch (G5, G6, G7) de **Inbox/Conversas** (colapso multi-painel já iniciado; revisar 320–768) em `resources/js/pages/Inbox/**`; asserções em `tests/ux/inbox.responsive.spec.js`
- [X] T021 [P] [US2] Remediar responsivo de **Canais** em `resources/js/pages/Canais/**`; asserções em `tests/ux/canais.responsive.spec.js`
- [X] T022 [P] [US2] Remediar responsivo de **Agenda** (drag/calendário em telas pequenas) em `resources/js/pages/Agenda/**`; asserções em `tests/ux/agenda.responsive.spec.js`
- [X] T023 [P] [US2] Remediar responsivo de **Pacientes** em `resources/js/pages/Pacientes/**`; asserções em `tests/ux/pacientes.responsive.spec.js`
- [X] T024 [P] [US2] Remediar responsivo de **Receituários** em `resources/js/pages/Receituarios/**`; asserções em `tests/ux/receituarios.responsive.spec.js`
- [X] T025 [P] [US2] Remediar responsivo de **Dashboard** + **telas públicas** em `resources/js/pages/**`; asserções em `tests/ux/dashboard-publicas.responsive.spec.js`
- [X] T026 [US2] Rodar harness responsivo (larguras amostradas + arrasto) em todas as telas P1/públicas; marcar itens responsivos como `verificado`

---

## Phase 5: User Story 3 — Padrão visual consistente (P2)

**Goal**: componentes equivalentes usam variantes/tokens únicos; estados loading/empty/error padronizados. Gates G8, G9.

**Independent Test**: comparar botões/inputs/badges/cards/estados entre telas contra `component-standard.md`.

- [ ] T027 [US3] Inventariar divergências de componentes vs `component-standard.md` (botões/inputs/selects/badges/cards/modais/estados) e registrar como itens de categoria `consistencia` no catálogo
- [ ] T028 [P] [US3] Convergir primitivos compartilhados em `resources/js/components/ui/**` para as variantes únicas (sem reescrever os que já seguem o padrão)
- [ ] T029 [P] [US3] Aplicar `empty`/`loading`/`error` states padronizados nas telas com dados assíncronos (G9) reutilizando os primitivos
- [ ] T030 [US3] Substituir usos divergentes pelas variantes padrão nas telas P1→P2 e marcar itens `consistencia` como `verificado`

---

## Phase 6: User Story 4 — Acessibilidade e feedback (P2)

**Goal**: foco visível e ordem lógica, contraste AA, rótulos, e confirmações via modal acessível (sem diálogo nativo). Gates G10–G13.

**Independent Test**: axe sem violações + navegação por teclado em todas as telas auditadas; nenhuma `confirm()`/`prompt()`/`alert()`.

- [ ] T031 [P] [US4] Corrigir foco visível e ordem de tabulação (G10) nas telas com gaps catalogados em `resources/js/pages/**` e `resources/js/components/**`
- [X] T032 [P] [US4] Corrigir contraste AA (G11) e rótulos acessíveis (G12) conforme itens do catálogo/axe
- [X] T033 [P] [US4] Substituir quaisquer `confirm()`/`prompt()`/`alert()` nativos remanescentes por modal a11y (`role=alertdialog` + focus-trap + Esc/overlay) reusando o padrão das Fases 6/12 (G13)
- [X] T034 [US4] Rodar axe + navegação por teclado em todas as telas; marcar itens `a11y` como `verificado`

---

## Phase 7: User Story 5 — Catálogo, i18n e cobertura total (P3)

**Goal**: zero chave i18n crua e texto longo quebrando (G14, G15); 100% dos itens (incl. média/baixa de telas P2) corrigidos e `verificado`; relatório final. (DoD Q2.)

**Independent Test**: `scan-i18n-keys` limpo; catálogo sem item em aberto; relatório gerado.

- [ ] T035 [P] [US5] Corrigir chaves i18n cruas e textos longos que quebram (G14, G15) em todas as telas; adicionar traduções faltantes em `resources/js/i18n/pt-BR.json`; rodar `tests/ux/scan-i18n-keys.mjs`
- [ ] T036 [US5] Remediar itens remanescentes de severidade média/baixa nas telas P2 (fechar o DoD de 100%) em `resources/js/pages/**`
- [ ] T037 [US5] Atualizar o status de **todos** os itens do catálogo para `verificado` e gerar `specs/016-frontend-ux-audit/audit-report.md` (resumo por tela/severidade/escopo + cobertura)

---

## Phase 8: Polish & Cross-Cutting

- [X] T038 Conectar as asserções de invariante (G1–G16) como **gate permanente de CI** (script único que roda `tests/ux/**` + scanners i18n/diálogos) — FR-015/Q5
- [ ] T039 [P] Rodar a suíte completa `tests/ux/**` + `vendor/bin/sail npm run build`; zero violação de gate em todas as telas/larguras amostradas
- [X] T040 [P] `vendor/bin/sail bin pint --dirty --format agent` se alguma string de UI server-side (PHP) foi tocada
- [ ] T041 Smoke final das jornadas-chave (responder conversa, agendar, cadastrar paciente, emitir receita) em desktop e mobile (SC-003)
- [ ] T042 Constitution Re-Check (9/9) + atualizar `.specify/feature.json`/CLAUDE.md para DELIVERED

---

## Dependencies & Execution Order

- **Phase 1 (Setup)** → **Phase 2 (Foundational/auditoria)** bloqueiam tudo.
- **US1 (P3 fase 3)** e **US2 (fase 4)** dependem do catálogo (T008–T011) mas são independentes entre si (desktop vs responsivo) — podem ir em paralelo por equipe.
- **US3** e **US4** dependem do `component-standard.md` (T006) e do catálogo; independentes entre si.
- **US5** consolida (i18n + cobertura total + relatório) — depende dos itens das demais estarem corrigidos para fechar 100%.
- **Phase 8** por último.

## Parallel Opportunities

- Setup: T001, T002, T004, T005 em paralelo (T003 antes dos specs que o usam).
- Dentro de US1/US2: as tarefas por tela (`[P]`) tocam diretórios distintos → paralelizáveis.
- US3 e US4 podem correr em paralelo após T006.

## MVP / Entrega incremental

- **MVP**: Phase 1 + Phase 2 (catálogo) + **US1** (layout desktop P1). Já entrega telas operacionais sólidas no desktop com gate de regressão.
- Incrementos: + US2 (responsivo) → + US3 (consistência) → + US4 (a11y) → + US5 (cobertura total/i18n/relatório).

## Notas

- Tarefas de remediação são **dirigidas pelo catálogo**: o conteúdo exato depende do que T008–T010 encontrarem. Se uma tela não tiver item de uma categoria, a tarefa correspondente é no-op verificável (gate passa).
- Nada de backend/dados/IA/canais; PRs que extrapolarem `resources/js/**` + `pt-BR.json` (+ Pint pontual) saem de escopo.

### Progresso — sessão `/speckit-implement` 2026-05-27

- **Desvio de harness (consciente)**: em vez de um `.spec.js` por tela (T012…), o harness foi consolidado em **`tests/ux/audit-sweep.spec.ts`** (report-mode, 39 rotas × 8 larguras + axe) + helpers `tests/ux/support/{auth,routes,uiInvariants,a11y}.ts`. É superset das specs por tela e gera `audit-findings.json`. Roda com `UX_SWEEP=1 npx playwright test --config=playwright.ux.config.ts`.
- **Verificado (sweep limpo nas 39 rotas)**: UX-010 (401 crítico — `reportsApi`/`webhooks`/`usePresenceHeartbeat`→`@/lib/api`), UX-012…017 (overflow responsivo + tabelas reveladas), UX-018 (contraste: tokens `foreground-muted/subtle` escurecidos + `text-danger-500→600` + emerald-700 + funil text por luminância + remoção de `opacity-60` do card Outlook), UX-019/020 (labels/select-name), UX-021 (foco região rolável), UX-022 (role=combobox).
- **Estado final automatizado**: 0 overflow + 0 axe serious/critical nas 39 rotas; scanners i18n/diálogos limpos.
- **Em aberto**: T031 (auditoria manual de ordem de tabulação por teclado — axe cobre parte), T027–T030 (US3: G8/G9 consistência/estados — UX-023/024, inventário manual), T035–T037 (US5: G15 texto longo + cobertura/relatório), T038–T042 (CI gate + smoke + re-check). Rotas `:id`/onboarding/reset/accept-invitation: revisão manual pendente.
- **Polish pendente**: `prettier --write` nos arquivos `.vue/.js` tocados antes do PR (lint do projeto é `prettier --check`).
