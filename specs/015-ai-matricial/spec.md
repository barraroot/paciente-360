# Feature Specification: IA Matricial

**Feature Branch**: `015-ai-matricial`
**Created**: 2026-05-25
**Status**: Draft
**Input**: User description: "Criar uma nova funcionalidade incremental chamada 'IA Matricial' — camada de configuração e orquestração de IA conectada ao atendimento omnichannel já existente."

## Overview

A **IA Matricial** é uma camada incremental de configuração e orquestração de inteligência artificial que se conecta ao atendimento omnichannel **já existente** (conversas, canais WhatsApp/Widget/Instagram, mensagens e atendimento humano). Ela **não recria** canais, conversas, autenticação nem multi-tenancy — apenas pluga uma inteligência configurável sobre o fluxo atual.

A "matriz" cruza sete dimensões já existentes ou novas para determinar **qual persona** atende **qual canal**, com **quais bases de conhecimento**, **quais guardrails** e **qual modelo de IA**:

1. Clínica (existente)
2. Canal de atendimento (existente: WhatsApp, Widget de site, Direct Instagram)
3. Persona/bot (novo)
4. Modelo de IA (novo, catálogo da plataforma)
5. Base de conhecimento (novo)
6. Guardrails (novo)
7. Conversa/chamado (existente)

## Clarifications

### Session 2026-05-25

- Q: Como a IA deve usar as bases de conhecimento na v1? → A: **Recuperação semântica (RAG)** — embeddings + chunking; apenas os trechos relevantes das bases ativas associadas à persona entram no contexto. (Move RAG do "Fora de escopo" para o escopo da v1.)
- Q: Quando a IA gera uma resposta, como ela deve ser entregue ao paciente? → A: **Auto-envio com escalonamento** — envia automaticamente pelo canal quando dentro dos guardrails; em risco/dúvida/limitação encaminha para humano e pausa.
- Q: De quem são as credenciais/chaves dos provedores de modelos de IA? → A: **Plataforma-global** — a plataforma mantém as credenciais do provedor; a clínica apenas seleciona modelos ativos do catálogo. Uso/custo apenas para log/auditoria.
- Q: Em uma conversa com IA ativa, a quais mensagens a IA responde? → A: **Toda mensagem inbound do paciente enquanto a conversa estiver em atendimento por IA, com debounce** (agrupamento de mensagens em rajada em uma resposta coesa).

### Session 2026-05-26

- Q: Como a IA é "habilitada" para um canal de uma clínica? → A: **Derivado das personas** — o canal está habilitado para IA se, e somente se, houver ≥1 persona **ativa** vinculada a ele na matriz. Não há flag/toggle separado.
- Q: Como a ferramenta assistida gera o rascunho de Markdown? → A: **Componente de front-end determinístico** (sem IA) com controles de formatação (título, subtítulo, ênfase, parágrafo, citação, lista, checklist, link, tabela) que produz o Markdown no cliente; a API apenas **sanitiza e salva** mediante ação explícita do usuário. Nenhuma geração por IA.
- Q: Quando o provedor de IA falha (erro/timeout)? → A: **Re-tenta com backoff (silencioso)**; só ao **esgotar** as tentativas a conversa entra em estado de erro e escala para humano. **Nenhuma** mensagem automática é enviada ao paciente durante/por causa da falha.
- Q: Persona desativada/removida do canal com conversas em andamento? → A: **Reatribui** as conversas em andamento via round-robin a outra persona **ativa** do canal; se não houver outra persona ativa, encaminha para humano (pausa).
- Q: Como termina a pausa da IA numa conversa (ou quando humano assume)? → A: **Manual e indefinida** — permanece pausada até reativação **explícita** por usuário com permissão (`inbox.respond`) em conversa não encerrada; **sem** auto-resume temporizado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configurar persona de IA com modelo (Priority: P1)

A clínica precisa criar uma ou mais personas de atendimento por IA, definindo identidade, comportamento e o modelo de IA que cada uma usará, escolhendo entre os modelos ativos disponibilizados pela plataforma.

**Why this priority**: Sem personas configuradas e um modelo selecionado, nada da camada de IA funciona. É a fundação de toda a matriz.

**Independent Test**: Pode ser testada criando uma persona com nome, conteúdo Markdown, tom de voz e um modelo ativo selecionado, e verificando que ela é listada, editável e isolada por clínica — entregando valor de configuração mesmo antes de qualquer atendimento por IA.

**Acceptance Scenarios**:

1. **Given** a plataforma disponibiliza ao menos um modelo de IA ativo, **When** um usuário autorizado da Clínica A cria uma persona selecionando esse modelo e preenchendo conteúdo Markdown válido, **Then** a persona é salva como pertencente exclusivamente à Clínica A e aparece na listagem dela.
2. **Given** uma persona existente da Clínica A, **When** um usuário da Clínica B tenta listar, visualizar ou editar essa persona, **Then** o sistema responde como recurso inexistente e não revela nenhum dado.
3. **Given** um modelo que foi marcado como inativo pela plataforma, **When** o usuário tenta criar uma nova persona com esse modelo, **Then** o sistema impede a seleção do modelo inativo.
4. **Given** uma persona já criada com um modelo que posteriormente ficou inativo, **When** o usuário visualiza ou edita os demais campos da persona, **Then** a configuração histórica continua válida e não é quebrada.
5. **Given** uma persona com configurações de modelo (ex.: temperatura, limite de tokens), **When** o usuário informa um valor incompatível com o modelo selecionado, **Then** o sistema rejeita o valor com mensagem clara.

---

### User Story 2 - Habilitar IA por canal e distribuir conversas (Priority: P1)

A clínica precisa definir, em uma matriz Persona × Canal, quais personas podem atender cada canal existente; e quando uma nova conversa chega por um canal habilitado, o sistema deve atribuir uma persona de forma equilibrada (round-robin) entre as personas ativas daquele canal.

**Why this priority**: É o coração da orquestração — decide se e quem da IA atende. Sem distribuição, personas configuradas não chegam a atender.

**Independent Test**: Pode ser testada configurando duas personas ativas no canal WhatsApp e simulando a chegada de várias novas conversas, verificando que a atribuição alterna de forma equilibrada, persiste por clínica+canal e registra qual persona ficou em cada conversa.

**Acceptance Scenarios**:

1. **Given** as personas P1 e P2 ativas e habilitadas no canal WhatsApp da Clínica A, **When** chegam quatro novas conversas por WhatsApp, **Then** a atribuição se distribui de forma equilibrada entre P1 e P2 (ex.: 2 e 2), e cada conversa registra a persona atribuída.
2. **Given** um canal sem nenhuma persona ativa configurada, **When** chega uma nova conversa por esse canal, **Then** a IA não atende, a conversa segue o fluxo atual de atendimento humano e nada é quebrado.
3. **Given** uma conversa já atribuída à persona P1, **When** novas mensagens chegam na mesma conversa, **Then** a conversa continua com P1 até encerramento, pausa, transferência humana ou regra explícita de reatribuição.
4. **Given** a persona P1 atribuída em conversas em andamento, **When** P1 é desativada ou removida do canal, **Then** P1 não recebe novas conversas **e** as conversas em andamento dela são **reatribuídas** via round-robin a outra persona ativa do canal; se não houver outra persona ativa, essas conversas são encaminhadas para humano (IA pausada).
5. **Given** o ciclo de distribuição de um canal, **When** uma nova persona P3 é adicionada e ativada nesse canal, **Then** P3 passa a entrar no rodízio de novas conversas.
6. **Given** dois tenants distintos com personas de mesmo nome, **When** conversas chegam em cada um, **Then** a distribuição é isolada por clínica e por canal e nunca mistura dados entre clínicas.

---

### User Story 3 - IA responde automaticamente na conversa existente (Priority: P1)

Quando a IA está habilitada para o canal e há persona atribuída, o sistema deve montar o contexto (persona + modelo + bases + guardrails), gerar uma resposta segura, enviá-la pelo mesmo canal usando a estrutura existente, e atualizar o estado da IA na conversa.

**Why this priority**: É a entrega de valor de ponta a ponta — a IA efetivamente atendendo. Depende de US1/US2, mas funciona mesmo sem bases ou guardrails personalizados (aplicando os guardrails médicos mínimos obrigatórios).

**Independent Test**: Pode ser testada simulando uma mensagem de entrada em uma conversa com persona atribuída e verificando que uma resposta é gerada respeitando os guardrails mínimos, enviada pelo canal existente como mensagem de IA, e que o estado da conversa reflete "em atendimento por IA".

**Acceptance Scenarios**:

1. **Given** uma conversa em canal habilitado com persona atribuída, **When** chega uma nova mensagem do paciente, **Then** o sistema monta o contexto, gera uma resposta e a envia pelo mesmo canal como mensagem de IA, registrando log da execução.
2. **Given** qualquer persona, mesmo sem guardrails personalizados, **When** a IA gera uma resposta, **Then** os guardrails médicos mínimos obrigatórios são sempre aplicados (não diagnosticar, não prescrever, não substituir consulta, orientar emergência, encaminhar humano em dúvida/risco/reclamação).
3. **Given** uma situação de dúvida clínica, risco, reclamação grave, solicitação sensível ou limitação de segurança detectada, **When** a IA processa a mensagem, **Then** a IA encaminha para atendimento humano e pausa as respostas automáticas.
4. **Given** uma falha na geração da resposta (ex.: indisponibilidade do modelo), **When** o sistema tenta atender, **Then** a conversa entra em estado de erro de IA, o fluxo humano permanece disponível e o erro é registrado em log sem expor dados sensíveis.
5. **Given** dados pessoais do paciente na conversa, **When** o contexto é enviado ao modelo de IA, **Then** os dados sensíveis desnecessários são minimizados/pseudonimizados conforme LGPD antes do envio externo.

---

### User Story 4 - Bases de conhecimento por clínica e associação à persona (Priority: P2)

A clínica precisa cadastrar bases de conhecimento em Markdown (serviços, horários, convênios, FAQ etc.) e associar uma ou mais a cada persona, para que a IA responda com informações corretas da clínica.

**Why this priority**: Enriquece a qualidade das respostas, mas a IA já atende (US3) com a persona e guardrails mínimos antes disso.

**Independent Test**: Pode ser testada criando uma base ativa, associando-a a uma persona e verificando que apenas bases ativas da mesma clínica associadas à persona são utilizadas, e que bases de outra clínica nunca são acessíveis.

**Acceptance Scenarios**:

1. **Given** uma base de conhecimento ativa da Clínica A associada à persona P1, **When** P1 atende uma conversa, **Then** somente bases ativas da Clínica A associadas a P1 são usadas no contexto.
2. **Given** uma base marcada como inativa, **When** a persona associada atende, **Then** a base inativa não é usada em novas respostas.
3. **Given** uma base da Clínica B, **When** um usuário da Clínica A tenta associá-la a uma persona, **Then** o sistema impede a associação e trata como recurso inexistente.

---

### User Story 5 - Guardrails por clínica e guardrails médicos mínimos (Priority: P2)

A clínica precisa cadastrar guardrails em Markdown (segurança, LGPD, atendimento médico, encaminhamento, tom de voz etc.) e associá-los a personas; e o sistema deve sempre aplicar um conjunto mínimo obrigatório de guardrails de segurança médica, independentemente da configuração da clínica.

**Why this priority**: Segurança e conformidade são essenciais; porém os guardrails mínimos obrigatórios já são garantidos em US3, e esta story adiciona a camada configurável pela clínica.

**Independent Test**: Pode ser testada criando um guardrail ativo, associando-o a uma persona, verificando que apenas guardrails ativos da mesma clínica associados à persona são aplicados, e que os guardrails médicos mínimos são aplicados mesmo sem nenhum guardrail personalizado.

**Acceptance Scenarios**:

1. **Given** um guardrail ativo da Clínica A associado à persona P1, **When** P1 atende, **Then** somente guardrails ativos da Clínica A associados a P1 são aplicados, somados aos mínimos obrigatórios.
2. **Given** um guardrail inativo, **When** a persona atende, **Then** o guardrail inativo não é aplicado em novas respostas.
3. **Given** uma persona sem nenhum guardrail personalizado, **When** ela atende, **Then** os guardrails médicos mínimos obrigatórios continuam sendo aplicados.
4. **Given** um guardrail da Clínica B, **When** um usuário da Clínica A tenta associá-lo a uma persona, **Then** o sistema impede e trata como recurso inexistente.

---

### User Story 6 - Controle humano da IA na conversa (Priority: P2)

O atendente humano precisa ver se uma conversa está sendo atendida por IA e por qual persona, e poder pausar a IA, assumir o atendimento e reativar a IA quando permitido.

**Why this priority**: Garante supervisão humana e segurança operacional, essencial em contexto médico, mas depende da IA já estar atendendo (US3).

**Independent Test**: Pode ser testada em uma conversa atendida por IA: o atendente visualiza a persona ativa, pausa a IA e confirma que novas mensagens não recebem resposta automática até a reativação.

**Acceptance Scenarios**:

1. **Given** uma conversa em atendimento por IA, **When** o atendente abre a conversa, **Then** ele vê que está em atendimento por IA e qual persona está atuando.
2. **Given** uma conversa em atendimento por IA, **When** o atendente pausa a IA, **Then** a IA não responde novas mensagens até ser reativada.
3. **Given** uma conversa com IA pausada, **When** o atendente reativa a IA (quando permitido), **Then** a IA volta a responder novas mensagens.
4. **Given** uma conversa, **When** o atendente assume o atendimento, **Then** a IA é pausada e o atendimento humano prossegue normalmente.

---

### User Story 7 - Logs e auditoria de IA (Priority: P3)

A clínica e o suporte precisam consultar logs das execuções da IA (persona, modelo, bases, guardrails, status, tempo, tokens, custo estimado quando disponível) respeitando LGPD e isolamento por clínica.

**Why this priority**: Importante para suporte, auditoria e melhoria, mas não bloqueia o atendimento por IA funcionar.

**Independent Test**: Pode ser testada gerando algumas execuções de IA e verificando que os logs são consultáveis, escopados por clínica, sem dados sensíveis desnecessários, e inacessíveis para outra clínica.

**Acceptance Scenarios**:

1. **Given** execuções de IA ocorreram na Clínica A, **When** um usuário autorizado da Clínica A consulta os logs, **Then** ele vê os registros da própria clínica com persona, modelo, status, tempo de resposta e demais campos disponíveis.
2. **Given** logs da Clínica A, **When** um usuário da Clínica B tenta acessá-los, **Then** o acesso é negado e nenhum dado é revelado.
3. **Given** uma execução de IA, **When** o log é registrado, **Then** dados sensíveis desnecessários não são armazenados.

---

### User Story 8 - Editor Markdown reutilizável e auxiliar de formatação (Priority: P3)

A clínica precisa de um editor Markdown reutilizável com preview seguro e **controles de formatação** (título, subtítulo, ênfase/negrito-itálico, parágrafo, citação, lista, checklist, link, tabela) que ajudem a montar o conteúdo de personas, bases e guardrails. O auxiliar é um **componente de front-end determinístico** (sem IA): ele produz o Markdown no próprio cliente, que é então enviado à API para **sanitizar e salvar** mediante ação explícita do usuário.

**Why this priority**: Melhora muito a experiência de configuração, mas as configurações podem ser feitas com Markdown manual antes disso.

**Independent Test**: Pode ser testada usando o editor/auxiliar para montar conteúdo com os controles de formatação e preview em tempo real sanitizado, confirmando que o Markdown gerado no cliente só é persistido por ação explícita do usuário (com sanitização no back-end).

**Acceptance Scenarios**:

1. **Given** o editor Markdown, **When** o usuário digita/cola conteúdo com formatação e tenta inserir scripts ou HTML inseguro, **Then** o preview é sanitizado e nenhum conteúdo executável é renderizado.
2. **Given** os controles de formatação (título/subtítulo/ênfase/citação/lista/checklist/link/tabela), **When** o usuário os aciona, **Then** o componente insere o Markdown correspondente no campo de edição (sem chamar IA).
3. **Given** o conteúdo montado no editor, **When** o usuário aciona salvar, **Then** o Markdown é enviado à API, sanitizado no back-end e persistido; nada é salvo sem ação explícita do usuário.
4. **Given** um campo de conteúdo obrigatório vazio, **When** o usuário tenta salvar, **Then** o sistema bloqueia com validação clara.

---

### Edge Cases

- **Canal sem persona ativa**: a IA não atende e o fluxo de atendimento humano existente segue inalterado.
- **Conversa já atribuída a persona desativada/removida do canal**: a persona não recebe **novas** conversas **e** as conversas em andamento dela são **reatribuídas** via round-robin a outra persona **ativa** do canal; não havendo outra persona ativa, são encaminhadas para humano (IA pausada). A reatribuição registra a nova persona.
- **Modelo de IA fica inativo após uso**: novas personas não podem selecioná-lo; personas e conversas históricas continuam válidas; novas execuções em uma persona cujo modelo ficou inativo devem degradar com segurança (encaminhar humano em vez de quebrar). *(ver Assumptions)*
- **Falha/timeout do provedor de IA**: o sistema **re-tenta com backoff** sem enviar nada ao paciente; só ao **esgotar** as tentativas a conversa entra em estado de erro de IA e escala para humano. O erro é logado sem PII; nenhuma mensagem automática é enviada por causa da falha.
- **IA pausada recebe novas mensagens**: nenhuma resposta automática é gerada; a pausa é **indefinida** e só termina por reativação **manual** de usuário com permissão.
- **Empate no round-robin / contagens iguais**: o sistema escolhe deterministicamente a próxima persona pelo estado persistente do ciclo, sem viés acumulado.
- **Persona removida do canal durante distribuição**: deixa de entrar no ciclo imediatamente para novas conversas.
- **Mensagem em conversa de canal não habilitado**: ignorada pela camada de IA, sem efeitos colaterais.
- **Conteúdo Markdown malicioso enviado via API**: sanitizado no back-end independentemente da sanitização do front-end.
- **Detecção de urgência/emergência**: a IA orienta busca de atendimento emergencial e encaminha para humano.
- **Recuperação semântica sem trechos relevantes**: quando a indexação das bases não retorna nenhum trecho suficientemente relevante, a IA responde apenas com a persona + guardrails (sem inventar informação da clínica) ou encaminha para humano, sem alucinar dados.
- **Base recém-criada ainda não indexada**: enquanto a indexação não conclui, a base não contribui para as respostas, sem quebrar o atendimento.
- **Tenant suspenso**: a camada de IA não inicia novos atendimentos automáticos (segue a política existente de suspensão).

## Requirements *(mandatory)*

### Functional Requirements

#### Catálogo de modelos de IA

- **FR-001**: O sistema MUST disponibilizar um catálogo de modelos de IA gerenciado pela plataforma, cada modelo com: nome, identificador interno, provedor, descrição, status ativo/inativo e o conjunto de configurações permitidas (ex.: temperatura, limite de tokens ou parâmetros equivalentes).
- **FR-002**: A clínica MUST poder apenas selecionar modelos **ativos** do catálogo ao criar/editar personas; a clínica MUST NOT poder criar, editar ou excluir modelos.
- **FR-002a**: As credenciais/chaves dos provedores de modelos de IA MUST ser mantidas e gerenciadas **pela plataforma** (escopo global), nunca pela clínica; a clínica não informa nem visualiza credenciais de provedor. Tokens/custo estimado por execução são registrados apenas para log/auditoria (cobrança por uso fora de escopo).
- **FR-003**: O sistema MUST impedir que modelos **inativos** sejam selecionados em **novas** personas, sem quebrar personas/configurações históricas que já usavam o modelo.

#### Personas/bots por clínica

- **FR-004**: A clínica MUST poder criar múltiplas personas, cada uma contendo: nome, descrição interna, status ativo/inativo, modelo de IA selecionado, conteúdo principal em Markdown, tom de voz, objetivo do atendimento, limitações, mensagem inicial (opcional), mensagem de fallback (opcional), regras para encaminhamento humano e configurações compatíveis com o modelo selecionado.
- **FR-005**: O sistema MUST garantir que uma persona pertence exclusivamente a uma clínica e NEVER pode ser usada, listada, associada ou acessada por outra clínica.
- **FR-006**: O sistema MUST impedir que uma persona **inativa** receba novos atendimentos.
- **FR-007**: O sistema MUST permitir que uma persona atenda mais de um canal, use múltiplas bases de conhecimento e use múltiplos guardrails.
- **FR-008**: O sistema MUST validar que as configurações de modelo informadas na persona (ex.: temperatura, limite de tokens) sejam compatíveis com o modelo selecionado.

#### Matriz Persona × Canal

- **FR-009**: A clínica MUST poder configurar, em formato de matriz (personas × canais existentes), quais personas podem atender quais canais, com status ativo/inativo por célula (associação persona-canal).
- **FR-010**: O sistema MUST permitir múltiplas personas ativas no mesmo canal.
- **FR-011**: Quando um canal não tiver nenhuma persona ativa configurada, o sistema MUST NOT acionar a IA nesse canal e MUST preservar o fluxo de atendimento atual sem alterações.
- **FR-011a**: A habilitação de IA em um canal é **derivada**: o canal está habilitado para IA se, e somente se, existir ≥1 persona **ativa** vinculada (ativa na matriz) a ele. NÃO há flag/toggle separado de "IA ligada no canal"; desligar a IA de um canal se faz desativando ou desvinculando suas personas.

#### Distribuição equilibrada por canal

- **FR-012**: Ao chegar uma **nova** conversa por um canal com IA habilitada, o sistema MUST selecionar uma persona entre as personas ativas vinculadas àquele canal, usando distribuição equilibrada round-robin **persistente por clínica + canal**.
- **FR-013**: O sistema MUST isolar a distribuição por clínica e por canal e NEVER misturar dados entre clínicas.
- **FR-014**: O sistema MUST considerar somente personas ativas e vinculadas ao canal da conversa na distribuição.
- **FR-015**: O sistema MUST manter uma conversa já atribuída com a **mesma** persona até encerramento, pausa da IA, transferência humana ou regra explícita de reatribuição.
- **FR-016**: O sistema MUST excluir do ciclo de novas conversas qualquer persona desativada ou removida do canal, e MUST incluir no ciclo qualquer persona recém-adicionada e ativada no canal.
- **FR-016a**: Quando uma persona é desativada ou removida de um canal, o sistema MUST **reatribuir** suas conversas em andamento, via round-robin, a outra persona **ativa** do mesmo canal, registrando a nova persona (FR-017). Se não houver outra persona ativa no canal, essas conversas MUST ser encaminhadas para atendimento humano (IA pausada). Esta é a "regra explícita de reatribuição" referida em FR-015.
- **FR-017**: O sistema MUST registrar qual persona foi atribuída a cada conversa.

#### Bases de conhecimento

- **FR-018**: A clínica MUST poder cadastrar múltiplas bases de conhecimento, cada uma com: nome, descrição, status ativo/inativo, conteúdo em Markdown, tags opcionais e metadados opcionais.
- **FR-019**: O sistema MUST garantir que uma base de conhecimento pertence exclusivamente a uma clínica e NEVER seja acessível por outra clínica.
- **FR-020**: A clínica MUST poder associar uma ou mais bases de conhecimento a cada persona.
- **FR-021**: A IA MUST usar somente bases da mesma clínica, associadas à persona atribuída e **ativas**; bases inativas MUST NOT ser usadas em novas respostas.
- **FR-021a**: O sistema MUST montar o contexto da IA a partir das bases por **recuperação semântica (RAG)**: o conteúdo das bases ativas associadas à persona é indexado (embeddings + chunking) e somente os trechos mais relevantes à mensagem do paciente são incluídos no contexto, respeitando o orçamento de tokens do modelo.
- **FR-021b**: Quando uma base é criada, editada, ativada/desativada ou desassociada de uma persona, o sistema MUST refletir essa mudança na recuperação (reindexar ao salvar e excluir trechos de bases inativas/desassociadas das respostas seguintes), garantindo que conteúdo inativo nunca seja recuperado.

#### Guardrails

- **FR-022**: A clínica MUST poder cadastrar múltiplos guardrails, cada um com: nome, descrição, status ativo/inativo, conteúdo em Markdown e categoria opcional (ex.: Segurança, LGPD, Atendimento médico, Encaminhamento humano, Tom de voz, Restrições comerciais, Emergência, Privacidade).
- **FR-023**: O sistema MUST garantir que um guardrail pertence exclusivamente a uma clínica e NEVER seja acessível por outra clínica.
- **FR-024**: A clínica MUST poder associar um ou mais guardrails a cada persona.
- **FR-025**: A IA MUST aplicar somente guardrails da mesma clínica, associados à persona atribuída e **ativos**; guardrails inativos MUST NOT ser aplicados em novas respostas.

#### Guardrails médicos mínimos obrigatórios

- **FR-026**: Independentemente dos guardrails configurados pela clínica, o sistema MUST sempre aplicar guardrails mínimos de segurança médica que **proíbem** a IA de: dar diagnóstico definitivo, prescrever medicamentos, substituir consulta médica, indicar condutas clínicas de risco sem orientação adequada, solicitar dados sensíveis desnecessários, expor informações de pacientes, prometer resultados médicos e ignorar sinais de urgência/emergência.
- **FR-027**: O sistema MUST garantir que a IA **oriente** busca de atendimento emergencial em casos críticos, **encaminhe** para humano em dúvida clínica/risco/reclamação grave/solicitação sensível/limitação de segurança, respeite privacidade e LGPD e use linguagem clara e segura.

#### Conexão com o atendimento existente

- **FR-028**: A camada de IA MUST se conectar ao fluxo existente sem alterar a estrutura principal dos canais, sem recriar webhooks e sem substituir o atendimento humano (camada plugável).
- **FR-029**: Ao chegar uma nova mensagem/conversa, o sistema MUST: identificar a clínica e o canal pelos mecanismos atuais; identificar a conversa existente; verificar se a IA está habilitada para aquele canal naquela clínica; verificar se a conversa já tem persona; se não tiver, selecionar persona pela matriz Persona × Canal; carregar modelo, bases e guardrails da persona; montar o contexto; gerar a resposta; enviar a resposta pelo mesmo canal usando a estrutura existente; e registrar logs da execução.
- **FR-030**: O sistema MUST garantir que, com a IA desabilitada para o canal, o fluxo atual de atendimento permaneça funcionando normalmente.
- **FR-030a**: A IA MUST entregar suas respostas por **auto-envio com escalonamento**: quando a resposta está dentro dos guardrails (incluindo os mínimos obrigatórios), o sistema a envia automaticamente pelo canal; quando há risco, dúvida clínica, limitação de segurança ou gatilho de encaminhamento, o sistema NÃO envia automaticamente, encaminha para humano e pausa a IA.
- **FR-030b**: Enquanto a conversa estiver em estado "em atendimento por IA", o sistema MUST processar **toda nova mensagem inbound do paciente**, aplicando **debounce/agrupamento** de mensagens recebidas em rajada para produzir uma única resposta coesa, em vez de responder a cada mensagem isoladamente.
- **FR-030c**: Em **falha/timeout do provedor de IA**, o sistema MUST **re-tentar com backoff** (número de tentativas configurável) sem enviar qualquer mensagem ao paciente; somente ao **esgotar** as tentativas a conversa MUST entrar em estado de erro de IA e ser encaminhada para humano. Toda falha MUST ser registrada em log sem PII.

#### Estados da IA na conversa

- **FR-031**: O sistema MUST representar o estado da IA em uma conversa, com pelo menos: IA não habilitada, aguardando atribuição de persona, em atendimento por IA, pausada para atendimento humano, encerrada e erro na IA.
- **FR-032**: O atendente humano MUST poder: ver se a conversa está em atendimento por IA, ver qual persona está atendendo, pausar a IA, assumir o atendimento e reativar a IA quando permitido.
- **FR-033**: Quando a IA estiver pausada, o sistema MUST NOT gerar respostas automáticas a novas mensagens até a reativação. A pausa é **indefinida** (sem auto-resume temporizado) e só termina por reativação **explícita** de usuário com permissão (`inbox.respond`) em conversa não encerrada.

#### Logs e auditoria

- **FR-034**: O sistema MUST registrar logs das execuções da IA contendo, quando disponíveis: clínica, canal, conversa, persona, modelo, bases usadas, guardrails usados, mensagem de entrada, resposta gerada, status da execução, erro (se houver), tempo de resposta, tokens usados, custo estimado e data/hora.
- **FR-035**: Os logs MUST respeitar a LGPD (sem dados sensíveis desnecessários) e MUST NOT ser acessíveis a usuários de outra clínica.

#### Editor Markdown e ferramenta assistida (front-end)

- **FR-036**: O front-end MUST oferecer um componente reutilizável de editor Markdown para persona, base de conhecimento e guardrail, com: campo de edição, preview em tempo real sanitizado, botões de formatação (título, lista, negrito, itálico, link, tabela, citação), templates prontos, validação de conteúdo obrigatório, avisos de boas práticas e opção de copiar/colar Markdown.
- **FR-037**: O front-end MUST oferecer um **auxiliar de formatação Markdown determinístico** (sem IA), integrado ao editor, com controles para título, subtítulo, ênfase (negrito/itálico), parágrafo, citação, lista, **checklist**, link e tabela. O auxiliar produz o Markdown **no cliente**; não há geração de conteúdo por IA nem endpoint de geração assistida.
- **FR-038**: O conteúdo montado no editor/auxiliar MUST ser persistido apenas por **ação explícita** do usuário (salvar). A API MUST **sanitizar** o Markdown recebido no back-end antes de persistir (FR-041). Nenhum conteúdo é salvo automaticamente.
- **FR-039**: O sistema MUST disponibilizar os templates Markdown sugeridos para persona, base de conhecimento e guardrail (conforme estrutura de seções definida na descrição da feature).

#### Segurança, isolamento e sanitização

- **FR-040**: Todas as APIs da camada de IA MUST validar autenticação, autorização (permissões) e a clínica atual, e todas as queries MUST ser escopadas pela clínica.
- **FR-041**: O sistema MUST sanitizar o Markdown no **back-end** e no **front-end** (preview), não permitindo scripts, eventos inline, HTML inseguro ou conteúdo executável.
- **FR-042**: Antes de enviar qualquer conteúdo de conversa a um modelo de IA, o sistema MUST minimizar/pseudonimizar dados pessoais sensíveis desnecessários conforme LGPD.

#### APIs necessárias

- **FR-043**: O sistema MUST expor as APIs necessárias à camada de IA Matricial, incluindo: listar modelos de IA disponíveis; CRUD e ativar/desativar de personas; CRUD e ativar/desativar de bases de conhecimento; CRUD e ativar/desativar de guardrails; associar bases e guardrails a personas; configurar e consultar a matriz Persona × Canal; pausar e reativar a IA em uma conversa; consultar logs de IA; e validar/sanitizar Markdown antes de salvar. (NÃO há endpoint de geração de rascunho por IA — o auxiliar de Markdown é client-side e determinístico, FR-037.) Exclusão de registros MUST seguir o padrão de exclusão já adotado pelo sistema (ex.: soft delete), quando permitido.

### Key Entities *(include if feature involves data)*

- **Modelo de IA (catálogo da plataforma)**: representa um modelo selecionável; atributos: nome, identificador interno, provedor, descrição, status ativo/inativo, configurações permitidas. Gerido pela plataforma, não pela clínica. Compartilhado entre clínicas (somente leitura para a clínica).
- **Persona/Bot**: configuração de atendimento por IA pertencente a **uma** clínica; referencia um Modelo de IA; possui conteúdo Markdown e parâmetros de comportamento; associa-se a canais (existentes), bases de conhecimento e guardrails; tem status ativo/inativo.
- **Base de Conhecimento**: conteúdo Markdown informativo pertencente a **uma** clínica; status ativo/inativo, tags e metadados opcionais; associável a múltiplas personas. É indexada para recuperação semântica.
- **Índice Semântico de Base (chunks/embeddings)**: representação fragmentada e vetorizada do conteúdo de uma base, escopada por clínica, usada para recuperar apenas os trechos relevantes no momento da resposta; reflete o status (ativo/inativo) e as associações da base de origem.
- **Guardrail**: regra de comportamento/segurança em Markdown pertencente a **uma** clínica; categoria opcional; status ativo/inativo; associável a múltiplas personas.
- **Associação Persona × Canal**: vínculo (matriz) entre uma persona e um canal **existente**, com status ativo/inativo, escopado por clínica.
- **Associação Persona × Base de Conhecimento**: vínculo N-para-N entre persona e base, escopado por clínica.
- **Associação Persona × Guardrail**: vínculo N-para-N entre persona e guardrail, escopado por clínica.
- **Estado de IA da Conversa**: extensão do estado da **conversa existente** indicando o estágio da IA (não habilitada, aguardando atribuição, em atendimento, pausada, encerrada, erro) e a persona atribuída. Reutiliza campos já existentes de pausa de IA na conversa quando aplicável.
- **Estado de Distribuição (round-robin)**: ponteiro/contador persistente por clínica + canal que determina a próxima persona do ciclo.
- **Log de Execução de IA**: registro auditável por execução, escopado por clínica, com os campos de FR-034, sem PII clínica desnecessária.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Uma clínica consegue criar uma persona com um modelo de IA ativo e conteúdo Markdown, do início ao salvamento, em até 5 minutos, sem assistência técnica.
- **SC-002**: Em um canal com N personas ativas, ao chegarem M novas conversas, a diferença entre a persona mais e a menos atribuída é de no máximo 1 (distribuição equilibrada verificável).
- **SC-003**: 100% das conversas já atribuídas mantêm a mesma persona até encerramento, pausa, transferência ou reatribuição explícita.
- **SC-004**: 100% das respostas geradas pela IA aplicam os guardrails médicos mínimos obrigatórios, inclusive em personas sem guardrails personalizados.
- **SC-005**: 0 casos de acesso cruzado entre clínicas a personas, bases, guardrails, configurações de canal, conversas ou logs (isolamento multi-tenant total).
- **SC-006**: Quando a IA está desabilitada para um canal, 100% das conversas desse canal seguem o fluxo de atendimento existente sem regressões.
- **SC-007**: Após o atendente pausar a IA, 0 respostas automáticas são enviadas nessa conversa até a reativação.
- **SC-008**: 100% das execuções de IA geram um log consultável com persona, modelo, status e tempo de resposta, sem armazenar dados sensíveis desnecessários.
- **SC-009**: 100% do conteúdo Markdown salvo passa por sanitização no back-end, e nenhum conteúdo executável é renderizável no preview do front-end.
- **SC-010**: 0% de conteúdo é persistido sem ação explícita de salvar do usuário; 100% do Markdown salvo é sanitizado no back-end.
- **SC-011**: 100% do conteúdo de conversa enviado a um modelo de IA passa pela minimização/pseudonimização de PII antes do envio externo.
- **SC-012**: 100% dos trechos de base recuperados para uma resposta pertencem a bases **ativas** da **mesma clínica** associadas à persona atribuída; 0 trechos de bases inativas, desassociadas ou de outra clínica entram no contexto.
- **SC-013**: Em situações que disparam encaminhamento (risco/dúvida clínica/limitação de segurança), 100% das respostas NÃO são auto-enviadas e a conversa é encaminhada para humano com a IA pausada.

## Assumptions

- **Sistema existente reutilizado**: clínica (Tenant por slug/subdomínio), autenticação, permissões, isolamento multi-tenant (global scope por tenant), canais (`whatsapp`/`web`/`instagram`), conversas e mensagens já existem e serão reutilizados — não recriados.
- **Ponto de integração inbound**: a camada de IA se conecta ao fluxo de entrada já existente (eventos de nova mensagem/nova conversa disparados após o processamento do webhook), sem recriar webhooks dos canais.
- **Campos de pausa de IA já existentes na conversa** serão reutilizados para representar o estado de pausa/controle humano da IA, em vez de criar mecanismo paralelo.
- **`sender_type = 'ai'` já existe nas mensagens** e será usado para identificar respostas geradas pela IA, enviadas pela estrutura de envio de mensagens existente.
- **Pseudonimização/minimização de PII** reutiliza a infraestrutura de LGPD existente (detecção/scrubbing de PII) antes de qualquer envio a provedor externo de IA.
- **Geração de resposta está no escopo desta feature** por trás de uma abstração de provedor de IA, permitindo que o catálogo liste múltiplos modelos/provedores. O modelo padrão da plataforma é o já adotado na stack do projeto. As credenciais dos provedores são **globais da plataforma** (não por clínica) — ver FR-002a (clarificação 2026-05-25).
- **Uso de bases de conhecimento (v1) = RAG**: as bases ativas associadas à persona são indexadas (embeddings + chunking) e a IA recupera apenas os trechos relevantes para o contexto — ver FR-021a/FR-021b (clarificação 2026-05-25). Isso requer infraestrutura de indexação vetorial.
- **Auto-envio com escalonamento** (clarificação 2026-05-25): respeitando os guardrails, a IA envia a resposta automaticamente pelo canal; em dúvida/risco/limitação de segurança, encaminha para humano (pausa a IA) em vez de enviar — ver FR-030a.
- **Gatilho de resposta** (clarificação 2026-05-25): a IA responde a toda mensagem inbound do paciente enquanto a conversa estiver em atendimento por IA, com debounce/agrupamento de rajadas — ver FR-030b.
- **Degradação segura quando o modelo da persona está inativo**: novas execuções encaminham para atendimento humano em vez de quebrar, preservando o histórico.
- **Permissões**: novas abilities específicas da IA Matricial (ex.: gestão de personas/bases/guardrails/matriz e controle de IA na conversa) seguirão a convenção de nomes e o mecanismo de autorização já existentes (Spatie por tenant). O controle de pausar/reativar IA na conversa alinha-se às abilities de inbox existentes.
- **Custo/uso de IA**: registro de tokens e custo estimado é apenas para auditoria/log; cobrança por uso de IA está fora de escopo.
- **Empate no round-robin** é resolvido deterministicamente pelo estado persistente do ciclo (sem aleatoriedade), garantindo reprodutibilidade.

## Out of Scope

- Criar/recriar sistema de clínicas, multi-tenancy, autenticação ou permissões do zero.
- Criar/recriar WhatsApp, Widget de site, Direct Instagram ou módulo de conversas/atendimento humano do zero.
- Criar CRM, agenda médica ou cadastro de pacientes.
- Cobrança por uso de IA.
- Fine-tuning ou treinamento avançado de modelos próprios.
- Substituir o atendimento humano.
- Refatorar a arquitetura inteira do sistema.
- Credenciais de provedor de IA por clínica (BYOK) — as credenciais são globais da plataforma na v1.
- Cobrança por uso de IA (tokens/custo são registrados apenas para log/auditoria).

## Dependencies

- Módulo omnichannel existente (canais, conversas, mensagens) e seu ponto de entrada de mensagens inbound.
- Mecanismo de resolução de tenant por slug/subdomínio e escopo multi-tenant global.
- Infraestrutura de LGPD existente (detecção/pseudonimização de PII) para o pré-processamento do contexto enviado à IA.
- Sistema de permissões (Spatie por tenant) para as abilities da camada de IA.
- Estrutura de envio de mensagens existente para entregar as respostas geradas pela IA pelo mesmo canal.
- Infraestrutura de indexação vetorial / embeddings (RAG) para a recuperação semântica das bases de conhecimento (FR-021a), escopada por clínica.
- Credenciais globais da plataforma para o(s) provedor(es) de modelos de IA (FR-002a).
