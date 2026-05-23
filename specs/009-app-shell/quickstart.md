# Quickstart — App Shell (009)

**Status**: Complete | **Date**: 2026-05-23

Guia operacional para implementar, validar e fazer rollout da feature App Shell. Para uso por implementer (humano ou agente).

---

## Pré-requisitos

Antes de começar:

- ✅ Branch `009-app-shell` checked out
- ✅ Spec aprovada (`specs/009-app-shell/spec.md` — 23 acceptance scenarios)
- ✅ Plan aprovado (`specs/009-app-shell/plan.md` — Constitution Check 7/7 ✅)
- ✅ Research consolidado (`specs/009-app-shell/research.md` — 13 decisões)
- ✅ Data model documentado (`specs/009-app-shell/data-model.md`)
- ✅ Navigation tree contract (`specs/009-app-shell/contracts/navigation-tree.md`)
- ✅ Sail rodando (`vendor/bin/sail up -d`)
- ✅ Vite em modo dev (`vendor/bin/sail npm run dev`)
- ✅ Pelo menos 1 tenant de teste criado com:
  - 1 usuário admin (todas as abilities) — para validar caminho completo
  - 1 usuário recepcionista (sem `prescription.view`, `report.view`, `webhook.manage`) — para validar permission filtering

---

## Ordem sugerida de implementação (Lotes)

A spec sugere quebrar em 4 lotes alinhados com user stories priorizadas. `/speckit-tasks` vai detalhar tasks individuais por lote.

### Lote A — Foundations + US-1 (Navegação Persistente) — P1

**Objetivo**: chrome visível em todas as rotas, navegação entre módulos funciona, item ativo destacado.

Implementar:

1. `resources/js/composables/useBreakpoint.js` (R2)
2. `resources/js/composables/useFocusTrap.js` (R3 — só placeholder; usado em Lote B)
3. `resources/js/composables/useShellPreferences.js` (R4)
4. `resources/js/composables/useNavigation.js` (R5)
5. `resources/js/config/navigation.js` (R5 — árvore canônica do contract)
6. `resources/js/components/layout/ShellSkeleton.vue` (R7)
7. `resources/js/components/layout/SidebarItem.vue`
8. `resources/js/components/layout/SidebarGroup.vue`
9. `resources/js/components/layout/Sidebar.vue` (desktop only neste lote)
10. `resources/js/components/layout/Topbar.vue` (sem user menu ainda)
11. `resources/js/layouts/AppShell.vue`
12. `resources/js/pages/PanelHome.vue` (substitui PanelPlaceholder)
13. `resources/js/router/index.js` — refactor: `/panel` vira rota pai com children (R1)
14. `lang/pt_BR/layout.php` (R12)

**Validar manualmente**:
- `http://rb-clinic.lvh.me/panel` mostra shell completo
- Navegação entre 3 módulos diferentes via sidebar (Dashboard → Agenda → Pacientes) — chrome persiste
- Item ativo na sidebar fica destacado

### Lote B — US-2 (User Menu + Logout) + US-3 (Permission Filtering) — P1

**Objetivo**: usuário consegue ver quem ele é e sair; itens sem permissão somem.

Implementar:

1. `resources/js/components/layout/UserMenu.vue` (R3 — focus trap + click-outside)
2. Topbar.vue — integrar UserMenu
3. Refinar `useNavigation` para garantir filtro estrito (gates G1–G5 do contract)
4. Auth store `auth.js` — adicionar getter `hasAnyModuleAccess` se ainda não existir (consumido por US-5 do spec)

**Validar manualmente**:
- Topbar exibe nome do tenant à esquerda
- Click no user menu → vê nome + email + opções
- Click "Sair" → revoga token + redirect `/login`
- Login como recepcionista — itens "Receituários" e "Relatórios" não aparecem na sidebar

### Lote C — US-4 (Mobile Drawer + Tablet Compacto + Toggle) — P2

**Objetivo**: app utilizável em mobile e tablet.

Implementar:

1. `resources/js/components/layout/MobileDrawer.vue` (R3 — focus trap + Teleport + Esc/click-outside)
2. AppShell.vue — alternar entre Sidebar (desktop/tablet) e MobileDrawer (mobile) via `useBreakpoint`
3. Topbar.vue — botão hambúrguer em mobile + botão "colapsar/expandir" em desktop/tablet
4. Sidebar.vue — variantes visuais expandida vs compacta + tooltips em compacta
5. Lógica de auto-fechamento do drawer ao clicar item (decisão Q1 — fecha imediatamente)

**Validar manualmente**:
- Viewport 360px (Chrome DevTools mobile) → hambúrguer abre drawer → Esc fecha
- Viewport 800px → sidebar compacta (só ícones) com tooltips
- Click botão colapsar em desktop → sidebar contrai → reload → permanece contraída

### Lote D — US-5 (Estados) + US-6 (Título Contextual) + Polish — P3

**Objetivo**: skeleton em boot, empty state, document.title contextual.

Implementar:

1. AppShell.vue — exibir `ShellSkeleton` enquanto `auth.isBooting`
2. AppShell.vue — empty state se `auth.hasAnyModuleAccess === false`
3. Navigation guard `router.afterEach` em `router/index.js` — atualiza `document.title` (R6)
4. Topbar.vue — exibir título contextual de `route.meta.title`
5. Adicionar `meta.title` nas 24+ rotas existentes em `router/index.js` (incremental — onde estiver faltando)

**Validar manualmente**:
- Cold-boot da SPA com network throttling → ver skeleton
- Logar como usuário sem nenhuma permission → ver mensagem orientadora
- Navegar entre 4 rotas → `document.title` muda

### Lote E — Testes E2E + Pint + ESLint

**Objetivo**: cobertura constitucional (Princípio IV) e qualidade.

Implementar (R11):

1. `tests/Browser/AppShellNavigationTest.php`
2. `tests/Browser/AppShellResponsiveTest.php`
3. `tests/Browser/AppShellPreferencesTest.php`
4. `tests/Browser/AppShellLogoutTest.php`
5. `vendor/bin/sail bin pint --dirty --format agent`
6. Audit axe/Lighthouse na rota `/panel` — 0 critical/serious

---

## Comandos úteis durante implementação

```bash
# Subir tudo
vendor/bin/sail up -d
vendor/bin/sail npm run dev

# Limpar cache de config quando mudar .env (locale, etc.)
vendor/bin/sail artisan config:clear

# Rodar Playwright (E2E) — só os testes do shell
vendor/bin/sail npx playwright test tests/Browser/AppShell*.php --headed

# Lint PHP (sempre antes de commit)
vendor/bin/sail bin pint --dirty --format agent

# Audit a11y manual em página específica
# (no Chrome DevTools: Lighthouse > Accessibility > Run)

# Logar como user específico em testes manuais (super admin Filament):
# http://admin.lvh.me/admin

# Resetar tenant local após testes destrutivos:
vendor/bin/sail artisan migrate:fresh --seed
```

---

## Critérios de pronto (Definition of Done)

Antes de declarar Lote ou feature como "done":

### Por user story

- [ ] **US-1**: 5 acceptance scenarios em US-1 do spec passam manualmente E em E2E
- [ ] **US-2**: 4 acceptance scenarios em US-2 do spec passam manualmente E em E2E
- [ ] **US-3**: 3 acceptance scenarios em US-3 do spec passam manualmente E em E2E
- [ ] **US-4**: 5 acceptance scenarios em US-4 do spec passam manualmente E em E2E
- [ ] **US-5**: 3 acceptance scenarios em US-5 do spec passam manualmente
- [ ] **US-6**: 3 acceptance scenarios em US-6 do spec passam manualmente

### Cobertura global

- [ ] 32 FRs do spec todos endereçados no código
- [ ] 8 edge cases do spec todos cobertos manualmente
- [ ] 7 success criteria do spec atingidos (com medição/validação documentada)
- [ ] 7 gates G1–G7 do contract — todos cobertos por teste E2E em `tests/Browser/AppShell*Test.php`

### Constitution Re-Check (post-implementation)

- [ ] Re-rodar Constitution Check após implementação (`/speckit-analyze` se quiser automatizar)
- [ ] Princípio I: nenhum dado novo persistido no banco + localStorage apenas com UI prefs (sem PII)
- [ ] Princípio II: testes G6 e G7 do contract validam isolamento de preferences entre tenants/users
- [ ] Princípio IV: 4 specs Playwright + Pint passa + ESLint passa
- [ ] Princípio VII: sem novo endpoint de auth; reuso de `/auth/logout` Bearer existente

### Suite full

- [ ] `vendor/bin/sail artisan test --compact` — sem regressão na suíte completa (deve continuar verde)
- [ ] `vendor/bin/sail npm run build` — build de produção sem erros nem warnings novos
- [ ] Smoke manual nas 6 maiores rotas do `/panel` (Agenda, Pacientes, Inbox, Receituários, Campanhas, Relatórios Executivo) — todas renderizam dentro do shell sem regressão visual

---

## Rollback strategy

Como a feature é PR-única (R13), rollback é trivial:

```bash
git revert <commit-hash>
git push
```

Sem migrations a desfazer, sem schema-changes em DB, sem feature flags a desligar. localStorage permanece — usuários que tinham preferências salvas só vão re-aplicar defaults na próxima sessão (sem prejuízo funcional).

---

## DEFERRED / Out-of-scope (não implementar neste spec)

Documentado também no spec (Assumptions), repetido aqui como guia operacional:

- ❌ Funcionalidade real de busca global na topbar — placeholder visual apenas
- ❌ Funcionalidade real de notificações (sino) — placeholder visual apenas
- ❌ Dark mode
- ❌ Conteúdo real do dashboard home (`/panel`) — apenas mensagem mínima; spec 010 trata
- ❌ Conteúdo refinado do Dashboard Executivo — spec 011 trata
- ❌ Sincronização entre múltiplas abas (preferências)
- ❌ Banner de tenant suspenso na topbar
- ❌ Mudanças no shell do Filament super-admin

Se durante a implementação você sentir que algum desses precisa entrar, abra issue ou faça `/speckit-clarify` em vez de incluir silenciosamente — viola escopo.

---

## Próximo comando

```
/speckit-tasks
```

Gera `tasks.md` com tasks atômicas (T###) ordenadas por dependência, prontas para `/speckit-implement` ou execução manual.
