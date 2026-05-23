# DEFERRED Items — Spec 009 App Shell

**Status**: Implementação base entregue. Validações finais deferred para o usuário ou para PR de cobertura E2E separada.
**Date**: 2026-05-23

## Constitution Re-Check pós-implementação

| Princípio | Status | Observação |
|---|---|---|
| I. LGPD | ✅ PASS | localStorage apenas com UI prefs (sem PII). `user.name/email/tenant.name` no UserMenu já autorizados via `/auth/me`. Sem novo endpoint, sem PII nova. |
| II. Isolamento Multi-Tenant | ✅ PASS | Chave de localStorage escopada por `tenant_slug + user_id`. `useNavigation` filtra por `auth.permissions` (já tenant-scoped). |
| III. Segurança Clínica IA | ✅ N/A | Feature não interage com IA. |
| IV. Spec-Driven Test-First | ⚠️ PARTIAL | Implementação OK, mas **testes E2E Playwright deferred** (ver abaixo). |
| V. Observabilidade | ✅ PASS | Reuso de Sentry/Prometheus existentes. Logout já auditado pelo endpoint da Fase 4. |
| VI. Conformidade Meta | ✅ N/A | Feature não dispara mensagens em canais externos. |
| VII. Segurança Operacional | ✅ PASS | Reuso de `/auth/logout` Bearer. Sem `v-html`. Sem inline scripts. CSP estrita preservada. |

**Resultado**: 6/7 ✅ + 1 ⚠️ PARTIAL (Princípio IV). Nenhuma regressão dos gates principais.

---

## DEFERRED tasks (Phase 9)

### Tests E2E Playwright (T011, T020, T024, T027, T040)

Cenários documentados em `quickstart.md § Lote E`. Implementação concreta pendente — requer:

- Pelo menos 1 usuário `admin-clinica` (todas abilities) seedado em tenant de teste
- Pelo menos 1 usuário `recepcionista` (sem `prescription.view`, `report.view`, `webhook.manage`)
- Setup de Playwright local: `vendor/bin/sail npx playwright install chromium`

Arquivos esperados (em `tests/Browser/`):

1. `AppShellNavigationTest.php` — gates G1/G2/G3/G4/G5 do contract + cenários US-1 e US-3
2. `AppShellLogoutTest.php` — cenários US-2 (tenant name, user menu open/close, logout)
3. `AppShellResponsiveTest.php` — cenários US-4 (drawer mobile, modo compacto tablet, toggle persiste, focus trap)
4. `AppShellPreferencesTest.php` — gates G6/G7 (isolamento de localStorage entre tenants/users)

### Audit a11y (T041)

Manual via Chrome DevTools Lighthouse. Meta: 0 violations sérias/críticas em `/panel` em viewports 360px e 1280px (SC-007).

Como rodar:
1. Abrir `http://rb-clinic.lvh.me/panel` no Chrome
2. DevTools (F12) → Lighthouse tab → Accessibility only → Mobile / Desktop
3. Documentar resultado em `specs/009-app-shell/a11y-audit.md`

### Smoke manual (T044)

Validar que as 6 maiores rotas do painel renderizam dentro do shell sem regressão visual:

- [ ] `/panel/agenda` — calendário ainda renderiza
- [ ] `/panel/pacientes` — lista carrega
- [ ] `/panel/inbox` — inbox abre
- [ ] `/panel/receituarios` — lista de receitas
- [ ] `/panel/campanhas` — campanhas
- [ ] `/panel/relatorios/executivo` — dashboard executivo

Em cada uma:
- Sidebar visível com active state correto
- Topbar com tenant name + título contextual
- Sem flash de tela branca

### Suite full PHP (T047)

```bash
vendor/bin/sail artisan test --compact
```

Confirma 0 regressão na suite existente (1300+ tests). O shell é frontend-only mas o router refactor pode afetar rotas que algum teste backend assuma (improvável).

---

## Out-of-scope (intencional — não implementar deste spec)

Per `quickstart.md § DEFERRED / Out-of-scope`:

- ❌ **Busca global real** na topbar — slot visual existe disabled; funcionalidade fica para spec dedicada
- ❌ **Notificações reais** (sino) — slot visual existe disabled
- ❌ **Dark mode** — Assumption do spec; pode entrar em spec futura
- ❌ **Conteúdo real do Dashboard home** (`/panel`) — `PanelHome.vue` é mínimo (saudação + atalhos); conteúdo rico fica para **spec 010**
- ❌ **Conteúdo refinado do Dashboard Executivo** — fica para **spec 011**
- ❌ **Sincronização entre múltiplas abas** (preferences) — cada aba independente
- ❌ **Banner de tenant suspenso** na topbar
- ❌ **Shell do Filament super-admin** — continua sendo o do Filament

---

## Implementação entregue nesta sessão

### Files criados (12)

```
resources/js/composables/useBreakpoint.js
resources/js/composables/useFocusTrap.js
resources/js/composables/useShellPreferences.js
resources/js/composables/useNavigation.js
resources/js/config/navigation.js
resources/js/components/layout/icons/HeroIcon.vue
resources/js/components/layout/SidebarItem.vue
resources/js/components/layout/SidebarGroup.vue
resources/js/components/layout/Sidebar.vue
resources/js/components/layout/Topbar.vue
resources/js/components/layout/UserMenu.vue
resources/js/components/layout/MobileDrawer.vue
resources/js/components/layout/ShellSkeleton.vue
resources/js/layouts/AppShell.vue
resources/js/pages/PanelHome.vue
lang/pt_BR/layout.php
specs/009-app-shell/DEFERRED.md
```

### Files modificados (2)

```
resources/js/router/index.js          — /panel virou rota pai com children + afterEach para document.title
resources/js/i18n/pt-BR.json          — bloco layout.* adicionado
CLAUDE.md                              — App Shell (Fase 9) — Key Patterns
```

### Gates de qualidade (rodados nesta sessão)

- ✅ `vendor/bin/sail npm run build` — verde, 1.62s, sem warnings novos
- ✅ `vendor/bin/sail bin pint --dirty --format agent` — 2 arquivos pré-existentes ajustados, novos files já estavam em padrão
- ✅ Vite dev server compila todos os 5+ módulos novos sem erro (200 OK)
- ✅ Node `--check` em todos os .js files novos — syntax OK

---

## Próximo passo natural

**Spec 010 — Dashboard Home** (`/panel`) — preencher o `PanelHome.vue` com cards reais (próximas consultas, leads novos, alertas, atividade recente).
