# Implementation Plan: Dashboard Executivo (US-10.1)

**Branch**: `011-dashboard-executivo` | **Date**: 2026-05-23 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/011-dashboard-executivo/spec.md`

## Summary

Polir o Dashboard Executivo (`/panel/relatorios/executivo`) aplicando o mockup visual e completando a integração com o backend já entregue na Fase 8. Esta spec é **predominantemente frontend** — backend já fornece 8 métricas pré-agregadas (cron horário) + drill-down + export PDF, e o frontend tem esqueleto funcional (`ExecutiveDashboardPage.vue` ~194 linhas + `KpiCardWithTrend.vue` + Pinia `reportsStore`).

Abordagem técnica: reescrever a página dentro do App Shell (spec 009) aplicando o mockup, **adicionar janela "24h" (atualmente só tem 7d/30d/90d)**, **persistir escolha em `localStorage`** com mesma estratégia das specs 009/010 (chave própria `executive_dashboard:window:v1`), **estender `KpiCardWithTrend.vue` com sparkline SVG inline** (ou criar `KpiCardWithSparkline.vue` separado), criar componentes para as 2 seções complementares (`TopProceduresCard.vue` + `OccupancyByProfessionalCard.vue`) e refinar os estados visuais (skeleton, error banner, empty state). Backend recebe **mudança mínima** — apenas adicionar suporte ao window `24h` (live query) no `ExecutiveDashboardService` se ainda não suportado.

## Technical Context

**Language/Version**: Vue 3.5 (Composition API), JavaScript ES2022+, PHP 8.5 (apenas para extensão mínima do backend Fase 8 se necessário)
**Primary Dependencies**: `vue@^3.5`, `vue-router@^4.5`, `pinia@^2.3`, `vue-i18n@^10.0`, `tailwindcss@^4.0`, `luxon` (relative time — já dep), `@vueuse/core@^12.0`. SVG inline para sparkline — sem nova dep (research R3).
**Storage**: PostgreSQL (consumido pelo backend Fase 8 — sem mudanças schema). Redis (cache do backend Fase 8 — sem mudanças). `localStorage` (cliente — preferência de window).
**Testing**: PHPUnit Feature (se backend mudar para suportar `window=24h`) + Playwright E2E para jornada US-1+US-2+US-5; Unit (Vitest não presente — testar via Playwright o sparkline e o filter behavior).
**Target Platform**: Navegadores modernos suportados pelo projeto; mobile responsivo até 360px.
**Project Type**: Web app frontend-heavy (backend ≥95% reuso da Fase 8).
**Performance Goals**: Render visível < 1,2 s (SC-002); troca de período < 2 s (SC-003); export PDF inicia download < 10 s (SC-007).
**Constraints**: 0 violations sérias/críticas axe/Lighthouse (SC-006); zero auto-refresh (FR-033 — diferente do Home).
**Scale/Scope**: 1 página principal + 8 KPI cards + 2 seções complementares + 1 menu de export + 1 filtro de período. Reusa store Pinia já existente.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Avaliação contra os 7 princípios (v1.4.0):

### I. Privacidade, Consentimento e Conformidade LGPD ✅ PASS

- **Sem PII individual exposta**: dashboard mostra apenas agregações (contagens, percentuais, médias) — não há nome de paciente, CPF, telefone ou dados clínicos.
- **Top procedimentos** mostra nome do procedimento (não-PII) + contagem.
- **Ocupação por profissional** mostra nome do profissional (já visível na agenda) + % de ocupação.
- Backend Fase 8 já garante isolamento — esta spec consome contratos imutáveis.

### II. Isolamento Multi-Tenant ✅ PASS

- Backend Fase 8 já garante via global scopes + `tenant_id` no `metric_aggregations` (PARTIAL UNIQUE composto).
- Cache do backend escopado por tenant.
- localStorage de window aninhado por `tenant_slug + user_id` (mesma estratégia das specs 009/010).

### III. Segurança Clínica e Auditabilidade da IA ✅ N/A

- Feature não interage com camada de IA. (KPI "AI autonomous resolution" é leitura passiva da métrica já agregada.)

### IV. Desenvolvimento Spec-Driven e Test-First ✅ PASS

- Spec aprovada com 25 acceptance scenarios.
- Testes planejados:
  - 1 Playwright E2E para jornada combinada US-1+US-2+US-5 (filter + KPIs + export)
  - 1 teste de regressão de localStorage (G3) — persiste/restaura
  - 1 teste de acessibilidade (axe via Playwright integration)
  - **Sem Vitest unit tests** — projeto não tem Vitest configurado (verificado no spec 009); cobertura via E2E é suficiente para esta camada visual.
  - Se backend precisar mudar para suportar `24h`: 1 PHPUnit Feature test do endpoint com `?window=24h`.
- Pint passa; sem novas migrations no banco (gate G2 documentado mas backend Fase 8 já valida N+1).

### V. Observabilidade e Excelência Operacional ✅ PASS

- Backend Fase 8 já tem `ReportsMetrics` Prometheus (não citado explicitamente na spec, mas existente). Nenhuma métrica nova necessária no frontend.
- Sentry: breadcrumb opcional em mudança de período (FR de UX, não constitucional).

### VI. Conformidade Meta nos Disparos ✅ N/A

- Feature não envia mensagens externas.

### VII. Segurança Operacional ✅ PASS

- Endpoint backend já usa Bearer Sanctum + `tenant.slug` + `tenant.not-suspended` (Fase 4 + Fase 8).
- Sem v-html. Sem inline scripts. CSP estrita preservada.
- Permission gate `report.view` já no router meta + backend Policy.

**Resultado Constitution Check**: 7/7 ✅. Nenhuma violação. Sem amendment. Sem entrada em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/011-dashboard-executivo/
├── plan.md                                # This file
├── research.md                            # Phase 0 output
├── data-model.md                          # Phase 1 output (view models — sem migrations)
├── quickstart.md                          # Phase 1 output (rollout guide)
├── contracts/
│   └── frontend-integration.md            # Como o front consome endpoints Fase 8
├── checklists/
│   └── requirements.md                    # 12/12 PASS
└── tasks.md                                # Phase 2 — /speckit-tasks
```

### Source Code (repository root)

Estrutura nova e modificada (predominantemente frontend):

```text
resources/js/
├── pages/Reports/
│   └── ExecutiveDashboardPage.vue              # [REWRITE] aplicar mockup, persistir window, 24h
├── components/Reports/
│   ├── KpiCardWithSparkline.vue                # [NEW] extends KpiCardWithTrend com SVG sparkline
│   ├── Sparkline.vue                           # [NEW] SVG inline standalone (~50 linhas)
│   ├── PeriodFilter.vue                        # [NEW] tablist com 4 windows + keyboard nav
│   ├── TopProceduresCard.vue                   # [NEW] barras horizontais — 5 procedimentos
│   ├── OccupancyByProfessionalCard.vue         # [NEW] barras horizontais — N profissionais
│   ├── ExportMenu.vue                          # [NEW] dropdown PDF + CSV placeholder
│   ├── StaleDataBanner.vue                     # [NEW] banner amistoso > 2h
│   └── DashboardSkeleton.vue                   # [NEW] skeleton para a página completa
├── composables/
│   ├── useExecutiveDashboard.js                # [NEW] wrapper sobre reportsStore + window persist
│   └── useDashboardWindow.js                   # [NEW] localStorage tenant+user da window escolhida
└── i18n/
    └── pt-BR.json                              # [MOD] bloco executive_dashboard.*

app/
└── Http/Controllers/Api/V1/Reports/
    └── ExecutiveDashboardController.php        # [MOD?] suporte window=24h se ainda não tem

app/Services/Reports/                            # [MOD?] ExecutiveDashboardService — branch para query live na janela 24h

tests/
└── Browser/
    └── ExecutiveDashboardE2ETest.php           # [NEW] Playwright jornada combinada
```

**Structure Decision**: Reuso máximo da infra Fase 8. **Backend toca apenas o mínimo necessário** (verificação se `window=24h` já é suportado pelo `ExecutiveDashboardService`; se não, adicionar branch para query live conforme Q9 da Fase 8). **Frontend reescreve** a página + adiciona 7 componentes novos + 2 composables. Pasta `components/Reports/` já existe da Fase 8 — coloca os novos lá para coesão com `KpiCardWithTrend.vue`. **Pinia store `reportsStore.js` permanece intocada** — apenas o consumer (composable + page) muda.

## Complexity Tracking

> Nenhuma violação constitucional detectada. Esta seção fica vazia.

Não há desvios. Esta feature é especificamente "polish + completar integração" — fora dos limites: zero novas tabelas, zero novos endpoints (backend Fase 8 reusado), zero nova permission, zero IA. Mesmo o backend pode receber 0 mudanças se `window=24h` já estiver implementado (verificar em Phase 0 research).
