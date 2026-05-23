# Feature Specification: App Shell do Painel Autenticado

**Feature Branch**: `009-app-shell`
**Created**: 2026-05-23
**Status**: Draft
**Input**: User description: "App Shell do painel autenticado (/panel) — chrome compartilhado entre todas as rotas autenticadas do tenant. Sidebar lateral com navegação por módulo (filtrada por permissões), topbar com identidade do tenant + user menu, layout responsivo desktop/tablet/mobile, acessível e visualmente consistente com o mockup de referência."

## Clarifications

### Session 2026-05-23

- Q: Quando o usuário toca em um item do drawer mobile, o drawer fecha imediatamente após o clique ou só depois que a nova rota terminou de carregar? → A: Fecha imediatamente ao clicar no item; navegação acontece em paralelo. Padrão de apps mobile modernos (Gmail, Asana, Linear) — feedback instantâneo, transição fluida, sem risco de drawer "preso" se a rota demorar.
- Q: Grupos da sidebar com sub-itens — múltiplos podem ficar abertos simultaneamente ou comporta-se como acordeão exclusivo? → A: Múltiplos grupos podem ficar abertos simultaneamente, cada um expande/colapsa de forma independente. Padrão de ferramentas profissionais (VS Code, GitLab, Jira) — respeita controle do usuário e evita perda de contexto visual ao trocar de grupo.
- Q: O estado expandido/colapsado dos grupos da sidebar (e o modo expandido/compacto da sidebar em desktop/tablet) é persistido entre sessões ou reseta a cada login? → A: Persiste em `localStorage` por usuário+tenant. Padrão de IDEs e SaaS de produtividade — usuário não precisa reorganizar a UI a cada login. Em caso de localStorage indisponível ou conflito de schema, aplica defaults (grupo da rota corrente aberto, sidebar expandida).
- Q: Em tablet (768–1023px), a sidebar fica compacta de forma fixa ou o usuário pode alternar manualmente para expandida? → A: Alternável pelo usuário. Tablet/desktop expõem um botão "colapsar/expandir sidebar" que troca entre modo compacto (só ícones) e modo expandido (ícones + rótulos). O default em tablet é compacto; o default em desktop (≥1024px) é expandido. A escolha é persistida em `localStorage` (ver Q3).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Navegação Persistente entre Módulos (Priority: P1)

Qualquer usuário autenticado, ao acessar uma rota dentro do painel, vê uma sidebar fixa à esquerda listando os módulos disponíveis e uma topbar superior com sua identidade. Ao clicar em qualquer item da sidebar, é levado para o módulo correspondente sem perder o chrome (sidebar/topbar permanecem visíveis). O item correspondente à rota atual é destacado visualmente.

**Why this priority**: Sem chrome de navegação, cada página `/panel/*` existente é uma ilha — o usuário tem que digitar URLs ou usar o histórico do browser para trocar de contexto. Esta é a fundação de UX que destrava o uso diário do produto e habilita todas as outras features que já estão implementadas (Agenda, Pacientes, Inbox, etc.).

**Independent Test**: Logar como qualquer usuário ativo de qualquer tenant; verificar que a sidebar aparece com os módulos para os quais o usuário tem permissão; navegar entre 3 módulos clicando nos itens; em cada navegação, validar que o item da rota corrente fica visualmente destacado e que sidebar/topbar permanecem na tela.

**Acceptance Scenarios**:

1. **Given** usuário autenticado em `/panel/pacientes`, **When** clica no item "Agenda" da sidebar, **Then** é redirecionado para `/panel/agenda`, o conteúdo da página muda, mas sidebar e topbar continuam visíveis sem recarregar.
2. **Given** usuário em `/panel/agenda`, **When** observa a sidebar, **Then** o item "Agenda" está visualmente destacado (cor de fundo, indicador lateral ou similar) e marcado com `aria-current="page"` para tecnologias assistivas.
3. **Given** usuário na rota `/panel/agenda/lista-espera`, **When** observa a sidebar com grupos expansíveis, **Then** o grupo "Agenda" aparece expandido e o subitem "Lista de espera" está destacado.
4. **Given** usuário expande manualmente os grupos "Agenda" e "Pacientes", **When** faz logout e login posteriormente, **Then** ambos os grupos retornam expandidos sem precisar de nova ação.
5. **Given** usuário com grupo "Agenda" expandido, **When** clica para expandir o grupo "Pacientes", **Then** ambos permanecem expandidos simultaneamente (não-acordeão).

---

### User Story 2 — Identidade, Tenant e Logout via Topbar (Priority: P1)

O usuário enxerga, na topbar, o nome da clínica em que está logado e tem acesso a um menu de usuário (acionado por clique/atalho) com seu nome e email, atalhos para configurações pessoais e a ação "Sair". Ao escolher "Sair", a sessão é encerrada e ele é levado de volta à tela de login.

**Why this priority**: Sem identidade visível, o usuário não confirma em qual tenant está (risco em quem opera múltiplas clínicas) e não tem caminho claro para logout — força a depender de URL `/logout` ou refresh manual. É P1 porque é cobertura mínima de auth UX que toda aplicação SaaS exige.

**Independent Test**: Logar em um tenant, conferir que o nome do tenant aparece na topbar, abrir o user menu, conferir que nome + email batem com `/api/v1/auth/me`, clicar em "Sair", validar que o session token é revogado e a navegação cai em `/login`.

**Acceptance Scenarios**:

1. **Given** usuário autenticado, **When** a página `/panel/*` termina de carregar, **Then** a topbar exibe o nome do tenant corrente em posição fixa à esquerda.
2. **Given** topbar visível, **When** o usuário clica no avatar/nome de usuário no canto direito, **Then** abre um menu suspenso com nome, email, atalho "Sessões", separador e ação "Sair".
3. **Given** menu de usuário aberto, **When** o usuário clica em "Sair", **Then** o token é revogado no backend, o estado local de auth é limpo, e o usuário é redirecionado para `/login`.
4. **Given** menu de usuário aberto, **When** o usuário pressiona Esc ou clica fora do menu, **Then** o menu fecha sem disparar ação.

---

### User Story 3 — Navegação Restrita por Permissão (Priority: P1)

Cada item da sidebar é exibido somente se o usuário tem a permissão (ability) requerida pela rota correspondente. Itens fora do alcance de permissão **somem completamente** — não aparecem como botões desabilitados ou cinzas. Sub-itens dentro de um grupo seguem a mesma regra; se nenhum sub-item está visível, o grupo inteiro também some.

**Why this priority**: Mostrar itens cinzas que o usuário não pode acessar polui a UI, gera frustração ("por que não posso clicar?") e cria um vetor de descoberta de features não-licenciadas. P1 porque é regra explícita do projeto (CLAUDE.md / Princípio II — isolamento de visibilidade entre perfis) e impacta diretamente a percepção de "produto sob medida".

**Independent Test**: Criar dois usuários no mesmo tenant — um com role `admin-clinica` (todas as abilities) e outro com role `recepcionista` (sem `prescription.view`, `report.view`, `webhook.manage`). Logar com cada um; confirmar que o `recepcionista` não vê os itens "Receituários", "Relatórios" e "Integrações" na sidebar, enquanto o `admin-clinica` vê todos.

**Acceptance Scenarios**:

1. **Given** usuário sem a permissão `prescription.view`, **When** o painel renderiza, **Then** o item "Receituários" não aparece em lugar algum da sidebar.
2. **Given** usuário com permissão para 0 itens em um grupo (ex.: nenhum sub-item de "Integrações" autorizado), **When** o painel renderiza, **Then** o grupo inteiro "Integrações" não aparece na sidebar.
3. **Given** usuário sem nenhuma permissão de módulo do painel, **When** ele acessa `/panel`, **Then** vê uma mensagem clara informando que sua conta não tem acesso a nenhum módulo e a quem recorrer (admin da clínica).

---

### User Story 4 — Layout Responsivo Mobile e Tablet (Priority: P2)

Em telas estreitas (mobile), a sidebar não ocupa espaço fixo — ela vira um drawer (gaveta) acessível por um botão hambúrguer na topbar. O drawer abre/fecha sob demanda, escurece o fundo, fecha ao clicar fora ou pressionar Esc, e mantém foco do teclado preso enquanto está aberto. Em tablet, a sidebar fica visível mas em modo compacto (somente ícones), com rótulos aparecendo em tooltip ao passar o mouse.

**Why this priority**: Sem responsividade, o painel fica inutilizável em celular/tablet — um caso real para profissionais que consultam a agenda em movimento ou administradores que acessam fora do consultório. P2 porque o uso primário é desktop; mobile é importante mas pode vir depois do MVP de chrome desktop estar de pé.

**Independent Test**: Abrir o painel em viewport < 768px; confirmar que sidebar não está visível e o botão hambúrguer aparece na topbar; clicar no hambúrguer, ver o drawer abrir cobrindo a esquerda; clicar fora do drawer ou pressionar Esc, ver o drawer fechar; repetir em viewport 768–1023px e confirmar modo compacto da sidebar com ícones apenas.

**Acceptance Scenarios**:

1. **Given** viewport < 768px de largura, **When** o painel renderiza, **Then** a sidebar não ocupa espaço lateral e um botão hambúrguer aparece na topbar.
2. **Given** drawer fechado em mobile, **When** o usuário toca no hambúrguer, **Then** o drawer abre da esquerda com a navegação completa, o fundo escurece e o foco do teclado é movido para o primeiro item navegável.
3. **Given** drawer aberto em mobile, **When** o usuário pressiona Esc ou toca no overlay escuro, **Then** o drawer fecha e o foco volta ao botão hambúrguer.
4. **Given** viewport entre 768px e 1023px, **When** o painel renderiza pela primeira vez para esse usuário (sem preferência salva), **Then** a sidebar aparece em formato compacto (apenas ícones) e cada item exibe rótulo em tooltip ao foco/hover.
5. **Given** viewport ≥ 768px com sidebar em modo expandido, **When** o usuário clica no botão "colapsar sidebar" visível na topbar ou no rodapé da sidebar, **Then** a sidebar contrai para o modo compacto e a preferência é persistida (sessões futuras abrem compacto até nova alteração).

---

### User Story 5 — Estados de Carregamento e Vazio (Priority: P3)

Enquanto a SPA está revalidando a sessão no boot (consultando `/auth/me` para popular usuário e tenant), o painel exibe um esqueleto visual da estrutura (skeleton) em vez de uma tela em branco. Se o usuário autenticado não tiver nenhuma permissão de módulo do painel, em vez de um shell vazio sem itens, ele vê uma mensagem orientadora.

**Why this priority**: Polish de percepção — sem skeleton, há "piscamento" visual que parece bug; sem empty state, um usuário sem permissões cai em tela vazia e confusa. P3 porque o caso ocorre em janela curta (boot) ou cenário raro (usuário sem permissões), mas vale para qualidade percebida.

**Independent Test**: Simular cold-start da SPA com network throttling — ver skeleton durante o `fetchMe`; logar com usuário sem nenhuma permission de módulo (caso raro mas válido em testes) — confirmar mensagem orientadora em vez de shell pelado.

**Acceptance Scenarios**:

1. **Given** o usuário acabou de carregar a SPA com sessão válida e dados ainda não chegaram, **When** o `auth.fetchMe()` ainda não retornou, **Then** o painel exibe placeholders de skeleton na posição da sidebar, topbar e área de conteúdo.
2. **Given** `auth.fetchMe()` completa com sucesso, **When** o estado é populado, **Then** o skeleton é substituído pelo chrome real sem flash de tela branca.
3. **Given** usuário com 0 permissões de módulo, **When** o painel renderiza, **Then** uma mensagem central diz "Sua conta não tem acesso a nenhum módulo. Contate o administrador da clínica." com opção visível de "Sair".

---

### User Story 6 — Título Contextual da Página na Topbar (Priority: P3)

A topbar exibe um título secundário com o nome do módulo/tela atual (ex.: "Pacientes › Funil Kanban"). O título é derivado da rota corrente e atualiza automaticamente em qualquer navegação.

**Why this priority**: Pequeno apoio de orientação contextual, sobretudo em mobile onde a sidebar não fica visível. P3 porque a sidebar já oferece esse sinal em desktop.

**Independent Test**: Navegar entre 4 rotas distintas (`/panel`, `/panel/agenda`, `/panel/pacientes/novo`, `/panel/relatorios/executivo`); validar que em cada uma o título da topbar reflete o nome da tela.

**Acceptance Scenarios**:

1. **Given** usuário em `/panel/agenda`, **When** observa a topbar, **Then** vê o texto "Agenda" como título contextual.
2. **Given** usuário navega para `/panel/relatorios/executivo`, **When** a página termina de carregar, **Then** o título da topbar muda para "Dashboard Executivo" sem precisar de refresh manual.
3. **Given** o título do `document.title` (aba do navegador), **When** o usuário troca de rota, **Then** o `document.title` também é atualizado para incluir o nome da tela.

---

### Edge Cases

- **Nome do tenant muito longo na topbar**: trunca com ellipsis (`…`) e exibe o nome completo em `title` ao hover.
- **Sidebar muito alta (muitos sub-itens)**: scroll vertical interno da sidebar; topbar e área de conteúdo nunca são empurrados.
- **Sessão expira durante uso**: ao receber 401 em qualquer chamada, a SPA já redireciona para `/login`; o shell desaparece naturalmente.
- **Usuário em `/panel/onboarding`**: o shell **não** é renderizado; onboarding é tela cheia para não distrair (sidebar não faz sentido enquanto wizard está em curso).
- **Tenant suspenso ou em estado restrito**: shell renderiza normalmente; banners de status do tenant ficam fora do escopo deste spec (responsabilidade futura).
- **Usuário tenta navegar para uma rota que não tem permissão (digitando URL direto)**: o guard de auth/permissão da rota é quem barra — o shell apenas reflete o resultado da navegação (não cria nova autorização).
- **Drawer mobile aberto quando rotação de tela leva para desktop**: ao cruzar o breakpoint, drawer fecha automaticamente e sidebar fixa aparece.
- **Múltiplas abas do mesmo usuário**: cada aba tem seu próprio estado de sidebar (drawer aberto/fechado) — não há sincronização entre abas neste spec.

## Requirements *(mandatory)*

### Functional Requirements

**Estrutura do shell**

- **FR-001**: O sistema MUST renderizar um chrome compartilhado (sidebar + topbar) em todas as rotas autenticadas do painel `/panel/*`, **exceto** `/panel/onboarding`, que permanece em tela cheia sem chrome.
- **FR-002**: O sistema MUST projetar a página corrente dentro de uma área de conteúdo única (slot/`<router-view>`) sem desmontar/remontar sidebar e topbar entre navegações.
- **FR-003**: O sistema MUST preservar o estado visual do chrome (item ativo destacado, posição de scroll da sidebar, drawer aberto/fechado em mobile) durante transições entre rotas dentro do painel.

**Navegação por sidebar**

- **FR-004**: A sidebar MUST listar grupos navegáveis correspondentes aos módulos: Dashboard, Agenda (com sub-itens), Pacientes (com sub-itens), Inbox (com sub-itens), Receituários, Campanhas, Relatórios (com sub-itens), Integrações (com sub-itens), Privacidade & LGPD (com sub-itens), Configurações (com sub-itens).
- **FR-005**: A sidebar MUST exibir um item OU grupo apenas se o usuário tiver a permissão (ability) requerida pela rota correspondente — itens inacessíveis somem em vez de aparecer desabilitados.
- **FR-006**: Quando um grupo possui sub-itens e nenhum sub-item está visível para o usuário, o grupo inteiro MUST ser ocultado da sidebar.
- **FR-007**: A sidebar MUST destacar visualmente o item correspondente à rota corrente, incluindo o atributo `aria-current="page"` no elemento ativo.
- **FR-008**: Grupos com sub-itens MUST ser expansíveis e abrir automaticamente quando a rota corrente está dentro do grupo. Múltiplos grupos PODEM permanecer abertos simultaneamente — expandir um grupo NÃO fecha os demais (não-acordeão).
- **FR-008a**: O estado expandido/colapsado de cada grupo MUST ser persistido entre sessões por usuário+tenant. Em caso de armazenamento local indisponível ou schema incompatível, o sistema aplica defaults seguros (grupo da rota corrente aberto, demais fechados).

**Topbar**

- **FR-009**: A topbar MUST exibir o nome do tenant corrente em posição fixa à esquerda, recuperado do estado de autenticação já carregado.
- **FR-010**: A topbar MUST exibir um título contextual derivado da rota corrente (campo `meta.title` da rota), atualizado automaticamente a cada navegação.
- **FR-011**: A topbar MUST atualizar o `document.title` (aba do navegador) com o nome da clínica e o nome da tela corrente.
- **FR-012**: A topbar MUST exibir um menu de usuário (dropdown) acionável por clique no nome/avatar do usuário no canto direito.
- **FR-013**: O menu de usuário MUST exibir nome, email, atalho para "Sessões/tokens", separador visual e ação "Sair".
- **FR-014**: A ação "Sair" MUST revogar a sessão no backend, limpar o estado local de autenticação e redirecionar para `/login`.
- **FR-015**: O menu de usuário MUST fechar ao clicar fora do menu, ao pressionar Esc, ou ao escolher uma das opções.
- **FR-016**: A topbar MUST exibir slots visuais reservados para busca global e sino de notificações, marcados como "em breve" — funcionalidades reais ficam fora deste spec.

**Responsividade**

- **FR-017**: Em viewport ≥ 1024px, a sidebar MUST estar fixa à esquerda com largura aproximada de 240px (modo expandido — ícones + rótulos) e topbar fixa no topo. Default neste breakpoint é expandido.
- **FR-018**: Em viewport entre 768px e 1023px, a sidebar MUST aparecer em modo compacto por default (apenas ícones, largura aproximada de 64px), com rótulos visíveis em tooltip ao foco do teclado ou hover do mouse.
- **FR-018a**: Em viewport ≥ 768px (tablet e desktop), o sistema MUST oferecer um controle visível (botão "colapsar/expandir sidebar") que alterna o modo da sidebar entre compacto e expandido. A escolha do usuário MUST ser persistida por usuário+tenant e sobrepõe-se ao default do breakpoint até nova alteração.
- **FR-019**: Em viewport < 768px, a sidebar MUST se transformar em drawer acessível por botão hambúrguer na topbar; a área principal de conteúdo ocupa toda a largura.
- **FR-020**: O drawer mobile MUST fechar ao toque/clique fora, ao pressionar Esc e ao escolher um item de navegação. O fechamento ao escolher um item MUST ser imediato (não aguardar a nova rota carregar) — a navegação ocorre em paralelo ao fechamento.
- **FR-021**: Quando o drawer mobile estiver aberto, o foco do teclado MUST permanecer preso aos elementos internos do drawer (focus trap).
- **FR-022**: Ao cruzar o breakpoint entre mobile e desktop por redimensionamento ou rotação, o estado do drawer MUST ser resetado para o modo correspondente (sidebar fixa em desktop, drawer fechado em mobile).

**Estados visuais**

- **FR-023**: Enquanto a sessão está sendo revalidada no boot (busca de identidade e tenant ainda em curso), o sistema MUST exibir um skeleton da estrutura do shell em vez de tela em branco.
- **FR-024**: Quando o usuário autenticado não possui nenhuma permission de nenhum módulo do painel, o sistema MUST exibir uma mensagem central orientando-o a contatar o administrador da clínica, com a ação "Sair" sempre acessível.

**Acessibilidade**

- **FR-025**: A sidebar MUST ter `role="navigation"` e `aria-label` descritivo (ex.: "Navegação principal").
- **FR-026**: O drawer mobile, quando aberto, MUST atender ao padrão de modal acessível: `role="dialog"`, `aria-modal="true"`, focus trap, Esc fecha, retorno de foco para o trigger ao fechar.
- **FR-027**: O menu de usuário MUST suportar navegação por teclado (setas para mover entre itens, Enter para acionar, Esc para fechar).
- **FR-028**: O sistema MUST NOT usar diálogos nativos do navegador (`confirm()`, `prompt()`, `alert()`) em nenhuma interação do shell.

**Tema e identidade visual**

- **FR-029**: O shell MUST seguir os tokens visuais já estabelecidos no projeto (cores de superfície, foreground, borda, paleta primária) e a tipografia e espaçamento do mockup de referência `02 _ Dashboard executivo`.
- **FR-030**: O shell MUST renderizar consistentemente em navegadores modernos suportados pelo projeto, sem dependência de modo escuro nesta versão.

### Key Entities

Esta feature não introduz novas entidades de dados persistentes — opera inteiramente sobre estado de autenticação e tenant já disponíveis no cliente.

- **Estado de Autenticação Local (já existente)**: nome do usuário, email, lista de permissions, dados básicos do tenant (nome, slug, status). O shell consome; não modifica.
- **Definição de Item de Navegação (configuração estática no cliente)**: rótulo, ícone, rota de destino, permission requerida, grupo pai (opcional). Não persiste em banco.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das rotas autenticadas do painel (atualmente 24+ rotas registradas) renderizam dentro do mesmo chrome após a implementação, com exceção apenas da rota de onboarding.
- **SC-002**: Um usuário consegue alcançar qualquer módulo do painel a partir de qualquer outra tela do painel em **no máximo 2 cliques** (1 clique se o módulo for de nível raiz, 2 se for sub-item dentro de grupo).
- **SC-003**: Em telas mobile (< 768px), o usuário consegue abrir o drawer, escolher um item e fechar o drawer em **no máximo 3 toques** sequenciais.
- **SC-004**: A renderização inicial do shell completo (sidebar + topbar + skeleton da página) acontece em até **300 ms** após o roteador resolver a rota — sem flash de tela em branco perceptível.
- **SC-005**: Um usuário sem permissões para um módulo X **nunca vê** o item correspondente na sidebar, em nenhum breakpoint, em nenhum estado de carregamento.
- **SC-006**: O usuário consegue completar o fluxo "encontrar onde está logado e sair" em **menos de 10 segundos** a partir de qualquer rota do painel.
- **SC-007**: Auditoria automática de acessibilidade (ferramentas como axe ou Lighthouse) reporta **0 violações sérias** ou críticas no shell em desktop e mobile.
- **SC-008**: Reaproveitamento — 100% das páginas `/panel/*` existentes funcionam sem alteração no seu próprio componente após a integração (apenas o roteador muda para envolvê-las no shell).

## Assumptions

- O fluxo de autenticação Bearer + identificação por subdomínio do tenant (Fase 4) já está em produção e fornece os dados necessários (usuário, tenant, permissions) ao estado local do cliente — esta feature consome esse estado sem modificá-lo.
- As 24+ rotas autenticadas existentes em `/panel/*` declaram corretamente sua exigência de auth e, quando aplicável, a permission necessária — adições/ajustes de meta (`title`) são considerados parte do escopo, mas redesenhos de cada página não são.
- O wizard de onboarding (`/panel/onboarding`) permanece em tela cheia sem chrome — decisão deliberada para reduzir distração durante o setup inicial; pode ser revisitado em spec futura.
- Busca global e notificações são placeholders nesta versão; o desenho dos respectivos slots na topbar é parte do escopo, mas a funcionalidade fica para specs futuras dedicadas.
- O super admin (Filament) continua tendo seu próprio shell separado — nada nesta spec o afeta.
- O painel **não** suporta tema escuro nesta versão — pode ser adicionado em spec futura.
- Internacionalização: novas strings de UI deste shell (rótulos de itens da sidebar, mensagens de estado) usam a infraestrutura i18n já existente (locale padrão pt_BR, fallback en).
- O comportamento de sincronização entre múltiplas abas (estado de drawer/menu) está fora do escopo — cada aba é independente.
- A persistência de preferências de UI (estado dos grupos da sidebar, modo expandido/compacto) usa armazenamento local do navegador escopado por usuário+tenant. Dados são considerados não-críticos: se o armazenamento estiver indisponível (modo privado, cota cheia, política do navegador), defaults seguros são aplicados sem bloquear o uso.
- As permissions/abilities exigidas por cada rota já estão declaradas no roteador atual (`meta.ability`) ou serão estendidas como parte do escopo onde estiverem faltando, sem alterar o motor de autorização existente.
