# Feature Specification: Auditoria e Correção de UI/UX do Frontend

**Feature Branch**: `016-frontend-ux-audit`
**Created**: 2026-05-26
**Status**: Draft
**Input**: User description: "precisamos analisar nosso frontend todo e entender por que alguns campos ou botões não estão de acordo com o layout, tanto na parte desktop quanto na parte responsiva, haja como uma UI/UX e veja as melhorias que precisamos tomar para deixar o layout de fácil uso para o cliente."

## Clarifications

### Session 2026-05-26

- Q: O painel super-admin (Filament) entra no escopo desta feature? → A: Não — escopo é apenas a SPA Vue do tenant + telas públicas; Filament fica fora desta feature.
- Q: Até onde vai a correção (Definição de Pronto)? → A: Corrigir 100% dos itens catalogados — todas as severidades, em todas as telas da SPA. Prioridade P1→P2 é apenas ordem de execução, não recorte de escopo.
- Q: Qual a referência de design (o que é "layout correto")? → A: Padrão de fato extraído das telas de melhor qualidade existentes (sem Figma/guia externo).
- Q: Quais resoluções/breakpoints oficialmente suportados? → A: Faixa contínua de ~320px a ≥1920px (layout fluido, sem quebra em nenhuma largura); larguras representativas (320/375/768/1024/1366/1440/1880/1920) são apenas pontos de amostragem da verificação.
- Q: Estratégia de prevenção de regressão? → A: Asserções de invariante (Playwright + axe; gates G1–G16) como gate permanente de CI; screenshots de pixel apenas como evidência pontual, não como gate.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Telas operacionais consistentes e usáveis no desktop (Priority: P1)

Operadores da clínica (atendente, recepcionista, médico, financeiro, admin) usam as telas centrais do dia a dia — Inbox/Conversas, Agenda, Pacientes, Receituários, Canais e Dashboard — sem encontrar campos ou botões cortados, sobrepostos ou desalinhados, e sem áreas que deixam de aproveitar o espaço disponível (espaços mortos ou conteúdo que vaza para fora da tela).

**Why this priority**: São as telas usadas o tempo todo. Layout quebrado custa tempo, induz a erros e mina a confiança no produto. Corrigir o uso diário entrega valor imediato e independente.

**Independent Test**: Percorrer cada tela operacional nas resoluções desktop alvo e verificar a ausência dos defeitos catalogados e a conformidade com o padrão visual; concluir as tarefas-chave de cada tela sem obstrução.

**Acceptance Scenarios**:

1. **Given** uma tela operacional aberta em resolução desktop alvo, **When** o usuário visualiza e opera, **Then** nenhum campo ou botão aparece cortado, sobreposto ou fora do seu container, e a área de conteúdo principal preenche o espaço disponível.
2. **Given** uma lista ou painel com poucos ou muitos itens, **When** a tela renderiza, **Then** não há rolagem horizontal inesperada nem espaços mortos, e os estados de carregando/vazio/erro são exibidos adequadamente.
3. **Given** um botão de ação primária, **When** exibido, **Then** segue o padrão visual (tamanho, cor, espaçamento, estados hover/foco/desabilitado) consistente com as demais telas.

---

### User Story 2 - Uso fluido no mobile e responsivo (Priority: P1)

Os mesmos fluxos operacionais funcionam bem em telas pequenas (celular) e médias (tablet): sem rolagem horizontal, com alvos de toque confortáveis, navegação acessível (menu/drawer), modais que cabem na tela e conteúdo que reflui em vez de quebrar.

**Why this priority**: A equipe acessa pelo celular e o pedido cita explicitamente a parte responsiva. É um corte de valor independente do desktop.

**Independent Test**: Percorrer as telas nos breakpoints alvo (celular e tablet) e confirmar ausência de overflow horizontal, toque confortável e fluxos completáveis ponta a ponta.

**Acceptance Scenarios**:

1. **Given** uma tela em viewport de celular, **When** renderiza, **Then** não há rolagem horizontal e todo o conteúdo essencial é acessível.
2. **Given** um controle em viewport pequeno, **When** o usuário toca, **Then** o alvo é confortável e responde sem sobreposição com elementos vizinhos.
3. **Given** um layout multi-painel (ex.: inbox), **When** em tela pequena, **Then** ele colapsa para um painel por vez com navegação clara de ida e volta.

---

### User Story 3 - Padrão visual consistente entre telas (Priority: P2)

Botões, campos, selects, badges, cards, espaçamentos, tipografia e estados (hover, foco, desabilitado, carregando, vazio, erro) seguem um padrão único em todo o app, de modo que o produto pareça coeso e previsível.

**Why this priority**: Consistência reduz carga cognitiva e erros, mas depende de US1/US2 terem estabilizado o essencial primeiro.

**Independent Test**: Comparar componentes equivalentes entre telas distintas e confirmar conformidade com o padrão definido.

**Acceptance Scenarios**:

1. **Given** dois botões primários em telas diferentes, **When** comparados, **Then** têm o mesmo estilo e variação.
2. **Given** um campo obrigatório com erro em telas diferentes, **When** comparado, **Then** o feedback de erro segue o mesmo padrão visual e textual.
3. **Given** uma lista vazia em telas diferentes, **When** comparada, **Then** segue o mesmo padrão de empty state.

---

### User Story 4 - Acessibilidade e feedback claros (Priority: P2)

Foco visível na navegação por teclado, contraste adequado, rótulos em todos os controles, mensagens de erro/sucesso claras e ausência de diálogos nativos (confirm/prompt/alert) — todos confirmam ações com componentes acessíveis.

**Why this priority**: Acessibilidade e feedback evitam erros e são coerentes com o contexto clínico/LGPD; complementam a consistência visual.

**Independent Test**: Navegar por teclado e com leitor de tela pelas telas auditadas e validar foco, rótulos, contraste e padrões de confirmação.

**Acceptance Scenarios**:

1. **Given** navegação por teclado, **When** o usuário avança pelos controles, **Then** o foco é sempre visível e a ordem é lógica.
2. **Given** uma ação destrutiva, **When** o usuário a aciona, **Then** a confirmação usa um modal acessível (nunca `confirm()` nativo).
3. **Given** texto sobre um fundo, **When** medido, **Then** o contraste atende ao mínimo do nível AA.

---

### User Story 5 - Catálogo de problemas auditado e priorizado (Priority: P3)

Um inventário documentado dos problemas de UI/UX encontrados — tela/rota, descrição, severidade, escopo (desktop/responsivo), recomendação e critério de verificação — serve como fonte única para acompanhar a remediação e prevenir regressões.

**Why this priority**: Dá rastreabilidade e organiza o trabalho; é o artefato que orienta as demais histórias, mas não entrega valor ao cliente final sozinho.

**Independent Test**: Revisar o catálogo e confirmar que cada problema está classificado por severidade e escopo, com status de verificação.

**Acceptance Scenarios**:

1. **Given** a auditoria concluída, **When** consultada, **Then** existe um catálogo com cada problema classificado por severidade e escopo.
2. **Given** um problema corrigido, **When** revisado, **Then** está marcado como resolvido com seu critério de verificação atendido.

---

### Edge Cases

- Conteúdo muito longo (nomes, mensagens, listas extensas) deve refluir/truncar sem estourar o container.
- Conteúdo vazio (sem dados) deve exibir empty state, não áreas em branco.
- Estados de erro e carregando devem ter feedback visível e consistente.
- Viewports extremos: muito largo (ex.: ~1880px) deve preencher sem espaços mortos; muito estreito (~320–360px) não deve gerar overflow.
- Densidade alta (ex.: inbox com 3 painéis) deve degradar bem em telas médias.
- Internacionalização: textos sem tradução não podem aparecer como chaves cruas nem quebrar o layout.
- Zoom do navegador e fontes maiores não devem quebrar o layout.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O produto MUST passar por uma auditoria sistemática de todas as telas do painel autenticado e das telas públicas (login, cadastro, recuperação de senha, onboarding), cobrindo desktop e responsivo.
- **FR-002**: A auditoria MUST registrar cada problema com tela/rota, descrição, severidade (crítico/alto/médio/baixo), escopo (desktop/responsivo/ambos) e recomendação.
- **FR-003**: Campos e botões MUST permanecer totalmente visíveis, alinhados e dentro de seus containers, sem corte, sobreposição ou vazamento, nas resoluções alvo.
- **FR-004**: A área de conteúdo principal de cada tela MUST preencher o espaço disponível, sem espaços mortos nem rolagem horizontal indevida.
- **FR-005**: Em viewports pequenos, as telas MUST refluir sem rolagem horizontal e com alvos de toque adequados.
- **FR-006**: Componentes equivalentes (botões, campos, badges, cards, modais, estados) MUST seguir um padrão visual consistente em todo o app.
- **FR-007**: Toda tela MUST exibir os estados de carregando, vazio e erro de forma consistente e clara.
- **FR-008**: Confirmações e alertas MUST usar componentes acessíveis, sem diálogos nativos (`confirm`/`prompt`/`alert`).
- **FR-009**: A navegação por teclado MUST ter foco visível e ordem lógica; o contraste MUST atender no mínimo ao nível AA.
- **FR-010**: Os textos da interface MUST estar traduzidos (sem chaves cruas) e não devem quebrar o layout quando longos.
- **FR-011**: TODOS os itens catalogados MUST ser corrigidos (todas as severidades, em todas as telas da SPA). A priorização por severidade e impacto (telas P1 antes de P2) define apenas a **ordem de execução**, não recorte de escopo (Clarification Q2).
- **FR-012**: Cada correção MUST ter um critério de verificação objetivo (visual e comportamental) para evitar regressão.
- **FR-015**: A prevenção de regressão MUST se apoiar em **asserções de invariante** (verificações de geometria/comportamento + acessibilidade) como gate permanente, não em comparação de screenshots de pixel (Clarification Q5). Screenshots servem apenas como evidência pontual.
- **FR-013**: A conformidade visual MUST ser medida contra um padrão de referência derivado das telas de melhor qualidade já existentes (ex.: Pacientes, Agenda) — um "design system implícito" extraído do próprio app (tokens, variantes de componente e estados), ao qual as demais telas devem ser alinhadas (confirmado em Clarification Q3).
- **FR-014**: O layout MUST ser fluido e íntegro em toda a faixa contínua de **~320px a ≥1920px**, sem quebra em nenhuma largura (Clarification Q4). A verificação amostra larguras representativas — 320, 375, 768, 1024, 1366, 1440, 1880, 1920 — mas a exigência é a integridade contínua, não apenas nesses pontos.

### Key Entities *(include if feature involves data)*

- **Problema de UI/UX (item do catálogo)**: tela/rota, descrição, severidade, escopo (desktop/responsivo), status (aberto/corrigido/verificado), recomendação e critério de verificação.
- **Tela/Rota**: unidade auditável — nome, finalidade, papéis que a usam, criticidade operacional.
- **Padrão de componente**: definição da aparência e dos estados esperados para um tipo de elemento (botão, campo, badge, modal, empty/loading/error).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das telas da SPA auditadas sem itens de catálogo em aberto (todas as severidades resolvidas) nas resoluções alvo (desktop e mobile) — telas operacionais centrais resolvidas primeiro.
- **SC-002**: Zero ocorrências de rolagem horizontal indevida e zero campos/botões cortados nas telas auditadas, nas resoluções alvo.
- **SC-003**: As tarefas-chave (responder uma conversa, agendar uma consulta, cadastrar um paciente, emitir uma receita) são concluídas sem obstrução de layout em desktop e mobile, com taxa de conclusão ≥95% em teste de usabilidade.
- **SC-004**: Nenhuma chave de tradução crua visível na interface.
- **SC-005**: Todas as telas auditadas apresentam estados de carregando/vazio/erro consistentes.
- **SC-006**: 100% das confirmações/alertas usam componentes acessíveis (zero diálogos nativos).
- **SC-007**: Catálogo de problemas completo, com cada item classificado por severidade e escopo e com status de verificação.
- **SC-008**: Redução mensurável da fricção percebida pelos usuários internos (ex.: feedback positivo ou queda de retrabalho/atendimentos sobre dificuldade de uso) após a remediação.

## Assumptions

- Reuso da estrutura e identidade visual atuais — o objetivo é consistência e usabilidade, não redesenho do zero nem rebranding.
- Escopo = SPA Vue do tenant + telas públicas (login, cadastro, recuperação, onboarding). O painel super-admin Filament está **fora do escopo** desta feature (Clarification Q1). Telas operacionais centrais (Inbox/Conversas, Agenda, Pacientes, Receituários, Canais, Dashboard) têm prioridade; demais telas da SPA vêm em seguida, com meta de cobertura total da SPA.
- Suporte fluido contínuo de ~320px a ≥1920px (FR-014, Clarification Q4); larguras representativas usadas só como amostragem de verificação.
- Acessibilidade alvo: WCAG 2.1 nível AA, coerente com o contexto clínico e a postura de LGPD do produto.
- A auditoria considera os perfis/papéis existentes, pois cada papel enxerga telas e menus diferentes.
- O idioma base é pt-BR; ausência de tradução conta como defeito de UI.
- Esta é uma feature corretiva/de qualidade: não introduz novas funcionalidades de negócio, apenas corrige e padroniza o que já existe.
