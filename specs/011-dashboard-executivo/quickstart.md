# Quickstart — Dashboard Executivo (011)

**Status**: Complete | **Date**: 2026-05-23

Guia operacional para implementar, validar e fazer rollout do Dashboard Executivo polish.

---

## Pré-requisitos

- ✅ Branch `011-dashboard-executivo` checked out
- ✅ Spec aprovada (39 FRs, 25 acceptance scenarios, 0 clarifications, 12/12 checklist PASS)
- ✅ Plan aprovado (Constitution Check 7/7)
- ✅ Research consolidado (13 decisões — R2 sparkline DEFERRED é decisão chave)
- ✅ Data model + contracts definidos
- ✅ App Shell (spec 009) entregue
- ✅ Dashboard Home (spec 010) entregue (patterns reutilizáveis)
- ✅ Backend Fase 8 entregue (`reports.executive.show` + `drill` + `export-pdf`)
- ✅ Sail rodando + Vite dev
- ✅ Tenant com dados ≥ 30 dias para visualização real dos KPIs

---

## Ordem sugerida (Lotes)

### Lote A — Backend mínimo + Composables (R1, R3, R4)

1. **Backend** (`app/Http/Controllers/Api/V1/Reports/ExecutiveDashboardController.php`):
   - Adicionar `'24h' => $end->copy()->subHours(24)` no match de `resolvePeriod()` (linha ~105).
   - 1 PHPUnit Feature test: `test_executive_endpoint_supports_24h_preset` validando que `?preset=24h` retorna `period.start === now()-24h`.
2. **Composables frontend**:
   - `resources/js/composables/useDashboardWindow.js` (R4 — pattern do spec 010 `usePanelHomeScope.js`).
   - `resources/js/composables/useExecutiveDashboard.js` (R3 — wrapper sobre `reportsStore`).
3. **i18n** (`resources/js/i18n/pt-BR.json` — bloco `executive_dashboard.*` conforme R12).

### Lote B — Componentes core (R5, R7, R10, R6)

1. `resources/js/components/Reports/PeriodFilter.vue` (R5 — tablist a11y).
2. `resources/js/components/Reports/KpiCardWithSparkline.vue` (R7 — novo card).
3. `resources/js/components/Reports/Sparkline.vue` (R6 — stub funcional preparado).
4. `resources/js/components/Reports/StaleDataBanner.vue` (R10 — extraído da page).

### Lote C — Seções complementares (R8)

1. `resources/js/components/Reports/TopProceduresCard.vue` (barras horizontais top 5).
2. `resources/js/components/Reports/OccupancyByProfessionalCard.vue` (barras + badge carga alta).

### Lote D — Export + Skeleton (R9)

1. `resources/js/components/Reports/ExportMenu.vue` (dropdown PDF + CSV placeholder).
2. `resources/js/components/Reports/DashboardSkeleton.vue` (skeleton da página completa).

### Lote E — Page rewrite + Integration

1. **Reescrever** `resources/js/pages/Reports/ExecutiveDashboardPage.vue`:
   - Consumir `useExecutiveDashboard` composable.
   - Renderizar `PeriodFilter` + `ExportMenu` no header.
   - `StaleDataBanner` condicional (oculta se preset=24h).
   - Grid 4×2 de `KpiCardWithSparkline`.
   - 2 cards inferiores: `TopProceduresCard` + `OccupancyByProfessionalCard`.
   - Empty state + error banner global.
2. Validar manualmente em `http://rb-clinic.lvh.me/panel/relatorios/executivo`.

### Lote F — Testes + Polish

1. `tests/Browser/ExecutiveDashboardE2ETest.php` (R13 — jornada combinada US-1+US-2+US-5).
2. `vendor/bin/sail bin pint --dirty --format agent`.
3. `vendor/bin/sail npm run build` — confirmar bundle sem warnings.
4. Audit a11y Lighthouse/axe na rota.
5. Constitution Re-Check.
6. CLAUDE.md: nova seção "Dashboard Executivo (Fase 11) — Key Patterns".
7. DEFERRED.md (sparkline real + drill-down + CSV).

---

## Comandos úteis

```bash
# Subir tudo
vendor/bin/sail up -d
vendor/bin/sail npm run dev

# Smoke do endpoint via curl (após login)
curl -s -H "Authorization: Bearer <token>" -H "X-Tenant-Slug: rb-clinic" \
  "http://crm.lvh.me/api/v1/reports/executive?preset=24h" | jq

# Tests do módulo apenas
vendor/bin/sail artisan test --compact tests/Browser/ExecutiveDashboardE2ETest.php
vendor/bin/sail artisan test --compact --filter=test_executive_endpoint_supports_24h_preset

# Lint PHP
vendor/bin/sail bin pint --dirty --format agent

# Inspect localStorage (no Chrome DevTools console)
JSON.parse(localStorage.getItem('executive_dashboard:window:v1'))

# Limpar cache do dashboard manualmente
vendor/bin/sail exec redis redis-cli KEYS 'reports:*'   # se houver
```

---

## Critérios de pronto

### Por user story

- [ ] **US-1**: 5 acceptance scenarios → 8 KPI cards renderizados, valores formatados, delta colorido (com polaridade invertida correta para no_show/response_time)
- [ ] **US-2**: 4 acceptance scenarios → filtro funciona, persiste em localStorage, navegação por teclado OK
- [ ] **US-3**: 3 acceptance scenarios → banner stale aparece > 2h, oculto em 24h, desaparece quando dado fresca
- [ ] **US-4**: 5 acceptance scenarios → top 5 procedimentos + ocupação ordenada, badge carga alta ≥ 90%
- [ ] **US-5**: 4 acceptance scenarios → PDF download em < 10s, spinner durante export, CSV desabilitado
- [ ] **US-6**: 4 acceptance scenarios → skeleton em load, error banner com retry, empty state para tenant sem dados

### Gates de validação (contract)

- [ ] **G1**: backend `?preset=24h` retorna `period.start === now()-24h`
- [ ] **G2**: window persiste após logout/login
- [ ] **G3**: window isolada entre tenants e users
- [ ] **G4**: banner stale oculto quando preset=24h
- [ ] **G5**: polaridade invertida correta (no_show diminuindo = seta verde)
- [ ] **G6**: export PDF inicia download em < 10s
- [ ] **G7**: 0 violations axe críticas/sérias
- [ ] **G8**: `reportsStore.js` não modificado nesta spec

### Suite

- [ ] `vendor/bin/sail artisan test --compact` — 0 regressão
- [ ] `vendor/bin/sail npm run build` — verde
- [ ] Manual smoke nos 4 períodos: 24h, 7d, 30d, 90d com tenant que tem dados em todas as janelas

---

## Rollback strategy

Frontend rewrite + 1 linha backend. Rollback = revert do PR. localStorage permanece (usuários sem perderão a preferência salva — só não vai mais ser lida).

---

## DEFERRED / Out-of-scope

Documentado em DEFERRED.md no final:

- **Sparkline real** (FR-012, FR-017): R2 do research. Backend precisa endpoint `/reports/executive/series` retornando série temporal por métrica. **Componente `Sparkline.vue` fica preparado mas sem dados.**
- **CSV export real** (FR-028 já marca como placeholder): backend não tem endpoint correspondente.
- **Drill-down detalhado** dentro do dashboard: rota separada.
- **Auto-refresh**: intencionalmente desligado (dashboard analítico).
- **Comparativo custom entre 2 períodos arbitrários**.
- **Filtros por profissional/tipo**: escopo do OperationalReport.
- **Dark mode**.
- **Personalização** (ocultar/reordenar cards).
- **Sync cross-device** da window escolhida (localStorage é local).

---

## Próximo comando

```
/speckit-tasks
```

Gera `tasks.md` com tasks atômicas T### organizadas pelos 6 lotes A–F.
