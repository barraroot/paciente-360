# Feature Specification: Conversa Reativa, Multimodal e Auto-Curadoria do Kanban via IA

**Feature Branch**: `018-ai-multimodal-mcp`
**Created**: 2026-05-30
**Status**: Draft
**Input**: User description: "Vamos criar uma melhoria na IA, para sempre que for fazer um dispach de mensagens verificar se não chegou mensagens novas do paciente, se tiver mensagens no espaço de tempo em que a ia ia disparar a mensagem, represa a mensagem não dispara e reprocessa o pensamento para um novo disparo, criando uma conversa humanizada com paciente. Vamos acrescenter a transcrição de audio também para quando chegar mensagem de midia tipo audio, transcrever a mensagem e responder ela, se o paciente mandar um audio dizendo que não sabe ler, precisamos nos comunicar com ele via audio, então precisaremos montar a mensagem e gerar um audio para enviar, isso somente nos canais whatsapp e direct, widget site não entra nesse escopo. Se o paicente não disser para enviar audio, vamos só transcrever entender o ele falou armazenar em texto e enviar texto. Preciso também que em Personas, junto com editar desativar e excluir, um botão testar, que irá abrir um pop de chat e eu irei fazer os testes como se fosse um paciente para ver se a ia esta respondendo correto ou se preciso realizar algum tipo de ajuste nas configurações. Vamos também nessa feature criar um servidor mcp com laravel-mcp que a ia possa consumir para ter dados de pacientes e leads, dados da clinica, profissionais e procedimentos, horarios. E com essas informações teremos os MCP de identificação do lead que irá funcionar da seguinte maneira: 1. Primeiro contato, ver o canal, whatsapp identifica o numero e ja coloca esse numero no kanban como new, instagram pega o instagram do usuario e coloca no kanban como new também quando contato novo, se ja tivermos umas dessas informações pelo canal que ele ja contatou eu ja consigo ter um mcp para puxar o histórico dele. Durante a conversa as informações que ele for passando eu preciso através dos servers MCP abastecer as informaçõpes dele jo kanban como nome observações, se ele agendar um horario ou confirmar um procedimento eu preciso mudar o status dele automatico no kaban, tudo pela conversa entre o lead/paciente e a IA."

## Context: o que esta fase resolve

A Fase 17 entregou *humanização* (histórico + work context + tools de dado vivo via `laravel/ai`). Esta fase ataca os 4 gaps que ainda fazem a conversa parecer "robô atendendo" e o CRM parecer "tabela esquecida":

1. **Atropelamento de mensagens** — hoje, se o paciente manda 3 mensagens em sequência (split-think típico de WhatsApp/Instagram), a IA processa cada uma isoladamente e responde 2-3 vezes em cima do mesmo turno. O resultado é um robô que "fala junto" — exatamente o oposto da humanização. A IA precisa **coalescer o burst**, segurar o disparo enquanto houver atividade do paciente, e *reprocessar* o pensamento com o conjunto completo de mensagens.
2. **Conversa só-texto num canal majoritariamente por voz** — boa parte dos pacientes (idosos, baixa alfabetização, ocupados) manda áudio. Hoje o áudio é ignorado/escapa. A IA precisa **transcrever áudio inbound** e responder; e, quando o paciente sinaliza que prefere voz (ou não consegue ler), **responder em áudio**. Restrito a WhatsApp e Instagram Direct; widget de site fica fora.
3. **CRM cego à conversa** — hoje, mesmo com a Fase 17 sabendo "qualificar" pelo chat, nada disso volta para o pipeline. Lead novo precisa ser **inserido automaticamente no kanban como "new" no 1º contato** (por número WhatsApp ou handle Instagram), o card precisa **engordar com o que o paciente vai dizendo** (nome, observações, urgência, procedimento), e o **status precisa transitar automaticamente** conforme a conversa avança (agendou → "agendado", confirmou → "confirmado", desistiu → "perdido"). Tudo sem dedo humano.
4. **Operador sem ferramenta de validação da Persona** — admins ajustam Persona/Work Context/Knowledge Base "no escuro" e só descobrem se quebrou quando paciente real reclamou. Falta um **chat de teste sandbox** dentro da tela de Personas para o admin conversar com a própria IA como se fosse paciente, antes de publicar mudança.

Adicionalmente, esta fase introduz um **servidor MCP (laravel-mcp)** que expõe as capabilities da IA (dados de pacientes/leads, clínica, profissionais, procedimentos, horários, identificação de lead). **Decisão arquitetural (Q2)**: o MCP **substitui** a stack `laravel/ai` da Fase 17 como caminho único de tools — a IA de produção passa a consumir as capabilities exclusivamente via o servidor MCP local. Isso unifica a implementação (uma única definição de tool), elimina drift entre o que a IA de produção e o chat de teste de Persona usam, e habilita reuso por integrações externas autorizadas (Claude Desktop do operador da clínica, ferramentas auxiliares). É também a decisão de maior risco da fase: exige reescrita das 6 tools da Fase 17 como capabilities MCP, com bateria de testes que prove paridade comportamental antes do cut-over.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - IA coalesce o burst do paciente antes de responder (Priority: P1)

Paciente manda "oi" → "boa noite" → "queria saber sobre consulta" em ~6 segundos. A IA não responde 3 vezes; ela percebe que o paciente ainda está digitando/falando, segura, e quando o burst encerra, produz **uma única resposta** que considera as 3 mensagens em conjunto. **Estratégia (Q1)**: híbrida — um debounce passivo curto (~3-4s) **antes** de começar a pensar (absorvendo bursts comuns sem gastar compute) combinado com **cancel-and-reprocess** durante o processamento (se chegar nova mensagem enquanto a IA pensa, descarta o rascunho e refaz com o conjunto coalescido).

**Why this priority**: É o defeito mais visível e mais "robótico" hoje, mais notório que qualquer ajuste de persona. Resolve sozinho a fração mais embaraçosa das conversas reais. Pré-requisito para qualquer feedback positivo de qualidade.

**Independent Test**: Em uma conversa-teste, enviar 3 mensagens com intervalo de 2-3 segundos entre elas. Confirmar que a IA respondeu uma vez só, que a resposta menciona/considera o conjunto, e que nenhuma das mensagens "ficou para trás" sem entrar no contexto.

**Acceptance Scenarios**:

1. **Given** a IA acabou de iniciar o processamento de uma mensagem do paciente, **When** o paciente envia outra mensagem antes de a IA disparar a resposta, **Then** o disparo é cancelado e o pensamento é refeito incluindo a nova mensagem.
2. **Given** a IA está montando uma resposta para uma sequência de mensagens, **When** o paciente continua enviando mensagens, **Then** o sistema continua adiando o disparo até o burst encerrar (sem novas mensagens dentro da janela configurada).
3. **Given** o burst do paciente excede um limite de tamanho/tempo, **When** o limite é atingido, **Then** a IA processa o que tem e responde, evitando paralisia.
4. **Given** já há uma resposta da IA enviada e o paciente reagiu, **When** novas mensagens chegam, **Then** abre-se um novo ciclo de processamento normalmente (a coalescência é por turno, não para sempre).
5. **Given** uma mensagem inbound chegou enquanto outra está sendo processada, **When** o reprocessamento ocorre, **Then** nada do que o paciente disse é descartado nem duplicado, e a auditoria registra que o turno coalesceu N mensagens.
6. **Given** a IA está em modo pausado/escalado, **When** mensagens chegam em sequência, **Then** a coalescência não muda esse estado nem produz resposta automática.

---

### User Story 2 - Lead entra sozinho no kanban no 1º contato pelo canal (Priority: P1)

Um número desconhecido manda mensagem no WhatsApp da clínica. Sem operador humano agir, o sistema cria/usa o contato pelo número, marca como **lead** e coloca o card no kanban na coluna **"new"**. Em Instagram Direct, o mesmo, identificando pelo handle do usuário. Se o contato já existe (mesmo canal ou outro), o sistema **anexa a conversa ao card existente** em vez de duplicar — e a IA passa a ter o histórico desse paciente no contexto.

**Why this priority**: Sem essa entrada automática, o restante da pipeline (US3, US4 etc.) não tem nada para popular. É o gatilho do funil. Hoje, a entrada no funil depende de ação humana; isso quebra a promessa de "vendedor 24/7".

**Independent Test**: Disparar uma mensagem WhatsApp de um número novo para a clínica e verificar que (a) um card surgiu na coluna "new" do kanban em <30s; (b) o card carrega o número/handle; (c) repetir o teste com um número já cadastrado e confirmar que NÃO foi criado card duplicado.

**Acceptance Scenarios**:

1. **Given** uma mensagem inbound no WhatsApp de um número que não existe na base do tenant, **When** o sistema processa, **Then** um contato é criado com `status='lead'`, o card aparece no kanban na coluna inicial ("new"/equivalente), e o telefone é registrado como o identificador primário.
2. **Given** uma mensagem inbound no Instagram Direct de um handle que não existe na base do tenant, **When** o sistema processa, **Then** um contato é criado pelo handle Instagram, marcado como lead, e o card vai para "new".
3. **Given** o canal de origem é o widget de site, **When** o contato é criado, **Then** ele também entra no funil (kanban) — o widget NÃO está fora deste escopo, apenas o áudio (US4/US5) está.
4. **Given** o número/handle já está vinculado a um lead/card existente do mesmo tenant, **When** uma nova mensagem chega, **Then** nenhum card duplicado é criado; a conversa anexa-se ao card existente; se o card estava em estado terminal (ex: "perdido"), é reativado para a coluna apropriada (default: "new") com registro do motivo de reabertura.
4a. **Given** o contato já existe como **paciente regular (não-lead)** do tenant, **When** uma mensagem inbound chega, **Then** NÃO é criado card no kanban de leads — a conversa anexa-se ao prontuário (Fase 2) e a IA opera com o contexto de paciente já conhecido; o operador pode, manualmente, promover essa conversa para uma nova oportunidade no kanban via ação dedicada do inbox.
5. **Given** o canal informa apenas um identificador anônimo (ex: chat de site sem login), **When** o sistema processa, **Then** o card é criado com um identificador opaco do canal e marcado para coleta de nome/telefone via conversa.
6. **Given** a criação automática falhe (canal sem permissão, tenant suspenso, política bloqueando), **When** o evento ocorre, **Then** o sistema registra falha auditável e a conversa segue sem o card — mas alerta o operador no inbox.
7. **Given** dois números/handles diferentes do mesmo paciente colidem (ex: WhatsApp e Instagram do mesmo nome), **When** isso é detectado posteriormente, **Then** o sistema NÃO funde cards automaticamente; sinaliza para o operador decidir.

---

### User Story 3 - Conversa abastece e move o card do kanban sozinho (Priority: P1)

À medida que o paciente conversa, o card no kanban se enche: o **nome** vira o nome real assim que o paciente diz "sou a Maria"; **observações** acumulam o que importa (cidade preferida, queixa principal, urgência, procedimento de interesse, faixa de preço aceita); e o **status do kanban transiciona automaticamente** conforme o funil avança: deixou de ser "new" → "qualificando", aceitou o valor e pediu horário → "negociando", agendou (tentative hold) → "agendado", confirmou pagamento → "confirmado", parou de responder por X dias → "perdido" (configurável).

**Why this priority**: Esta é a feature que torna o CRM "vivo" e libera o operador humano de digitar tudo. Sem isso, a IA conversa bem mas o pipeline de vendas continua manual. Empate de prioridade com US2 — uma sem a outra entrega meio valor.

**Independent Test**: Rodar uma conversa de qualificação → preço → agendamento e verificar, a cada etapa, que o card do kanban (a) tem nome preenchido após o paciente dizê-lo; (b) tem observações estruturadas com queixa/cidade/procedimento; (c) está na coluna esperada do funil em cada estágio.

**Acceptance Scenarios**:

1. **Given** o paciente menciona o próprio nome durante a conversa, **When** a IA processa, **Then** o nome no card é atualizado e exibido no kanban — sem necessidade de operador clicar.
2. **Given** o paciente declara queixa, cidade, urgência ou procedimento de interesse, **When** a IA processa, **Then** as observações do card incorporam esses fatos de forma estruturada (campos rotulados, não bloco de texto livre) e visíveis no kanban; informações clínicas seguem mascaradas conforme política existente.
3. **Given** o paciente confirma que quer agendar e escolhe um horário, **When** a tentativa de hold é colocada, **Then** o status do card transita automaticamente para "agendado" (ou equivalente configurado).
4. **Given** o pagamento/PIX é confirmado no fluxo existente de reserva, **When** o evento é registrado, **Then** o card transita para "confirmado".
5. **Given** o paciente para de responder por N dias (configurável por tenant), **When** o limite é atingido, **Then** o card transita para "perdido" — com motivo "inatividade".
6. **Given** o operador moveu manualmente o card para um status, **When** uma transição automática conflitante chegaria, **Then** a movimentação manual tem precedência (não é sobrescrita) e a transição automática é registrada em log como "suprimida".
7. **Given** o paciente diz algo que conflita com um fato já populado (ex: novo telefone, mudou cidade), **When** isso é detectado, **Then** o sistema atualiza o campo e mantém histórico do valor anterior (auditável).
8. **Given** a conversa é escalada para humano, **When** isso ocorre, **Then** o status do kanban transita para uma coluna apropriada (ex: "humano") e o motivo da escalação aparece nas observações.

---

### User Story 4 - Paciente manda áudio, IA entende e responde texto (Priority: P2)

Paciente manda áudio de 20s no WhatsApp dizendo a queixa. O sistema **transcreve** o áudio, **armazena o texto da transcrição** como conteúdo da mensagem (com indicação de origem áudio) e a IA responde normalmente em **texto**. O áudio original fica disponível na conversa para o operador ouvir, mas a IA trabalha sobre a transcrição.

**Why this priority**: Já existe demanda real (idosos, motoristas, etc.). Sem isso, o canal mais usado fica meio-mudo. Independente das outras stories — pode ir sozinho.

**Independent Test**: Enviar um áudio em português pelo WhatsApp para a clínica e verificar (a) a transcrição apareceu na conversa em até alguns segundos; (b) a IA respondeu em texto coerentemente com o conteúdo do áudio; (c) o operador consegue acessar o áudio original.

**Acceptance Scenarios**:

1. **Given** uma mensagem inbound do tipo áudio chega via WhatsApp, **When** o sistema processa, **Then** o áudio é transcrito para texto em português e a transcrição é o que entra na conversa para a IA processar.
2. **Given** uma mensagem inbound do tipo áudio chega via Instagram Direct, **When** o sistema processa, **Then** o mesmo fluxo de transcrição se aplica.
3. **Given** a mensagem inbound é áudio mas o canal é widget de site, **When** isso ocorre, **Then** a transcrição NÃO é aplicada (fora de escopo) — comportamento atual mantido (operador trata).
4. **Given** o áudio não pode ser transcrito (silêncio, qualidade muito baixa, idioma não suportado, áudio corrompido), **When** a falha ocorre, **Then** uma indicação visível "áudio não entendido — peça ao paciente para repetir" aparece na conversa e a IA NÃO inventa conteúdo.
5. **Given** um áudio muito longo (> limite configurado), **When** ele chega, **Then** o sistema transcreve até o limite e marca claramente que houve truncamento.
6. **Given** o áudio contém PII sensível (CPF, dados clínicos), **When** transcrito, **Then** a transcrição segue a mesma política de mascaramento/pseudonimização de texto livre já aplicada hoje (Fase 17).
7. **Given** a transcrição foi salva, **When** o operador abre a conversa, **Then** vê o texto transcrito + botão/link para ouvir o áudio original.

---

### User Story 5 - IA responde em áudio quando o paciente precisa (Priority: P2)

Paciente envia áudio dizendo "moça eu não sei ler direito, pode me responder por áudio?". A IA reconhece esse sinal, compõe sua resposta em texto (mesmo fluxo do US1/US3) e **gera um áudio TTS dessa resposta** para enviar ao paciente. A partir daí, enquanto o paciente seguir nesse modo, a IA responde em áudio. Se o paciente voltar a digitar, a IA volta a responder em texto.

**Why this priority**: Acessibilidade real. Sem isso, o público que justifica o US4 ainda fica de fora. Mas depende da infra de TTS e tem custo por mensagem, por isso P2 (não P1).

**Independent Test**: Rodar uma conversa em que o paciente diz explicitamente "responda em áudio" e verificar que (a) a próxima resposta da IA chega como áudio; (b) o conteúdo do áudio bate com o texto da resposta; (c) o operador vê a resposta como texto + áudio na timeline.

**Acceptance Scenarios**:

1. **Given** uma transcrição (ou mensagem texto) do paciente contém um sinal explícito de preferência por áudio ("não sei ler", "me responda em áudio", "manda áudio", "tô dirigindo"), **When** a IA prepara a próxima resposta, **Then** o sistema gera o áudio TTS do texto da resposta e o envia pelo mesmo canal, além de salvar o texto na timeline.
2. **Given** o canal é WhatsApp ou Instagram Direct, **When** a IA precisa enviar áudio, **Then** o envio funciona; **given** o canal é widget de site, **When** o gatilho ocorre, **Then** o áudio NÃO é gerado — a IA segue em texto.
3. **Given** o paciente, depois de ter pedido áudio, volta a digitar texto sem pedir áudio explicitamente, **When** isso é detectado, **Then** a IA volta a responder em texto (preferência reversível por turno; não persiste indefinidamente).
4. **Given** a geração de áudio falha (provedor indisponível, texto muito longo, erro), **When** isso ocorre, **Then** a IA envia a resposta em texto como fallback automático, sem perder a mensagem, e registra a falha para auditoria.
5. **Given** a resposta em texto excede o limite seguro de geração de áudio, **When** isso ocorre, **Then** o áudio é gerado de uma versão **resumida/segmentada** da resposta, com o texto completo enviado em paralelo ou marcado como complemento.
6. **Given** a resposta inclui dados sensíveis (preço, slot proposto), **When** convertida em áudio, **Then** a leitura é clara e os valores numéricos são pronunciáveis (formatação adequada).
7. **Given** o tenant configurou TTS desativado, **When** o gatilho ocorre, **Then** o sistema responde em texto e registra que respondeu em texto por configuração, não por falha.

---

### User Story 6 - Admin testa a Persona antes de publicar (Priority: P2)

Na tela de Personas do painel, além de Editar / Desativar / Excluir, existe um botão **Testar**. Ao clicar, abre um chat dentro do painel onde o admin digita mensagens como se fosse paciente. A IA responde usando exatamente aquela Persona + Work Context + Knowledge Base + tools — mas em ambiente isolado: nada vai para o kanban real, nada cria leads de verdade, nada dispara mensagens para fora.

**Why this priority**: Sem isso, ajuste de persona é tentativa-erro sobre paciente real. Risco operacional alto. Não bloqueia entrega das outras stories, mas é o que dá confiança ao operador para mexer.

**Independent Test**: Abrir a tela de Personas, clicar Testar, conversar 5 turnos e verificar (a) a IA responde com a persona em edição (não a publicada, se forem diferentes); (b) nenhum lead foi criado no kanban; (c) nenhuma mensagem real saiu para canal externo; (d) o teste é descartado/arquivável ao fechar.

**Acceptance Scenarios**:

1. **Given** a tela de listagem de Personas, **When** o admin clica em "Testar" numa persona ativa, **Then** abre um chat sandbox dentro da UI vinculado àquela persona.
2. **Given** o admin está com mudanças não-publicadas na persona em edição, **When** clica Testar a partir do formulário, **Then** o sandbox usa a versão em edição (não a versão publicada) para que o admin possa validar antes de salvar.
3. **Given** o admin envia uma mensagem no sandbox, **When** a IA responde, **Then** o ciclo completo é exercido (histórico + work context + tools), porém: nenhuma `OutboundNotification` real sai por canal, nenhum lead vai para o kanban de produção, nenhuma reserva real é criada (tools de escrita devem operar em sandbox/dry-run).
4. **Given** o admin fecha o modal de teste, **When** isso ocorre, **Then** a conversa de teste é descartada ou marcada como `sandbox` para análise (não polui métricas de produção).
5. **Given** a persona testada faz uso de tools (ex: `get-availability`), **When** o sandbox executa, **Then** o resultado das tools é dados reais (read-only) ou dados mockados/marcados — nunca causa efeito colateral (não cria reserva real, não cria lead real).
6. **Given** o sandbox de teste, **When** o admin abre browser tools/inspeciona, **Then** as mensagens trocadas são claramente marcadas como `sandbox=true` em qualquer payload/log.
7. **Given** o admin sem permissão para gerenciar IA, **When** tenta abrir o teste, **Then** acesso é negado (autorização granular).

---

### User Story 7 - Servidor MCP como caminho único de tools (Priority: P1)

O sistema disponibiliza um **servidor MCP** (Model Context Protocol, via laravel-mcp) que expõe as capabilities de dados da IA: informações da clínica, listagem de profissionais, disponibilidade real, procedimentos/preços, dados do paciente da conversa atual, criação/lookup de lead, e hold tentativo de slot. **Decisão Q2 (substituição)**: a IA de produção passa a consumir tools **exclusivamente** via este servidor MCP — a stack `laravel/ai` da Fase 17 deixa de carregar tools próprias. O mesmo servidor é consumido por (a) IA de produção, (b) chat de teste da US6 (caminho único garante paridade), (c) integrações externas autorizadas (Claude Desktop, ferramentas auxiliares).

**Why this priority**: Sob a decisão Q2, o MCP deixou de ser refinamento e virou **infra-crítica** — sem ele, a IA de produção fica sem tools e regride para o estado pré-Fase-17. Promovido a P1, com requisito explícito de **paridade comportamental verificada** com as 6 tools da Fase 17 antes do cut-over (toda asserção da suíte 148-testes da IA precisa permanecer verde).

**Independent Test**: Rodar a suíte completa de testes da IA (Fase 15 + Fase 17) com o backend de tools comutado para o servidor MCP local e verificar paridade 100%. Em paralelo: configurar um cliente MCP externo, listar capabilities, invocar `get-clinic-info` e `list-professionals` autenticado pelo tenant correto, e verificar (a) tenant resolvido na autenticação não no input; (b) tentar acessar outro tenant retorna negação; (c) capability de escrita (hold) é reversível e auditada.

**Acceptance Scenarios**:

1. **Given** o servidor MCP está rodando, **When** um cliente MCP autenticado lista as capabilities, **Then** vê pelo menos: dados da clínica, profissionais, disponibilidade, procedimentos/preços, paciente da conversa, criar/buscar lead, hold tentativo de slot — todas as descrições visíveis em PT-BR.
2. **Given** uma chamada de capability vem autenticada com credencial de um tenant, **When** retorna dados, **Then** retorna apenas dados desse tenant; nenhuma capability aceita "tenant_id" como input do cliente.
3. **Given** a capability de leitura de paciente, **When** invocada, **Then** só responde para o contato vinculado à credencial/contexto (mesma regra da Fase 17 — nada de busca por nome aberta).
4. **Given** uma capability de escrita (criar lead ou hold), **When** invocada, **Then** registra auditoria igual às tools da Fase 17 e a ação é reversível.
5. **Given** o chat de teste (US6) opera, **When** a IA do sandbox invoca uma tool, **Then** ela passa pelo servidor MCP (caminho único de chamada) e isso valida que o servidor está operacional.
6. **Given** uma capability não existir ou estar desativada por config, **When** invocada, **Then** o servidor responde com erro claro e não vaza superficies internas.
7. **Given** uma credencial MCP foi emitida para um operador externo (futuro), **When** revogada, **Then** as chamadas seguintes falham imediatamente.

---

### Edge Cases

- **Burst infinito** — paciente que envia mensagens sem parar (bot, ataque, paciente nervoso): coalescência por turno atinge limite de tempo OU de mensagens e responde de qualquer forma; sistema NÃO trava indefinidamente. **Adicionalmente**, ao exceder o rate limit por conversa (FR-008a/b), a IA entra em cooldown e alerta o operador — protege custo/operação além do turno atual.
- **Mensagem chega no momento exato do dispatch** — race condition: o dispatch deve detectar (com lock/versão) que há nova mensagem e cancelar; mensagem nunca pode "atravessar" e gerar resposta dupla.
- **Cancel-and-reprocess gera reprocessos infinitos** — limite de N reprocessos por turno; ao atingir, IA responde com o que tem.
- **Áudio sem fala / ruído branco / música** — STT retorna vazio ou ininteligível: marcado claramente, IA pede repetição, não inventa.
- **Áudio em outro idioma** (espanhol, inglês) — sistema detecta e (a) tenta transcrever; (b) se idioma não está no perfil da clínica, alerta operador e responde em PT pedindo confirmação.
- **TTS gera áudio enorme** (resposta longa, lista de horários) — segmentar e/ou resumir; nunca enviar > limite do canal (WA: 16MB).
- **Paciente alterna áudio↔texto várias vezes** — a preferência por áudio é por turno, baseada em sinais; o sistema não trava em modo áudio por causa de um pedido antigo.
- **Card já estava em "perdido" há meses, paciente volta** — reabertura: card volta para "new" com nota "reativado pelo próprio paciente em <data>"; histórico antigo preservado.
- **Operador moveu manualmente para "agendado", IA tenta mover para "confirmado"** — operação manual tem precedência apenas no campo *status* sob conflito direto; transições que **avancem** o funil (ex: confirmação subsequente) seguem normalmente; documentar em audit log.
- **Auto-criação de lead colide com contato já existente em outro tenant** — multi-tenant isolation: cada tenant tem seu próprio card; mesmo número pode ser lead em duas clínicas diferentes (cada uma tem seu pipeline).
- **Sandbox de teste de Persona acidentalmente conectado a tools que escrevem em produção** — separação rígida: tools no modo sandbox são instanciadas com flag `sandbox=true` que neutraliza efeitos colaterais.
- **MCP server sem autenticação** — qualquer chamada não autenticada é recusada; auth obrigatória inclusive para `list capabilities`.
- **Mesmo paciente em WhatsApp e Instagram** — não funde automaticamente; sinaliza para operador no inbox/CRM como "possível duplicação".
- **Áudio do paciente entra durante a coalescência** — entra no burst normalmente; a transcrição completa só faz parte do contexto se completou antes do flush; senão, vira o gatilho do próximo turno.
- **Mudança automática de status quebra automação a jusante** (ex: webhook configurado dispara dupla por status novo) — disparos automáticos respeitam idempotência da Fase 8 (webhooks/eventos).
- **Paciente clinicamente em situação grave** identificado via áudio transcrito — guardrails de escalação imediata (Fase 15) prevalecem; nem auto-status nem multimodal weakeneam isso.
- **Sandbox usado simultaneamente por vários admins** — cada sessão é isolada por usuário; histórico do chat de teste não vaza entre admins.
- **TTS lê preço/horário errado** por formatação numérica — texto a ser convertido em áudio passa por normalização (R$ 300,00 → "trezentos reais", 14h30 → "duas e meia da tarde") antes de ir ao TTS.

## Requirements *(mandatory)*

### Functional Requirements

#### Coalescência de mensagens (US1)

- **FR-001**: O sistema MUST adotar coalescência **híbrida** (decisão Q1) por conversa, com duas fases: (i) **debounce passivo de entrada** — ao receber a 1ª mensagem do turno, aguardar uma janela curta configurável (default ~3-4s) sem nova mensagem antes de iniciar o processamento da IA; novas mensagens dentro desse intervalo reiniciam a janela; (ii) **cancel-and-reprocess** — uma vez iniciado o processamento, se nova mensagem chegar antes do dispatch, o disparo é cancelado, o rascunho é descartado e o pensamento é refeito com o conjunto coalescido.
- **FR-002**: O cancel-and-reprocess (fase ii) MUST garantir que nenhuma mensagem inbound recebida antes do dispatch resulte em resposta duplicada nem em mensagem ignorada — o conjunto coalescido sempre reflete TODAS as mensagens recebidas até o instante do dispatch efetivo.
- **FR-003**: Cada fase da coalescência MUST ter limites configuráveis por tenant: (i) janela de debounce passivo (default ~3-4s, teto ~10s); (ii) teto absoluto do turno entre 1ª mensagem e dispatch (default ~30s); ao atingir qualquer teto, o sistema MUST processar com o que tem e disparar.
- **FR-004**: O número de reprocessamentos por turno MUST ser limitado (ex.: até 3); ao atingir o limite, o sistema MUST processar com o que tem e disparar a resposta.
- **FR-005**: O sistema MUST registrar em auditoria, por turno: quantas mensagens foram coalescidas, quantos reprocessamentos houveram, e o motivo do flush (timeout sem nova msg, limite atingido, dispatch).
- **FR-006**: A coalescência MUST ser por conversa (não global) e MUST respeitar o estado de pausa/escalação existente — em conversa pausada/escalada para humano, nenhuma resposta automática é gerada.
- **FR-007**: A coalescência MUST preservar ordem cronológica das mensagens recebidas; mensagem alguma pode ser perdida nem duplicada no contexto enviado à IA.
- **FR-008**: O sistema MUST garantir, via mecanismo de concorrência (lock/versão/idempotência), que mensagens chegando exatamente no momento do dispatch sejam tratadas com determinismo — sempre uma única resposta para cada turno coalescido.
- **FR-008a**: O sistema MUST aplicar rate limiting em **2 camadas** para proteger contra abuso/bot/paciente descontrolado (decisão Q-clarify-5=C), reusando o `rate_limiter` da Fase 8: (i) **por conversa** — limite configurável de mensagens inbound por janela (default ex.: 30 msg / 10 min); (ii) **por identificador de canal globalmente no tenant** — limite configurável agregado (default ex.: 100 msg / 10 min do mesmo número/handle em todas as conversas do tenant).
- **FR-008b**: Ao exceder qualquer limite do FR-008a, a conversa MUST entrar em **modo cooldown** auditável — a IA para de responder automaticamente, novas mensagens inbound continuam sendo registradas (sem perda) mas não disparam processamento da IA, e o operador é alertado no inbox com o motivo ("rate limit excedido: N msg em M min"). Cooldown tem duração configurável (default ex.: 15 min) ou até intervenção do operador.
- **FR-008c**: Durante o cooldown, NÃO MUST haver geração de TTS, NÃO MUST haver mutação automática do kanban via IA, e NÃO MUST haver invocação de capabilities MCP em nome dessa conversa. A intervenção do operador (encerrar cooldown, banir contato, ou marcar como legítimo) MUST ser auditada.
- **FR-008d**: O rate limit MUST distinguir, no log/alerta, **causa provável**: "abuso provável" (mensagens repetidas idênticas, frequência > humano possível), "paciente em crise" (mensagens diferentes em alta frequência) — a distinção é heurística e serve só para priorização da fila do operador, não para mudança de comportamento automático.

#### Auto-identificação de lead e entrada no kanban (US2)

- **FR-009**: Toda mensagem inbound de canal suportado MUST, antes do processamento conversacional, garantir a existência de um contato no tenant correspondente — criado se inexistente, vinculado se existente.
- **FR-010**: Para WhatsApp, o identificador primário do contato MUST ser o telefone normalizado; para Instagram Direct, o handle Instagram; para widget de site, um identificador opaco do canal.
- **FR-011**: Ao criar contato **novo** via mensagem inbound (sem registro prévio no tenant), o sistema MUST marcá-lo como `status='lead'` e inseri-lo na coluna inicial do kanban do tenant ("new" ou equivalente configurado) em <30s do recebimento. Quando o contato JÁ existe como paciente regular (não-lead), o comportamento muda — ver FR-011a.
- **FR-011a**: Quando o contato inbound corresponde a um **paciente já existente em estado não-lead** (decisão Q-clarify-3=B), o sistema NÃO MUST inseri-lo no kanban de leads. A conversa MUST ser anexada à timeline/prontuário do paciente (Fase 2). O kanban segue **restrito ao funil de conversão** (leads em processo). O operador MUST ter, no inbox, uma ação explícita "promover para nova oportunidade" que cria um card opcional no kanban e é auditada — usada quando a conversa representa intenção comercial nova (ex.: paciente terapêutico solicitando procedimento estético).
- **FR-011b**: A IA MUST distinguir, no contexto de trabalho, se o interlocutor é lead (em conversão) ou paciente já existente (relacionamento ativo) — para que tom, perguntas de qualificação e oferta de procedimentos sejam apropriadas (não se "qualifica" um paciente recorrente como se fosse desconhecido).
- **FR-012**: O sistema MUST NUNCA criar dois contatos para o mesmo identificador no mesmo tenant; chamadas concorrentes ao mesmo identificador MUST resolver para o mesmo registro (idempotência forte).
- **FR-013**: Contatos que **eram leads** e estão em coluna terminal (ex.: "perdido"/"arquivado") que voltam a enviar mensagem MUST ser reabertos para a coluna inicial com nota auditável da reabertura; histórico anterior preservado. (Para paciente que se tornou regular após conversão, aplica-se FR-011a, não esta regra.)
- **FR-014**: O sistema NÃO MUST fundir contatos de canais diferentes automaticamente; quando suspeita de duplicidade (ex.: mesmo nome em WA e IG), MUST sinalizar para revisão humana.
- **FR-015**: Falhas na auto-criação (tenant suspenso, permissão negada, dados inválidos) MUST ser auditadas e o operador alertado; a conversa segue (sem deixar o paciente sem resposta).

#### Abastecimento e movimentação automática do kanban (US3)

- **FR-016**: O sistema MUST permitir que a IA atualize, via tool/capability auditada, os seguintes campos do card a partir do que o paciente informa: nome (1º + último), observações estruturadas (queixa, cidade preferida, urgência, procedimento de interesse, faixa de preço aceita).
- **FR-017**: Observações MUST ser armazenadas em campos rotulados/estruturados (não bloco de texto livre solto) para serem exibidas no kanban; informações com PII clínica seguem mascaramento/política existentes.
- **FR-018**: O sistema MUST atualizar o status do kanban automaticamente conforme eventos do funil: hold colocado (Fase 17) → "agendado"; reserva confirmada/PIX recebido → "confirmado"; inatividade > N dias (configurável por tenant) → "perdido"; escalação para humano → "humano".
- **FR-019**: O conjunto de colunas/estados do kanban e o mapeamento evento→status MUST ser **configurável por tenant** (cada clínica pode ter seu pipeline próprio); o sistema MUST trazer um mapeamento default razoável para tenants novos.
- **FR-020**: Quando o operador moveu manualmente o card para um status, transições automáticas que **regrediriam** o card (ex.: tentar voltar de "confirmado" para "agendado") MUST ser bloqueadas e auditadas como suprimidas; transições que **avançam** o funil seguem.
- **FR-021**: Quando há conflito de valor (ex.: paciente informa cidade diferente da anterior), o sistema MUST atualizar o valor atual e manter histórico do valor anterior consultável.
- **FR-022**: Toda mutação automática no card MUST ser auditável (origem: "IA por tool X", turno da conversa, justificativa).
- **FR-023**: A IA NÃO MUST mover cards para status que impliquem cobrança ou reserva confirmada autonomamente — esses estados refletem eventos do fluxo financeiro/agenda, não decisão livre da IA (consistente com FR-018 da Fase 17).

#### Áudio inbound — transcrição (US4)

- **FR-024**: Quando uma mensagem inbound do tipo áudio chega via WhatsApp ou Instagram Direct, o sistema MUST submetê-la a transcrição automática (STT) em português antes de processamento conversacional.
- **FR-025**: A transcrição MUST entrar como o conteúdo de texto da mensagem na conversa, marcada com origem "transcrito de áudio"; o arquivo de áudio original MUST permanecer acessível ao operador.
- **FR-026**: Para o canal widget de site, áudio inbound MUST seguir o tratamento atual (sem STT automático) — fora do escopo desta fase.
- **FR-027**: Em caso de falha de transcrição (silêncio, ruído, idioma não suportado, timeout, áudio corrompido), o sistema MUST registrar a falha visivelmente na conversa ("áudio não entendido"), NÃO MUST inventar conteúdo, e MUST permitir que o operador acione fallback manual.
- **FR-028**: Áudios com duração acima do limite configurado MUST ser transcritos até o limite com marca explícita de truncamento.
- **FR-029**: Texto transcrito segue exatamente as mesmas regras de pseudonimização/mascaramento da Fase 17 antes de ir para o modelo.
- **FR-030**: Falhas/latências de STT NÃO MUST bloquear o pipeline de coalescência (US1) indefinidamente — há timeout dedicado.

#### Áudio outbound — resposta em voz (US5)

- **FR-031**: Quando, dentro do turno atual, o paciente sinalizou (na mensagem ou na sequência recente) preferência explícita por áudio ("não sei ler", "responde por áudio", "tô dirigindo", etc.), o sistema MUST gerar TTS do texto da resposta da IA e enviar como áudio pelo mesmo canal, **adicional ao texto persistido na timeline**.
- **FR-032**: TTS MUST ser limitado a canais WhatsApp e Instagram Direct; widget de site NÃO recebe áudio gerado.
- **FR-033**: A preferência por áudio MUST ser por turno (baseada em sinais detectados); quando o paciente volta a digitar texto sem sinalizar preferência por áudio, a IA volta a responder em texto.
- **FR-034**: Falha de TTS (provedor indisponível, texto longo demais, erro) MUST resultar em fallback automático para envio em texto, sem perder a mensagem, com registro auditável.
- **FR-035**: Antes de gerar TTS, o texto MUST passar por normalização para fala (preços, horários, abreviações, telefones, datas).
- **FR-036**: Respostas que excedem o limite seguro do canal MUST ser segmentadas ou resumidas para a versão de áudio; o texto completo permanece disponível.
- **FR-037**: O tenant MUST poder desativar TTS globalmente (config); quando desativado, a IA responde sempre em texto e isso é registrado como decisão de configuração, não como falha.
- **FR-037a**: A voz usada no TTS MUST ser configurável como **atributo da Persona** (decisão Q-clarify-4=B) — cada Persona tem um `voice_id` que define gênero/tom/sotaque PT-BR. O tenant MUST ter um `default_voice_id` aplicado quando a Persona não especifica voz. O catálogo de vozes disponíveis (femininas/masculinas, acolhedoras/profissionais, regionais quando suportado) é gerenciado pelo super-admin e exposto às clínicas como lista para escolha (sem expor identificadores técnicos do provedor STT/TTS).
- **FR-037b**: A escolha de voz da Persona MUST ser auditável (admin que mudou, momento, valor anterior) e testável imediatamente pelo chat de teste de Persona (US6) — admin pode ouvir a Persona falando antes de publicar.
- **FR-037c**: Vozes do catálogo MUST estar marcadas com gênero declarado (M/F/neutro) e tom (acolhedor, profissional, energético) para a UI ajudar o admin a escolher coerentemente com a Persona; o sistema NÃO MUST inferir voz automaticamente (admin escolhe explicitamente).

#### Chat de teste da Persona (US6)

- **FR-038**: A tela de gestão de Personas MUST oferecer, junto às ações Editar / Desativar / Excluir, uma ação "Testar" disponível para usuários com permissão de gerenciar IA.
- **FR-039**: A ação "Testar" MUST abrir um chat sandbox dentro do painel, vinculado à persona selecionada (versão publicada por default; versão em edição quando aberto a partir do formulário com mudanças não salvas).
- **FR-040**: O sandbox MUST executar o ciclo conversacional completo (histórico + work context + tools) com a persona escolhida, mas em modo isolado: nenhuma `OutboundNotification` real, nenhum card de kanban real, nenhuma reserva ou cobrança real.
- **FR-041**: Tools de escrita invocadas durante o sandbox MUST ser executadas em modo `sandbox=true` com efeitos neutralizados ou em sandbox dedicado; tools de leitura MUST funcionar normalmente sobre dados reais (read-only) para o teste ser fiel.
- **FR-042**: Conversas de sandbox MUST ser marcadas distintamente em qualquer log/métrica e NÃO MUST poluir métricas de produção (conversion rate, AHT, etc.).
- **FR-043**: Cada sessão de sandbox MUST ser isolada por usuário admin; histórico não vaza entre admins; sessão é descartável ao fechar (ou arquivável para revisão futura, configurável).
- **FR-044**: Acesso ao "Testar" MUST ser controlado por permissão granular (ex.: `ai.persona.test`).

#### Servidor MCP (US7)

- **FR-045**: O sistema MUST expor um servidor MCP (laravel-mcp) com as capabilities equivalentes às 6 tools da Fase 17: informações da clínica (serviços/preços/horários/endereço), listagem de profissionais, disponibilidade real de slots, dados do paciente da conversa atual, criar/buscar lead, hold tentativo de slot — todas com nomes, descrições e schemas de input/output equivalentes às tools atuais.
- **FR-046**: O servidor MCP MUST autenticar todas as chamadas (inclusive `list capabilities`); a credencial MUST carregar o tenant — `tenant_id` NUNCA é input do cliente. Para a IA de produção, a autenticação MUST derivar do contexto da conversa (tenant + conversation + patient), nunca de input do modelo.
- **FR-047**: Capabilities de leitura de paciente MUST resolver apenas o contato vinculado ao contexto/credencial; NÃO MUST suportar busca aberta por nome (consistente com FR-029 da Fase 17).
- **FR-048**: Capabilities de escrita MUST ser limitadas a ações reversíveis (criar/buscar lead, hold tentativo); confirmação de booking e cobrança permanecem fora do MCP.
- **FR-049**: Cada invocação de capability MUST ser auditada (capability, input sanitizado, outcome, tenant, credencial) sob as mesmas regras de retenção da Fase 17 — mantendo a equivalência de auditoria com `ai_tool_invocations`.
- **FR-050**: O servidor MCP MUST aplicar isolamento de tenant no data layer, não apenas via metadata; impossível retornar dados de outro tenant mesmo com input adversarial.
- **FR-051**: Credenciais MCP MUST ser revogáveis com efeito imediato; revogação não exige reinício do servidor.
- **FR-052**: A IA de produção (Fase 17) MUST passar a consumir tools **exclusivamente** via o servidor MCP local quando a feature flag `AI_TOOLS_VIA_MCP` estiver `true` (decisão Q2 — substituição). As tools nativas `laravel/ai` da Fase 17 MUST ser **mantidas no código** após o cut-over como camada de fallback runtime (decisão Q-clarify-1=B) — NÃO devem ser removidas. O chat de teste de Persona (US6) MUST usar o mesmo caminho (MCP) — garantindo paridade total entre produção e sandbox quando o flag está ativo.
- **FR-053**: O cut-over para o MCP MUST ser precedido por **paridade comportamental verificada**: 100% das asserções das suítes existentes da IA (Fase 15 e Fase 17) MUST permanecer verdes com o backend MCP, sob a feature flag `AI_TOOLS_VIA_MCP` que permite rollback rápido em caso de regressão detectada.
- **FR-053a**: A latência adicional introduzida pelo round-trip MCP local MUST ser monitorada como métrica de produção (Prometheus); se exceder o desvio aceitável (ex.: +500ms sobre o baseline da Fase 17 em p95), a feature flag MUST permitir reverter para a stack nativa enquanto se mitiga.
- **FR-053b**: O sistema MUST implementar um **circuit breaker** sobre o servidor MCP em produção: após N falhas consecutivas (default configurável, ex.: 3 falhas em ≤30s) ou taxa de erro acima do limite configurado em janela curta, o circuito MUST abrir automaticamente e a IA MUST passar a usar as tools nativas `laravel/ai` (mantidas pelo FR-052) como fallback runtime — sem perder a resposta em curso, sem escalação automática. O operador MUST ser alertado imediatamente.
- **FR-053c**: O circuito aberto MUST ter um caminho de recuperação automática — após janela de cooldown configurável (default ex.: 60s) o sistema MUST tentar uma requisição-canário ao MCP; sucesso fecha o circuito; falha re-abre e amplia o cooldown (backoff). Toda transição (abrir/fechar) MUST ser auditável e contar como evento Prometheus.
- **FR-053d**: A ativação do circuit breaker (fallback runtime para tools nativas) MUST ser distinguida em auditoria/observabilidade da ativação manual do flag `AI_TOOLS_VIA_MCP=false` (rollback operacional) — são caminhos diferentes com causas diferentes.

#### Auditoria & privacidade

- **FR-054**: Todas as decisões automatizadas (coalescência, criação de lead, mudança de status do kanban, transcrição, geração de áudio, abertura/uso de sandbox, invocações MCP) MUST ser auditáveis sob as regras de retenção/escopo já vigentes; PII clínica permanece protegida pelos guardrails da Fase 15.
- **FR-055**: STT (transcrição) e TTS (síntese) MUST operar sob a base de licitude do consentimento `ConsentFinalidade::Comunicacao` já existente (decisão Q-clarify-2=B) — são meios técnicos para o mesmo propósito comunicacional já consentido no momento que o paciente iniciou o contato pelo canal. NÃO MUST exigir opt-in adicional para a IA poder transcrever o áudio inbound nem para gerar áudio outbound (US5).
- **FR-055a**: A retenção do **áudio bruto inbound** MUST seguir, por default, a mesma política de retenção das demais mídias do canal (Fase 13). Para reter o áudio bruto **além desse prazo default** (auditoria de qualidade, treinamento interno, revisão de incidentes), o tenant MUST coletar consentimento sob uma **finalidade nova `ConsentFinalidade::Transcricao`** (opt-in granular), distinta da finalidade de comunicação. Sem esse consentimento, o áudio bruto MUST ser purgado no prazo padrão e apenas a transcrição (texto) permanece.
- **FR-055b**: A transcrição (texto) MUST seguir as mesmas regras de pseudonimização/mascaramento da Fase 17 antes de ir ao modelo, e a retenção do texto transcrito MUST seguir a política de mensagens de conversa (já existente). A transcrição NÃO MUST ampliar a superfície de PII enviada ao modelo além do que a Fase 17 já envia.
- **FR-055c**: A UI MUST expor a nova finalidade `Transcricao` no fluxo de consentimentos do paciente (mesmo lugar das demais finalidades), com texto claro do propósito ("permitir armazenamento prolongado do áudio para fins de auditoria interna e melhoria do atendimento") — paciente pode revogar a qualquer momento, purgando o áudio bruto retroativamente.
- **FR-056**: Áudios gerados (outbound) MUST ser armazenados/retidos sob a mesma política das mensagens outbound da Fase 13.
- **FR-057**: Toda execução da IA continua respeitando integralmente os guardrails clínicos, intenção/confiança e escalação da Fase 15; multimodal e auto-curadoria do kanban NÃO MUST enfraquecer nenhum gate de segurança existente.

### Key Entities *(include if feature involves data)*

- **Turno coalescido (Conversation Turn)**: Conjunto de mensagens inbound consecutivas do paciente agrupadas em uma única unidade de processamento da IA. Carrega a janela de tempo, número de mensagens, número de reprocessos e motivo de flush. Escopado a uma conversa.
- **Kanban Stage / Pipeline Configuration**: Conjunto de colunas/estados do funil de leads de um tenant + mapeamento evento→status para automação. Por tenant, com defaults sensíveis.
- **Card de Lead/Paciente no Kanban**: Visão de funil do contato existente (reutiliza o `Paciente` da Fase 2, no estado lead); ganha posição no pipeline e timeline de transições.
- **Auto-Curadoria Event**: Registro auditável de cada mutação automática feita pela IA no card (campo alterado, valor antigo, valor novo, turno, tool).
- **Mídia inbound áudio + Transcrição**: Arquivo de áudio original + texto transcrito (com origem marcada + idioma detectado + status truncamento + status falha).
- **Mídia outbound áudio**: Áudio TTS gerado a partir do texto de resposta da IA (com referência ao texto de origem, normalização aplicada, segmentação se houve).
- **Sandbox Session da Persona**: Sessão de teste isolada vinculada a (admin, persona, versão) com mensagens marcadas `sandbox=true`; descartável ou arquivável.
- **Capability MCP**: Definição de uma operação exposta pelo servidor MCP (nome, descrição PT-BR, schema de input/output, leitura/escrita, escopo de auth); reaproveita as tools de domínio existentes (Fase 17) no backend.
- **Credencial MCP**: Token/segredo emitido para um consumidor MCP (chat de teste interno, integração externa) que carrega tenant + escopo; revogável.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Em conversas com burst de 2+ mensagens em ≤10s, a IA produz **1 (e apenas 1)** resposta por turno em **≥99%** dos casos (zero "atropelamento" perceptível em produção).
- **SC-002**: Mensagens inbound de números/handles novos resultam em card no kanban "new" em **<30s** em **≥99%** dos casos; **0** cards duplicados para o mesmo identificador no mesmo tenant em janela de 24h.
- **SC-003**: Em conversas que avançam pelo funil, o status do kanban reflete o estágio real (agendado/confirmado/perdido) sem ação manual em **≥95%** dos casos; o operador interfere manualmente em <5% para corrigir transições.
- **SC-004**: Para mensagens inbound áudio no WhatsApp/IG Direct, transcrição em PT é entregue em mediana **<5s** e p95 **<10s**; taxa de falha de STT em áudio com fala clara fica **<3%**.
- **SC-005**: Quando o paciente sinaliza preferência por áudio, **≥95%** das próximas respostas da IA chegam como áudio TTS; fallback para texto sob falha em **100%** dos casos sem perder a mensagem.
- **SC-006**: Em **100%** das mudanças de persona/work context, o admin consegue testar e iterar via chat sandbox antes de publicar; **0** efeitos colaterais reais (lead/reserva/notificação) detectados em sessões de sandbox.
- **SC-007**: **0** cross-tenant leaks no servidor MCP nos testes de fuzzing/adversarial; **100%** das invocações MCP autenticadas; **0** invocações de capability sem auditoria.
- **SC-008**: Nome do paciente aparece corretamente no card do kanban dentro de **2 turnos** após o paciente declarar o próprio nome em **≥95%** dos casos; observações estruturadas (queixa/cidade/procedimento) populadas em **≥90%** das conversas qualificadas.
- **SC-009**: **0** regressões nas suites de segurança/escalação clínica da Fase 15; **0** regressões funcionais nas asserções da Fase 17 (humanização, tools, contexto) — paridade comportamental verificada sob backend MCP antes do cut-over (FR-053).
- **SC-010**: Latência p95 fim-a-fim da resposta da IA, incluindo coalescência híbrida, round-trip MCP local e (quando aplicável) TTS, fica **≤12s** — o overhead do round-trip MCP não excede **+500ms** sobre o baseline da Fase 17 em p95; coalescência por si só (debounce + cancel-and-reprocess) adiciona até **+4s** percebidos pelo paciente no pior caso.
- **SC-011**: Em **100%** dos turnos, o paciente recebe **exatamente uma** resposta — zero duplicatas e zero perdas — verificado pela contagem `mensagens_inbound_no_turno × respostas_disparadas` na auditoria de coalescência.

## Assumptions

- A Fase 17 (humanização + tools de dado vivo via `laravel/ai`) está estável; esta fase **estende** sem reescrever a stack de IA.
- O Kanban de leads/pacientes existe ou é trivialmente extensível a partir do CRM da Fase 2 — cada tenant tem seu pipeline e colunas (configuráveis). Se ainda não existir tela de Kanban completa, a entrada de dados (status, observações, posições) é via API/store que a UI consumirá; a tela visual em si pode estar em outra spec/fase, mas os dados são alimentados aqui.
- WhatsApp suportado é o canal já integrado (Twilio oficial e Evolution não-oficial, Fase 14) — provedores existentes já recebem áudio inbound e suportam envio de áudio outbound.
- Instagram Direct está incluído nos canais da plataforma — a transcrição/áudio se aplica quando o provedor expõe a mídia.
- Widget de site segue como canal texto-only para esta fase (decisão de produto explícita no input).
- A capacidade de STT/TTS é provida via serviço externo (provedor de IA, AWS, Google ou similar); a escolha do provedor é decisão da fase de planejamento, não impacta este spec.
- Sinais de preferência por áudio são detectados via lista de gatilhos (texto contém certas frases/palavras em PT-BR); refinamento via classificador é evolução futura.
- A coalescência opera em memória durável (cache/redis) por conversa; não exige novas tabelas estruturadas, apenas auditoria via os logs já existentes da Fase 17.
- O servidor MCP é uma camada de fachada sobre os Services já existentes (lógica de domínio das tools da Fase 17 + Kanban services novos desta fase); não duplica regra de negócio. Sob a decisão Q2 (substituição), a stack `laravel/ai` da Fase 17 deixa de ter tools próprias após o cut-over; toda chamada de tool da IA passa pelo servidor MCP local.
- A IA do sandbox (US6) usa o mesmo motor da produção (e portanto o mesmo MCP), com flag `sandbox=true` propagada via metadata da credencial MCP que neutraliza efeitos colaterais em capabilities de escrita.
- Mapeamento default evento→status do kanban: `lead criado → "new"`, `qualification iniciada → "qualificando"`, `valor aceito + horário pedido → "negociando"`, `hold colocado → "agendado"`, `PIX/confirmação registrada → "confirmado"`, `escalado para humano → "humano"`, `inativo X dias → "perdido"`. Tenant pode customizar.
- Limites de coalescência híbrida default (Q1=C): debounce passivo de entrada 3-4s sem nova mensagem (teto 10s); teto absoluto de turno entre 1ª msg e dispatch 30s; máximo 3 reprocessamentos durante a fase ativa.
- Gatilho de áudio outbound (Q3=A): apenas detecção explícita de preferência via lista de frases/palavras em PT-BR ("não sei ler", "manda áudio", "tô dirigindo" e variações). Refinamento por inferência de padrão de uso fica fora do escopo desta fase.
- Latência alvo para STT/TTS fica em janela compatível com a coalescência (US1) — STT pode iniciar em paralelo à fase de debounce passivo (quando há áudio); TTS roda após o texto da IA estar pronto.
- Cut-over do MCP usa feature flag `AI_TOOLS_VIA_MCP` para rollback rápido se regressão de paridade ou latência for detectada.

## Clarifications

### Session 2026-05-30

Resolved 2026-05-30:

- **Q1 — Estratégia de coalescência de mensagens (FR-001/FR-002/FR-003)**: **Híbrida (C)** — debounce passivo de entrada (~3-4s sem nova mensagem) ANTES de iniciar o processamento da IA, combinado com cancel-and-reprocess se nova mensagem chegar durante o pensar. Cobre tanto o burst curto típico (sem custo de reprocesso) quanto o cenário de mensagem chegando enquanto a IA pensa (refaz com conjunto completo). Limites configuráveis por tenant; tetos absolutos garantem que o paciente nunca espere indefinidamente.
- **Q2 — Escopo do servidor MCP (FR-045/FR-052/FR-053)**: **Substituição (B)** — a IA de produção passa a consumir tools **exclusivamente** via o servidor MCP local; a stack `laravel/ai` da Fase 17 deixa de carregar tools próprias após o cut-over. Implicações: (i) reescrita das 6 tools da Fase 17 como capabilities MCP; (ii) verificação obrigatória de paridade comportamental (suítes Fase 15 + 17 verdes sob backend MCP) antes do cut-over; (iii) feature flag `AI_TOOLS_VIA_MCP` para rollback rápido; (iv) overhead de round-trip MCP monitorado e limitado a +500ms p95 sobre o baseline. US7 reclassificada de P3 para **P1** por virar infra-crítica da IA de produção.
- **Q3 — Gatilho para responder em áudio (FR-031/FR-033)**: **Apenas gatilho explícito (A)** — IA responde em áudio somente quando o paciente sinaliza preferência via lista de frases/palavras em PT-BR ("não sei ler", "manda áudio", "tô dirigindo" e variações). Conservador, previsível, baixo custo TTS, sem risco de incomodar paciente que mandou áudio só por conveniência mas leria a resposta. Inferência por padrão de uso fica como evolução futura.

### Risco arquitetural conhecido (decorrente de Q2=B)

A escolha pela **substituição** (em vez da aditiva recomendada) eleva o risco de regressão da Fase 17 e introduz latência adicional pelo round-trip MCP local. Os mitigadores definidos no spec são:

1. **Paridade obrigatória** — FR-053 exige 100% das asserções existentes verdes sob backend MCP antes do cut-over.
2. **Feature flag** — FR-053 cria `AI_TOOLS_VIA_MCP` que permite reverter para a stack nativa em < 1 minuto se regressão detectada em produção.
3. **Métrica de latência** — FR-053a obriga monitoramento do overhead em Prometheus; ultrapassar +500ms p95 dispara alerta.
4. **Cut-over por etapas** — sugestão para o plano (`/speckit-plan`): rodar `AI_TOOLS_VIA_MCP=false` (default) por janela em produção com o MCP já operacional e sendo exercido pelo chat de teste (US6); só ativar `=true` quando o sandbox demonstrar estabilidade.
5. **Circuit breaker runtime (Q-clarify-1=B)** — FR-053b/c/d formalizam fallback automático para tools nativas em caso de falhas pontuais do MCP em produção (não regressão de paridade, mas indisponibilidade transitória). Para isso, as tools nativas `laravel/ai` da Fase 17 são **mantidas no código** após o cut-over como camada de fallback runtime (FR-052 ajustado).

### Session 2026-05-30 (clarify)

- Q: Fallback quando o servidor MCP fica indisponível em produção (decorrente de Q2=B, SPOF da IA)? → A: **B — Circuit breaker com auto-revert para tools nativas `laravel/ai`** após N falhas consecutivas (mantidas no código como fallback runtime, alertando operador). Spec ajustada: FR-052 reescrito (nativas mantidas); FR-053b/c/d adicionados (circuit breaker, cooldown com backoff, distinção auditável vs. rollback manual).
- Q: Consentimento LGPD para captura/processamento de áudio (voz é PII; áudio clínico = dado sensível)? → A: **B — Híbrido**. STT/TTS reusam `ConsentFinalidade::Comunicacao` como base de licitude (meios técnicos do mesmo propósito comunicacional já consentido). Retenção do **áudio bruto além do prazo padrão de mídias** exige consentimento opt-in da nova finalidade `ConsentFinalidade::Transcricao`. Sem esse consentimento, áudio bruto é purgado no prazo padrão; só a transcrição texto permanece. Spec ajustada: FR-055 reescrito + FR-055a/b/c adicionados.
- Q: Paciente já existente (não-lead, com prontuário, fora do funil) que volta a entrar em contato pelo canal — entra no kanban? → A: **B — Não entra no kanban**. A conversa anexa ao prontuário (Fase 2); o operador promove manualmente para nova oportunidade quando aplicável (ação dedicada no inbox). Kanban segue restrito a leads em conversão. A IA distingue lead vs. paciente regular no contexto de trabalho para adequar tom e perguntas. Spec ajustada: FR-011 escopado para *novo* contato + FR-011a/b adicionados; AC4a adicionado à US2.
- Q: Voz/idioma default para TTS — como modelar a identidade auditiva da clínica? → A: **B — Voz como atributo da Persona**. Cada Persona tem `voice_id` configurável; tenant tem `default_voice_id` como fallback; catálogo de vozes curado pelo super-admin com gênero/tom declarados na UI; admin escolhe explicitamente (sem inferência); testável imediatamente via chat de teste US6. Spec ajustada: FR-037a/b/c adicionados.
- Q: Rate limiting de bursts agressivos (abuso/bot/paciente descontrolado, fora do escopo de coalescência por turno)? → A: **C — 2 camadas reusando rate_limiter da Fase 8**: (i) por conversa (default 30 msg / 10 min), (ii) por identificador globalmente no tenant (default 100 msg / 10 min). Excedido, conversa entra em **cooldown auditável** (IA para de responder, mensagens continuam sendo registradas, operador alertado no inbox); intervenção humana ou expiração do cooldown libera. Distinção heurística "abuso provável" vs "paciente em crise" só para priorizar fila do operador. Spec ajustada: FR-008a/b/c/d adicionados; edge case "burst infinito" enriquecido.
