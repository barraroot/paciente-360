# Quickstart — Dashboard Home (010)

**Status**: Complete | **Date**: 2026-05-23

Guia operacional para implementar, validar e fazer rollout do Dashboard Home. Para uso por implementer (humano ou agente).

---

## Pré-requisitos

- ✅ Branch `010-dashboard-home` checked out
- ✅ Spec aprovada (43 FRs, 29 acceptance scenarios, 3 clarifications, 12/12 checklist PASS)
- ✅ Plan aprovado (Constitution Check 7/7)
- ✅ Research consolidado (13 decisões)
- ✅ Data model + contract definidos
- ✅ App Shell (spec 009) entregue — `/panel` já tem chrome
- ✅ Sail rodando + Vite dev
- ✅ Pelo menos 1 tenant com dados de teste (consultas, conversas, pacientes, receitas)
- ✅ Usuários para teste: `admin-clinica`, `medico`, `recepcionista`, e um híbrido `admin-clinica + medico`

---

## Ordem sugerida de implementação (Lotes)

### Lote A — Backend Foundation + KPIs (US-1)

Implementar:

1. `config/panel.php` (R3) com `cache_ttl_seconds`, `autorefresh_seconds`, `upcoming_window_minutes`
2. `app/Support/Metrics/PanelHomeMetricsContract.php` (interface)
3. `app/Support/Metrics/PanelHomeMetrics.php` (extends `AbstractModuleMetrics`, R12)
4. `app/Policies/Panel/PanelHomePolicy.php` (R8 — `canSeeClinicScope`, `canSeeWebhookDlqAlerts`)
5. `app/Services/Panel/Collectors/KpiCollector.php` (R4 — 4 counts agregados, com filtros de scope)
6. `app/Services/Panel/PanelHomeService.php` (orquestrador inicial — só KPIs)
7. `app/Http/Resources/Panel/KpisResource.php`
8. `app/Http/Resources/Panel/PanelHomeResource.php` (envelope com apenas `kpis` no início)
9. `app/Http/Requests/Panel/PanelHomeIndexRequest.php`
10. `app/Http/Controllers/Api/V1/Panel/PanelHomeController.php`
11. `routes/api.php` — `Route::get('/panel/home', PanelHomeController::class)->middleware(['auth:sanctum','tenant.slug','tenant.not-suspended'])`
12. Tests:
    - `tests/Unit/Panel/KpiCollectorTest.php` (assertion por KPI individual)
    - `tests/Feature/Panel/PanelHomeEndpointTest.php` (cobertura US-1 cenário 1)
    - `tests/Feature/Panel/PanelHomeCrossTenantTest.php` (gate G1)
    - `tests/Feature/Panel/PanelHomeScopeTest.php` (gates G4, G5 — Q1)
    - `tests/Feature/Panel/PanelHomeCacheTest.php` (gate G3)

**Validar manualmente**:
- `curl -H "Authorization: Bearer <token>" -H "X-Tenant-Slug: rb-clinic" http://crm.lvh.me/api/v1/panel/home`
- KPIs devem bater com queries manuais no banco

### Lote B — Upcoming Appointments + Attention Items (US-2 + US-3)

Implementar:

13. `UpcomingAppointmentsCollector.php` (eager loading: appointment + appointment_type + paciente + professional)
14. `UpcomingAppointmentResource.php`
15. `AttentionItemsCollector.php` (heterogêneo — 5 sub-queries por tipo, união ordenada)
16. `AttentionItemResource.php`
17. Adicionar seções no `PanelHomeService` + `PanelHomeResource`
18. Tests:
    - `UpcomingAppointmentsCollectorTest.php`
    - `AttentionItemsCollectorTest.php` (1 teste por tipo de alerta)
    - Feature test ampliado em `PanelHomeEndpointTest`
    - `PanelHomeNplusOneTest.php` (gate G2 — assertQueryCount ≤ 12)
    - `PanelHomeEndpointTest::test_user_without_webhook_manage_does_not_see_dlq_alerts` (gate G8)
    - `PanelHomeEndpointTest::test_upcoming_appointments_limited_to_5_in_6h_window` (gate G10)

**Validar manualmente**:
- Criar artificialmente cenários para cada tipo de alerta
- Confirmar ordenação por severidade

### Lote C — Recent Activity + LGPD Gates (US-4)

Implementar:

19. Helper `humanizeAuditEvent(AuditLog): string` em `app/Support/AuditLog/Humanizer.php`
20. `RecentActivityCollector.php` com allow-list de event types (R7)
21. `RecentActivityEntryResource.php`
22. Adicionar seção no `PanelHomeService` + `PanelHomeResource`
23. Tests:
    - `RecentActivityCollectorTest.php`
    - `PanelHomeRecentActivityLgpdTest.php` — **gates G6 e G7 (críticos LGPD)**
    - Feature test scenarios US-4

**Validar manualmente**:
- Criar 3 eventos auditáveis → ver na timeline com texto humanizado
- Confirmar que evento de visualização (`paciente.viewed`) NÃO aparece

### Lote D — Frontend Page + Components (US-1..US-4 visual)

Implementar:

24. `resources/js/composables/usePanelHome.js` (R9 — fetch + estado)
25. `resources/js/composables/usePanelHomeScope.js` (R11 — localStorage)
26. `resources/js/composables/useAutoRefresh.js` (R10 — Page Visibility API)
27. `resources/js/i18n/pt-BR.json` (bloco `panel_home.*`)
28. `resources/js/components/panel-home/KpiCard.vue`
29. `KpiCardsGrid.vue`
30. `UpcomingAppointmentsCard.vue`
31. `AttentionItemsCard.vue`
32. `RecentActivityCard.vue`
33. `ScopeToggle.vue`
34. `RefreshButton.vue`
35. Reescrever `resources/js/pages/PanelHome.vue` consumindo o composable + componentes
36. Validar visual no `/panel`

### Lote E — Auto-refresh + Polish + E2E (US-5 + US-6)

Implementar:

37. Integrar `useAutoRefresh` no `usePanelHome` (intervalo 120s)
38. Skeleton states refinados por seção (4 componentes de skeleton)
39. Empty states por seção (4 mensagens i18n)
40. Banner de erro global + retry
41. `tests/Browser/PanelHomeE2ETest.php` (Playwright)
42. Audit a11y (Lighthouse + axe) na rota `/panel`

### Lote F — Gates & Polish final

43. Constitution Re-Check pós-implementação
44. CLAUDE.md: nova seção "Dashboard Home (Fase 10) — Key Patterns"
45. `vendor/bin/sail bin pint --dirty --format agent`
46. `vendor/bin/sail npm run build` (validar bundle)
47. Suite full PHP: `vendor/bin/sail artisan test --compact tests/Feature/Panel tests/Unit/Panel`
48. DEFERRED.md (se aplicável)

---

## Comandos úteis durante implementação

```bash
# Subir tudo
vendor/bin/sail up -d
vendor/bin/sail npm run dev

# Curl direto no endpoint (após login)
curl -s -H "Authorization: Bearer <token>" \
     -H "X-Tenant-Slug: rb-clinic" \
     -H "Accept: application/json" \
     http://crm.lvh.me/api/v1/panel/home?scope=user | jq

# Tests do módulo Panel apenas
vendor/bin/sail artisan test --compact tests/Feature/Panel tests/Unit/Panel

# Gate test específico
vendor/bin/sail artisan test --filter=PanelHomeCrossTenantTest

# Lint
vendor/bin/sail bin pint --dirty --format agent

# Inspect cache key Redis
vendor/bin/sail exec redis redis-cli KEYS 'panel_home:*'
vendor/bin/sail exec redis redis-cli GET 'panel_home:1:1:user'

# Limpar cache antes de teste manual
vendor/bin/sail artisan cache:clear
```

---

## Critérios de pronto (Definition of Done)

### Por user story

- [ ] **US-1**: 4 cenários acceptance → 4 KPIs corretos + clicks levam para telas certas
- [ ] **US-2**: 4 cenários → lista 5×6h ordenada + empty state + "Ver agenda completa"
- [ ] **US-3**: 6 cenários (incluindo filtro de estágios Q3 e webhook permission) → alertas ordenados por severidade
- [ ] **US-4**: 5 cenários → timeline humanizada, sem PII sensível, links funcionais
- [ ] **US-5**: 6 cenários (incluindo role dupla Q1) → toggle persiste em localStorage
- [ ] **US-6**: 4 cenários → auto-refresh 2min com pause em background, refresh manual com spinner

### Cobertura global

- [ ] 43 FRs do spec endereçados
- [ ] 10 gates G1–G10 do contract verdes
- [ ] 10 SCs do spec validados (incluindo p95 < 500ms em load test informal)

### Constitution Re-Check (post-implementation)

- [ ] I. LGPD: timeline sem CPF/email/telefone/clínico (gate G6 verde)
- [ ] II. Multi-tenant: gate G1 verde + cache key escopada
- [ ] IV. Test-first: 11 testes (6 feature + 4 unit + 1 E2E) + 10 gates do contract
- [ ] V. Observabilidade: 4 métricas Prometheus + Sentry tags

### Suite

- [ ] `vendor/bin/sail artisan test --compact` — 0 regressão na suíte completa
- [ ] `vendor/bin/sail npm run build` — build verde sem warnings
- [ ] Lighthouse a11y na `/panel` — 0 violations sérias/críticas

---

## Rollback strategy

Endpoint novo, frontend novo, sem migrations. Rollback = revert do PR. Cache Redis pode ser limpo manualmente (`redis-cli DEL` ou via `cache:clear`) mas expira sozinho em 30s.

---

## DEFERRED / Out-of-scope (não implementar neste spec)

Per `quickstart.md § Out-of-scope` do spec:

- ❌ Conteúdo do Dashboard Executivo (spec 011)
- ❌ Customização de cards (arrastar/esconder/reordenar)
- ❌ Notificações push em tempo real via Reverb
- ❌ Filtros adicionais por profissional ou período
- ❌ Exportar dashboard como PDF/imagem
- ❌ Comparativos período-a-período
- ❌ Gráficos de tendência ou sparklines
- ❌ Sincronização cross-device da preferência de scope

---

## Próximo comando

```
/speckit-tasks
```

Gera `tasks.md` com tasks atômicas (T###) ordenadas por dependência, mapeadas aos lotes A–F.
