# Implementation Plan: Dashboard Home (US-1.5)

**Branch**: `010-dashboard-home` | **Date**: 2026-05-23 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-dashboard-home/spec.md`

## Summary

Substituir o placeholder mínimo do `/panel` (entregue no spec 009) por um dashboard operacional consolidado em UM endpoint backend (`GET /api/v1/panel/home`) que retorna 4 KPIs + lista de próximas consultas (6h) + lista de alertas acionáveis + timeline de atividade recente. Frontend monta a tela via componentes Vue dentro do `AppShell` já existente. Toggle "Minha visão / Visão da clínica" persistido localmente. Auto-refresh 2min com pausa em background.

Abordagem técnica: introduzir um **PanelHomeService** que orquestra queries agregadas (count) e queries de listas com eager-loading rigoroso através de **collectors específicos por seção** (`KpiCollector`, `UpcomingAppointmentsCollector`, `AttentionItemsCollector`, `RecentActivityCollector`). Cache Redis 30s escopado por `tenant_id + user_id + scope` (chave: `panel_home:{tenant}:{user}:{scope}`). Métricas Prometheus em `PanelHomeMetrics extends AbstractModuleMetrics`. Permission gate por seção via `PanelHomePolicy`. Zero novas tabelas, zero novas migrations — feature 100% read-only sobre dados existentes.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), Vue 3.5 (Composition API)
**Primary Dependencies**: `laravel/framework@^13`, `laravel/sanctum@^4`, `predis/predis` (Redis cache), `pinia@^2`, `vue-router@^4`, `vue-i18n@^10`, `tailwindcss@^4`. Métricas via wrapper interno `App\Support\Metrics\AbstractModuleMetrics` (já presente desde Fase 5).
**Storage**: PostgreSQL 18 (queries read-only). Redis 7 (cache 30s + métrica). Nenhuma migration nova.
**Testing**: PHPUnit Feature tests (predominantes), Unit tests para os 4 collectors, Playwright E2E para jornadas críticas do front. Cobertura mínima: gate test cross-tenant + gate test N+1 + 1 cenário feature por user story.
**Target Platform**: SPA Vue dentro de Laravel monolith (mesmo monorepo); navegadores modernos suportados pelo projeto.
**Project Type**: Web app (backend Laravel + frontend Vue SPA — convenção do projeto).
**Performance Goals**: p95 do endpoint < 500 ms (SC-003). Render inicial visível em < 1 s em rede típica (SC-002). Auto-refresh a cada 2 min com aba visível (FR-027). Cache TTL 30 s configurável (FR-031).
**Constraints**: 0 violações sérias/críticas em axe/Lighthouse (SC-007). 1 única request HTTP para a primeira carga (SC-008 — endpoint consolidado). 0 requests com aba em background (SC-009). Zero N+1 garantido por gate test.
**Scale/Scope**: Endpoint chamado em todo login + a cada 2 min em sessão ativa. Para clínica média (~10 profissionais, ~500 pacientes ativos, ~50 consultas/dia), p95 alvo é trivial; para tenant grande (~100 profissionais, ~10k consultas/dia), cache Redis 30s amortece a maior parte das chamadas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Avaliação contra os 7 princípios (v1.4.0):

### I. Privacidade, Consentimento e Conformidade LGPD ✅ PASS

- **Sem novo PII coletado**: feature consome dados que já existem em consultas, conversas, pacientes, receitas, audit logs — todos já com gates LGPD aplicados em sua fase de origem.
- **Timeline minimiza PII** (FR-019): gate explícito de NÃO exibir CPF, telefone completo, email completo ou conteúdo clínico na descrição humanizada da atividade. Apenas nomes dos atores e dos recursos (já visíveis em telas individuais).
- **Cache key** não inclui PII (tenant_id e user_id são identificadores internos, não pessoais).
- **Sem envio ao LLM**: dashboard não interage com IA.
- **Audit log**: queries do `RecentActivityCollector` são SELECT — não geram novos audit entries.

### II. Isolamento Multi-Tenant ✅ PASS

- **TODAS as queries usam global scopes existentes** (`Appointment`, `Paciente`, `Conversation`, `Prescription`, `ConfirmationDispatch`, `WebhookDelivery`, `AuditLog`) — já garantem `tenant_id = current_tenant`.
- **Gate test obrigatório**: `PanelHomeCrossTenantTest` valida que user de tenant A acessando o endpoint NUNCA recebe dados de tenant B (FR-034, FR-035).
- **Cache key escopada**: `panel_home:{tenant_id}:{user_id}:{scope}` — chaves de tenants diferentes nunca colidem no Redis.

### III. Segurança Clínica e Auditabilidade da IA ✅ N/A

- Feature não interage com camada de IA.

### IV. Desenvolvimento Spec-Driven e Test-First ✅ PASS

- Spec aprovada com 29 acceptance scenarios + 3 clarifications resolvidas.
- Testes obrigatórios planejados:
  - 1 Feature test por user story (6 testes)
  - 4 Unit tests (um por collector)
  - 1 gate test cross-tenant
  - 1 gate test N+1 (`assertQueryCount` ou similar)
  - 1 Playwright E2E para jornada US-1 + US-2 + US-3 combinados
  - 1 gate test LGPD (FR-019) — valida que descrições da timeline não contêm CPF/email/telefone/conteúdo clínico via regex assertions
- Pint passa; sem novas migrations.

### V. Observabilidade e Excelência Operacional ✅ PASS

- **Métricas Prometheus**: `panel_home_requests_total{tenant,scope,cache_hit}` + `panel_home_duration_seconds` (histogram com buckets adequados a p95 < 500ms) + `panel_home_cache_hits_total{tenant}`.
- **Sentry tags**: `panel_home.section_failed` quando algum collector falha individualmente (response degradado com placeholder na seção, não erro 500 total).
- **Logs estruturados**: ações já são logadas; dashboard apenas LÊ logs existentes.

### VI. Conformidade Meta nos Disparos ✅ N/A

- Feature não envia mensagens externas.

### VII. Segurança Operacional ✅ PASS

- **Auth**: endpoint usa `auth:sanctum` (Bearer) + `tenant.slug` (triple-check Fase 4) + `tenant.not-suspended`. Sem nova superfície de auth.
- **Sem v-html**: descrições da timeline e títulos de alertas usam interpolação Vue padrão (auto-escape).
- **Rate limit**: aplicado pelo middleware `throttle:api` padrão (60 req/min). Cache Redis amortece bursts.
- **DOMPurify**: N/A (sem HTML user-provided no payload).

**Resultado Constitution Check**: 7/7 ✅ — Nenhuma violação. Sem amendment. Sem entrada em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/010-dashboard-home/
├── plan.md                         # This file
├── research.md                     # Phase 0 output
├── data-model.md                   # Phase 1 output (view models — sem migrations)
├── quickstart.md                   # Phase 1 output (rollout guide)
├── contracts/
│   └── api-panel-home.md           # Contrato do GET /api/v1/panel/home
├── checklists/
│   └── requirements.md             # 12/12 PASS (gerado por /speckit-specify)
└── tasks.md                        # Phase 2 output — gerado por /speckit-tasks
```

### Source Code (repository root)

Estrutura nova ou modificada:

```text
app/
├── Http/
│   ├── Controllers/Api/V1/Panel/
│   │   └── PanelHomeController.php             # [NEW] GET /api/v1/panel/home
│   ├── Requests/Panel/
│   │   └── PanelHomeIndexRequest.php           # [NEW] valida ?scope=user|clinic
│   └── Resources/Panel/
│       ├── PanelHomeResource.php               # [NEW] envelope completo
│       ├── KpisResource.php                    # [NEW] 4 KPIs
│       ├── UpcomingAppointmentResource.php     # [NEW] item de próxima consulta
│       ├── AttentionItemResource.php           # [NEW] item de alerta heterogêneo
│       └── RecentActivityEntryResource.php     # [NEW] entry de timeline
├── Policies/Panel/
│   └── PanelHomePolicy.php                     # [NEW] canSeeClinicScope, canSeeWebhookDlqAlerts
├── Services/Panel/
│   ├── PanelHomeService.php                    # [NEW] orquestrador + cache
│   └── Collectors/
│       ├── KpiCollector.php                    # [NEW] 4 contagens agregadas
│       ├── UpcomingAppointmentsCollector.php   # [NEW] lista 5×6h
│       ├── AttentionItemsCollector.php         # [NEW] mix de 5 alertas por severidade
│       └── RecentActivityCollector.php         # [NEW] timeline 8 entries 24h
└── Support/Metrics/
    ├── PanelHomeMetrics.php                    # [NEW] extends AbstractModuleMetrics
    └── PanelHomeMetricsContract.php            # [NEW] interface

routes/
└── api.php                                     # [MOD] +1 rota authenticated

resources/
├── js/
│   ├── pages/
│   │   └── PanelHome.vue                       # [MOD] substitui placeholder atual
│   ├── components/panel-home/
│   │   ├── KpiCard.vue                         # [NEW]
│   │   ├── KpiCardsGrid.vue                    # [NEW]
│   │   ├── UpcomingAppointmentsCard.vue        # [NEW]
│   │   ├── AttentionItemsCard.vue              # [NEW]
│   │   ├── RecentActivityCard.vue              # [NEW]
│   │   ├── ScopeToggle.vue                     # [NEW]
│   │   └── RefreshButton.vue                   # [NEW]
│   ├── composables/
│   │   ├── usePanelHome.js                     # [NEW] fetch + estado + auto-refresh
│   │   ├── usePanelHomeScope.js                # [NEW] persistência local do scope
│   │   └── useAutoRefresh.js                   # [NEW] intervalo + Page Visibility API
│   └── i18n/
│       └── pt-BR.json                          # [MOD] +bloco panel_home.*

config/
└── panel.php                                   # [NEW] cache_ttl, autorefresh_ms, janela 6h

lang/pt_BR/
└── panel.php                                   # [NEW] strings backend (envelope error msgs)

tests/
├── Feature/Panel/
│   ├── PanelHomeEndpointTest.php               # [NEW] cobertura US-1..US-6
│   ├── PanelHomeCrossTenantTest.php            # [NEW] gate Princípio II
│   ├── PanelHomeNplusOneTest.php               # [NEW] gate Princípio IV (assertQueryCount)
│   ├── PanelHomeCacheTest.php                  # [NEW] TTL 30s + invalidação
│   ├── PanelHomeScopeTest.php                  # [NEW] Minha × Clínica + role dupla (Q1)
│   └── PanelHomeRecentActivityLgpdTest.php     # [NEW] gate FR-019 LGPD
├── Unit/Panel/
│   ├── KpiCollectorTest.php
│   ├── UpcomingAppointmentsCollectorTest.php
│   ├── AttentionItemsCollectorTest.php
│   └── RecentActivityCollectorTest.php
└── Browser/
    └── PanelHomeE2ETest.php                    # [NEW] Playwright
```

**Structure Decision**: Feature híbrida (backend service + endpoint + frontend SPA). Backend respeita pattern já estabelecido: `app/Http/Controllers/Api/V1/{Module}/`, `app/Services/{Module}/`, `app/Http/Resources/{Module}/`, `app/Support/Metrics/{Module}Metrics`. Sem nova subpasta `app/Domain/` porque o módulo não introduz domínio próprio — apenas agrega dados de domínios existentes. Frontend cria nova subpasta `components/panel-home/` (não conflita com `components/layout/` do spec 009).

## Complexity Tracking

> Nenhuma violação constitucional detectada. Esta seção fica vazia.

Não há desvios. Toda a feature opera dentro dos limites: zero novas tabelas, zero novas integrações externas, zero novos endpoints de autenticação, zero novo PII coletado, zero interação com IA. Apenas agrega read-only dados existentes.
