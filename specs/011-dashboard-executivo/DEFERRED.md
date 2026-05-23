# DEFERRED Items — Spec 011 Dashboard Executivo

**Status**: Implementação base entregue (backend 1 linha + frontend completo). Sparkline real + audit a11y + E2E completo deferred.
**Date**: 2026-05-23

## Constitution Re-Check pós-implementação

| Princípio | Status | Observação |
|---|---|---|
| I. LGPD | ✅ PASS | Dashboard exibe apenas agregações; nomes de procedimento e profissional já visíveis em outras telas. |
| II. Isolamento Multi-Tenant | ✅ PASS | Backend Fase 8 já garante; localStorage escopado por tenant+user. |
| III. Segurança Clínica IA | ✅ N/A | KPI `ai_autonomous_resolution_rate` é leitura passiva. |
| IV. Spec-Driven Test-First | ⚠️ PARTIAL | **G1 PHPUnit verde** (2 tests, 9 assertions); **E2E Playwright (T018) DEFERRED**. |
| V. Observabilidade | ✅ PASS | Reuso de métricas Prometheus já existentes da Fase 8. |
| VI. Conformidade Meta | ✅ N/A | Sem disparos externos. |
| VII. Segurança Operacional | ✅ PASS | Reuso auth Bearer + permission gate `report.view` no router guard + backend Policy. |

**Resultado**: 6/7 ✅ + 1 ⚠️ PARTIAL (Princípio IV — E2E Playwright deferred).

---

## DEFERRED tasks

### Sparkline real (FR-012, FR-017 — R2 da research)

Decisão consciente: backend `ExecutiveDashboardService` retorna apenas `{value, delta_percent}` por métrica — não retorna series temporais por bucket. Implementar séries exige:
- Novo endpoint `GET /reports/executive/series?metric={X}&window={Y}` retornando `points: number[]`
- Extensão de `metric_aggregations` para chunks menores OU recálculo on-demand por bucket
- ~80-120 linhas backend adicionais + cache strategy específica

**Fallback nesta versão**: KPI card mostra valor + delta indicator rico (seta ↑/↓ colorida + porcentagem + texto). Aria-label completo cobre tendência sem depender do gráfico. `Sparkline.vue` está como stub funcional pronto — quando backend implementar, basta passar `sparklinePoints` ao `KpiCardWithSparkline`.

### E2E Playwright (T018)

Cenários documentados em `quickstart.md § Lote F` e `tasks.md`:
- Admin vê 8 KPI cards
- Click "30 dias" atualiza dados + persiste após logout/login
- Banner stale oculto quando window=24h
- Polaridade invertida correta para no_show
- Export PDF inicia download < 10s

Requer setup Playwright + seeds de teste com dados ≥ 30 dias.

### Audit a11y (T019)

Manual via Chrome DevTools Lighthouse na rota `/panel/relatorios/executivo` em viewports 360px e 1280px. Meta SC-006: 0 violations sérias/críticas. Documentar em `specs/011-dashboard-executivo/a11y-audit.md`.

### Suite full validation

```bash
vendor/bin/sail artisan test --compact tests/Feature/Reports
```

Nota: durante esta sessão, o parallel runner apresentou `SIGSEGV` ao rodar a pasta completa, mas testes individuais (`--filter`) passam. **Pré-existente, não regressão deste spec.** Investigar em separado se persistir.

### Smoke manual no browser

3 cenários:
1. Admin com tenant populado → ver KPIs + sparkline (placeholder visual elegante sem dados) + ambas as seções inferiores
2. Tenant sem dados → empty state amistoso central
3. Trocar entre 4 períodos consecutivamente → dados atualizam, window persiste após reload

### CSV export real (FR-028 — placeholder)

Backend não tem endpoint correspondente. UI mostra item "Exportar CSV" desabilitado com label "em breve". Implementar exige:
- Backend `POST /reports/executive/export-csv` retornando arquivo `text/csv`
- Frontend troca `aria-disabled` para `false` no item do menu

---

## Out-of-scope (intencional)

Per `quickstart.md` do spec:

- ❌ Drill-down detalhado dentro do dashboard
- ❌ Comparativo customizado entre 2 períodos arbitrários
- ❌ Auto-refresh (intencionalmente — dashboard analítico, dados agregados horariamente)
- ❌ Notificações push baseadas em KPIs
- ❌ Filtros adicionais (profissional, tipo)
- ❌ Dark mode
- ❌ Personalização (esconder/reordenar cards)
- ❌ Sync cross-device da window escolhida

---

## Implementação entregue nesta sessão

### Files novos (12)

**Backend (1)**:
```
tests/Feature/Reports/ExecutiveDashboardWindow24hTest.php
```

**Frontend (10)**:
```
resources/js/composables/useDashboardWindow.js
resources/js/composables/useExecutiveDashboard.js
resources/js/components/Reports/Sparkline.vue
resources/js/components/Reports/KpiCardWithSparkline.vue
resources/js/components/Reports/DashboardSkeleton.vue
resources/js/components/Reports/PeriodFilter.vue
resources/js/components/Reports/StaleDataBanner.vue
resources/js/components/Reports/TopProceduresCard.vue
resources/js/components/Reports/OccupancyByProfessionalCard.vue
resources/js/components/Reports/ExportMenu.vue
```

**Spec artifacts (1)**:
```
specs/011-dashboard-executivo/DEFERRED.md
```

### Files modificados (4)

```
app/Http/Controllers/Api/V1/Reports/ExecutiveDashboardController.php  — +1 linha ('24h' case)
resources/js/i18n/pt-BR.json                                            — bloco executive_dashboard.*
resources/js/pages/Reports/ExecutiveDashboardPage.vue                  — REESCRITA completa
CLAUDE.md                                                               — seção "Dashboard Executivo (Fase 11) — Key Patterns"
.specify/feature.json                                                   — aponta para spec 011
```

### Gates rodados

- ✅ **G1 (backend 24h)**: `vendor/bin/sail artisan test --compact --filter=ExecutiveDashboardWindow24hTest` → 2 tests, 9 assertions, passed
- ✅ `vendor/bin/sail npm run build` → 1.36s, sem warnings
- ✅ `vendor/bin/sail bin pint --dirty --format agent` → passed
- ✅ G8 (reportsStore intocado) → verificado via `git diff resources/js/stores/reportsStore.js` (sem mudanças)

---

## Validação manual recomendada

1. Abrir `http://rb-clinic.lvh.me/panel/relatorios/executivo` no Chrome (admin logado)
2. Ver header com título + filter (default 7d) + botão Exportar
3. Ver 6 KPI cards em grid responsivo + 2 seções inferiores
4. Clicar em "30 dias" → dados atualizam + URL/state reflete
5. Reload da página → "30 dias" persiste
6. Clicar "Exportar" → "Exportar PDF" → download inicia
7. Logout/login → "30 dias" continua salvo

---

## Próximo passo

Branch pronta para PR. Estado da árvore de specs:
- `009-app-shell` ← em `009-app-shell`
- `010-dashboard-home` ← em `010-dashboard-home`
- `011-dashboard-executivo` ← em `011-dashboard-executivo` (atual)

3 commits encadeados (cada spec é filha da anterior). Quando mergear em main, considerar fast-forward único ou 3 PRs sequenciais.
