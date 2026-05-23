---
description: "Tasks for 011 — Dashboard Executivo (US-10.1)"
---

# Tasks: Dashboard Executivo

**Input**: Design documents from `/specs/011-dashboard-executivo/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/frontend-integration.md, quickstart.md
**Tests**: Conforme Princípio IV — 1 PHPUnit Feature (24h preset) + 1 Playwright E2E (jornada combinada). Gates G1–G8 do contract.

**Organização**: Tasks por user story. Lotes A–F do `quickstart.md` mapeiam para combinações de phases.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paralelizável (arquivos distintos, sem dependências).
- **[Story]**: User story do spec (US1..US6).
- Cada task referencia caminho absoluto a partir da raiz do repo.

## Path Conventions

Frontend-heavy (backend ≥95% reuso da Fase 8). Caminhos:

- Controller backend: `app/Http/Controllers/Api/V1/Reports/ExecutiveDashboardController.php`
- Frontend page: `resources/js/pages/Reports/ExecutiveDashboardPage.vue`
- Frontend components: `resources/js/components/Reports/`
- Composables: `resources/js/composables/`
- i18n: `resources/js/i18n/pt-BR.json` (bloco `executive_dashboard.*`)
- Tests: `tests/Feature/Reports/`, `tests/Browser/`

---

## Phase 1: Setup

**Purpose**: Pré-requisitos e i18n.

- [X] T001 Verificar branch `011-dashboard-executivo` checked out; Sail rodando (`vendor/bin/sail up -d`); Vite dev rodando (`vendor/bin/sail npm run dev`); pelo menos 1 tenant com dados ≥ 30 dias (para todas as métricas terem valor); usuário admin com `report.view` para teste manual
- [X] T002 [P] Adicionar bloco `executive_dashboard.*` em `resources/js/i18n/pt-BR.json` cobrindo (R12): `period_filter.{24h,7d,30d,90d}`, `metrics.{leads_by_channel,conversion_rate,no_show_rate,estimated_revenue,response_time_first_p95,ai_autonomous_resolution_rate,top_procedure_types,occupancy_by_professional}`, `sections.{top_procedures,occupancy_by_professional}`, `export.{title,pdf,csv,csv_disabled,exporting,failed}`, `stale_banner.{title,updated_ago}`, `empty_state.{no_data,explanation}`, `errors.{global,retry}`, `aria.{tablist,card_with_metric,sparkline_trend,occupancy_high}`

**Checkpoint**: i18n disponível.

---

## Phase 2: Foundational

**Purpose**: Mudança mínima backend + composables compartilhados.

**⚠️ CRITICAL**: Bloqueia user stories.

- [X] T003 Adicionar suporte ao preset `'24h'` em `app/Http/Controllers/Api/V1/Reports/ExecutiveDashboardController.php` — incluir `'24h' => $end->copy()->subHours(24)` no match de `resolvePeriod()` (linha ~105). Validar via curl: `GET /api/v1/reports/executive?preset=24h` retorna `period.start === now()-24h`.
- [X] T004 [P] Criar `tests/Feature/Reports/ExecutiveDashboardWindow24hTest.php` (gate G1): `test_executive_endpoint_supports_24h_preset` validando `period.start ≈ now()-24h` (tolerância 5s); cobertura adicional: `test_unknown_preset_falls_back_to_30d_default` (regression)
- [X] T005 [P] Criar composable `resources/js/composables/useDashboardWindow.js` (R4 — pattern do spec 010 `usePanelHomeScope.js`): API `{ window: Ref<'24h'|'7d'|'30d'|'90d'>, setWindow(value) }`. Chave localStorage `executive_dashboard:window:v1` aninhada por `tenant_slug + user_id`. Default `'7d'`. Tolerância robusta a localStorage indisponível e JSON corrompido (INV-1..4)
- [X] T006 [P] Criar composable `resources/js/composables/useExecutiveDashboard.js` (R3 — wrapper sobre `reportsStore` Pinia já existente): API `{ data, loading, error, window, setWindow, refresh, exportPdf }`. Internamente: usa `useDashboardWindow` + `reportsStore.fetchExecutive({preset})`; watcher em `window` dispara `refresh()` com `AbortController` cancelando request anterior; `exportPdf()` chama `reportsStore.exportPdf` retornando Blob → download via `URL.createObjectURL`

**Checkpoint**: Backend ready + composables. Pode começar US-1.

---

## Phase 3: User Story 1 — Visão Analítica do Período (Priority: P1) 🎯 MVP

**Goal**: 8 KPI cards renderizados em grid com valor formatado + delta colorido + polaridade correta.

**Independent Test**: Logar como admin com `report.view`; abrir `/panel/relatorios/executivo`; verificar que 8 KPIs aparecem com formatação correta (%, BRL, segundos) e seta verde/vermelha conforme natureza da métrica (no_show e response_time invertidos).

### Implementation for User Story 1

- [X] T007 [P] [US1] Criar `resources/js/components/Reports/Sparkline.vue` (R6) — SVG inline stub: props `{ points: number[], width: number, height: number, color: string }`; renderiza `<polyline>` calculando coordenadas normalizadas; retorna `null` se `points.length === 0` (caso atual nesta versão — sparkline real deferred R2)
- [X] T008 [P] [US1] Criar `resources/js/components/Reports/KpiCardWithSparkline.vue` (R7) — variant do `KpiCardWithTrend` já existente: props `{ label, value, formatType: 'percent'|'currency_brl'|'seconds'|'count', deltaPercent, inversePolarity: boolean, sparklinePoints?: number[], loading, error }`. Renderiza: label + value formatado (Intl.NumberFormat conforme formatType) + delta indicator (seta ↑/↓ + cor verde/vermelha respeitando `inversePolarity` para no_show/response_time + texto explícito) + Sparkline (omitido quando points vazio); `aria-label` rico (R11) descrevendo label + valor + tendência sem depender do gráfico; skeleton state quando loading; error state quando error
- [X] T009 [US1] Criar `resources/js/components/Reports/DashboardSkeleton.vue` — skeleton da página completa: 8 cards skeleton + 2 seções skeleton; placeholder consistente com KpiCardWithSparkline e demais cards

**Checkpoint**: Componentes prontos para US-1.

---

## Phase 4: User Story 2 — Filtro de Período Persistente (Priority: P1)

**Goal**: Tablist com 4 windows (24h/7d/30d/90d) + persistência localStorage + keyboard nav.

**Independent Test**: Abrir dashboard; clicar em "30 dias"; verificar atualização dos dados em < 2s; logout/login; window "30 dias" persiste.

### Implementation for User Story 2

- [X] T010 [US2] Criar `resources/js/components/Reports/PeriodFilter.vue` (R5) — tablist a11y: container `role="tablist"` + `aria-label`; 4 botões com `role="tab"` + `aria-selected` + `aria-controls`; emite `update:modelValue`; navegação por setas Left/Right via keydown handler (deslocamento circular entre tabs); indicador visual de seleção via Tailwind (bg-primary-700 text-white para tab ativo)

**Checkpoint**: Filter pronto. Combinado com `useDashboardWindow` (T005) e `useExecutiveDashboard` (T006) entrega US-2 ponta-a-ponta.

---

## Phase 5: User Story 3 — Banner de Frescor (Priority: P2)

**Goal**: Banner amistoso aparece quando lag > 2h; oculto na janela 24h.

**Independent Test**: Atrasar artificialmente o cron de agregação > 2h; recarregar dashboard; banner aparece com timestamp relativo correto.

### Implementation for User Story 3

- [X] T011 [P] [US3] Criar `resources/js/components/Reports/StaleDataBanner.vue` (R10) — props `{ lagSeconds: number, window: string }`; renderiza banner amarelo/atenção apenas se `lagSeconds > 7200 && window !== '24h'`; usa `luxon` para formatar timestamp relativo ("há 3 horas") a partir de `now() - lagSeconds`; estilo informativo não-bloqueante (border + bg suave + ícone info)

**Checkpoint**: Banner standalone testável.

---

## Phase 6: User Story 4 — Comparativos de Volume (Priority: P2)

**Goal**: 2 seções abaixo dos KPIs — top 5 procedimentos + ocupação por profissional.

**Independent Test**: Tenant com 10 tipos de procedimento e 5 profissionais; abrir dashboard; ver top 5 procedimentos com nome+contagem+% e profissionais ordenados por ocupação decrescente com badge para ≥90%.

### Implementation for User Story 4

- [X] T012 [P] [US4] Criar `resources/js/components/Reports/TopProceduresCard.vue` (R8) — props `{ items: Array<{name, count, percentage}>, loading, error }`. Renderiza card shell + `<ul role="list">` com 5 itens; cada item: nome (truncado se longo) + contagem absoluta + barra horizontal CSS (`:style="{width: percentage + '%'}"`) + texto da %; empty state se items vazio; emite `@click(item)` (drill DEFERRED — sem handler na page)
- [X] T013 [P] [US4] Criar `resources/js/components/Reports/OccupancyByProfessionalCard.vue` — props `{ items: Array<{professional_id, name, occupancy_percent}>, loading, error }`. Renderiza card shell + `<ul role="list">`; cada item: nome do profissional + barra horizontal `aria-valuenow/min/max` + % numérica + badge "Carga alta" + ícone destacado quando `occupancy_percent >= 90` (FR-021); ordenação `sort by occupancy_percent desc` no client (defesa em profundidade); emite `@click(item)`

**Checkpoint**: Seções complementares prontas.

---

## Phase 7: User Story 5 — Exportação (Priority: P2)

**Goal**: Menu de export com PDF funcional (CSV placeholder).

**Independent Test**: Click "Exportar" → "PDF" → spinner aparece → arquivo baixa em < 10s; click "CSV" desabilitado mostra "em breve".

### Implementation for User Story 5

- [X] T014 [US5] Criar `resources/js/components/Reports/ExportMenu.vue` (R9) — dropdown com 2 itens: "Exportar PDF" (chama `@export-pdf` emitida ao caller; spinner via prop `loading`; aria-busy true durante export) e "Exportar CSV" (sempre desabilitado, label "em breve" via `aria-disabled` + texto secundário); botão trigger no header; teleport para body para escape de overflow; click fora ou Esc fecha o menu

**Checkpoint**: ExportMenu pronto. Caller (page) implementa o handler real em T016.

---

## Phase 8: User Story 6 — Estados Visuais (Priority: P3)

**Goal**: Skeleton inicial + error banner com retry + empty state amistoso.

**Independent Test**: Network throttling → ver skeleton; bloquear endpoint → ver banner erro + retry; tenant novo sem dados → ver empty state central.

### Implementation for User Story 6

> Cobertura via integração final na page (T016). `DashboardSkeleton.vue` (T009) já cobre o loading inicial; ErrorBanner é Tailwind simples inline na page; EmptyState similar.

---

## Phase 9: Integration — Reescrita da Page (cobre US-1 a US-6 visual)

**Goal**: `ExecutiveDashboardPage.vue` reescrita consumindo todos os componentes + composable.

### Implementation

- [X] T015 [P] Validar que `reportsStore.js` Pinia store (em `resources/js/stores/reportsStore.js`) **não precisa modificações** — gate G8: read-only verification; smoke do método `fetchExecutive({preset: '24h'})` via Vue DevTools no browser
- [X] T016 Reescrever `resources/js/pages/Reports/ExecutiveDashboardPage.vue` consumindo `useExecutiveDashboard` composable: header com título da página + `PeriodFilter v-model="window"` + `ExportMenu @export-pdf="handleExportPdf"`; `StaleDataBanner` condicional; banner de erro global + botão retry (FR-031); empty state se `!loading && !data?.metrics` (FR-032); `DashboardSkeleton` quando `loading && !data`; grid responsivo 4 colunas (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`) com 8 `KpiCardWithSparkline` (mapeamento: cada métrica → label i18n + value + formatType + inversePolarity); abaixo: 2 colunas com `TopProceduresCard` + `OccupancyByProfessionalCard` (data extraída de `data.metrics.top_procedure_types.json` e `data.metrics.occupancy_by_professional.json`); `handleExportPdf` mostra toast de sucesso/falha
- [X] T017 Validar manualmente em `http://rb-clinic.lvh.me/panel/relatorios/executivo`: ver header + filter + 8 KPIs + 2 sections; alternar entre 4 períodos; clicar export PDF e validar download; logout/login e confirmar window persistida

**Checkpoint**: Page completa visualmente.

---

## Phase 10: Polish & Gates

**Purpose**: Testes E2E + a11y audit + Constitution Re-Check + docs.

### Tests E2E (R13)

- [ ] T018 [P] Criar `tests/Browser/ExecutiveDashboardE2ETest.php` (Playwright) — cobre G1/G2/G4/G5/G6 do contract:
  - `test_admin_can_see_8_kpi_cards`: login admin → acessar `/panel/relatorios/executivo` → ver 8 cards renderizados
  - `test_period_filter_switches_data_and_persists`: clicar "30 dias" → URL/params atualiza → dados atualizam → logout/login → "30 dias" continua selecionado (G2/G3)
  - `test_stale_banner_hidden_when_window_is_24h` (G4)
  - `test_no_show_rate_inverted_polarity` (G5): mockar resposta com `delta_percent > 0` para no_show → seta deve ser vermelha (não verde)
  - `test_export_pdf_initiates_download` (G6): click export PDF → blob download em < 10s

### Qualidade

- [ ] T019 [P] Audit a11y Lighthouse/axe (manual no Chrome DevTools) em `/panel/relatorios/executivo` em viewports 360px e 1280px (G7) — meta SC-006: 0 violations sérias/críticas; gravar evidência em `specs/011-dashboard-executivo/a11y-audit.md`
- [X] T020 [P] `vendor/bin/sail npm run build` — confirmar build verde, sem warnings, bundle dos componentes Reports < 60KB minified+gzip
- [X] T021 [P] `vendor/bin/sail bin pint --dirty --format agent` — formatar arquivos PHP novos/modificados
- [ ] T022 [P] `vendor/bin/sail artisan test --compact tests/Feature/Reports tests/Browser/ExecutiveDashboardE2ETest.php` — todos verdes
- [ ] T023 Smoke manual: validar nos 4 períodos (24h, 7d, 30d, 90d) com tenant com dados; admin sem `report.view` é barrado pelo router guard; export PDF gera arquivo válido (abrir e conferir conteúdo)

### Re-check & docs

- [X] T024 Constitution Re-Check pós-implementação — confirmar 7/7 PASS continua válido; documentar em DEFERRED.md (mesmo status do plan: 6/7 + 1 PARTIAL se testes não rodados)
- [X] T025 [P] Atualizar `CLAUDE.md` adicionando seção "Dashboard Executivo (Fase 11) — Key Patterns": composables `useDashboardWindow` e `useExecutiveDashboard`, reuso de `reportsStore` Pinia, polaridade invertida explícita em FR-014, sparkline DEFERRED com stub preparado, ExportMenu pattern, PeriodFilter com `role="tablist"`, 1 linha de mudança backend (preset 24h), localStorage `executive_dashboard:window:v1` separado dos demais
- [X] T026 [P] Criar `specs/011-dashboard-executivo/DEFERRED.md` listando: sparkline real (R2 — depende de extensão backend), CSV export, drill-down detalhado, auto-refresh, comparativo período arbitrário, filtros adicionais, dark mode, personalização, sync cross-device
- [X] T027 Atualizar `.specify/feature.json` marcando 011 como DELIVERED quando todos os gates passarem

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — sem dependências
- **Phase 2 (Foundational)** — depende de Phase 1; BLOQUEIA US stories
- **Phase 3 (US-1, P1)** — depende de Phase 2
- **Phase 4 (US-2, P1)** — depende de Phase 2; PeriodFilter pode ser desenvolvido em paralelo com Phase 3
- **Phase 5 (US-3, P2)** — depende de Phase 2; banner é standalone
- **Phase 6 (US-4, P2)** — depende de Phase 2; cards standalone
- **Phase 7 (US-5, P2)** — depende de Phase 2; ExportMenu standalone
- **Phase 8 (US-6, P3)** — coberta na Page (T016)
- **Phase 9 (Integration)** — depende de Phases 3–7 (todos componentes prontos)
- **Phase 10 (Polish)** — depende de Phase 9

### Within Each User Story

- Backend (T003) ANTES de qualquer integração com `?preset=24h`
- Composables (T005, T006) ANTES de Page
- Componentes individuais (T007–T014) podem ser desenvolvidos em paralelo (arquivos distintos)
- Page rewrite (T016) DEPOIS de todos os componentes prontos

### Parallel Opportunities

- **Phase 1**: T002 standalone
- **Phase 2**: T003 sequencial (backend) — T004, T005, T006 todos [P] em paralelo
- **Phase 3**: T007, T008 [P]; T009 standalone
- **Phase 5/6/7**: T011, T012, T013, T014 todos [P] (componentes em arquivos distintos)
- **Phase 10**: T018, T019, T020, T021, T022, T025, T026 todos [P]

### MVP Cut Point

**Após Phase 4 (T010)** — você tem o MVP funcional: backend suporta 24h, composables prontos, 8 KPI cards rendering, filter persistido. Pode demo. Phases 5–7 adicionam polish (banner stale, top procedures, occupancy, export). Phase 9 (integration na Page) é o ponto de "tudo conectado".

---

## Parallel Example: Phase 2 (Foundational)

```bash
# 1 sequencial (backend):
Task: "ExecutiveDashboardController::resolvePeriod() add '24h' case"

# 3 em paralelo (testes + composables, arquivos distintos):
Task: "ExecutiveDashboardWindow24hTest.php"
Task: "useDashboardWindow.js"
Task: "useExecutiveDashboard.js"
```

## Parallel Example: Phase 3–7 (Components em paralelo)

```bash
# 7 componentes em arquivos distintos, todos [P]:
Task: "Sparkline.vue (stub)"
Task: "KpiCardWithSparkline.vue"
Task: "DashboardSkeleton.vue"
Task: "PeriodFilter.vue"
Task: "StaleDataBanner.vue"
Task: "TopProceduresCard.vue"
Task: "OccupancyByProfessionalCard.vue"
Task: "ExportMenu.vue"
```

---

## Implementation Strategy

### MVP First (Lotes A + B do quickstart)

1. Phase 1 (Setup) + Phase 2 (Foundational)
2. Phase 3 (US-1 KPIs) + Phase 4 (US-2 Filter) em paralelo
3. **STOP, VALIDATE**: backend smoke + composables + 8 KPI cards renderizados

### Incremental delivery

1. Phase 1+2 → backend pronto
2. Phase 3 → KPIs visíveis (com dados crus, sem polish)
3. Phase 4 → filter funcional
4. Phase 5+6+7 paralelo → polish e features adicionais
5. Phase 9 → page integrada
6. Phase 10 → tests + audit

### Parallel team strategy

Com 2 devs após Phase 2:
- **Dev A**: Phase 3 (US-1) → Phase 5 (US-3) → Phase 7 (US-5)
- **Dev B**: Phase 4 (US-2) → Phase 6 (US-4)
- **Ambos**: Phase 9 (integration) + Phase 10 (polish)

---

## Notes

- **[P]** = arquivos distintos, sem dependência em task incompleta
- **[Story]** label rastreia task → user story do spec
- Cada user story independentemente completável e testável
- **Test-first** (Princípio IV): T004 e T018 antes da feature correspondente
- Commit por phase ou grupo lógico
- **Constitution Re-Check (T024)** é gate de DoD
- **G1–G8 gates** devem TODOS estar verdes antes do PR final — ver `contracts/frontend-integration.md § 4`
- **Sparkline DEFERRED** (R2): componente `Sparkline.vue` criado como stub funcional; quando backend implementar séries temporais, basta atualizar `KpiCardWithSparkline` para passar `sparklinePoints` que já é prop opcional
