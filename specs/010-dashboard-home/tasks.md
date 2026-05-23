---
description: "Tasks for 010 — Dashboard Home (US-1.5)"
---

# Tasks: Dashboard Home

**Input**: Design documents from `/specs/010-dashboard-home/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-panel-home.md, quickstart.md
**Tests**: Incluídos conforme Princípio IV (Test-First). 10 gates G1–G10 do contract + 6 feature tests + 4 unit tests + 1 E2E Playwright.

**Organização**: Tasks por user story para implementação e teste independentes. Lotes A–F do `quickstart.md` mapeiam para combinações de phases abaixo.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paralelizável (arquivos distintos, sem dependências).
- **[Story]**: User story do spec (US1..US6).
- Cada task referencia caminho absoluto a partir da raiz do repo.

## Path Conventions

Backend Laravel + Frontend Vue SPA. Caminhos:

- Controllers: `app/Http/Controllers/Api/V1/Panel/`
- Requests: `app/Http/Requests/Panel/`
- Resources: `app/Http/Resources/Panel/`
- Service + collectors: `app/Services/Panel/` e `app/Services/Panel/Collectors/`
- Policy: `app/Policies/Panel/`
- Metrics: `app/Support/Metrics/`
- Config: `config/panel.php`
- i18n backend: `lang/pt_BR/panel.php`
- Frontend page: `resources/js/pages/PanelHome.vue`
- Frontend components: `resources/js/components/panel-home/`
- Frontend composables: `resources/js/composables/`
- i18n frontend: `resources/js/i18n/pt-BR.json` (bloco `panel_home.*`)
- Tests: `tests/Feature/Panel/`, `tests/Unit/Panel/`, `tests/Browser/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuração base que destrava tudo.

- [X] T001 Verificar branch `010-dashboard-home` checked out, Sail rodando (`vendor/bin/sail up -d`), Vite dev rodando (`vendor/bin/sail npm run dev`), e ao menos 2 usuários de teste seedados em `rb-clinic` (1 `admin-clinica`, 1 `medico`, 1 híbrido `admin-clinica + medico`, 1 `recepcionista`)
- [X] T002 [P] Criar `config/panel.php` com chaves `cache_ttl_seconds` (default 30, env `PANEL_HOME_CACHE_TTL`), `autorefresh_seconds` (default 120, env `PANEL_HOME_AUTOREFRESH`), `upcoming_window_minutes` (default 360, env `PANEL_HOME_UPCOMING_WINDOW`) — research R3
- [X] T003 [P] Criar `lang/pt_BR/panel.php` com strings de envelope error messages (ex.: `'section_load_failed' => 'Não foi possível carregar esta seção.'`, `'global_error' => 'Não foi possível atualizar.'`)
- [X] T004 [P] Adicionar bloco `panel_home.*` em `resources/js/i18n/pt-BR.json` cobrindo: KPIs labels (4 cards + sub-info), seção "Próximas consultas" (heading + empty state), seção "Alertas de atenção" (heading + empty state + textos por tipo: conversation_escalated, prescription_expiring, paciente_funil_stale, confirmation_pending, webhook_dlq), seção "Atividade recente" (heading + empty state), toggle "Minha visão"/"Visão da clínica", botão "Atualizar", botão "Ver agenda completa", banner de erro global

**Checkpoint**: Config + i18n disponíveis.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Service layer + endpoint skeleton + policy + métricas. Bloqueia user stories.

**⚠️ CRITICAL**: Nenhum trabalho em user story pode começar até esta phase estar completa.

- [X] T005 [P] Criar interface `app/Support/Metrics/PanelHomeMetricsContract.php` com métodos `recordRequest(string $tenant, string $scope, bool $cacheHit, float $durationSeconds): void`, `recordCacheHit(string $tenant): void`, `recordSectionFailure(string $section): void` (research R12)
- [X] T006 [P] Criar `app/Support/Metrics/PanelHomeMetrics.php` extends `AbstractModuleMetrics` implementando os 4 contadores/histograms Prometheus: `panel_home_requests_total{tenant,scope,cache_hit}`, `panel_home_duration_seconds{section}` (buckets `[0.05, 0.1, 0.25, 0.5, 1.0, 2.5]`), `panel_home_cache_hits_total{tenant}`, `panel_home_section_failures_total{section}`
- [X] T007 [P] Criar `app/Policies/Panel/PanelHomePolicy.php` (research R8) com métodos: `canSeeClinicScope(User $user): bool` (retorna `$user->hasRole('admin-clinica')`), `canSeeWebhookDlqAlerts(User $user): bool` (retorna `$user->can('webhook.manage')`), `canSeeConfirmationAlerts(User $user): bool` (retorna `$user->can('agenda.view')`)
- [X] T008 [P] Criar request `app/Http/Requests/Panel/PanelHomeIndexRequest.php` — valida query param `scope` como `in:user,clinic`, default `'user'`
- [X] T009 Criar `app/Services/Panel/PanelHomeService.php` (skeleton: construtor recebe os 4 collectors via DI; método `getHome(User $user, string $requestedScope): array` que aplica policy de scope, monta cache key `panel_home:{tenant}:{user}:{scope}`, faz `Cache::remember(key, config('panel.cache_ttl_seconds'), fn => $this->buildPayload(...))`, registra métricas via `PanelHomeMetrics`). Cada chamada de collector deve ser envelopada em try/catch para degradação graceful (research R13) — em caso de exceção: registra `recordSectionFailure`, retorna `['data' => null, 'error' => true]` para aquela seção
- [X] T010 Criar resource envelope `app/Http/Resources/Panel/PanelHomeResource.php` com campos `scope_requested, scope_applied, can_toggle_scope, generated_at, cache_hit, sections` (contracts/api-panel-home.md § 1)
- [X] T011 Criar controller `app/Http/Controllers/Api/V1/Panel/PanelHomeController.php` (action `__invoke(PanelHomeIndexRequest $request)`); injeta `PanelHomeService`; retorna `PanelHomeResource::make(...)`
- [X] T012 Registrar rota em `routes/api.php` dentro do grupo `auth:sanctum`: `Route::get('/panel/home', PanelHomeController::class)->middleware(['tenant.slug','tenant.not-suspended'])->name('panel.home')`

**Checkpoint**: Service skeleton + endpoint reachable retornando envelope vazio. Pode começar US-1.

---

## Phase 3: User Story 1 — KPIs Operacionais do Dia (Priority: P1) 🎯 MVP

**Goal**: 4 KPI cards no topo do dashboard com contagens corretas + clicks levam para telas filtradas.

**Independent Test**: Logar como `admin-clinica` em tenant com dados; conferir que cada KPI bate com query manual no banco; clicar em cada card e validar a rota destino.

### Tests for User Story 1 ⚠️ (Test-First)

- [ ] T013 [P] [US1] Criar `tests/Unit/Panel/KpiCollectorTest.php` com 4 testes (um por KPI): `test_appointments_today_counts_only_today_within_status_set`, `test_conversations_pending_excludes_assigned_to_other_user`, `test_leads_new_7d_filters_by_funil_stage_and_window`, `test_prescriptions_expiring_30d_filters_active_only` — cada um cria fixtures via factory e assert que o count agregado bate
- [ ] T014 [P] [US1] Criar `tests/Feature/Panel/PanelHomeEndpointTest.php` (estrutura inicial) com cenário `test_endpoint_returns_4_kpis_for_authenticated_user` cobrindo US-1 cenário 1
- [ ] T015 [P] [US1] Criar `tests/Feature/Panel/PanelHomeCrossTenantTest.php` — gate G1: `test_user_of_tenant_a_cannot_see_data_of_tenant_b` (cria 2 tenants com 12 e 5 consultas hoje cada, loga como user do A e valida que appointments_today.total = 12, não 17)
- [ ] T016 [P] [US1] Criar `tests/Feature/Panel/PanelHomeScopeTest.php` — gates G4 + G5: `test_user_without_admin_role_forced_to_user_scope` (recepcionista pedindo `?scope=clinic` recebe `scope_applied='user'` + `can_toggle_scope=false`), `test_admin_medico_minha_visao_filters_by_professional` (usuário com role dupla pedindo `scope=user` vê apenas suas consultas/conversas — Q1 da clarification)
- [ ] T017 [P] [US1] Criar `tests/Feature/Panel/PanelHomeCacheTest.php` — gate G3: `test_second_call_within_ttl_is_served_from_cache` (mede tempo da 2ª chamada; assert `cache_hit=true`), `test_cache_key_is_scoped_by_tenant_user_scope` (3 chamadas com (T1,U1,user), (T1,U1,clinic), (T2,U1,user) — todas geram chaves distintas; verificar via `Redis::keys('panel_home:*')`)

### Implementation for User Story 1

- [X] T018 [P] [US1] Criar `app/Services/Panel/Collectors/KpiCollector.php` (research R4) com método `collect(Tenant $tenant, User $user, string $scope): array` retornando struct conforme data-model § 1.2. Implementar 4 contagens agregadas via `count()` Eloquent — NUNCA materializar lista para contar. Aplicar filtros de scope conforme contracts § 3
- [X] T019 [US1] Integrar `KpiCollector` no `PanelHomeService::buildPayload()` — primeiro collector ligado. Mantém os outros 3 como `null + error=false` por enquanto
- [X] T020 [P] [US1] Criar `app/Http/Resources/Panel/KpisResource.php` que estrutura a section `kpis` do envelope (data-model § 1.2)
- [X] T021 [US1] Rodar tests T013–T017; iterar até verde
- [X] T022 [P] [US1] Validar manualmente via curl: `vendor/bin/sail exec laravel.test curl -s -H "Authorization: Bearer <token>" -H "X-Tenant-Slug: rb-clinic" http://crm.lvh.me/api/v1/panel/home | jq .sections.kpis`

**Checkpoint**: US-1 backend completo. Endpoint retorna KPIs corretos + cache funciona + cross-tenant isolado.

---

## Phase 4: User Story 2 — Próximas Consultas em Tempo Útil (Priority: P1)

**Goal**: Seção lista até 5 consultas nas próximas 6h (janela fixa per Q2), ordenadas cronologicamente, com link para agenda.

**Independent Test**: Criar 3 appointments nas próximas 2/4/7 horas para o profissional logado; validar que a seção retorna apenas as 2 dentro de 6h.

### Tests for User Story 2 ⚠️

- [ ] T023 [P] [US2] Criar `tests/Unit/Panel/UpcomingAppointmentsCollectorTest.php` com 4 testes: `test_returns_only_appointments_within_6h_window` (Q2: janela fixa), `test_orders_by_starts_at_ascending`, `test_limits_to_5_items`, `test_scope_user_filters_by_professional_id` (gate G10)
- [ ] T024 [P] [US2] Adicionar em `PanelHomeEndpointTest`: cenário `test_endpoint_returns_upcoming_appointments_section` (US-2 cenários 1, 2, 3)
- [ ] T025 [P] [US2] Adicionar gate G2 em `tests/Feature/Panel/PanelHomeNplusOneTest.php`: `test_endpoint_uses_at_most_12_queries` usando `DB::enableQueryLog()` + assertCount

### Implementation for User Story 2

- [X] T026 [US2] Criar `app/Services/Panel/Collectors/UpcomingAppointmentsCollector.php`: query com eager loading rigoroso (`with(['appointmentType', 'paciente', 'professional'])`); filtro `starts_at BETWEEN now() AND now()+config('panel.upcoming_window_minutes') minutes`; `status IN ('scheduled','confirmed')`; scope='user' adiciona `professional_id = user.id`; limit 5; order `starts_at ASC` (data-model § 1.3)
- [X] T027 [P] [US2] Criar `app/Http/Resources/Panel/UpcomingAppointmentResource.php` (item) e `UpcomingAppointmentsSectionResource.php` (envelope da seção)
- [X] T028 [US2] Integrar `UpcomingAppointmentsCollector` no `PanelHomeService::buildPayload()`
- [X] T029 [US2] Rodar T023–T025; iterar até verde

**Checkpoint**: US-2 backend completo. Lista 6h funcional, query budget respeitado.

---

## Phase 5: User Story 3 — Alertas de Atenção Acionáveis (Priority: P1)

**Goal**: Lista heterogênea de até 5 itens (5 tipos diferentes) ordenada por severidade, com permission filtering.

**Independent Test**: Criar artificialmente 1 cenário de cada tipo de alerta + 1 cenário fora de cada permission scope; validar que admin-clinica vê 5 itens ordenados por severidade e que recepcionista NÃO vê webhook_dlq.

### Tests for User Story 3 ⚠️

- [ ] T030 [P] [US3] Criar `tests/Unit/Panel/AttentionItemsCollectorTest.php` com 7 testes:
  - `test_collects_conversation_escalated_after_10min`
  - `test_collects_prescription_expiring_in_7d`
  - `test_collects_paciente_funil_stale_excludes_terminal_stages` (Q3: filtro de estágios)
  - `test_collects_confirmation_pending`
  - `test_collects_webhook_dlq_within_24h`
  - `test_orders_by_severity_then_occurred_at`
  - `test_limits_to_5_items`
- [ ] T031 [P] [US3] Adicionar gate G8 em `PanelHomeEndpointTest`: `test_user_without_webhook_manage_does_not_see_dlq_alerts`
- [ ] T032 [P] [US3] Adicionar gate G9 em `PanelHomeEndpointTest`: `test_kpi_collector_failure_returns_other_sections_normally` (mocka exceção no `KpiCollector`, assert que `sections.kpis = null + error=true` mas `sections.upcoming_appointments` e `sections.attention_items` permanecem normais)
- [ ] T033 [P] [US3] Adicionar cenários US-3 (1, 2, 3, 4, 6) em `PanelHomeEndpointTest`

### Implementation for User Story 3

- [X] T034 [US3] Criar DTO `app/Services/Panel/DataObjects/AttentionItemDto.php` (research R6) com campos `type, severity, title_key, description, link, occurred_at`
- [X] T035 [US3] Criar `app/Services/Panel/Collectors/AttentionItemsCollector.php`: orquestra 5 sub-queries (uma por tipo); consulta `PanelHomePolicy` antes de adicionar `webhook_dlq` e `confirmation_pending`; merge e ordena por (severity DESC, occurred_at DESC); limita a 5. Para `paciente_funil_stale`, filtra `funil_stage IN ('lead','qualificando','interessado','agendamento')` excluindo `agendado, concluído, perdido` (Q3 da clarification)
- [X] T036 [P] [US3] Criar `app/Http/Resources/Panel/AttentionItemResource.php` (mapeia DTO para resource público; resolve i18n key para `title`)
- [X] T037 [US3] Integrar `AttentionItemsCollector` no `PanelHomeService::buildPayload()`
- [X] T038 [US3] Rodar T030–T033; iterar até verde

**Checkpoint**: Phase 5 completa. **MVP backend de Dashboard Home** (US-1 + US-2 + US-3 = todos P1). Pode demo/deploy parando aqui — frontend mostraria 3 seções principais com endpoint real.

---

## Phase 6: User Story 4 — Timeline de Atividade Recente (Priority: P2)

**Goal**: Lista de até 8 entries de audit log das últimas 24h, humanizadas, com gate LGPD.

**Independent Test**: Provocar 8+ eventos auditáveis em 24h; verificar que aparecem em ordem cronológica reversa; tentar provocar evento `paciente.viewed` (visualização) e validar que NÃO aparece.

### Tests for User Story 4 ⚠️

- [ ] T039 [P] [US4] Criar `tests/Unit/Panel/RecentActivityCollectorTest.php` com 5 testes:
  - `test_filters_by_24h_window`
  - `test_excludes_events_without_user_actor`
  - `test_orders_by_occurred_at_desc`
  - `test_limits_to_8_items`
  - `test_excludes_events_outside_allowlist` (gate G7: `paciente.viewed` NÃO aparece)
- [ ] T040 [P] [US4] Criar `tests/Feature/Panel/PanelHomeRecentActivityLgpdTest.php` — **gate G6 crítico LGPD**: `test_descriptions_never_contain_cpf_email_phone_clinical` (FR-019; usa regex para CPF `/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/`, telefone `/\b(?:\+?55)?\s?\(?\d{2}\)?\s?9?\d{4}-?\d{4}\b/`, email `/\b[\w.-]+@[\w.-]+\.\w+\b/`, e palavras-chave clínicas em deny-list)
- [ ] T041 [P] [US4] Adicionar cenários US-4 em `PanelHomeEndpointTest` (5 acceptance scenarios)

### Implementation for User Story 4

- [X] T042 [P] [US4] Criar helper `app/Support/AuditLog/Humanizer.php` com método estático `humanize(AuditLog $event): string` (research R7). Mapeia event_type → template PT-BR. Allow-list de event types em constante: `paciente.created`, `paciente.updated`, `paciente.merged`, `appointment.created`, `appointment.confirmed`, `appointment.realizada`, `appointment.cancelada`, `appointment.rescheduled`, `prescription.created`, `prescription.renewed`, `conversation.assigned`, `conversation.closed`, `tag.created`, `funil_stage.updated`
- [X] T043 [US4] Criar `app/Services/Panel/Collectors/RecentActivityCollector.php`: query `audit_logs` com filtros `created_at >= now()-24h AND user_id IS NOT NULL AND event_type IN (allow_list)`; eager load `user`; limit 8; order `created_at DESC`; mapeia via `Humanizer::humanize()`; resolve link do recurso quando aplicável
- [X] T044 [P] [US4] Criar `app/Http/Resources/Panel/RecentActivityEntryResource.php` (data-model § 1.5; inclui cálculo de iniciais do actor.name)
- [X] T045 [US4] Integrar `RecentActivityCollector` no `PanelHomeService::buildPayload()`
- [X] T046 [US4] Rodar T039–T041; iterar até verde
- [X] T047 [US4] Validar manualmente: provocar eventos no banco, confirmar humanização, confirmar gate G6 visualmente

**Checkpoint**: US-4 completo. Backend completo (4 seções entregues).

---

## Phase 7: User Story 5 — Toggle Minha Visão / Visão da Clínica (Priority: P2)

**Goal**: Toggle visível para admins, persistido em localStorage por tenant+user. Estado já tratado no backend desde Phase 2 (T007 + T009).

**Independent Test**: Logar como admin-clinica; alternar para "Visão da clínica"; verificar mudança nos números; logout/login e validar que escolha persiste.

### Tests for User Story 5 ⚠️

- [ ] T048 [P] [US5] Estender `PanelHomeScopeTest` (já criado em T016) com cenários US-5 1, 2, 3, 5: `test_admin_sees_toggle`, `test_regular_user_does_not_see_toggle`, `test_clinic_scope_returns_tenant_wide_data`, `test_scope_persists_via_localstorage` (este último em Playwright — mover pra T064)

### Implementation for User Story 5 (frontend)

- [X] T049 [P] [US5] Criar composable `resources/js/composables/usePanelHomeScope.js` (research R11; data-model § 2): API `{ scope: Ref<'user'|'clinic'>, setScope(value), canToggle: Ref<boolean> }`. Lê/escreve chave localStorage `panel_home:scope:v1` aninhada por `tenant_slug + user_id`. Fallback robusto (INV-2, INV-3)
- [X] T050 [P] [US5] Criar componente `resources/js/components/panel-home/ScopeToggle.vue`: dois botões pill ("Minha visão" / "Visão da clínica"); usa `usePanelHomeScope`; renderiza apenas se `canToggle === true` (derivado de `panelHomeResponse.can_toggle_scope`); emite `@change` quando user clica
- [X] T051 [US5] Rodar T048; iterar até verde

**Checkpoint**: US-5 completo (frontend toggle + persistência local).

---

## Phase 8: User Story 6 — Atualização Manual e Automática (Priority: P3)

**Goal**: Botão refresh + auto-refresh 2min com pause em background.

**Independent Test**: Validar via DevTools network: dashboard carregado, 2 min depois 1 nova chamada à API; mudar para outra aba, voltar após 5 min, ver chamada imediata; click no botão dispara chamada + spinner.

### Tests for User Story 6 ⚠️

> Cobertura primária em Playwright E2E (T064) — Page Visibility API não simula bem em PHPUnit.

### Implementation for User Story 6

- [X] T052 [P] [US6] Criar composable `resources/js/composables/useAutoRefresh.js` (research R10): aceita `(callback, intervalMs)` e retorna `{ pause, resume, isRunning }`. Internamente usa `setInterval` apenas quando `document.visibilityState === 'visible'`; escuta `visibilitychange`; ao retornar do background após mais de `intervalMs / 2`, dispara callback imediato uma vez
- [X] T053 [P] [US6] Criar componente `resources/js/components/panel-home/RefreshButton.vue`: botão com ícone Heroicon `arrow-path` (ou similar); recebe prop `loading: boolean` e desabilita + spinner durante carga; `aria-busy="true"` quando loading

**Checkpoint**: Composables prontos para integração final.

---

## Phase 9: Frontend Page + Integration (cobre US-1..US-4 visual)

**Goal**: `PanelHome.vue` reescrita consumindo `usePanelHome` composable + 4 section components. Esta phase consolida o frontend de TODAS as user stories anteriores.

### Implementation

- [X] T054 [P] Criar composable `resources/js/composables/usePanelHome.js` (research R9): API `{ data: Ref<PanelHomeResponse|null>, loading: Ref<boolean>, error: Ref<string|null>, refresh(): Promise<void> }`. Internamente: lê scope via `usePanelHomeScope`; chama `api.get('/panel/home', { params: { scope } })`; gerencia retry/erro; integra `useAutoRefresh(refresh, config.autorefresh_seconds * 1000)`. Pausa request em flight se scope muda durante carga (cancel via AbortController)
- [X] T055 [P] Criar `resources/js/components/panel-home/KpiCard.vue`: props `{ icon: string, label: string, total: number, subInfo: string, link: string, loading: boolean }`; renderiza link clicável; aria-label descritivo com total+subInfo (FR-039); skeleton quando `loading=true`
- [X] T056 [P] Criar `resources/js/components/panel-home/KpiCardsGrid.vue`: grid responsivo `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`; renderiza 4 KpiCard com props derivados da response
- [X] T057 [P] Criar `resources/js/components/panel-home/UpcomingAppointmentsCard.vue`: card de seção com heading + `<ul>`; cada item formatado conforme data-model § 1.3 (hora HH:mm, nome truncado, tipo, profissional, badge status); empty state texto + ícone + botão "Ver agenda completa" → `/panel/agenda`; loading skeleton
- [X] T058 [P] Criar `resources/js/components/panel-home/AttentionItemsCard.vue`: card de seção; cada item com ícone de severidade (danger/warn/info — cores Tailwind `danger-500`/`warning-500`/`primary-500`); título + descrição; link clicável; empty state "Tudo em dia ✅"; loading skeleton
- [X] T059 [P] Criar `resources/js/components/panel-home/RecentActivityCard.vue`: `<ol>` semântico (cronologia); cada entry com avatar de iniciais (círculo bg-primary-100) + descrição humanizada (com link no nome do recurso se `entry.link`) + timestamp relativo (Luxon `DateTime.fromISO(occurred_at).toRelative({ locale: 'pt-BR' })`); empty state; loading skeleton
- [X] T060 Reescrever `resources/js/pages/PanelHome.vue` consumindo `usePanelHome`: header com saudação (`auth.user.name`) + `ScopeToggle` (se `data.can_toggle_scope`) + `RefreshButton`; banner de erro global (FR-037); 4 section cards na ordem (KPIs grid em cima → próximas + alertas em 2 colunas no desktop → atividade full-width)
- [X] T061 Validar manualmente `http://rb-clinic.lvh.me/panel`: ver 4 seções com dados reais, clicar em KPI levou pra tela certa, alternar scope, click refresh, ficar 2min na aba, ver nova chamada na DevTools

**Checkpoint**: Frontend completo.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Acabamento + gates de qualidade.

### Tests adicionais & validation

- [ ] T062 [P] Criar `tests/Browser/PanelHomeE2ETest.php` (Playwright) cobrindo jornada US-1 + US-2 + US-3 combinada: login → ver 4 KPIs → click "Consultas hoje" → URL `/panel/agenda` → voltar → click alerta → URL do recurso. Inclui assertion de toggle persistir após reload (US-5 cenário 4)
- [ ] T063 [P] Adicionar gate G2 explícito (caso ainda não cubra todos os endpoints): `PanelHomeNplusOneTest` valida `assertCount` ≤ 12 queries em chamada cache-miss completa (todos os 4 collectors em ação)
- [ ] T064 Smoke manual no `http://rb-clinic.lvh.me/panel` em 3 cenários: (a) admin-clinica com toggle clínica; (b) recepcionista sem toggle visível; (c) admin+medico com toggle alternando entre minha/clínica

### Qualidade e cobertura

- [ ] T065 [P] Audit a11y Lighthouse/axe em `/panel` em viewports 360px e 1280px — meta SC-007: 0 violations sérias/críticas. Gravar evidência em `specs/010-dashboard-home/a11y-audit.md`
- [X] T066 [P] `vendor/bin/sail npm run build` — confirmar build verde, sem warnings, bundle do PanelHome dentro do orçamento esperado (componentes panel-home/* < 40KB minified gzip)
- [X] T067 [P] `vendor/bin/sail bin pint --dirty --format agent` — formatar arquivos PHP novos
- [ ] T068 [P] `vendor/bin/sail artisan test --compact tests/Feature/Panel tests/Unit/Panel` — todos verdes (6 feature + 4 unit + gates G1–G10 cobertos)
- [ ] T069 [P] `vendor/bin/sail artisan test --compact` (suite full) — confirmar 0 regressão na suíte completa do projeto

### Documentação & re-checks

- [X] T070 Constitution Re-Check pós-implementação — confirmar 7/7 PASS continua válido após código real (especialmente Princípios I LGPD via G6, II multi-tenant via G1, IV test-first via cobertura)
- [X] T071 [P] Atualizar `CLAUDE.md` adicionando seção "Dashboard Home (Fase 10) — Key Patterns" no estilo das outras fases: endpoint consolidado único, 4 collectors com degradação graceful, cache Redis 30s escopado tenant+user+scope, `AttentionItemDto` heterogêneo com severity sorting, Humanizer com allow-list LGPD, useAutoRefresh com Page Visibility, scope persistido em localStorage separado do app-shell
- [X] T072 [P] Criar `specs/010-dashboard-home/DEFERRED.md` listando out-of-scope confirmado + tasks deferred (audit a11y se não pôde rodar agora; Playwright se infra falta; load test informal de p95)
- [X] T073 Atualizar `.specify/feature.json` marcando spec 010 como DELIVERED quando todos os gates passarem

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — sem dependências
- **Phase 2 (Foundational)** — depende de Phase 1; BLOQUEIA US stories
- **Phase 3 (US-1, P1)** — depende de Phase 2
- **Phase 4 (US-2, P1)** — depende de Phase 3 (precisa do service inicial com 1 collector funcionando, padrão de teste, etc.)
- **Phase 5 (US-3, P1)** — depende de Phase 4 (mesmo padrão de adicionar collector)
- **Phase 6 (US-4, P2)** — depende de Phase 5
- **Phase 7 (US-5, P2)** — depende de Phase 2 (T007 + T009 do backend); frontend depende de Phase 9 estar inicializado
- **Phase 8 (US-6, P3)** — frontend-only; pode rodar em paralelo com Phase 9
- **Phase 9 (Frontend Integration)** — depende de Phases 3–6 (backend) + Phases 7–8 (composables)
- **Phase 10 (Polish)** — depende de TODAS phases anteriores

### Within Each User Story

- Testes T013+ (gates) escritos antes da implementação correspondente (Test-First — Princípio IV)
- Collectors antes de integração no service
- Resources antes de uso no controller

### Parallel Opportunities

- **Phase 1**: T002, T003, T004 em paralelo
- **Phase 2**: T005, T006, T007, T008 em paralelo (interface/policy/request); T009–T012 sequenciais
- **Phase 3**: T013–T017 todos [P] (testes diferentes); T018–T020 [P]; T019/T021 sequenciais
- **Phase 5**: T030, T031, T032, T033 testes [P]; T034–T036 [P] (DTOs e Resources)
- **Phase 9**: T054–T059 todos [P] (composable + 5 componentes em arquivos distintos); T060 depende de todos; T061 depende de T060
- **Phase 10**: T062–T069 todos [P]; T070–T073 finais

### MVP Cut Point

**Após Phase 5 (T038)** — você tem o **MVP backend** de Dashboard Home: 3 user stories P1 entregues no backend (KPIs + próximas consultas + alertas). Pode parar aqui se o frontend visual for entregue em PR separada.

**Após Phase 9 (T061)** — você tem o **MVP completo** visual + funcional (P1 + P2). US-6 (auto-refresh) e polish em Phase 10 podem ir em PR menor depois.

---

## Parallel Example: Phase 3 (US-1)

```bash
# Testes em paralelo (4 arquivos distintos):
Task: "KpiCollectorTest.php em tests/Unit/Panel/"
Task: "PanelHomeEndpointTest.php (estrutura) em tests/Feature/Panel/"
Task: "PanelHomeCrossTenantTest.php em tests/Feature/Panel/"
Task: "PanelHomeScopeTest.php em tests/Feature/Panel/"

# Implementação em paralelo:
Task: "KpiCollector.php em app/Services/Panel/Collectors/"
Task: "KpisResource.php em app/Http/Resources/Panel/"
```

## Parallel Example: Phase 9 (Frontend Integration)

```bash
# 5 componentes + 1 composable em paralelo:
Task: "usePanelHome.js em resources/js/composables/"
Task: "KpiCard.vue em resources/js/components/panel-home/"
Task: "KpiCardsGrid.vue em resources/js/components/panel-home/"
Task: "UpcomingAppointmentsCard.vue em resources/js/components/panel-home/"
Task: "AttentionItemsCard.vue em resources/js/components/panel-home/"
Task: "RecentActivityCard.vue em resources/js/components/panel-home/"

# Sequenciais (depende):
# T060 PanelHome.vue DEPOIS de T054–T059
# T061 validação DEPOIS de T060
```

---

## Implementation Strategy

### MVP First (Lotes A + B + C do quickstart — só backend P1)

1. Phase 1 (Setup) → Phase 2 (Foundational)
2. Phase 3 (US-1 KPIs) → demo curl + tests verdes
3. Phase 4 (US-2 Upcoming) → demo
4. Phase 5 (US-3 Attention) → demo (MVP backend completo dos 3 P1)
5. **STOP, VALIDATE** com testes; frontend pode entrar em PR separada

### Incremental delivery completa

1. Setup + Foundational (Phase 1+2)
2. Phase 3 → demo curl com KPIs
3. Phase 4 → demo (US-1 + US-2)
4. Phase 5 → demo (MVP backend dos 3 P1)
5. Phase 6 → demo (US-4 LGPD-safe)
6. Phase 7 → toggle backend
7. Phase 8 → composables prontos
8. Phase 9 → frontend completo no `/panel` ✨
9. Phase 10 → polish, audit, docs

### Parallel team strategy

Com 2 devs após Phase 2:
- **Dev A** (backend): Phase 3 → Phase 4 → Phase 5 → Phase 6
- **Dev B** (frontend): Phase 8 (composables) → Phase 9 (componentes) → smoke local com mocks até backend ficar pronto, depois conecta

---

## Notes

- **[P]** = arquivos distintos, sem dependência em task incompleta
- **[Story]** label rastreia task → user story do spec
- Cada user story independentemente completável e testável
- **Test-first** (Princípio IV): tests dos collectors e gates do contract MUST ser escritos antes da implementação correspondente
- Commit por task ou grupo lógico (sugerido: 1 commit por phase)
- **Constitution Re-Check (T070)** é gate de DoD — não merge sem rodar
- **G1–G10 gates do contract** devem TODOS estar verdes antes do PR final — ver `contracts/api-panel-home.md § 7`
- LGPD G6 e G7 são bloqueantes — não merge mesmo que outros gates estejam verdes
