# Research — App Shell (009)

**Status**: Complete | **Date**: 2026-05-23

Todas as decisões técnicas necessárias para destravar Phase 1 (data-model, contracts, quickstart) consolidadas aqui. Não há `NEEDS CLARIFICATION` pendentes vindos do plan.

---

## R1 — Estratégia de rotas aninhadas: rota pai com `<router-view>`

**Decision**: Reestruturar o roteador para que `/panel` seja uma rota pai cuja `component` é `AppShell.vue`. Todas as rotas atuais com path `/panel/*` viram **rotas filhas** (children) com paths relativos. A rota `/panel/onboarding` permanece em nível raiz (irmã de `/panel`) para ficar fora do shell, conforme decisão arquitetural.

**Rationale**:
- Padrão idiomático do Vue Router 4 para layouts — shell montado uma única vez, evita re-render do chrome a cada navegação interna.
- Rotas filhas herdam meta do pai (`requiresAuth`), reduzindo duplicação. Mantém-se `meta.ability` por filha para o guard de permissão.
- Preserva 100% das URLs públicas existentes (path absoluto não muda — apenas a estrutura interna do array de routes).
- Permite manter `/panel/onboarding` fora do shell sem hack — é uma rota irmã do `/panel` pai, não filha.

**Alternatives considered**:
- *Wrapper component dentro de cada page*: pediria importar e wrap em cada uma das 24+ pages, fricção enorme e propenso a esquecimentos.
- *Slot dinâmico no `App.vue` raiz baseado em `route.matched`*: funciona mas perde clareza estrutural; mistura concerns de layout com app root.
- *Layout via `meta.layout` resolvido em `App.vue`*: introduz indireção; cada rota teria que declarar layout. Útil para apps multi-layout — overkill para um shell único.

---

## R2 — Detecção reativa de breakpoint: `useBreakpoint` baseado em `@vueuse/core`

**Decision**: Criar `useBreakpoint()` composable que retorna refs reativas: `isMobile` (< 768px), `isTablet` (768–1023px), `isDesktop` (≥ 1024px). Implementação via `useMediaQuery` do `@vueuse/core` (já dependência do projeto).

**Rationale**:
- `@vueuse/core` já está instalado (v12+), `useMediaQuery` é estável e leve.
- Reatividade nativa: ao redimensionar a janela ou rotacionar dispositivo, o componente atualiza automaticamente — atende ao FR "ao cruzar o breakpoint entre mobile e desktop por redimensionamento, o estado do drawer MUST ser resetado" (FR-022).
- Breakpoints em **pixels CSS** alinhados com Tailwind defaults (`md: 768px`, `lg: 1024px`).

**Alternatives considered**:
- *Listener manual em `window.resize`*: requer debounce, cleanup em `onUnmounted`, e perde precisão em modo iPad (Safari trata orientação separadamente). VueUse abstrai isso.
- *Detecção via `window.matchMedia` direto*: funciona mas mais verboso e não-reativo sem wrapper.

---

## R3 — Focus trap e click-outside para drawer e user menu

**Decision**: Usar `useFocusTrap()` e `onClickOutside()` do `@vueuse/core`. Drawer mobile e UserMenu encapsulam-se em `<Teleport to="body">` para garantir z-index e que o focus trap funcione fora da hierarquia do shell.

**Rationale**:
- VueUse oferece implementações testadas conformes ao WAI-ARIA APG (Authoring Practices Guide). Atende FR-021 (drawer focus trap), FR-026 (modal acessível), FR-027 (keyboard nav no user menu).
- `Teleport` evita issues de overflow/transform no parent que quebrariam o overlay.

**Alternatives considered**:
- *Bibliotecas dedicadas (`focus-trap`, `@headlessui/vue`)*: Headless UI traria peso de mais componentes que não usamos; `focus-trap` puro precisaria adaptação para Vue. VueUse cobre o caso 1-1.
- *Implementação manual de focus trap*: alto risco de bugs sutis (Tab/Shift+Tab edge cases), violaria padrão de "não reinventar" do projeto.

---

## R4 — Esquema de `localStorage` por usuário+tenant

**Decision**: Chave única `app-shell:preferences:v1` no `localStorage`. Valor é JSON `{ [tenant_slug]: { [user_id]: { sidebarMode: 'expanded'|'compact', expandedGroups: string[] } } }`. Versão `v1` no nome da chave permite migração futura sem perder defaults.

**Rationale**:
- Uma chave única evita poluição de localStorage com N chaves por sessão.
- Estrutura aninhada por `tenant_slug → user_id` garante isolamento multi-tenant (Princípio II): trocar de tenant ou de usuário no mesmo navegador NUNCA carrega preferências do outro contexto.
- Versionamento `v1` no nome blinda contra migração de schema: ao incrementar para `v2`, o `v1` pode ser deletado em janela de cleanup ou simplesmente ignorado.
- Fallback robusto: se `localStorage` indisponível (modo privado/cota), `useShellPreferences` retorna defaults sem lançar exceção (cumpre Assumption do spec).

**Alternatives considered**:
- *IndexedDB*: overkill para ~200 bytes de UI state.
- *Cookie*: enviaria desnecessariamente a cada request; cota mais baixa; não é o caso de uso.
- *Pinia plugin de persistência*: traria dependência nova (`pinia-plugin-persistedstate`). Composable custom de ~30 linhas resolve sem dep nova.

---

## R5 — Definição da árvore de navegação: arquivo estático + filtro em runtime

**Decision**: Árvore de navegação declarada em `resources/js/config/navigation.js` como uma constante exportada. Cada item ou grupo tem: `key`, `labelKey` (i18n), `icon` (componente Heroicons), `routeName` (para items singulares) ou `children` (para grupos), `ability` (string da permission requerida) ou `anyOf` (array para "OR" de permissions).

**Rationale**:
- Estrutura estática é trivial de versionar, reviewar em PR e testar.
- Filtro de permission acontece em runtime via composable `useNavigation()` que cruza com `auth.permissions` — atende FR-005 (itens sem ability somem) e FR-006 (grupo inteiro some se 0 sub-itens visíveis).
- Acopla rotas a items via `routeName` (não path), evitando hard-coding de URLs e permitindo refactors do router sem quebrar a sidebar.
- Trivial de adicionar novos módulos no futuro — uma entrada no array.

**Alternatives considered**:
- *Derivar nav tree do array de routes do router*: tentador mas perderia controle fino sobre ordem visual, agrupamento e separação de "Configurações" vs "Pacientes". O router já é a fonte de truth de URLs; a nav é uma camada de apresentação.
- *Buscar nav tree do backend*: introduziria endpoint novo + dependência de rede no boot. Sem ganho — a estrutura é estática por design.

---

## R6 — Atualização do `document.title` e título contextual da topbar

**Decision**: Usar um navigation guard `router.afterEach` que lê `to.meta.title` (string ou função `(route) => string`) e atualiza `document.title = "{nomeDaClinica} — {títuloDaRota}"`. Topbar lê o mesmo valor via `route.meta.title` reativo. Defaults: rotas sem `meta.title` caem no rótulo do item de navegação que aponta para elas; falha total → `"Paciente360"`.

**Rationale**:
- `meta.title` é convenção idiomática do Vue Router.
- Reatividade do `route` no Composition API já garante que o componente Topbar atualize sem watcher manual.
- Concentrar atualização do `document.title` em UM lugar (afterEach) evita duplicação em cada page.

**Alternatives considered**:
- *Computar título dentro do `Topbar.vue` com lookup na nav tree*: funciona mas separa título de browser do título da topbar — duas fontes de truth.
- *Componente `<TitleHead>`*: padrão React, anti-pattern em Vue onde a single source é `route.meta`.

---

## R7 — Skeleton durante boot (`auth.fetchMe()` em curso)

**Decision**: `AppShell.vue` exibe `ShellSkeleton.vue` enquanto `auth.isBooting === true`. A store já tem um flag `booting` que vira `false` após o primeiro `fetchMe` resolver (sucesso ou falha). O skeleton replica a estrutura visual (sidebar largura + topbar altura + content placeholder cinza) com Tailwind `animate-pulse`.

**Rationale**:
- Aproveita estado já existente em `auth.js` (verificado durante research).
- `animate-pulse` é built-in do Tailwind v4 — zero peso adicional.
- Previne flash de tela branca (FR-023, SC-004 — render em 300ms sem flash).

**Alternatives considered**:
- *Bloquear render do shell até `auth.user` chegar*: viola UX — usuário vê tela branca.
- *Spinner central simples*: aceitável mas o "shape" do skeleton dá pista visual antecipada da estrutura, mais polido.

---

## R8 — Logout: reuso de `useAuthStore.logout()` existente

**Decision**: User menu invoca `auth.logout()` (método já implementado na Fase 4 que faz `POST /auth/logout` com Bearer atual, limpa estado local, e remove token do localStorage). Após sucesso, o `router.push({ name: 'auth.login' })` é responsabilidade do menu (não do store — store fica desacoplada de routing). Em falha de rede, exibe toast de erro mas ainda limpa estado local (o token Bearer pode estar inválido de qualquer forma) e redireciona — fail-safe.

**Rationale**:
- Não há novo endpoint nem novo controller — Bearer auth + scope de logout token atual já cobertos pela Fase 4 (Princípio VII).
- Lógica de fallback "limpar estado local mesmo em erro" segue padrão da Fase 4 (`logout()` já é tolerante).

**Alternatives considered**:
- *Confirmar antes com modal*: spec não pediu; pode ser polish futuro. UX padrão SaaS é "click → logout imediato".

---

## R9 — Página `/panel` (raiz): placeholder mínimo até spec 010

**Decision**: Substituir `PanelPlaceholder` (atual `h(...)` em router/index.js) por uma SFC `resources/js/pages/PanelHome.vue` simples com mensagem orientadora ("Bem-vindo de volta, {nome}. Use o menu lateral para começar.") e um link para o módulo mais usado pelo perfil (atalho simples). Conteúdo real do dashboard fica para o spec 010.

**Rationale**:
- Hoje `/panel` é só `<h1>em construção</h1>` — substituir por algo levemente útil melhora a primeira impressão sem invadir o escopo do spec 010.
- SFC permite estender depois sem reescrever da raiz.

**Alternatives considered**:
- *Redirect automático para o primeiro módulo permitido*: surpreendente para o usuário, viola previsibilidade da URL.
- *Manter placeholder e ignorar*: cria má impressão durante o gap entre specs.

---

## R10 — Ícones: Heroicons via SVG inline

**Decision**: Usar Heroicons (24x24 outline) inline como SVG dentro dos componentes. Heroicons não está nas deps do projeto, mas o spec não impõe escolha — vou inline-ar os ~12 ícones necessários (Home, Calendar, Users, Inbox, ClipboardDocumentList, Megaphone, ChartBar, Cog, ShieldCheck, ArrowRightOnRectangle, Bars3, XMark) diretamente nos componentes ou em `components/layout/icons/`. Isto evita dep nova e mantém o bundle pequeno.

**Rationale**:
- 12 SVGs inline (~300 bytes cada) somam ~4KB — desprezível.
- Sem dep nova significa menos surface de update/security.
- SVG inline aceita `class` Tailwind para tamanho/cor sem CSS extra.

**Alternatives considered**:
- *Instalar `@heroicons/vue`*: traria árvore de tree-shaking + import por nome, mas adiciona dep para um caso simples.
- *Iconify-vue ou Lucide*: bibliotecas excelentes mas mesma fricção de dep nova sem ganho material.

---

## R11 — Estratégia de testes E2E com Playwright

**Decision**: 4 specs Playwright cobrindo as user stories P1+P2:

1. `AppShellNavigationTest.php` — login → ver sidebar → clicar 3 módulos diferentes → validar URL + active state + chrome persistente.
2. `AppShellResponsiveTest.php` — viewport 360px → hambúrguer abre drawer → Esc fecha → viewport 1280px → sidebar fixa visível → toggle collapse → persiste após reload.
3. `AppShellPreferencesTest.php` — login user A em tenant X → expandir grupos → logout → login user B mesmo tenant → grupos do A não vazam.
4. `AppShellLogoutTest.php` — abrir user menu → clicar Sair → redireciona `/login` → tentar acessar `/panel` → guard redireciona pra `/login`.

US-3 (permission filtering) testado via factory de roles diferentes em `AppShellNavigationTest`.

US-5 e US-6 (loading + título) cobertos por unit assertions dentro dos testes acima (não vale spec dedicada).

**Rationale**:
- Estrutura espelha as user stories priorizadas (P1 → cobertura completa; P2 → cobertura completa; P3 → cobertura derivativa).
- Playwright já está configurado no projeto (`@playwright/test ^1.49`).
- Padrão "1 spec por user story" facilita revisão e debug.

**Alternatives considered**:
- *Cypress*: não está no projeto.
- *Vitest + JSDOM*: não pegaria interação real de viewport/responsividade — limitação fundamental para US-4.

---

## R12 — Internacionalização: arquivo `lang/pt_BR/layout.php`

**Decision**: Criar `lang/pt_BR/layout.php` com chaves para todos os rótulos novos do shell:

- `layout.sidebar.dashboard`, `.agenda.title`, `.agenda.calendar`, `.agenda.waitlist`, ... (todos os items + sub-items)
- `layout.topbar.search_placeholder`, `.notifications`, `.user_menu.profile`, `.user_menu.sessions`, `.user_menu.logout`
- `layout.drawer.aria_label`, `.toggle_collapse.aria_label_collapse`, `.toggle_collapse.aria_label_expand`
- `layout.empty_state.no_modules_title`, `.no_modules_message`

Sem fallback `en` para este spec (locale do projeto é `pt_BR` por padrão, e o caso de usuário em outro idioma é raro no MVP).

**Rationale**:
- Centraliza strings traduzíveis em ÚNICO arquivo, padrão Laravel.
- Vue I18n já está configurado e carrega esses arquivos via `lang/pt_BR/*.php` (verificado).

---

## R13 — Backward compatibility durante rollout

**Decision**: Para evitar regressão visual em produção durante deploy, o shell é introduzido em uma única PR que (a) cria todos os componentes, (b) reestrutura o router em uma operação atômica, (c) cobre com testes E2E todas as rotas existentes em uma matriz de smoke. Não há feature flag — o shell ou está totalmente ativo ou não está mergeado. PR-única reduz risco de estados parciais.

**Rationale**:
- O shell é uma reorganização de layout — não há produto "metade no shell, metade fora" que faça sentido.
- Feature flag adicionaria complexidade temporária sem ganho operacional concreto.
- Rollback é fácil via revert do PR; localStorage não tem dado crítico.

**Alternatives considered**:
- *Feature flag `panel.shell_v2`*: padrão usado em outras features (rollouts canário), mas aqui o público é interno (usuários do tenant) e a feature é UX puro — pouca chance de bug que afete produção sem ser pego em E2E.

---

## Resumo das decisões

| ID | Decisão | Impacto |
|---|---|---|
| R1 | Rota pai `/panel` com children + `/panel/onboarding` irmã | Estrutural — router refactor |
| R2 | `useBreakpoint` com VueUse `useMediaQuery` | Composable simples |
| R3 | `useFocusTrap` + `onClickOutside` do VueUse + `<Teleport>` | A11y de modal/drawer |
| R4 | localStorage `app-shell:preferences:v1` escopado por tenant+user | Isolamento multi-tenant em UI |
| R5 | Nav tree estática em `config/navigation.js`, filtrada em runtime | Permission filtering |
| R6 | `meta.title` + `router.afterEach` | document.title + topbar title |
| R7 | `ShellSkeleton.vue` enquanto `auth.isBooting` | Sem flash de tela branca |
| R8 | Reuso de `auth.logout()` da Fase 4 | Zero novo backend |
| R9 | `PanelHome.vue` mínimo, dashboard real fica para spec 010 | Out-of-scope claro |
| R10 | Heroicons SVG inline (sem dep nova) | Bundle leve |
| R11 | 4 specs Playwright (uma por user story P1+P2) | Cobertura E2E completa |
| R12 | `lang/pt_BR/layout.php` único | i18n centralizado |
| R13 | PR única, sem feature flag | Rollout simples |

Todas as decisões honram o Constitution Check (zero violações detectadas em todas as 7 etapas).
