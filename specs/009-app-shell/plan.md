# Implementation Plan: App Shell do Painel Autenticado

**Branch**: `009-app-shell` | **Date**: 2026-05-23 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/009-app-shell/spec.md`

## Summary

Entregar um chrome compartilhado (sidebar + topbar) que envolve todas as rotas autenticadas `/panel/*` (exceto `/panel/onboarding`), com navegação filtrada por permissões, layout responsivo (desktop/tablet/mobile), persistência de preferências em `localStorage` por usuário+tenant, e acessibilidade compatível com leitores de tela.

Abordagem técnica: introduzir uma rota Vue Router pai (`/panel`) cuja `component` é o `AppShell.vue` (layout); todas as rotas existentes viram **filhas** dessa rota e renderizam dentro de um `<router-view>` interno do shell. A árvore de navegação é definida em uma estrutura estática JS (`navigation.js`), filtrada em runtime por `auth.permissions`. Preferências de UI vão para `localStorage` com chave escopada `app-shell:{tenant_slug}:{user_id}`. Zero mudança de backend — feature 100% frontend, consumindo a store Pinia `useAuthStore` já existente (Fase 4).

## Technical Context

**Language/Version**: JavaScript (ES2022+), Vue 3.5 (Composition API com `<script setup>`)
**Primary Dependencies**: `vue@^3.5`, `vue-router@^4.5`, `pinia@^2.3`, `vue-i18n@^10.0`, `tailwindcss@^4.0`, `@vueuse/core` (já presente, usado para click-outside/focus-trap)
**Storage**: `localStorage` apenas para preferências de UI (estado dos grupos da sidebar, modo expandido/compacto). Sem dados persistentes em backend.
**Testing**: Playwright E2E (`@playwright/test` já instalado) para jornadas críticas do shell; PHPUnit Feature (já presente) sem testes novos — não há mudança de backend.
**Target Platform**: Navegadores modernos suportados pelo projeto (Chrome/Edge/Firefox/Safari ≥ versões atuais). Mobile responsivo até viewports de 360px de largura.
**Project Type**: Web SPA frontend (camada Vue dentro de monorepo Laravel + SPA).
**Performance Goals**: Render inicial do shell completo (chrome + skeleton da página) em ≤ 300ms após o roteador resolver a rota; sem flash de tela branca perceptível.
**Constraints**: 0 violações sérias ou críticas em auditoria axe/Lighthouse; suporte a teclado completo (Tab, Enter, Esc, setas no menu); sem `confirm()`/`prompt()`/`alert()` nativos.
**Scale/Scope**: Envolve as 24+ rotas existentes em `/panel/*`; árvore de navegação com 10 grupos de módulo (alguns com 2-5 sub-itens); 1 usuário ativo por tab.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Avaliação contra os 7 princípios (v1.4.0):

### I. Privacidade, Consentimento e Conformidade LGPD ✅ PASS

- Shell consome apenas dados já autorizados na sessão atual (`user.name`, `user.email`, `tenant.name`) via `/auth/me` — endpoint já existente e auditado na Fase 4.
- Sem novo fluxo de PII: nenhum input do usuário é coletado pelo shell; localStorage armazena APENAS preferências de UI (boolean/string curtos), sem PII nem token.
- Sem envio ao LLM: feature não interage com IA.
- Logout: usa endpoint `POST /auth/logout` existente, que já revoga apenas o token corrente (Princípio VII, FR-014).

### II. Isolamento Multi-Tenant ✅ PASS

- Shell não consulta dados de tenants — usa estado já populado pelo `ResolveTenant` + `auth.fetchMe`.
- **Gate crítico**: chave de localStorage MUST ser escopada por `tenant_slug + user_id` para evitar vazamento de preferências entre tenants no mesmo navegador (cenário usuário multi-clínica). Validação em teste de integração.
- Itens da sidebar derivam de `auth.permissions` — permissions já são escopadas por tenant pelo backend (Spatie Permissions + tenant_id na pivot). Nenhum bypass possível pelo shell.

### III. Segurança Clínica e Auditabilidade da IA ✅ N/A

- Feature não interage com camada de IA.

### IV. Desenvolvimento Spec-Driven e Test-First ✅ PASS

- Spec aprovada com 23 acceptance scenarios e 4 clarifications resolvidas.
- Testes obrigatórios planejados em Phase 1: E2E Playwright para jornadas críticas (navegação, permissão, logout, drawer mobile, persistência localStorage). Cobertura específica de cenários:
  - `tests/Browser/AppShellNavigationTest.php` — US-1 + US-3 (navegação + permission filtering)
  - `tests/Browser/AppShellResponsiveTest.php` — US-4 (drawer, modo compacto)
  - `tests/Browser/AppShellPreferencesTest.php` — US-1.4-5 (persistência)
  - `tests/Browser/AppShellLogoutTest.php` — US-2
- Pint + ESLint passam no CI normalmente; sem novas migrations.

### V. Observabilidade e Excelência Operacional ✅ PASS

- Erros de runtime (ex.: localStorage indisponível, falha em fetchMe) já são reportados ao Sentry pelo handler global existente.
- Nenhuma nova métrica Prometheus necessária — shell não tem caminho crítico de SLO próprio (a observabilidade é por rota individual, que continua intacta).
- Eventos de logout já são auditados pelo handler de `/auth/logout` (Fase 4).

### VI. Conformidade Meta nos Disparos ✅ N/A

- Feature não envia mensagens em canais externos.

### VII. Segurança Operacional ✅ PASS

- Shell **não introduz nova superfície de autenticação** — reusa `useAuthStore.logout()` (calls `POST /auth/logout` Bearer-authenticated).
- Sem `v-html` ou render de HTML user-provided no chrome — todas as strings são i18n estáticas; nome do tenant e usuário renderizados via interpolação Vue (`{{ }}`) com auto-escape.
- CSP: shell não injeta `<script>` inline; usa apenas Vue templates compilados em build-time (Vite). Conforme com CSP estrita.
- Sem expansão de surface CORS — frontend permanece em `{slug}.crm.com.br`, API em `api.crm.com.br` (env-driven, já configurado).
- localStorage usado **apenas** para preferências de UI não-sensíveis. Nenhuma escrita de token ou dado de paciente.

**Resultado Constitution Check**: 7/7 ✅ — Nenhuma violação. Nenhuma justificativa em Complexity Tracking necessária.

## Project Structure

### Documentation (this feature)

```text
specs/009-app-shell/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (localStorage schema)
├── quickstart.md        # Phase 1 output (rollout guide)
├── contracts/
│   └── navigation-tree.md  # Estrutura estática da nav + permissions exigidas
├── checklists/
│   └── requirements.md  # PASS 12/12 (gerado por /speckit-specify)
└── tasks.md             # Phase 2 output — gerado por /speckit-tasks
```

### Source Code (repository root)

Esta feature toca **apenas** o frontend SPA. Estrutura nova ou modificada:

```text
resources/js/
├── layouts/
│   └── AppShell.vue                   # [NEW] Layout root: sidebar + topbar + <router-view>
├── components/
│   └── layout/
│       ├── Sidebar.vue                # [NEW] Sidebar fixa/compacta (desktop/tablet)
│       ├── SidebarGroup.vue           # [NEW] Grupo expansível com sub-itens
│       ├── SidebarItem.vue            # [NEW] Item de nav (link único, sem sub-itens)
│       ├── Topbar.vue                 # [NEW] Header com tenant, título, user menu
│       ├── UserMenu.vue               # [NEW] Dropdown do usuário (Esc/click-outside)
│       ├── MobileDrawer.vue           # [NEW] Drawer + overlay + focus trap (< md)
│       └── ShellSkeleton.vue          # [NEW] Skeleton de loading durante fetchMe
├── composables/
│   ├── useShellPreferences.js         # [NEW] Read/write localStorage scoped
│   ├── useNavigation.js               # [NEW] Filtra nav tree por permissions
│   ├── useFocusTrap.js                # [NEW] Helper de focus trap para drawer/menu
│   └── useBreakpoint.js               # [NEW] Reactive viewport breakpoint detection
├── config/
│   └── navigation.js                  # [NEW] Definição estática da árvore de nav
├── pages/
│   └── PanelHome.vue                  # [MOD] Substitui PanelPlaceholder do router
├── router/
│   └── index.js                       # [MOD] Reestruturar /panel como rota pai do shell
└── stores/
    └── auth.js                        # [MOD] Adicionar getter `hasAnyModuleAccess` (US-5)

lang/pt_BR/
└── layout.php                         # [NEW] i18n keys do shell

tests/Browser/
├── AppShellNavigationTest.php         # [NEW] Playwright E2E — nav + permission filtering
├── AppShellResponsiveTest.php         # [NEW] Playwright E2E — drawer + modo compacto
├── AppShellPreferencesTest.php        # [NEW] Playwright E2E — persistência localStorage
└── AppShellLogoutTest.php             # [NEW] Playwright E2E — fluxo de logout

resources/views/
└── app.blade.php                      # [potentially MOD] Verificar se algum head meta precisa ajuste
```

**Structure Decision**: Frontend-only feature dentro do monorepo Laravel. Mantém a estrutura existente `resources/js/{layouts,components,composables,config,pages,router,stores}`. Não cria nova "base folder". Não toca `app/`, `database/migrations/`, `routes/api.php`, `routes/web.php` (este último foi tocado em iteração anterior para registrar SPA aliases — não relacionado a este spec).

## Complexity Tracking

> Nenhuma violação constitucional detectada. Esta seção fica vazia.

Não há desvios. Toda a feature opera dentro dos limites: zero novos endpoints, zero novas tabelas, zero novas integrações externas, zero impacto em LGPD/IA/Meta, zero alteração em surface de autenticação.
