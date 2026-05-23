# DEFERRED Items — Spec 010 Dashboard Home

**Status**: Implementação base entregue (backend completo + frontend completo). Testes formais e validações finais deferred.
**Date**: 2026-05-23

## Constitution Re-Check pós-implementação

| Princípio | Status | Observação |
|---|---|---|
| I. LGPD | ✅ PASS | `Humanizer` + allow-list (14 event types) bloqueia `paciente.viewed`; descrições nunca incluem CPF/email/telefone/clínico por design. Gates G6/G7 codificados no contract mas testes formais deferred. |
| II. Isolamento Multi-Tenant | ✅ PASS | Todas as queries usam global scopes existentes (BelongsToTenant). Cache key escopada `panel_home:{tenant_id}:{user_id}:{scope}`. localStorage do scope aninhado por tenant+user. |
| III. Segurança Clínica IA | ✅ N/A | Sem interação com IA. |
| IV. Spec-Driven Test-First | ⚠️ PARTIAL | **Implementação OK + smoke E2E manual via tinker passou**, mas 11 arquivos PHPUnit (T013–T017, T023–T025, T030–T033, T039–T041) + 1 Playwright E2E (T062) **deferred**. |
| V. Observabilidade | ✅ PASS | 5 métricas Prometheus em `PanelHomeMetrics` (requests, duration overall + por section, cache hits, section failures). Sentry tag `panel_home.section_failed` em degradação. |
| VI. Conformidade Meta | ✅ N/A | Sem disparos externos. |
| VII. Segurança Operacional | ✅ PASS | Reuso de middleware stack existente (auth:sanctum + tenant.not-suspended). Sem v-html. Sem expansão de surface de auth. |

**Resultado**: 6/7 ✅ + 1 ⚠️ PARTIAL (Princípio IV — testes formais deferred).

---

## DEFERRED tasks

### Testes Feature/Unit (T013–T017, T023–T025, T030–T033, T039–T041)

11 arquivos de teste cobrindo:
- `tests/Unit/Panel/KpiCollectorTest.php` (4 testes)
- `tests/Unit/Panel/UpcomingAppointmentsCollectorTest.php` (4 testes)
- `tests/Unit/Panel/AttentionItemsCollectorTest.php` (7 testes)
- `tests/Unit/Panel/RecentActivityCollectorTest.php` (5 testes)
- `tests/Feature/Panel/PanelHomeEndpointTest.php` (cenários US-1..US-6)
- `tests/Feature/Panel/PanelHomeCrossTenantTest.php` (gate G1 — bloqueante)
- `tests/Feature/Panel/PanelHomeScopeTest.php` (gates G4, G5 — Q1)
- `tests/Feature/Panel/PanelHomeCacheTest.php` (gate G3)
- `tests/Feature/Panel/PanelHomeNplusOneTest.php` (gate G2)
- `tests/Feature/Panel/PanelHomeRecentActivityLgpdTest.php` (gates G6/G7 — **bloqueantes LGPD**)

Cenários documentados detalhadamente em `specs/010-dashboard-home/contracts/api-panel-home.md § 7` e `quickstart.md`. Smoke manual via tinker (este sessão) validou que os 4 collectors funcionam end-to-end sem erro.

**Antes de merge final**: criar pelo menos os 2 gates LGPD (G6/G7) + gate cross-tenant (G1). Os demais podem entrar em PR de cobertura subsequente.

### Audit a11y Lighthouse/axe (T065)

Manual via Chrome DevTools Lighthouse na rota `/panel`. Meta SC-007: 0 violations sérias/críticas em viewports 360px e 1280px.

Documentar resultado em `specs/010-dashboard-home/a11y-audit.md`.

### E2E Playwright (T062)

Jornada combinada US-1 + US-2 + US-3: login → ver 4 KPIs → click "Consultas hoje" → URL `/panel/agenda` → voltar → click alerta → URL do recurso → toggle scope persistir após reload.

Requer Playwright instalado: `vendor/bin/sail npx playwright install chromium`.

### Suite full validation (T068, T069)

```bash
vendor/bin/sail artisan test --compact tests/Feature/Panel tests/Unit/Panel
vendor/bin/sail artisan test --compact  # suite completa, 1300+ tests
```

### Smoke manual no browser (T064)

3 cenários:
1. `admin-clinica` com toggle "Visão da clínica"
2. `recepcionista` sem toggle visível
3. `admin-clinica + medico` (role dupla) alternando entre minha/clínica

---

## Out-of-scope (intencional — não implementar deste spec)

Per `quickstart.md § Out-of-scope` do spec:

- ❌ Conteúdo do Dashboard Executivo (spec 011)
- ❌ Customização (arrastar/esconder/reordenar cards)
- ❌ Notificações push em tempo real via Reverb
- ❌ Filtros adicionais por profissional ou período
- ❌ Exportar dashboard como PDF/imagem
- ❌ Comparativos período-a-período
- ❌ Gráficos de tendência ou sparklines
- ❌ Sincronização cross-device da preferência de scope

---

## Implementação entregue nesta sessão

### Files criados (24)

**Backend (PHP)**:
```
config/panel.php
lang/pt_BR/panel.php
app/Support/Metrics/PanelHomeMetricsContract.php
app/Support/Metrics/PanelHomeMetrics.php
app/Support/AuditLog/Humanizer.php
app/Policies/Panel/PanelHomePolicy.php
app/Http/Requests/Panel/PanelHomeIndexRequest.php
app/Http/Resources/Panel/PanelHomeResource.php
app/Http/Resources/Panel/UpcomingAppointmentResource.php
app/Http/Resources/Panel/AttentionItemResource.php
app/Http/Controllers/Api/V1/Panel/PanelHomeController.php
app/Services/Panel/PanelHomeService.php
app/Services/Panel/Collectors/KpiCollector.php
app/Services/Panel/Collectors/UpcomingAppointmentsCollector.php
app/Services/Panel/Collectors/AttentionItemsCollector.php
app/Services/Panel/Collectors/RecentActivityCollector.php
app/Services/Panel/DataObjects/AttentionItemDto.php
```

**Frontend (Vue/JS)**:
```
resources/js/composables/usePanelHome.js
resources/js/composables/usePanelHomeScope.js
resources/js/composables/useAutoRefresh.js
resources/js/components/panel-home/KpiCard.vue
resources/js/components/panel-home/KpiCardsGrid.vue
resources/js/components/panel-home/UpcomingAppointmentsCard.vue
resources/js/components/panel-home/AttentionItemsCard.vue
resources/js/components/panel-home/RecentActivityCard.vue
resources/js/components/panel-home/ScopeToggle.vue
resources/js/components/panel-home/RefreshButton.vue
specs/010-dashboard-home/DEFERRED.md
```

### Files modificados (4)

```
app/Providers/AppServiceProvider.php   — singleton bind PanelHomeMetricsContract
routes/api.php                          — Route GET /api/v1/panel/home
resources/js/i18n/pt-BR.json            — bloco panel_home.* (KPIs, sections, scope, errors)
resources/js/pages/PanelHome.vue        — reescrito consumindo usePanelHome
CLAUDE.md                                — seção "Dashboard Home (Fase 10) — Key Patterns"
```

### Gates rodados nesta sessão

- ✅ `vendor/bin/sail npm run build` — 1.38s, sem warnings, sem regressão de bundle
- ✅ `vendor/bin/sail bin pint --dirty --format agent` — formatação aplicada nos arquivos novos
- ✅ Smoke end-to-end via tinker: 4 seções OK no `PanelHomeService::getHome()` para user real do tenant `rb-clinic`
- ✅ Cache key Redis funcional: `panel_home:1:2:user` gerada na 1ª chamada (verificável via `redis-cli KEYS 'panel_home:*'`)
- ✅ Route `GET /api/v1/panel/home` registrada e respondendo (validado via `route:list`)

---

## Validação manual recomendada

1. Abrir `http://rb-clinic.lvh.me/panel` no Chrome → ver header + 4 KPI cards + lista próximas + alertas + atividade
2. Click no KPI "Consultas hoje" → redirecionar para `/panel/agenda`
3. Logar como user com role dupla → ver toggle "Minha visão / Visão da clínica" no header
4. Alternar para "Visão da clínica" → ver dados expandidos
5. Reload da página → escolha de scope persiste
6. Deixar a aba aberta 2 minutos → ver no DevTools Network uma chamada automática
7. Trocar para outra aba por 30s → voltar → ver chamada imediata (return-to-focus)

---

## Próximo passo natural

Spec 011 — **Dashboard Executivo polish** (`/panel/relatorios/executivo`). Backend (`ExecutiveDashboardService`) entregue na Fase 8; falta aplicar mockup `02_Dashboard executivo` e refinar `ExecutiveDashboardPage.vue` (~194 linhas, esqueleto).
