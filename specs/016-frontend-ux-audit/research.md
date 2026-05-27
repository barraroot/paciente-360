# Research — Auditoria e Correção de UI/UX do Frontend

Fase 0. Resolve as decisões de método e ferramentas. Sem NEEDS CLARIFICATION pendente — FR-013 confirmado (Clarification Q3) e FR-014 atualizado para faixa contínua (Clarification Q4).

## R1 — Fonte de referência de design (resolve FR-013)

- **Decisão**: Codificar um "design system implícito" extraído das telas de melhor qualidade já existentes (ex.: Pacientes, Agenda, modais a11y das Fases 6/12). Catalogar tokens de fato (cores via variáveis CSS `--shadow-card`, `surface`, `border`, `foreground*`, espaçamentos, raios, tipografia) e as variantes de componente recorrentes (botão primário/secundário/perigo, input, badge, card, modal).
- **Rationale**: Não há Figma/guia de marca canônico fornecido; o app já tem padrões bons em telas específicas. Extrair e convergir é mais rápido e evita rebranding.
- **Alternativas**: (B) seguir design system externo — bloqueado por falta de artefato; (C) criar mini design system do zero — escopo maior, adiado.

## R2 — Suporte responsivo contínuo (resolve FR-014 — atualizado por Clarification Q4)

- **Decisão**: Layout **fluido e íntegro em toda a faixa contínua de ~320px a ≥1920px**, sem quebra em nenhuma largura. A verificação **amostra** larguras representativas — **320 / 375 / 768 / 1024 / 1366 / 1440 / 1880 / 1920** — mas a exigência é a integridade contínua, não só nesses pontos.
- **Rationale**: o cliente optou pela faixa contínua (mais rigorosa) em vez de pontos fixos; cobre qualquer dispositivo/janela. Tailwind v4 (`sm/md/lg/xl`) continua sendo a ferramenta de implementação dos saltos.
- **Verificação**: além das larguras amostradas, varrer larguras intermediárias em telas críticas (ex.: arrastar a janela) para flagrar quebras entre breakpoints.

## R3 — Metodologia de auditoria

- **Decisão**: Varredura **rota a rota** (enumeradas a partir de `resources/js/config/navigation.js` + `router/index.js`), em cada um dos 4 breakpoints, registrando defeitos no catálogo. Para cada tela: (a) checagens automatizadas de geometria, (b) checagem de a11y, (c) varredura de i18n, (d) revisão visual manual de consistência.
- **Rationale**: Cobertura sistemática e reprodutível; evita auditoria ad-hoc que perde telas.
- **Estados a inspecionar por tela**: carregando, vazio, erro, com poucos dados, com muitos dados, com texto longo.

## R4 — Ferramentas de verificação automatizada

- **Decisão**:
  - **Playwright** (já presente no projeto via MCP) para medir geometria em runtime: overflow horizontal (`scrollWidth > clientWidth`), preenchimento de área (elemento alcança a borda/rodapé esperados), alvos de toque (área mínima ~44×44px), foco visível.
  - **axe-core** para violações de acessibilidade (contraste, rótulos, roles).
  - **Varredura de i18n cruas**: comparar chaves `t('...')` referenciadas no código com as presentes em `pt-BR.json`; detectar render de chave crua (regex `[a-z_]+\.[a-z_.]+` em texto visível).
  - ESLint `no-unsanitized` (já exigido pela Constituição VII) para `v-html` sem sanitização.
- **Rationale**: torna os critérios da spec (SC-002, SC-004, SC-006) verificáveis objetivamente e reaproveitáveis como regressão.
- **Alternativas**: verificação só visual (não reprodutível, sem gate de regressão) — rejeitada como único método.

## R5 — Padronização de componentes

- **Decisão**: Inventariar primitivos compartilhados em `resources/js/components/ui/` e mapear divergências (botões/inputs/badges/modais/empty-loading-error states espalhados por telas). Convergir para variantes únicas, reaproveitando padrões já consagrados (modal a11y `Teleport`+focus-trap das Fases 6/12; toast local; `Intl`/Luxon para datas/moeda).
- **Rationale**: Consistência (US3) com mínimo retrabalho, partindo do que já funciona.
- **Nota**: não recriar componentes que já seguem o padrão; só alinhar os divergentes.

## R6 — Cobertura de i18n (FR-010)

- **Decisão**: Tratar chave i18n crua na tela como defeito de severidade alta (já corrigimos `inbox.ai_pause.*`). Verificar também que textos longos não quebram layout (usar truncate/refluxo).
- **Rationale**: Constituição (Localização) + experiência ruim ao cliente.

## R7 — Prevenção de regressão

- **Decisão**: Para cada invariante crítico, asserção Playwright reutilizável: sem overflow horizontal por tela/breakpoint; área principal preenche; sem chave i18n crua; foco visível em navegação por teclado; confirmação destrutiva via modal (não `confirm()` nativo).
- **Rationale**: Princípio IV (test-first em comportamento observável). Desvio consciente para defeitos puramente estéticos (verificação manual documentada).

## R8 — Inventário de telas (escopo)

- **Decisão**: Fonte da lista = `navigation.js` + `router/index.js`. Priorização:
  - **P1 (operacionais centrais)**: Inbox/Conversas, Canais, Agenda, Pacientes (lista/funil/merge), Receituários, Dashboard.
  - **P1 (públicas)**: login, cadastro de clínica, recuperação de senha, onboarding.
  - **P2**: Campanhas, Relatórios, Integrações, Privacidade & LGPD, Configurações, Profissionais, Regras de atribuição, Respostas rápidas, IA Matricial.
  - **Secundário (fora do P1)**: Painel super-admin Filament (app server-rendered com convenções próprias; menor risco de layout custom).
- **Rationale**: entrega valor onde dói mais primeiro (uso diário), mantendo meta de cobertura total.
