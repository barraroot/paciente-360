# Feature Specification: Fase 3 — Atendimento Omnichannel (Inbox Unificada)

**Feature Branch**: `003-omnichannel-inbox`
**Created**: 2026-05-11
**Status**: Clarified (pronto para `/speckit.plan`) — 17/17 NCs resolvidos em 2026-05-11
**Input**: Inbox unificada com WhatsApp Business Cloud API, Instagram Direct via Graph API e widget de chat web embutível. Atribuição/transferência de conversa, modo "humano assume" (placeholder de IA), respostas rápidas. Sem IA, sem agendamento, sem campanhas — tudo isso vem nas Fases 4–7.

---

## 1. Visão Geral

Esta fase entrega o **canal de comunicação síncrono** do Paciente360. É a primeira fase a tocar **integrações externas** (Meta) e **tempo real** (WebSocket). É também a primeira a expor o produto para o **paciente final** (não apenas para a equipe da clínica).

A fase é **deliberadamente sem IA**: o "Modo Humano Assume" entrega apenas o **mecanismo de pausa/retomada** que a Fase 4 (IA matricial) vai usar. Aqui, qualquer mensagem recebida fica aguardando atendente humano — sem resposta automática.

A inbox **consome eventos da Fase 2** (`PacienteCriado`, `PacienteMesclado`) para manter conversas vinculadas ao paciente correto mesmo após mesclagem. E **publica eventos** de conversa que serão consumidos pelas Fases 4 (IA), 5 (Agenda) e 7 (Campanhas).

**Objetivo de produto**: clínica deixa de operar com WhatsApp web pessoal + DM solto do Instagram + e-mail; centraliza tudo numa caixa única auditável e multi-usuário, com permissões de acesso a dados clínicos respeitadas.

---

## 2. Contratos Herdados das Fases 0–2

### 2.1 Multi-tenancy (herdado da Fase 0)

Toda entidade introduzida nesta fase (`Canal`, `Conversa`, `Mensagem`, `RespostaRapida`, etc.) é **escopada por tenant**. Cross-tenant query é proibida exceto para Super Admin via painel administrativo (sem PII de mensagem, apenas contagens).

### 2.2 Auditoria (herdada da Fase 0)

Eventos sensíveis MUST gravar em `audit_logs` com o schema existente:

- Conexão/desconexão de canal externo.
- Transferência de conversa entre atendentes.
- Modo "humano assume" ativado/desativado.
- Falha de webhook recebida e/ou repetida.
- Mensagem enviada (somente metadados — conteúdo NÃO entra em audit_logs).
- Visualização de mensagem por atendente (sample-based — não toda visualização; só primeira por (atendente, conversa)).

Imutabilidade garantida pelos triggers PG da Fase 0.

### 2.3 Permissões via Spatie (8 novos abilities nesta fase)

| Ability                 | Admin Clínica | Médico                       | Atendente | Recepcionista | Financeiro | Super Admin |
|-------------------------|:-------------:|:----------------------------:|:---------:|:-------------:|:----------:|:-----------:|
| `inbox.view`            | ✅            | ✅ (próprio + designado)     | ✅        | ✅            | ❌         | ❌          |
| `inbox.respond`         | ✅            | ✅ (próprio + designado)     | ✅        | ✅            | ❌         | ❌          |
| `inbox.assign`          | ✅            | ❌                           | ✅        | ✅            | ❌         | ❌          |
| `inbox.transfer`        | ✅            | ❌                           | ✅        | ✅            | ❌         | ❌          |
| `inbox.takeover_ai`     | ✅            | ✅                           | ✅        | ✅            | ❌         | ❌          |
| `channel.connect`       | ✅            | ❌                           | ❌        | ❌            | ❌         | ❌          |
| `channel.disconnect`    | ✅            | ❌                           | ❌        | ❌            | ❌         | ❌          |
| `quick_reply.manage`    | ✅            | ✅ (próprias)                | ✅        | ✅            | ❌         | ❌          |

**"Próprio + designado" para Médico**: o médico vê apenas conversas (a) em que ele é o atendente atribuído OU (b) cujo paciente tem ele como `profissional_responsavel_id` (Fase 2).

Financeiro e Super Admin **não enxergam mensagens** (princípio I LGPD — minimização). Super Admin vê apenas métricas agregadas (contagens por tenant) no painel administrativo.

### 2.4 Pacientes (herdado da Fase 2)

A inbox **consome** os contratos públicos da Fase 2:

- **Deduplicação por telefone/CPF**: quando um paciente novo entra por canal externo, executa o mesmo `DedupService` da Fase 2. Política definida em NC-1.
- **Mesclagem**: quando um paciente é mesclado (`PacienteMesclado` event), todas as conversas do paciente origem **devem migrar** para o paciente alvo. Reversão restaura.
- **Anonimização**: paciente anonimizado (`anonimizado_em != null`) tem **conversas mantidas mas conteúdo de mensagens próprias é apagado**. Política em NC-14.
- **Eventos consumidos**: `PacienteCriado`, `PacienteMesclado`, `PacienteMesclagemRevertida`, `PacienteAnonimizado`.

### 2.5 Idioma e Localização (herdado)

- Toda UI, mensagens de erro e textos do widget são em **pt-BR**.
- Telefone canonicalizado em E.164 (`+55...`) na persistência; exibição BR.
- Datas em `America/Sao_Paulo`.

### 2.6 Restrições técnicas herdadas

- Stack fixada (Laravel 13 + Vue 3 + PG 18 + Redis). **Nenhuma nova lib core sem aprovação**.
- **Nova dependência aprovada (NC-1)**: Twilio Programmable Messaging como provedor WhatsApp Business API. Substitui integração direta com Meta Cloud API. Detalhes de SDK/configuração em `plan.md`.
- WebSocket/broadcast pelo motor já presente (Princípio II — autoriza por tenant).
- Eventos `Auditable` da Fase 0 + listener wildcard → audit automático.

### 2.7 Regra transversal — Vinculação manual de conversa a paciente

Em **qualquer canal** (WhatsApp, Instagram, Web) e em **qualquer momento** da conversa, atendente com `inbox.view` pode vincular manualmente uma conversa a um paciente (existente ou novo). Vinculação manual:

- Atualiza `conversa.paciente_id`.
- Gera evento `ConversaVinculadaAPaciente` (adicionado à seção 6) com payload `{conversa_id, paciente_id, executor_id, modo='manual'}`.
- Grava em `audit_logs` com `action='conversa.vinculada_paciente'`.
- Quando paciente é vinculado a uma conversa anteriormente sem `paciente_id`, sistema **migra mensagens passadas da conversa para a timeline do paciente** (consome contrato de `EventoTimeline` da Fase 2).

---

## 3. User Scenarios & Testing

**Convenções de leitura**:
- Cada AC numerado `AC-4.x.y` (referência cruzada com `tasks.md`).
- 🔴 = crítico (bloqueia release). 🟡 = importante (prioritário, não bloqueante). 🟢 = nice-to-have.
- Cada AC é redigido em formato Given/When/Then quando aplicável.

---

### User Story 1 — Conectar WhatsApp Business (Priority: P1)

> Como Admin Clínica, quero conectar a conta oficial do WhatsApp Business (via Twilio Programmable Messaging) para que mensagens de pacientes cheguem na inbox.

**Por que P1**: sem este canal, o produto não tem valor. WhatsApp é o canal dominante na clínica brasileira.

**Provedor (decidido em NC-1)**: integração com WhatsApp Business API via **Twilio Programmable Messaging**. Meta continua sendo autoridade para aprovação de templates e Quality Rating (Twilio surface esses status).

**Independent Test**: Admin Clínica em `clinica-alfa` completa fluxo de conexão Twilio (Account SID + Sender) com número WhatsApp de teste; status mostra "Ativo"; webhook do Twilio entrega uma mensagem de teste e ela aparece em `/panel/inbox`.

#### Acceptance Scenarios (US-4.1)

- 🔴 **AC-4.1.1 — Fluxo de conexão Twilio**
  **Given** Admin Clínica em `clinica-alfa` com ability `channel.connect`
  **When** acessa "Conectar WhatsApp" e fornece credenciais Twilio (Account SID + Auth Token + escolha de Messaging Service / WhatsApp Sender)
  **Then** o canal fica persistido com `status='ativo'`, `tipo='whatsapp'`, identificadores Twilio (account_sid, messaging_service_sid, whatsapp_sender) criptografados em repouso; e o webhook do Twilio passa a entregar mensagens.

- 🔴 **AC-4.1.2 — Validação de número Business verificado pela Meta (via Twilio)**
  **Given** Admin tenta conectar um WhatsApp Sender que NÃO está vinculado a número Business verificado pela Meta
  **When** Twilio retorna esse status na sincronização inicial
  **Then** a UI rejeita a conexão com mensagem traduzida explicando que o número precisa estar verificado como Business no Meta Business Manager (mesmo via Twilio, a aprovação da Meta é pré-requisito).

- 🔴 **AC-4.1.3 — Webhook do Twilio valida e processa mensagens**
  **Given** canal ativo
  **When** Twilio envia POST `/webhooks/twilio/whatsapp/{tenant}` com payload válido
  **Then** o sistema valida a assinatura HMAC do Twilio (`X-Twilio-Signature`), deduplica por `MessageSid` do Twilio, persiste a mensagem em DB, vincula à conversa (ou cria uma) e emite evento `MensagemRecebida`. Resposta HTTP < 5s com status 200.

- 🔴 **AC-4.1.4 — Idempotência de webhook**
  **Given** Twilio envia o mesmo `MessageSid` duas vezes (retry após timeout)
  **When** webhook chega
  **Then** a segunda chamada NÃO duplica mensagem (verifica registro em tabela de eventos webhook); evento `MensagemRecebida` é emitido apenas na primeira.

- 🟡 **AC-4.1.5 — Status visível da conexão**
  **Given** canal conectado
  **When** Admin acessa `/panel/canais`
  **Then** vê status `ativo` (verde), `inválido` (vermelho, com motivo), `expirado` (amarelo, com CTA "Reconectar") ou `degradado` (Quality Rating Meta caiu).

- 🟡 **AC-4.1.6 — Reenvio manual de validação do webhook**
  **Given** validação inicial do webhook Meta falhou
  **When** Admin clica "Tentar novamente"
  **Then** o sistema refaz o handshake (`hub.challenge`) com a Meta; se OK, status muda para `ativo`.

- 🟡 **AC-4.1.7 — Cadastro de templates aprovados pela Meta (sync via Twilio)**
  **Given** canal conectado
  **When** Admin acessa "Templates" do canal
  **Then** vê lista de Content Templates do Twilio (que são templates Meta aprovados, sincronizados pela Content API do Twilio) com status `approved`, `pending`, `rejected`. Templates aprovados ficam disponíveis para uso em respostas rápidas (NC-4) e para Fase 7 (campanhas).

- 🟢 **AC-4.1.8 — Notificação ao admin quando Quality Rating cai**
  **Given** Twilio reporta queda de Quality Rating do número Meta (`Low` ou `Flagged`) via webhook de status
  **When** webhook é recebido
  **Then** sistema notifica Admin Clínica via in-app + e-mail; comportamento de bloqueio de envios em NC-17.

- 🔴 **AC-4.1.9 — Auditoria de conexão**
  **Given** qualquer conexão/desconexão de canal
  **When** se consulta `audit_logs`
  **Then** existe entrada `channel.connected` ou `channel.disconnected` com payload `{tipo, waba_id (mascarado em logs), executor_id}`.

- 🟡 **AC-4.1.10 — Desconexão limpa**
  **Given** Admin acessa canal e clica "Desconectar"
  **When** confirma
  **Then** canal vai para `status='desconectado'`, mensagens existentes permanecem mas webhook não processa novas, evento `CanalDesconectado` é emitido.

**Dependências**: Fase 0 (auth, audit, rate limit), **conta Twilio** com Messaging API habilitada para WhatsApp + número Business verificado na Meta (pré-requisito Twilio para WhatsApp Sender).

**Riscos**:
- Mudanças no SDK/API do Twilio podem quebrar integração; mitigação: usar version pinned + monitorar releases Twilio.
- Quality Rating Meta queda (via Twilio surface) → suspensão; mitigação em NC-17.
- Twilio adiciona camada de custo extra ($0.005/segmento + custo Meta). Documentar para Admin Clínica no painel.

**Pontos de ambiguidade**: NC-1, NC-4, NC-15, NC-17.

---

### User Story 2 — Conectar Instagram Direct (Priority: P2)

> Como Admin Clínica, quero conectar Instagram Direct via Graph API para que DMs cheguem à inbox unificada.

**Por que P2**: Instagram é canal complementar relevante para clínicas estéticas/odontológicas que captam por mídia social, mas não bloqueia produto. WhatsApp (US-4.1) sozinho já entrega valor.

**Independent Test**: Admin em `clinica-alfa` completa Login Facebook + escolha de página + Instagram profissional vinculado; envia DM da própria conta Instagram para a clínica; mensagem aparece em `/panel/inbox` com canal "Instagram".

#### Acceptance Scenarios (US-4.2)

- 🔴 **AC-4.2.1 — Login OAuth Facebook + Instagram Business**
  **Given** Admin com ability `channel.connect`
  **When** completa Facebook Login com escopos `instagram_basic, instagram_manage_messages, pages_messaging, pages_show_list`
  **Then** sistema valida que existe conta Instagram **Profissional** vinculada à Página; canal persiste `tipo='instagram'`, `status='ativo'`, `instagram_business_account_id`.

- 🔴 **AC-4.2.2 — Conta pessoal Instagram é rejeitada**
  **Given** conta Instagram do tipo Pessoal
  **When** Admin tenta conectar
  **Then** rejeita com mensagem clara explicando que precisa converter para Profissional/Creator.

- 🔴 **AC-4.2.3 — Webhook Graph API entrega DM em tempo real**
  **Given** canal Instagram ativo
  **When** paciente envia DM
  **Then** webhook `messages` chega, assinatura validada, mensagem persistida com `igsid` do remetente, evento `MensagemRecebida` emitido em < 5s.

- 🔴 **AC-4.2.4 — Idempotência de webhook Instagram**
  Mesmo padrão de `AC-4.1.4`, dedup por `message_id` Graph.

- 🟡 **AC-4.2.5 — Janela de 24h documentada no painel**
  **Given** canal Instagram ativo
  **When** Admin acessa documentação do canal
  **Then** texto explica claramente: "Instagram permite responder apenas dentro de 24h da última mensagem do paciente. Fora disso, apenas template `MESSAGE_TAG` aprovado é permitido."

- 🟡 **AC-4.2.6 — Status do canal idem WhatsApp**
  AC-4.1.5 aplica.

- 🟢 **AC-4.2.7 — Suporte a comentários em post**
  Fora de escopo nesta fase (vide NC-16). Apenas DM.

**Dependências**: AC-4.1.x parcial (mesma infra de canal/conexão); conta Instagram Business para teste.

**Riscos**: Graph API tem mais quebras de breaking changes que WhatsApp Cloud API; mitigação: monitorar `webhook_failures`.

**Pontos de ambiguidade**: NC-1 (IGSID vs telefone), NC-16 (multi-conta + comentários).

---

### User Story 3 — Widget de Chat Web Embutível (Priority: P2)

> Como Admin Clínica, quero gerar um snippet JS para o site da clínica para que visitantes do site iniciem conversa.

**Por que P2**: capta lead que chegou pelo site (anúncio, SEO) e o leva para a inbox unificada. Importante para clínicas com captação digital, não bloqueia o MVP.

**Independent Test**: Admin gera snippet, cola em página HTML estática local, abre a página → vê widget; envia mensagem como visitante; mensagem aparece em `/panel/inbox` com canal "Web".

#### Acceptance Scenarios (US-4.3)

- 🔴 **AC-4.3.1 — Gerar snippet personalizável**
  **Given** Admin acessa "Widget Web" em canais
  **When** configura cores, logo, mensagem inicial, horário de funcionamento, `outside_hours_behavior`, `allowed_origins`
  **Then** sistema gera snippet `<script async src="https://widget.crm.com.br/v1/{tenant_public_key}.js">` único por tenant; preview funciona em iframe sandbox.

- 🔴 **AC-4.3.2 — Visitante anônimo inicia conversa**
  **Given** snippet carregado em página externa
  **When** visitante abre o widget e clica "Iniciar conversa"
  **Then** widget cria sessão temporária (cookie/localStorage); mensagem entra na inbox com `tipo='web'`, `paciente_id=null` inicialmente.

- 🔴 **AC-4.3.3 — Coleta opcional de nome/telefone antes do chat**
  **Given** Admin configurou "exigir nome+telefone"
  **When** visitante tenta abrir o chat
  **Then** form pré-chat aparece exigindo os campos; só após preenchimento o chat abre. Visitante vira **lead** (paciente em status `lead` criado conforme NC-10).

- 🔴 **AC-4.3.4 — Mensagens entram na inbox unificada**
  **Given** conversa via widget existe
  **When** atendente abre `/panel/inbox`
  **Then** conversa aparece com badge "Web", última mensagem visível, contador de não lidas.

- 🟡 **AC-4.3.5 — Comportamento fora do horário (3 modos configuráveis)**
  **Given** Admin configurou horário `Seg-Sex 08:00-18:00 BRT` + `outside_hours_behavior`
  **When** visitante acessa o widget às 22:00
  **Then**:
    - `bloqueia`: exibe "Estamos fechados. Horário: ..."; envio desabilitado.
    - `fila` (default): aceita mensagem com banner "Responderemos quando abrirmos"; entra na inbox marcada `recebida_fora_horario=true`.
    - `normal`: aceita como horário comercial.

- 🟡 **AC-4.3.6 — Autenticação por chave + whitelist de domínio**
  **Given** snippet de tenant A com `tenant_public_key` + whitelist `[clinica-alfa.com.br]`
  **When** widget tenta carregar de outro domínio (ex.: site fake)
  **Then** backend valida `Origin` header contra whitelist; rejeita silenciosamente (não revela chave); registra tentativa para audit do admin. Whitelist vazia (default) aceita qualquer origem com aviso UX para configurar.

- 🟡 **AC-4.3.7 — Resposta do atendente chega em tempo real ao widget**
  **Given** atendente responde no painel
  **When** widget está aberto
  **Then** mensagem aparece no widget < 2s (RNF-001).

- 🟢 **AC-4.3.8 — Upload de mídia pelo visitante**
  **Given** widget aberto
  **When** visitante anexa imagem
  **Then** mídia chega na inbox; limites em NC-9.

**Dependências**: US-4.4 (inbox precisa estar pronta para mostrar conversas Web), config CORS/CSP, infra de WebSocket.

**Riscos**:
- Carregamento do JS atrasa página do cliente — mitigar com async + tamanho mínimo.
- CSP/CSRF do site host bloqueia widget — testar em 3 sites diferentes.

**Pontos de ambiguidade**: NC-10 (hospedagem, auth, horário, integração).

---

### User Story 4 — Caixa de Entrada Unificada (Priority: P1)

> Como Atendente, quero ver todas as conversas de todos os canais em uma única tela para que eu não troque de ferramenta.

**Por que P1**: o produto **é** a inbox. Sem ela, os canais externos não geram valor.

**Independent Test**: Atendente loga em `clinica-alfa` e abre `/panel/inbox`; vê conversas dos 3 canais em uma lista unificada; abre uma conversa, vê histórico, responde e mensagem é entregue ao paciente; outro atendente em outra aba vê a atualização em tempo real (< 2s).

#### Acceptance Scenarios (US-4.4)

- 🔴 **AC-4.4.1 — Lista unificada com todos os canais**
  **Given** Atendente loga e abre inbox
  **When** carrega
  **Then** vê lista de conversas com: avatar do paciente, **badge de canal** (WhatsApp/Instagram/Web), nome (ou identificador anônimo), último trecho da mensagem, hora, contador de não lidas, prioridade (placeholder agora — preenchido pela IA na Fase 4).

- 🔴 **AC-4.4.2 — Atualização em tempo real**
  **Given** dois atendentes do mesmo tenant com a inbox aberta
  **When** uma nova mensagem chega no tenant
  **Then** ambos veem a atualização em **menos de 2 segundos** (RNF-001). Sem refresh manual.

- 🔴 **AC-4.4.3 — Abrir conversa carrega histórico**
  **Given** conversa selecionada
  **When** atendente clica
  **Then** painel direito mostra todas as mensagens em ordem cronológica, agrupadas por dia, com sender (paciente vs equipe), status de leitura, indicador de mídia.

- 🔴 **AC-4.4.4 — Responder mensagem com texto**
  **Given** conversa aberta + ability `inbox.respond` + (WhatsApp) janela 24h aberta
  **When** atendente envia texto
  **Then** mensagem é despachada ao canal correto, persistida com `status='enviada'`, evento `MensagemEnviada` emitido, ID da Meta retorna `accepted`, `delivered`, `read` atualizam status na UI.

- 🔴 **AC-4.4.5 — Envio bloqueado fora da janela 24h sem template**
  **Given** WhatsApp, última mensagem do paciente > 24h (badge cinza com cadeado)
  **When** atendente tenta enviar texto livre
  **Then** campo está **desabilitado** com tooltip explicativo; seletor de templates aprovados aparece logo abaixo. Backend rejeita 422 com `error='janela_24h_fechada'` mesmo se UI falhar. Tentativa bloqueada gera evento `mensagem.bloqueada_fora_janela` em audit_logs.

- 🔴 **AC-4.4.6 — Indicador de digitação (typing)**
  **Given** atendente está digitando no campo
  **When** > 1s sem mudança
  **Then** evento de typing chega ao paciente (quando canal suporta — WhatsApp/Instagram sim, Web sim) e ao outro atendente. Backoff: para se atendente para de digitar > 3s.

- 🔴 **AC-4.4.7 — Status de leitura**
  **Given** paciente recebe mensagem
  **When** lê (read receipt)
  **Then** UI atualiza ícone para "lida" (✓✓ azul).

- 🟡 **AC-4.4.8 — Filtros da inbox (7 dimensões)**
  **Given** lista com 100+ conversas
  **When** atendente aplica filtros (canal, status, atendente, profissional vinculado, tag do paciente, presença de mídia, idade)
  **Then** lista filtrada renderiza com query string compartilhável (ex.: `?canal=whatsapp&status=aberta&tag=vip`); combinação é AND entre dimensões e OR dentro de cada multi-select.

- 🟡 **AC-4.4.9 — Busca full-text por similaridade (nome/telefone/conteúdo)**
  **Given** lista com 10k conversas
  **When** atendente digita no campo busca
  **Then** debounce 350ms, query híbrida com `pg_trgm` busca em nome do paciente + telefone + conteúdo das mensagens; p95 < 500ms para 50k conversas/tenant; resultado mostra trecho com match destacado.

- 🟡 **AC-4.4.10 — Indicador de presença online dos atendentes**
  **Given** múltiplos atendentes logados
  **When** abrir inbox
  **Then** ver quem está online (status manual ou inferido — vide NC-6).

- 🟢 **AC-4.4.11 — Visualização não duplicada de notificação**
  **Given** atendente já viu uma conversa em outra aba
  **When** abre outra aba
  **Then** badge "não lida" sincronizado entre abas (mesmo usuário).

- 🔴 **AC-4.4.12 — Isolamento entre tenants em WebSocket**
  **Given** dois tenants com inboxes ativas
  **When** atendente de tenant A tenta se inscrever em `tenant.{B}.inbox` ou `tenant.{B}.conversa.{cid}` via payload manipulado
  **Then** servidor valida `Authorization` na inscrição; rejeita com erro de canal; atendente **NUNCA** recebe eventos de B. Teste cobre 100% dos canais (sala tenant + salas de conversa).

**Dependências**: US-4.1, US-4.2, US-4.3 (canais entregam mensagens), infra de tempo real, eventos do listener wildcard da Fase 0.

**Riscos**: latência WebSocket sob carga (RNF-003: 1000 conversas simultâneas/tenant). Mitigar com room por tenant + lazy loading da lista.

**Pontos de ambiguidade restantes**: NC-11 (WebSocket strategy), NC-12 (notificações), NC-13 (filtros e busca). *(NC-2 e NC-3 resolvidos em 2026-05-11.)*

---

### User Story 5 — Atribuir e Transferir Conversa (Priority: P1)

> Como Atendente ou Admin Clínica, quero atribuir/transferir conversa a outro atendente para que o caso vá para a pessoa certa.

**Por que P1**: sem atribuição, equipe se atropela respondendo a mesma conversa. Sem transferência, atendentes ficam presos a casos fora do seu domínio.

**Independent Test**: Atendente A está atendendo conversa; transfere para Médico M com nota "Paciente quer agendar exame, encaminhando"; Médico M recebe notificação e vê a conversa em sua fila com a nota interna preservada.

#### Acceptance Scenarios (US-4.5)

- 🔴 **AC-4.5.1 — Atribuir conversa manualmente**
  **Given** conversa sem atendente (na fila "Sem atendente")
  **When** Atendente com `inbox.assign` clica "Pegar conversa" ou seleciona outro usuário
  **Then** conversa fica atribuída; evento `ConversaAtribuida` emitido; notificação ao destinatário.

- 🔴 **AC-4.5.2 — Atribuição automática por regra (NC-6 resolvido)**
  **Given** Admin configurou `auto_assign_strategy` ∈ `{round_robin, profissional_vinculado}`
  **When** nova conversa entra
  **Then** sistema aplica a regra escolhida:
    - `round_robin`: escolhe próximo atendente "online" (≤5min de atividade) com < 15 conversas abertas (limites configuráveis).
    - `profissional_vinculado`: se paciente identificado tem `profissional_responsavel_id` e profissional está online com vaga → atribui a ele; senão fallback round-robin.
  Conversa atribuída dispara `ConversaAtribuida` com `modo` apropriado. Se nenhum atendente elegível, vai para fila "Sem atendente".

- 🔴 **AC-4.5.3 — Transferir com nota obrigatória (mín. 10 chars)**
  **Given** conversa atribuída a A
  **When** A clica "Transferir" e seleciona destinatário (usuário OU role) + escreve nota interna ≥ 10 caracteres
  **Then** conversa atribuída ao destinatário (usuário direto OU via auto-atribuição se role); nota registrada como evento `transferencia.realizada` no histórico (visível só para equipe); evento `ConversaTransferida` emitido com payload `{de_user_id, para_user_id|para_role, nota_sanitizada}`.

- 🔴 **AC-4.5.4 — Histórico de atribuições preservado**
  **Given** conversa transferida várias vezes
  **When** atendente atual abre o histórico
  **Then** vê linha do tempo: A → B (nota: "..."), B → C (nota: "..."), com timestamps.

- 🟡 **AC-4.5.5 — Notificação ao novo responsável**
  AC-4.5.1 e AC-4.5.3 disparam notificação em-app + canal configurado (NC-12).

- 🔴 **AC-4.5.6 — Cross-tenant proibido (não-negociável)**
  **Given** atendente A em tenant `clinica-alfa` tenta transferir para usuário de `clinica-beta` via payload manipulado
  **When** backend recebe a request
  **Then** Policy valida que destinatário pertence ao mesmo tenant; retorna 403; gera evento `audit_logs` com tentativa registrada (Princípio II não-negociável).

- 🟡 **AC-4.5.7 — Transferir para role em vez de usuário**
  **Given** atendente seleciona "qualquer Médico online"
  **When** confirma transferência
  **Then** sistema aplica auto-atribuição (NC-6) restrita a usuários com role `medico` online; se ninguém disponível, conversa vai para fila "Sem atendente" com `motivo='transferencia_role:medico'`. Roles selecionáveis: `medico`, `atendente`, `recepcionista`, `admin-clinica` (financeiro excluído por falta de `inbox.view`).

- 🟡 **AC-4.5.8 — Limite de conversas por atendente (NC-6)**
  **Given** atendente com `auto_assign_max_per_user` (default 15) conversas com status `aberta` ou `pendente`
  **When** auto-atribuição vai escolhê-lo
  **Then** sistema pula para próximo atendente elegível; nunca força atribuição acima do limite. Atribuição **manual** por outro usuário continua possível mesmo acima do limite (decisão consciente).

- 🟢 **AC-4.5.9 — Reassignar quando atendente fica offline > X minutos**
  **Given** atendente com conversas atribuídas fica offline
  **When** X minutos passam
  **Then** sistema oferece reassign ou move para fila. NC-6.

**Dependências**: US-4.4 (inbox); permissões; user online status (estado simples — sessão ativa).

**Riscos**: thundering herd quando muitos atendentes pegam mesma conversa simultaneamente → lock pessimista na atribuição.

**Pontos de ambiguidade**: NC-6 (auto-assign), NC-7 (transferência), NC-12 (notificações).

---

### User Story 6 — Modo "Humano Assume" (Priority: P1)

> Como Atendente, quero pausar a IA quando entro em uma conversa para que o paciente não receba mensagens automáticas concorrentes.

**Por que P1**: contrato com Fase 4. **Sem este mecanismo agora**, quando a Fase 4 entrar, terá que retrofit no histórico → bug-magnet. Esta fase entrega o **estado** (`ia_pausada_ate`) e os **hooks de UI/eventos**, mesmo que a IA real não exista ainda.

**Independent Test**: Atendente abre conversa e clica "Assumir"; campo `ia_pausada_ate` é setado para `now() + duração padrão`; evento `ConversaAssumidaPorHumano` emitido. Quando o timer expira, evento `ConversaRetomadaPelaIA` emitido. Como não há IA real, nenhuma mensagem automática é enviada — mas o ciclo de evento existe para a Fase 4 consumir.

#### Acceptance Scenarios (US-4.6)

- 🔴 **AC-4.6.1 — Botão "Assumir" pausa a IA**
  **Given** conversa onde `ia_pausada_ate IS NULL` ou expirada
  **When** Atendente com `inbox.takeover_ai` clica "Assumir"
  **Then** `ia_pausada_ate = now() + ia_pausa_duracao_minutos` (default 30, configurável 5–240 por tenant); evento `ConversaAssumidaPorHumano` emitido com `motivo='manual_click'`; UI mostra badge "IA pausada por X min" com contador.

- 🔴 **AC-4.6.2 — Mensagem manual pausa IA implicitamente (sem clique)**
  **Given** conversa com IA ativa + atendente envia mensagem direto (sem clicar "Assumir" antes)
  **When** mensagem persiste
  **Then** `ia_pausada_ate = now() + ia_pausa_duracao_minutos` é setado automaticamente; evento `ConversaAssumidaPorHumano` com `motivo='mensagem_enviada'`. Não exige interação extra do atendente.

- 🔴 **AC-4.6.2b — Reprise da pausa**
  **Given** IA já pausada, contador rodando
  **When** atendente envia mensagem nova ou clica "Assumir" novamente
  **Then** `ia_pausada_ate` é **estendido** para `now() + duração_padrão` (reinicia o timer); não acumula.

- 🔴 **AC-4.6.3 — Retomada automática após timeout**
  **Given** `ia_pausada_ate < now()`
  **When** o periódico/listener detecta expiração
  **Then** estado vira "IA ativa"; evento `ConversaRetomadaPelaIA` emitido. (Como IA não existe ainda, evento fica sem subscriber até Fase 4.)

- 🔴 **AC-4.6.4 — Retomada manual**
  **Given** conversa com IA pausada
  **When** atendente clica "Liberar IA"
  **Then** `ia_pausada_ate = null`; evento `ConversaRetomadaPelaIA` emitido imediatamente.

- 🟡 **AC-4.6.5 — Indicador visual de "IA pausada"**
  **Given** conversa com IA pausada
  **When** atendente vê a conversa
  **Then** badge claro + tempo restante; estilo distinto de conversa normal.

- 🟡 **AC-4.6.6 — Auditoria do takeover**
  **Given** assume/libera
  **When** consulta audit_logs
  **Then** entradas `conversa.assumida_por_humano` e `conversa.retomada_pela_ia` com `executor_id, motivo (auto/manual/timeout), conversa_id`.

- 🟡 **AC-4.6.7 — Configuração da duração padrão pelo Admin**
  **Given** Admin Clínica acessa "Configurações da Inbox"
  **When** edita `ia_pausa_duracao_minutos` (range 5–240, default 30)
  **Then** novo valor passa a valer para próximas pausas no tenant.

- 🟢 **AC-4.6.8 — Sem resposta automática nesta fase**
  **Given** conversa com IA ativa (não pausada) e mensagem nova chega
  **When** sistema processa
  **Then** **nenhuma resposta automática é gerada** — só persiste mensagem e atualiza inbox. Resposta automática vem na Fase 4 (IA matricial) consumindo o evento `MensagemRecebida`.

**Dependências**: US-4.4 (conversa existe); evento `Auditable`.

**Riscos**: contrato instável com Fase 4 — se a forma como a Fase 4 consome `ia_pausada_ate` mudar, retrofit. Mitigar especificando contrato como interface explícita (`ConversaIATogglingContract`).

**Pontos de ambiguidade**: NC-5 (gatilhos, retomada, stub).

---

### User Story 7 — Respostas Rápidas (Templates) (Priority: P2)

> Como Atendente, quero usar templates pré-cadastrados para que eu responda mais rapidamente.

**Por que P2**: acelera operação, mas atendente consegue trabalhar digitando manualmente sem isso. Pode ser entregue mesmo após primeiro release.

**Independent Test**: Atendente cadastra resposta rápida `/preço` com texto "Olá {nome_paciente}, valores na nossa tabela:..."; digita `/preço` na conversa; texto auto-completa com variáveis substituídas.

#### Acceptance Scenarios (US-4.7)

- 🔴 **AC-4.7.1 — CRUD de respostas rápidas com escopo dual**
  **Given** usuário com `quick_reply.manage`
  **When** acessa "Respostas Rápidas"
  **Then** vê duas seções: **"Da equipe"** (tenant — todos editam se têm a ability; todos veem) e **"Minhas"** (privadas, só o autor edita/vê). Cria, edita, exclui. Tentativa de criar atalho duplicado no mesmo escopo retorna 409.

- 🔴 **AC-4.7.2 — Atalho dispara autocomplete; privada override tenant**
  **Given** lista de respostas rápidas com `/preço` em tenant E privada
  **When** atendente digita `/` no campo de mensagem
  **Then** dropdown mostra atalhos disponíveis com indicador (🔒 privada vs 🏢 equipe); ao digitar `/preço` a privada do usuário **vence** sobre a do tenant (override).

- 🔴 **AC-4.7.3 — 6 variáveis substituídas no envio**
  **Given** template `"Olá {primeiro_nome_paciente}, sou {nome_atendente} da {nome_clinica}."` selecionado em conversa de "Maria Silva" por atendente "João" no tenant "Clínica Alfa"
  **When** atendente seleciona
  **Then** texto renderizado: `"Olá Maria, sou João da Clínica Alfa."`. Variáveis nulas (ex.: `{data_proxima_consulta}` sem Fase 5) renderizam como string vazia. Atendente vê preview antes de enviar.

- 🟡 **AC-4.7.4 — Templates Meta separados em UI**
  **Given** janela 24h aberta
  **When** atendente abre seletor de respostas
  **Then** vê **2 abas visivelmente separadas**: "Respostas rápidas" (texto livre) e "Templates aprovados" (Meta via Twilio). Não há mistura. Templates Meta também suportam as 6 variáveis quando configurados com parameters.

- 🟢 **AC-4.7.5 — Mídia em respostas rápidas fora de escopo MVP**
  Não implementado nesta fase. Anexo de mídia continua possível como envio individual durante conversa.

- 🟢 **AC-4.7.6 — Estatísticas de uso de templates**
  Quais templates são mais usados (relatório).

**Dependências**: US-4.4.

**Pontos de ambiguidade**: NC-8.

---

### Edge Cases (transversais)

- **Webhook Meta atrasado**: mensagem que chega 30 min depois (ex.: Meta com problema) — preserva timestamp original do paciente, não `received_at` do servidor.
- **Paciente bloqueado** (status `bloqueado` da Fase 2): mensagens recebidas **continuam** chegando à inbox para visibilidade, mas envio automático (Fase 4+) é bloqueado. Atendente humano pode responder manualmente.
- **Atendente é desativado** mid-conversa: conversas dele vão para fila "Sem atendente" + tarefa para Admin reatribuir (mesmo padrão da Fase 2 com `TarefaReatribuicao`).
- **Tenant suspenso**: webhooks param de processar; canais ficam `status='suspenso'`; quando reativado, retorna normalmente. Mensagens recebidas durante suspensão **podem ser perdidas** — documentar para o cliente.
- **Mensagem com 4000+ chars no WhatsApp**: WhatsApp aceita até 4096; respeitar limite. Maior que isso: rejeitar UI ou splitar — escolha em assumption.
- **Mídia maior que limite Meta**: rejeitar antes de chamar API (validação client + server).
- **Reabertura de conversa antiga** (paciente volta a mandar mensagem após 60 dias): mesma `conversa_id` é reaberta automaticamente (NC-2 resolvido — modelo stream contínuo). Status `resolvida → aberta`, dispara `ConversaReaberta` com `motivo='nova_msg'`. **Atenção janela 24h WhatsApp**: se última mensagem do paciente foi > 24h, atendente só pode responder com template aprovado (NFR-4 / Princípio VI).
- **Paciente mesclado** durante conversa ativa: conversa migra para alvo automaticamente; atendente vê notificação.
- **Mensagem do paciente contém PII de terceiro** (foto de receita com CPF de filho): nenhuma sanitização automática (impossível). UX alerta atendente quando primeiro arquivo é aberto.
- **Atendente entra na conversa e IA estava no meio de resposta**: mesmo problema que se IA já existisse — comportamento na Fase 4. Esta fase só entrega o gate de pausa.

---

## 4. Requirements *(mandatory)*

### 4.1 Functional Requirements

#### Canais e conexão (Princípio II + VI + VII)

- **FR-001**: Sistema MUST permitir conectar conta WhatsApp Business via **Twilio Programmable Messaging** (decidido em NC-1): Account SID + Auth Token + escolha de Messaging Service / WhatsApp Sender. O sender precisa estar pré-aprovado pela Meta como Business verificado.
- **FR-002**: Sistema MUST permitir conectar Instagram Direct via Facebook Login (Graph API) com escopos messaging adequados; rejeita conta não-Profissional.
- **FR-003**: Sistema MUST permitir gerar snippet JavaScript embutível por tenant para widget de chat web, com personalização visual.
- **FR-004**: Sistema MUST validar assinatura de webhooks antes de processar — `X-Twilio-Signature` (HMAC) para canais WhatsApp/Twilio; `X-Hub-Signature-256` (HMAC) para Instagram Graph; HMAC próprio para widget web.
- **FR-005**: Sistema MUST garantir idempotência em webhooks de mensagens recebidas — dedup por `MessageSid` (Twilio/WhatsApp), `message_id` (Graph/Instagram), `event_id` (widget) em tabela de eventos webhook.
- **FR-006**: Sistema MUST expor status do canal (`ativo`, `desconectado`, `inválido`, `expirado`, `degradado`, `suspenso`) na UI.
- **FR-007**: Sistema MUST sincronizar lista de Content Templates do Twilio (templates Meta aprovados, expostos via Twilio Content API) com status `approved`, `pending`, `rejected`.
- **FR-008**: Sistema MUST permitir conectar **múltiplos números WhatsApp** por tenant, com limite por plano (Starter: 1 / Pro: 3 / Enterprise: ilimitado — NC-15 resolvido). Cada número é `canal_id` independente; conversas NÃO migram entre números (paciente que escreve para número B cria nova conversa no canal B).
- **FR-009**: Sistema MUST permitir conectar **múltiplas contas Instagram** por tenant, com limite por plano (Starter: 1 / Pro: 2 / Enterprise: ilimitado — NC-16 resolvido). Cada conta = `canal_id` independente. Apenas DM nesta fase; comentários em posts fora do escopo.

#### Inbox e conversas (Princípio II + V)

- **FR-010**: Sistema MUST manter conversa única por (tenant, canal, identificador externo do contato) — **modelo stream contínuo** (NC-2 resolvido). Status da conversa segue máquina `aberta → pendente → resolvida → reaberta`. Resolução automática após `auto_resolve_after_hours` (default 72h, configurável 24–168h por tenant). Nova mensagem do paciente após resolução **reabre a mesma conversa**, preservando `conversa_id`.
- **FR-011**: Sistema MUST vincular conversa a paciente quando identificável (telefone → CPF na Fase 2; IGSID/widget em NC-1).
- **FR-012**: Sistema MUST exibir lista de conversas com avatar, canal, último trecho, hora, contador não lidas, prioridade placeholder.
- **FR-013**: Sistema MUST atualizar UI em **< 2s** quando nova mensagem chega (RNF-001 herdado).
- **FR-014**: Sistema MUST suportar até **1000 conversas simultâneas por tenant** em horário de pico sem degradação (RNF-003).
- **FR-015**: Sistema MUST suportar **7 filtros** combináveis (canal, status, atendente, profissional vinculado, tag, presença de mídia, idade) via query string + **busca full-text com pg_trgm** em nome/telefone/conteúdo das mensagens (p95 < 500ms para 50k conversas). Filtros salvos persistentes fora do MVP.
- **FR-016**: Sistema MUST exibir indicador de digitação e status de leitura quando canal suporta.
- **FR-017**: Sistema MUST consumir evento `PacienteMesclado` da Fase 2 e migrar conversas do paciente origem para o alvo automaticamente.
- **FR-018**: Sistema MUST consumir evento `PacienteAnonimizado` (Fase 2) e disparar `AnonimizaMensagensDoPacienteJob` que aplica regra granular (NC-14 resolvido): mensagens **recebidas** → conteúdo apagado + mídias deletadas; mensagens **enviadas** → preservadas; metadata estrutural mantida em ambos os casos. Audit log `paciente.mensagens_anonimizadas`.

#### Envio e recebimento de mensagens (Princípio I + VI)

- **FR-019**: Sistema MUST permitir envio de texto livre dentro da janela 24h (WhatsApp e Instagram); **bloquear fora dela em runtime no backend** retornando 422 `janela_24h_fechada`, exceto via template aprovado (Princípio VI não-negociável). UI exibe badge com contador regressivo (verde/amarelo/vermelho/cadeado) e desabilita o campo + mostra seletor de templates quando fechada.
- **FR-020**: Sistema MUST permitir envio e recebimento de mídia com limites **16 MB para imagem/áudio/vídeo e 100 MB para PDF** (NC-9). Armazenamento S3-compatível com criptografia em repouso AES-256; URLs assinadas temporariamente (24h). Retenção 2 anos. Validação de tipo MIME no recebimento; lista de bloqueio fixa para executáveis. Antivírus completo fora do MVP.
- **FR-021**: Sistema MUST atualizar status de mensagem enviada (`enviada`, `entregue`, `lida`, `falhou`) em tempo real conforme callbacks do canal.
- **FR-022**: Sistema MUST registrar todas as mensagens (entrada e saída) com `tenant_id`, `paciente_id` (quando vinculado), `conversa_id`, `canal_id`, `direcao`, `tipo` (texto/mídia/template/sistema), `conteúdo`, `metadata`, `created_at`.
- **FR-023**: Sistema MUST emitir evento `MensagemRecebida` ou `MensagemEnviada` para cada mensagem persistida com sucesso.

#### Atribuição e transferência

- **FR-024**: Sistema MUST suportar atribuição manual de conversa a atendente do mesmo tenant.
- **FR-025**: Sistema MUST suportar atribuição automática via regras configuráveis (vide NC-6).
- **FR-026**: Sistema MUST exigir nota interna na transferência (vide NC-7).
- **FR-027**: Sistema MUST notificar destinatário de atribuição/transferência (canal de notificação em NC-12).
- **FR-028**: Sistema MUST preservar histórico de atribuições (auditável).

#### Modo Humano Assume

- **FR-029**: Sistema MUST manter coluna `ia_pausada_ate TIMESTAMPTZ NULL` na conversa + configuração `ia_pausa_duracao_minutos` por tenant (default 30, range 5–240).
- **FR-030**: Sistema MUST suportar takeover manual via clique ("Assumir") + takeover implícito por envio de mensagem manual. Liberação manual via "Liberar IA" zera `ia_pausada_ate`. Reprise estende o timer (não acumula).
- **FR-031**: Sistema MUST detectar expiração de pausa em janela ≤60s e emitir `ConversaRetomadaPelaIA` com `motivo='timeout'`.
- **FR-032**: Sistema MUST emitir `ConversaAssumidaPorHumano` (com `motivo` ∈ `manual_click`/`mensagem_enviada`) e `ConversaRetomadaPelaIA` (com `motivo` ∈ `manual`/`timeout`) — contrato público `ConversaIATogglingContract` para Fase 4 consumir.
- **FR-032b**: Sistema MUST NÃO gerar resposta automática a mensagens entrantes nesta fase (sem IA). Mensagens entram na inbox e aguardam atendente humano.

#### Respostas rápidas

- **FR-033**: Sistema MUST suportar CRUD de respostas rápidas com escopo dual — **tenant** (visíveis a todos com `inbox.respond`) e **privadas** (só o autor); em conflito de atalho, privada vence (NC-8 resolvido).
- **FR-034**: Sistema MUST suportar 6 variáveis dinâmicas: `{nome_paciente}`, `{primeiro_nome_paciente}`, `{nome_profissional}`, `{nome_clinica}`, `{nome_atendente}`, `{data_proxima_consulta}` (placeholder Fase 5; renderiza vazio nesta fase). Variáveis nulas → string vazia.
- **FR-035**: Sistema MUST oferecer autocomplete por atalho `/` no campo de mensagem com indicador de escopo (privada/equipe) em cada item.
- **FR-035b**: Sistema MUST manter templates Meta (Twilio Content Templates) **separados** das respostas rápidas em entidade dedicada e em **UI distinta** (2 seletores: respostas rápidas para dentro da janela 24h, templates Meta para fora). Templates Meta suportam as mesmas 6 variáveis quando configurados com parameters.
- **FR-035c**: Sistema MUST NÃO suportar mídia anexada em respostas rápidas no MVP (Q8.d). Envio de mídia individual durante conversa continua via FR-020.

#### Auditoria, segurança, LGPD

- **FR-036**: Sistema MUST registrar em `audit_logs` cada conexão/desconexão de canal, transferência de conversa, takeover/retomada de IA, falha de webhook.
- **FR-037**: Sistema MUST sanitizar payload de audit log — conteúdo de mensagem **não** entra; apenas metadados (canal, paciente_id, sender, length, has_media).
- **FR-038**: Sistema MUST aplicar rate limit por tenant E por endpoint (Princípio VII; RNF-009). Limites específicos em assumption.
- **FR-039**: Sistema MUST aplicar política de retenção configurável por tenant: mensagens texto **2 anos** default (`message_retention_months`, range 6–60); mídia recebida e enviada **1 ano** default (`media_retention_months`, range 6–24). Job mensal arquiva texto expirado (deleta conteúdo, mantém metadata estrutural) e deleta mídia expirada do S3 com substituição por placeholder no histórico.
- **FR-040**: Sistema MUST validar origem em widget web (chave pública + opcional whitelist de domínio — NC-10).

#### Tempo real

- **FR-041**: Sistema MUST estruturar broadcast em **salas híbridas** (NC-11 resolvido): 1 sala por tenant `tenant.{id}.inbox` (eventos leves — novas conversas, contadores, presença) + 1 sala por conversa `tenant.{id}.conversa.{cid}` (eventos pesados — mensagens detalhadas, typing, read receipts). Autorização por canal: usuário pertence ao tenant E (para conversa) tem visibilidade naquela conversa. Princípio II não-negociável.
- **FR-042**: Sistema MUST suportar reconexão automática do cliente WebSocket com **backoff exponencial 1s→2s→4s→8s→16s→30s** (max), tentativas infinitas, heartbeat 25s ping / 60s timeout server.
- **FR-043**: Sistema MUST cair automaticamente para **long polling** após 2 minutos sem WebSocket; cliente faz `GET /api/v1/inbox/poll?since={cursor}` a cada 10s; banner UI alerta modo limitado; indicador de digitação e read receipts ficam desabilitados em polling.

#### Permissões

- **FR-044**: Sistema MUST aplicar as 8 abilities da seção 2.3 via Spatie team mode.
- **FR-045**: Sistema MUST recusar 403 a Financeiro e Super Admin em **todos** endpoints de inbox e mensagens.

### 4.2 Non-Functional Requirements (recap herdados)

- **NFR-1** (Princípio I — LGPD): conteúdo de mensagem nunca em log de aplicação; CPF mascarado em audit (já garantido Fase 2).
- **NFR-2** (Princípio II): teste de isolamento multi-tenant cobre 100% dos endpoints novos.
- **NFR-3** (Princípio V): logs estruturados com `tenant_id, user_id, request_id, conversa_id, canal_id`.
- **NFR-4** (Princípio VI — NÃO-NEGOCIÁVEL): envio bloqueado fora da janela 24h sem template aprovado; cada bloqueio gera evento auditável.
- **NFR-5** (Princípio VII): hash de credenciais Meta (long-lived tokens) criptografado em repouso.
- **NFR-6**: cobertura de testes ≥ 75% nesta fase; ≥ 70% global mantida.
- **NFR-7**: SLA de uptime alvo 99,5% (herdado).
- **NFR-8**: latência envio→recebimento (lado servidor) **p95 < 2s**.

### 4.3 Key Entities

- **Canal**: instância conectada de WhatsApp/Instagram/Web por tenant. Atributos: tipo, status, credenciais (criptografadas), config visual (widget), Quality Rating (WhatsApp), templates aprovados.
- **Conversa**: thread por (tenant, canal, contato externo, [paciente]). Atributos: status, atendente, profissional vinculado, `ia_pausada_ate`, `ultima_msg_paciente_at` (cálculo de janela 24h), prioridade placeholder.
- **Mensagem**: unidade de comunicação dentro de uma conversa. Atributos: direção, tipo, conteúdo (sanitizado em logs), `external_message_id` (Meta), status entrega/leitura, autor (paciente, atendente, IA — IA placeholder), mídia.
- **MediaAsset**: arquivo (imagem, áudio, vídeo, PDF) recebido ou enviado. Em armazenamento externo conforme NC-9.
- **WebhookEvent**: registro de toda chamada de webhook recebida (raw payload + status processamento + dedup key).
- **RespostaRapida**: template de texto curto. Escopo (private/team) em NC-8.
- **TemplateMeta**: template aprovado Meta (sincronizado da WABA, read-only no MVP).
- **AtribuicaoHistorico**: rastro de atribuições/transferências de uma conversa.
- **WidgetSnippet** (config + chave pública): identificação do widget para autenticação.
- **NotificacaoUsuario**: registro de notificações em-app por usuário.

---

## 5. Fora de Escopo desta Fase

- **IA matricial, classificação de intenção, agente automático** — Fase 4. Esta fase entrega apenas o **mecanismo de pausa** (`ia_pausada_ate`).
- **Agendamento via chat (paciente pede horário pela IA)** — Fase 4 + Fase 5.
- **Disparos em massa, campanhas, reativação, sazonais** — Fase 7. Templates Meta nesta fase são apenas para uso reativo dentro/fora da janela 24h em conversas existentes.
- **Renovação automática de receituários por mensagem** — Fase 6.
- **Medidor de consumo IA** — `ai_usage_meters` existe desde a Fase 0; uso real começa Fase 4.
- **Comentários em posts do Instagram** — apenas DM nesta fase (vide NC-16).
- **Áudio com transcrição automática** — Fase 4 (IA).
- **Pesquisa NPS pós-atendimento** — Fase futura.
- **App mobile dedicado para atendente** — fora do produto v1; SPA responsiva supre.
- **Push notification mobile (iOS/Android)** — fora; apenas browser web push.
- **Conversa de voz / vídeo chamada** — fora.

---

## 6. Eventos de Domínio Emitidos

Contrato público publicado por esta fase para fases futuras (4, 5, 7) consumirem.

| Evento                          | Disparado em                                                | Payload (campos lógicos)                                                                          | Audit action                          |
|---------------------------------|-------------------------------------------------------------|---------------------------------------------------------------------------------------------------|---------------------------------------|
| `CanalConectado`                | Conexão de canal externo bem-sucedida                       | `canal_id, tipo, executor_id, external_account_id (mascarado)`                                    | `channel.connected`                   |
| `CanalDesconectado`             | Desconexão manual ou expiração                              | `canal_id, motivo (manual|expirado|invalidado_pela_meta)`                                          | `channel.disconnected`                |
| `CanalComFalha`                 | Webhook handshake falha / Quality Rating despenca / API ban | `canal_id, tipo_falha, detalhes_sanitizados`                                                      | `channel.failed`                      |
| `ConversaCriada`                | Primeira mensagem cria conversa nova                        | `conversa_id, canal_id, paciente_id|null, origem (canal externo iniciou|atendente iniciou|web visitor)` | `conversa.criada`                |
| `MensagemRecebida`              | Mensagem do paciente chega via webhook ou widget            | `mensagem_id, conversa_id, paciente_id|null, tipo (texto|midia), tem_midia: bool`                  | `mensagem.recebida`                   |
| `MensagemEnviada`               | Atendente ou IA (futuro) envia                              | `mensagem_id, conversa_id, autor_id, autor_tipo (user|ia), tipo`                                  | `mensagem.enviada`                    |
| `ConversaAtribuida`             | Atribuição manual ou automática                             | `conversa_id, atendente_id, modo (manual|auto:round_robin|auto:profissional_vinculado)`           | `conversa.atribuida`                  |
| `ConversaTransferida`           | Transferência entre atendentes                              | `conversa_id, de_user_id, para_user_id, nota_interna_sanitizada, motivo`                          | `conversa.transferida`                |
| `ConversaAssumidaPorHumano`     | Takeover manual ou implícito                                | `conversa_id, executor_id, motivo (manual_click|mensagem_enviada), duracao_pausa_seg`             | `conversa.assumida_por_humano`        |
| `ConversaRetomadaPelaIA`        | Liberação manual ou timeout                                 | `conversa_id, motivo (manual|timeout)`                                                            | `conversa.retomada_pela_ia`           |
| `ConversaResolvida`             | Marcação manual ou automática (NC-2)                        | `conversa_id, modo (manual|auto_inatividade), resolutor_id|null`                                  | `conversa.resolvida`                  |
| `ConversaReaberta`              | Mensagem nova após resolução (NC-2)                         | `conversa_id, motivo (nova_msg|manual)`                                                           | `conversa.reaberta`                   |
| `WebhookFalhou`                 | Processamento de webhook erra mesmo após retries            | `canal_id, webhook_event_id, motivo, tentativas`                                                  | `webhook.falhou`                      |
| `ConversaVinculadaAPaciente`    | Atendente vincula manualmente ou sistema auto-vincula por CPF/telefone em DM (Instagram) | `conversa_id, paciente_id, executor_id, modo (manual|auto_cpf_no_texto|auto_telefone_no_texto)` | `conversa.vinculada_paciente`         |

---

## 7. Clarifications resolvidas (17/17 em 2026-05-11)

Todos os 17 NCs originais foram resolvidos via `/speckit.clarify` em sessão interativa one-by-one (protocolo definido pelo usuário). Decisões persistidas nos blocos `### ✅ NC-N` abaixo + propagadas para FRs, ACs, Riscos, Eventos e contratos herdados.

**Resumo das decisões** (lista rápida; detalhes nos blocos resolvidos):

| #   | Tópico                          | Decisão-chave                                                                                                                |
|-----|---------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| Q1  | Identificação paciente x canal  | **Twilio** como provider WhatsApp; colisão → fila "Não identificado"; widget `pre_chat_form` configurável; vinculação manual sempre disponível |
| Q2  | Modelo de conversa              | Stream contínuo com máquina `aberta→pendente→resolvida→reaberta`; auto-resolve 72h configurável; reabre mesma conversa       |
| Q3  | Múltiplos canais por paciente   | Uma conversa por canal na inbox; ficha do paciente unifica histórico via timeline Fase 2                                     |
| Q4  | Janela 24h WhatsApp             | Badge regressivo verde/amarelo/vermelho/cadeado; envio bloqueado em runtime; templates sync read-only (cadastro = Fase 7)    |
| Q5  | Modo Humano Assume              | Pausa implícita por envio; duração 30min configurável (5–240); clique pausa mesma duração; sem stub de IA                    |
| Q6  | Atribuição automática           | 2 regras (`round_robin` ou `profissional_vinculado` com fallback); online ≤5min; limite default 15 conversas; fila órfã      |
| Q7  | Transferência                   | Nota mín 10 chars; transferir para usuário OU role; cross-tenant proibido (Princípio II)                                     |
| Q8  | Respostas rápidas               | Escopo dual (tenant + privada com override); 6 variáveis; templates Meta separados em UI; sem mídia no MVP                   |
| Q9  | Mídia recebida                  | Limites 16/16/16/100MB; S3+AES-256, URLs 24h, retenção 2 anos; preview-once com aviso LGPD; sem antivírus MVP                |
| Q10 | Widget web                      | JS central; chave + whitelist; lead provisório 30d; 3 modos fora horário (default `fila`); sem cross-channel                 |
| Q11 | WebSocket                       | Salas híbridas (tenant + conversa); backoff 1→30s infinito; long polling automático após 2min sem WS                         |
| Q12 | Notificações                    | In-app + som + browser push opt-in; sons diferenciados; prioridade alta agressiva; config híbrida (tenant default + user)    |
| Q13 | Filtros e busca                 | 7 filtros combináveis; full-text com `pg_trgm` (reuso Fase 2) p95<500ms; filtros salvos fora do MVP                          |
| Q14 | Retenção e LGPD                 | Texto 2 anos / mídia 1 ano (configurável); anonimização granular (recebidas apagam, enviadas preservam)                      |
| Q15 | Multi-número WhatsApp           | Permitido por plano (Starter 1 / Pro 3 / Enterprise ∞); cada número = canal independente                                     |
| Q16 | Instagram multi-conta + comentários | Multi-conta por plano (Starter 1 / Pro 2 / Enterprise ∞); comentários em posts fora do MVP                              |
| Q17 | Compliance Meta operacional     | Tenant cadastra templates no Twilio/Meta direto; 2 templates pré-requisito documentados; Quality Rating Low pausa auto, mantém manual com aviso |

### ✅ NC-1 — Identificação de paciente x conversa — RESOLVIDO (2026-05-11)

**Decisões aplicadas** (Q1.a/Q1.b/Q1.c):

- **WhatsApp**: provedor da WhatsApp Business API será o **Twilio Programmable Messaging**[^nc1-twilio]. Identificação por telefone E.164 contra a base de pacientes do tenant. **Em colisão** (2+ pacientes com mesmo telefone — caso "família" da Fase 2 NC-1): conversa criada com `paciente_id=null` e entra na **fila "Não identificado"**; UI mostra os candidatos lado a lado e atendente escolhe entre eles ou cria novo paciente ao abrir.
- **Instagram**: identificação por `igsid` (Instagram Scoped ID). Cria **lead provisório** com `igsid` referenciado em campo dedicado (`instagram_igsid` em metadados do paciente); vinculação ao paciente fica em aberto até que (a) atendente vincule manualmente OU (b) paciente envie CPF/telefone reconhecível no texto da DM, hipótese em que o sistema executa match na base e **sugere vínculo automático com confirmação do atendente** (nunca silencioso, para evitar erro de identificação).
- **Widget web**: comportamento **configurável por tenant** via setting `pre_chat_form`:
  - `opcional` (default) — visitante inicia chat anônimo; vira lead com `session_id`.
  - `exigido_para_iniciar` — form pré-chat obrigatório antes de abrir.
  - `exigido_para_enviar` — chat abre, mas envio bloqueia até nome+telefone preenchidos.
- **Regra transversal a todos os canais**: atendente **sempre pode vincular conversa a paciente manualmente** a qualquer momento, em qualquer canal — sem exceção. Vinculação manual gera evento auditável.

[^nc1-twilio]: Twilio substitui Meta Cloud API direta como provedor da WhatsApp Business API. Implica que webhooks, autenticação, templates e billing fluem via Twilio. Detalhes de integração ficam em `plan.md`. Meta continua sendo a autoridade que aprova templates e gerencia Quality Rating do número — Twilio apenas surface esses dados.

### ✅ NC-2 — Modelo de conversa vs. ticket — RESOLVIDO (2026-05-11)

**Decisão**: modelo **stream contínuo** com ciclo de vida[^nc2-modelo].

- **Q2.a — Modelo**: stream contínuo por par `(paciente_id ou identificador_externo, canal_id)`. Mesma `conversa_id` durante toda a vida do paciente naquele canal. Status muda ao longo do tempo: `aberta → pendente → resolvida → reaberta`.
- **Q2.b — Resolução**: **manual OU automática** após **72h sem nova mensagem do paciente**. Janela é configurável por tenant entre 24h e 168h (`auto_resolve_after_hours`, default `72`). Resolução automática dispara `ConversaResolvida` com `modo='auto_inatividade'`. Atendente pode marcar resolvida manualmente a qualquer momento (gera `modo='manual'`).
- **Q2.c — Reabertura**: mensagem nova após resolução **reabre a mesma conversa** — status volta a `aberta`, `conversa_id` é preservada, dispara `ConversaReaberta` com `motivo='nova_msg'`. Atendente também pode reabrir manualmente (`motivo='manual'`).
- **Status formal da conversa** (máquina de estados):
  - `aberta`: criada ou reaberta; aguarda interação.
  - `pendente`: atendente respondeu, esperando paciente.
  - `resolvida`: marcada como concluída (manual ou auto após inatividade).
- Estados aplicam-se a **todos os canais** (WhatsApp, Instagram, Web).

[^nc2-modelo]: Modelo stream contínuo é o padrão de helpdesks modernos (Intercom, Zendesk Messaging) e preserva contexto histórico do paciente naquele canal. Métricas de "tempo de resolução" são calculadas por **ciclo aberta→resolvida** dentro da mesma conversa.

### ✅ NC-3 — Múltiplos canais por paciente — RESOLVIDO (2026-05-11)

**Decisão**: **uma conversa por canal** na inbox; ficha do paciente unifica histórico via timeline[^nc3-modelo].

- **Inbox**: lista mostra entrada separada por canal. Paciente Maria que falou via WhatsApp e Instagram aparece como 2 entradas: "Maria (WhatsApp)" + "Maria (Instagram)". Cada conversa tem seu próprio `canal_id`, status, `ia_pausada_ate`, atendente.
- **Ficha do paciente (Fase 2)**: timeline unificada cronológica mostra eventos de **todas as conversas** (todos os canais) do paciente, intercalados com outros eventos (anotações, mudanças de status, etc.). Reutiliza `eventos_timeline` da Fase 2 — cada `MensagemRecebida`/`MensagemEnviada` da Fase 3 vira evento de timeline via `RegistraEventoTimelineListener` (já registrado na Fase 2).
- **Resposta do atendente**: responde **no canal da conversa aberta** (sem possibilidade de "trocar de canal" durante atendimento — cross-channel é fora de escopo, vide NC-10 e Fora de Escopo).
- **Janela 24h e estado IA**: por **conversa individual**, não por paciente. Conversa Instagram com janela aberta + Conversa WhatsApp com janela fechada coexistem.

[^nc3-modelo]: Padrão de produtos omnichannel sérios (Manychat, RD Conversas). Preserva clareza operacional (atendente sabe em que canal está respondendo) e isolamento da janela 24h por canal. Visualização unificada acontece na ficha do paciente, onde o contexto histórico importa mais que a operação síncrona.

### ✅ NC-4 — Janela 24h do WhatsApp — RESOLVIDO (2026-05-11)

**Decisão**: sinalização visual ativa + bloqueio com fallback de template + sync read-only de templates[^nc4-meta].

- **Q4.a — Sinalização visual**: cada conversa WhatsApp exibe **badge com contador regressivo** baseado em `ultima_msg_paciente_at + 24h`:
  - **Verde** (`⏱ Xh restantes`): janela com > 4h.
  - **Amarelo**: janela com 1–4h.
  - **Vermelho**: janela com < 1h.
  - **Cinza com cadeado** (`🔒 Janela fechada`): expirada (> 24h sem mensagem do paciente).
  - Para Instagram aplica regra equivalente (janela de 24h também).
- **Q4.b — Envio com janela fechada**: campo de mensagem fica **desabilitado** com tooltip "Janela 24h fechada — use template aprovado abaixo". **Seletor de templates aprovados** aparece logo abaixo do campo desabilitado. Envio de texto livre fora da janela é **bloqueado em runtime** (não apenas UI — backend rejeita; Princípio VI não-negociável). Cada tentativa bloqueada gera evento auditável `mensagem.bloqueada_fora_janela`.
- **Q4.c — Cadastro de templates**: **fora de escopo nesta fase** — somente **sync read-only** dos templates aprovados via Twilio Content API. Cadastro/submissão de novos templates para aprovação Meta é responsabilidade da **Fase 7 (Campanhas)**. Tenants em produção devem cadastrar templates iniciais (boas-vindas, retorno) no painel Twilio + Meta Business Manager antes do go-live.
- **Templates obrigatórios para go-live de tenant em produção** (documentar no quickstart, não implementar como gate hard):
  - `boas_vindas` — primeira interação fora da janela.
  - `retorno_consulta` — agendamento/lembrete (mesmo que Fase 5 ainda não use).
- **Auditoria**: cada envio bloqueado por janela fechada **sem template** gera `audit_logs.action='mensagem.bloqueada_fora_janela'` com payload `{conversa_id, ultima_msg_paciente_at, tempo_excedido_horas}`.

[^nc4-meta]: Princípio VI (Conformidade Meta) é não-negociável: enviar texto livre fora da janela suspende a conta Meta. UI sinaliza ativamente para reduzir tentativas; backend bloqueia para garantir conformidade mesmo se UI falha.

### ✅ NC-5 — Modo "Humano Assume" — RESOLVIDO (2026-05-11)

**Decisão**: pausa implícita por envio + 30min configurável + clique pausa mesma duração + sem stub de resposta IA[^nc5-fase4].

- **Q5.a — Gatilho implícito**: qualquer mensagem manual enviada pelo atendente seta `ia_pausada_ate = now() + ia_pausa_duracao_minutos` automaticamente. Não exige clique explícito. Evento `ConversaAssumidaPorHumano` é emitido com `motivo='mensagem_enviada'`.
- **Q5.b — Duração padrão**: **30 minutos**, configurável por tenant em `inbox_settings.ia_pausa_duracao_minutos` (range 5–240). Aplicada a todos os canais.
- **Q5.c — Pausa por clique "Assumir"**: clique pausa pela **mesma duração padrão** (`now() + ia_pausa_duracao_minutos`). Atendente pode "Liberar IA" manualmente antes do expire, ou pausa expira sozinha e dispara `ConversaRetomadaPelaIA`. Evento de assumir tem `motivo='manual_click'`.
- **Q5.d — Stub de IA nesta fase**: **apenas mecanismo de estado e eventos** — `ia_pausada_ate` é atualizado, eventos `ConversaAssumidaPorHumano`/`ConversaRetomadaPelaIA` são emitidos, contrato `ConversaIATogglingContract` é definido. **Nenhuma resposta automática é gerada nesta fase**. Quando mensagem entra e IA está "ativa" (`ia_pausada_ate IS NULL OR ia_pausada_ate < now()`), o sistema apenas grava a mensagem e a conversa aguarda atendente humano. Stub de "olá, recebemos sua mensagem..." NÃO é entregue.
- **Retomada automática**: job/listener verifica conversas com `ia_pausada_ate < now()` em janela curta (≤ 60s) e dispara `ConversaRetomadaPelaIA` com `motivo='timeout'`. Frequência exata em `plan.md`.
- **Reprise da pausa**: atendente envia mensagem nova enquanto pausa ainda está ativa — `ia_pausada_ate` é **estendido** para `now() + duração_padrão` (reinicia o timer). Não acumula múltiplas pausas.

[^nc5-fase4]: O contrato de interface `ConversaIATogglingContract` é estável: Fase 4 (IA matricial) implementa o **subscriber** do evento `ConversaRetomadaPelaIA` para retomar geração automática. Esta fase apenas publica o evento; sem subscriber consumir, comportamento é no-op (correto para MVP sem IA).

### ✅ NC-6 — Atribuição automática — RESOLVIDO (2026-05-11)

**Decisão**: 2 regras + disponibilidade inferida + limite configurável + fila órfã visível[^nc6-mvp].

- **Q6.a — Regras suportadas (MVP)**: duas estratégias selecionáveis por tenant em `inbox_settings.auto_assign_strategy`:
  - **`round_robin`** (default): atribui ao próximo atendente disponível da fila circular (escopo: todos os usuários com `inbox.respond` no tenant que estão "online" — vide Q6.b).
  - **`profissional_vinculado`**: se a conversa tem paciente identificado **e** o paciente tem `profissional_responsavel_id` (Fase 2) **e** o profissional está disponível → atribui a ele. Caso contrário, faz **fallback para round-robin**.
  - Regras adicionais (por canal, por tag, por horário) ficam para fase futura.
- **Q6.b — Disponibilidade ("online")**: **inferida** pela última atividade do atendente — usuário com sessão ativa (request/WebSocket heartbeat) nos últimos **5 minutos**. Sem status manual no MVP. Threshold configurável em `inbox_settings.user_idle_minutes` (default 5, range 1–60).
- **Q6.c — Limite por atendente**: configurável por tenant em `inbox_settings.auto_assign_max_per_user`, **default 15** conversas com status `aberta` ou `pendente`. Se atendente está no limite, sistema **pula** para próximo elegível na regra escolhida (sem forçar).
- **Q6.d — Conversa não atribuída**: fica em **fila "Sem atendente"** visível para **todos os usuários do tenant com `inbox.view`**. Qualquer um pode clicar "Pegar conversa" para auto-atribuir. Conversa órfã por **> 24h** gera alerta in-app para Admin Clínica (sem bloqueio, só sinal).
- **Caso particular — nenhum atendente disponível**: nova conversa entra direto na fila "Sem atendente" e dispara notificação geral (NC-12 detalha canal).
- **Reassign por atendente offline**: vide NC-6.5 (já antecipado em AC-4.5.9 — futuro). Não no MVP.

[^nc6-mvp]: 2 regras (round-robin e profissional vinculado) cobrem ~95% dos casos brasileiros de clínica pequena/média. Regras avançadas (tag, canal, horário) são casos enterprise que entram em fase posterior se demanda real surgir.

### ✅ NC-7 — Transferência — RESOLVIDO (2026-05-11)

**Decisão**: nota mínima de contexto + transferência para usuário ou role + bloqueio cross-tenant não-negociável[^nc7-tenant].

- **Q7.a — Nota interna**: **mínimo 10 caracteres**. UI sugere template "Encaminhando para [motivo]: ..." como placeholder no campo. Sem máximo (até 2000 chars). Nota é registrada em `audit_logs` (sanitizada — sem PII em texto livre) e na timeline da conversa (visível só para equipe).
- **Q7.b — Transferir para perfil/role**: suporta **ambos**:
  - **Usuário específico**: dropdown de usuários do tenant com `inbox.view`. Atribuído diretamente.
  - **Role / perfil** (ex.: "qualquer Médico online", "qualquer Atendente disponível"): sistema aplica regra de auto-atribuição (NC-6) restrita aos usuários com aquele role. Se nenhum disponível, vai para fila "Sem atendente" com `motivo='transferencia_role'`.
  - Roles selecionáveis na UI: `medico`, `atendente`, `recepcionista`, `admin-clinica`. `financeiro` não aparece (sem ability `inbox.view`).
- **Q7.c — Cross-tenant**: **PROIBIDO** sem exceção. UI filtra apenas usuários do tenant atual; backend valida via Policy e retorna **403** mesmo se payload manipulado. Princípio II não-negociável.

[^nc7-tenant]: Cross-tenant viola isolamento de dados. Mesmo cenários "tenant-irmão / mesmo grupo empresarial" não são exceção — se houver demanda futura, exige modelo de "compartilhamento explícito" auditável, fora do MVP.

### ✅ NC-8 — Respostas Rápidas — RESOLVIDO (2026-05-11)

**Decisão**: escopo dual com herança + 6 variáveis + entidades distintas de templates Meta + sem mídia no MVP[^nc8-mvp].

- **Q8.a — Escopo (dual com herança)**: dois níveis convivem:
  - **Tenant**: criadas por Admin Clínica ou Atendente com `quick_reply.manage`, visíveis a **todos** com `inbox.respond` no tenant. Padrão da equipe.
  - **Privada**: criada por usuário individual; **só o autor enxerga e edita**. Override pessoal.
  - **Conflito de atalho** (mesma string ex.: `/preço` existe em ambas): **privada vence** (override pessoal sobre coletiva). UI mostra indicador "🔒 privada" ao lado.
- **Q8.b — Variáveis suportadas no MVP** (6):
  - `{nome_paciente}` — nome completo do paciente vinculado à conversa.
  - `{primeiro_nome_paciente}` — primeiro token de `nome_paciente`.
  - `{nome_profissional}` — `profissional_responsavel.nome` (Fase 2). Vazio se paciente sem responsável.
  - `{nome_clinica}` — `tenant.name`.
  - `{nome_atendente}` — `user.name` do atendente que está enviando.
  - `{data_proxima_consulta}` — placeholder. Vazio nesta fase (Fase 5 não existe). Quando Fase 5 entrar, preenche com data da próxima consulta agendada.
  - **Variáveis com valor nulo** são renderizadas como string vazia (sem warning); atendente vê preview com `{nome_profissional}` literal antes de enviar e pode corrigir.
- **Q8.c — Templates Meta vs. respostas rápidas**: **entidades distintas**. Sem mistura.
  - **Resposta rápida**: texto livre criado pelo tenant/usuário. Disparada via atalho `/`. Usável **só dentro** da janela 24h.
  - **Template Meta** (Twilio Content Template aprovado): texto pré-aprovado pela Meta. Usável **fora** da janela 24h. Selecionado via seletor separado abaixo do campo desabilitado (vide NC-4).
  - UI **separa em 2 seletores** visíveis:
    - "Respostas rápidas" (atalho `/`).
    - "Templates aprovados" (botão "Template..." que abre listagem do Twilio sync).
  - Templates Meta também suportam as 6 variáveis acima quando configurados com parameters; sistema injeta.
- **Q8.d — Mídia em respostas rápidas**: **NÃO no MVP**. Respostas rápidas são apenas texto + variáveis. Mídia individual continua suportada como envio direto na conversa (anexar arquivo no momento da mensagem). Cadastro de respostas rápidas com mídia anexada fica para fase posterior se demanda surgir.

[^nc8-mvp]: Escopo dual cobre padronização da equipe (tenant) + personalização individual (privada). 6 variáveis cobrem ~95% dos casos comuns de chat humano; placeholders para Fase 5 ficam prontos. Separação entre respostas rápidas e templates Meta evita confusão de fluxo legal (janela 24h vs. fora).

### ✅ NC-9 — Mídia recebida — RESOLVIDO (2026-05-11)

**Decisão**: limites pragmáticos + S3 com retenção 2 anos + preview-once com aviso LGPD + tipo MIME validado (sem antivírus no MVP)[^nc9-lgpd].

- **Q9.a — Limites de tamanho por tipo**:
  - Imagens (`image/*`): **16 MB**.
  - Áudio (`audio/*`): **16 MB**.
  - Vídeo (`video/*`): **16 MB**.
  - Documento (`application/pdf`): **100 MB**.
  - Outros tipos MIME (`application/zip`, `application/x-rar`, etc.): **rejeitados** com mensagem clara ao paciente.
  - Excedeu o limite: webhook rejeita, sistema responde ao canal com mensagem traduzida "Arquivo muito grande, máximo X MB para este tipo".
- **Q9.b — Storage**: armazenamento em **S3-compatível** (provedor específico fica para `plan.md`) com **criptografia em repouso (AES-256)**. URL de acesso para atendentes é **assinada temporariamente (24h)**; URL bruto nunca exposto. **Retenção de 2 anos** alinhada com `audit_logs` da Fase 0; após 2 anos, mídia movida para "cold storage" ou deletada conforme política LGPD (NC-14 detalha cascateamento com anonimização).
- **Q9.c — Preview de mídia ("sensível")**: na **primeira vez** que cada atendente abre mídia em uma conversa, modal aparece com aviso:
  > "Esta mídia pode conter dados sensíveis do paciente (foto de exame, receita, documento). Apenas pessoas autorizadas devem visualizar. Conforme a LGPD, sua visualização é registrada para auditoria."
  
  Aceitação é registrada por `(atendente_id, conversa_id)` em audit_logs (`mensagem.midia_visualizada`). Mídias subsequentes na mesma conversa abrem direto sem repetir aviso.
- **Q9.d — Antivírus**: **NÃO obrigatório no MVP**. Sistema confia na sanitização prévia do canal (Meta/Twilio fazem scan antes de entregar; Instagram idem). Validação local:
  - Tipo MIME no header HTTP do download confere com extensão.
  - **Lista de bloqueio fixa**: executáveis (`.exe`, `.bat`, `.sh`, `.ps1`, `.cmd`, `.scr`, `.com`, `.msi`, `.jar`, `.app`) rejeitados imediatamente mesmo se vierem.
  - Antivírus completo (ClamAV/AWS GuardDuty/etc.) entra em fase posterior se compliance/cliente exigir.

[^nc9-lgpd]: Limites de 16/16/16/100 MB cobrem 95% dos casos reais de paciente enviando exame ou receita. URLs assinadas + criptografia em repouso atendem Princípio I (criptografia em repouso). Aviso preview-once balanceia friction vs. consciência LGPD do atendente.

### ✅ NC-10 — Widget web embutível — RESOLVIDO (2026-05-11)

**Decisão**: hospedagem central + chave + whitelist + lead provisório + horário configurável + sem cross-channel[^nc10-widget].

- **Q10.a — Hospedagem**: JS **hospedado pelo CRM** em `https://widget.crm.com.br/v1/{tenant_public_key}.js`. Cliente cola apenas snippet `<script async src="...">`. Hotfixes propagam globalmente sem cliente reembedar.
- **Q10.b — Autenticação**: **chave pública + whitelist de domínio**:
  - Snippet expõe apenas `tenant_public_key` (não secreta — pode aparecer no HTML do cliente).
  - Admin Clínica configura **whitelist de domínios autorizados** em `widget_settings.allowed_origins` (ex.: `["clinica-alfa.com.br", "blog.clinica-alfa.com.br"]`).
  - No carregamento, widget envia `Origin` header; backend valida contra whitelist. **Default: vazio** (aceita qualquer origem) com aviso "⚠️ Configure domínios autorizados para evitar uso indevido" no painel.
  - Origem não permitida → widget rejeita carregamento com erro silencioso (não revela chave).
- **Q10.c — Visitante anônimo**: ao iniciar conversa cria **lead provisório** com:
  - `paciente_id=null` na conversa inicialmente.
  - **Registro temporário** em estrutura `widget_visitor_sessions` (cookie + session_id; persiste 30 dias).
  - Quando visitante preenche nome + telefone (manual em form pré/durante chat, ou via IA na Fase 4), sistema **cria paciente** em status `lead` (Fase 2) e vincula `conversa.paciente_id`.
  - Lead provisório sem dados completos por **30 dias** é purgado por job (idempotente, mantém audit log da conversa mas remove dados de sessão).
- **Q10.d — Fora do horário**: comportamento **configurável por tenant** em `widget_settings.outside_hours_behavior`:
  - **`bloqueia`**: widget exibe "Estamos fechados. Horário: {horário}."; visitante não consegue enviar.
  - **`fila`** (default): visitante envia normal; widget exibe "Responderemos assim que abrirmos. Horário: {horário}."; mensagem entra na inbox marcada com `recebida_fora_horario=true`.
  - **`normal`**: aceita exatamente igual horário comercial (caso clínica tenha plantão).
- **Q10.e — Cross-channel**: **NÃO no MVP**. Conversa iniciada via widget **permanece no canal Web** independentemente de paciente fornecer telefone/Instagram durante a conversa. Atendente vê dados de contato na ficha e pode opcionalmente iniciar **conversa separada** em outro canal (cria nova conversa naquele canal — não migra a do Web).

[^nc10-widget]: Hospedagem central permite atualização de JS sem ação do cliente (segurança + bugfix). Whitelist com default aberto reduz fricção inicial mas com aviso UX para configurar. Cross-channel migração é complexo (mistura janelas 24h e contexto) — adiar para fase posterior se demanda real surgir.

### ✅ NC-11 — WebSocket strategy — RESOLVIDO (2026-05-11)

**Decisão**: salas híbridas (tenant + conversa) + backoff exponencial infinito + long polling fallback automático[^nc11-rt].

- **Q11.a — Estrutura de canais (híbrido por sala)**:
  - **Sala por tenant** (1): `tenant.{tenant_id}.inbox`. Toda nova conversa, contador de não lidas atualizado, presença online de atendentes, alertas de fila órfã — broadcast leve.
  - **Sala por conversa** (1 por aberta na UI): `tenant.{tenant_id}.conversa.{conversa_id}`. Mensagens detalhadas, indicador de digitação (typing), read receipts — broadcast pesado mas só para inscritos.
  - **Comportamento do cliente**:
    - Ao logar e abrir inbox: inscreve-se em `tenant.{tenant_id}.inbox`.
    - Ao abrir uma conversa específica: inscreve-se em `tenant.{tenant_id}.conversa.{conversa_id}`.
    - Ao fechar a conversa: desinscreve da sala daquela conversa (libera bandwidth).
  - **Autorização** (Princípio II não-negociável): cada inscrição valida que o usuário pertence ao tenant E tem `inbox.view` (e para sala de conversa, que pode ver aquela conversa específica — médico vê só atribuídas a ele ou ao seu paciente).
- **Q11.b — Reconexão automática (backoff exponencial)**:
  - Sequência: **1s → 2s → 4s → 8s → 16s → 30s** (max). Após atingir 30s, mantém polling a cada 30s.
  - Reset do contador ao reconectar com sucesso.
  - **Limite de tentativas: infinito** (sempre tenta voltar a conectar). Atendente nunca fica permanentemente offline sem ação.
  - **Heartbeat**: ping cliente→servidor a cada 25s; timeout server-side: 60s sem ping → marca conexão como morta e dispara reconexão.
- **Q11.c — Fallback long polling**: cliente detecta **falha persistente do WebSocket** (>2 minutos sem conseguir estabelecer ou manter conexão) e **automaticamente** cai para modo **long polling**:
  - Cliente faz `GET /api/v1/inbox/poll?since={cursor}` a cada 10 segundos.
  - Banner UI no topo da inbox: "⚠️ Modo limitado — atualizações podem atrasar (10–15s). Verifique seu firewall/proxy se persistir."
  - Sistema continua tentando restabelecer WebSocket em paralelo; ao reconectar, banner some.
  - Eventos durante long polling: indicador de digitação e read receipts ficam **desabilitados** (não vale a pena via polling); só atualizações de mensagem (entrada/saída).
  - Métrica em produção: contar quantos usuários ficam em long polling > 5 min como sinal de problema de infra.

[^nc11-rt]: Salas híbridas é o padrão de produtos sérios de chat (Slack, Discord). Backoff infinito é melhor UX que limite arbitrário — sempre que rede voltar, conexão volta. Long polling automático cobre redes corporativas que bloqueiam WebSocket (~5% dos casos em B2B saúde).

### ✅ NC-12 — Notificações ao atendente — RESOLVIDO (2026-05-11)

**Decisão**: in-app + som + browser push opt-in + agressivo em prioridade alta + config por usuário com defaults do tenant[^nc12-uxa].

- **Q12.a — Canais no MVP** (3 canais combinados):
  - **In-app**: badge contador na aba do navegador (`(3) Paciente360`), contador em sidebar da inbox, indicador visual na conversa nova.
  - **Som**: curto sino suave (~300ms) — som distinto para "nova mensagem" vs "conversa atribuída a mim".
  - **Browser push** (opt-in pelo usuário): notificação nativa do SO (Chrome/Firefox/Safari) mesmo se aba inativa. Requer permissão do navegador.
  - **E-mail digest**: **FORA do MVP** (entra em fase posterior se demanda real surgir; complica fluxo de retenção LGPD).
- **Q12.b — Som diferenciado**:
  - **Mensagem nova em conversa atribuída a mim**: som "ding" curto + único.
  - **Conversa atribuída/transferida para mim**: som "chime" duplo + alerta visual.
  - **Configurável por usuário**: volume (0–100%), on/off por tipo, escolha entre 2-3 presets de som.
- **Q12.c — Prioridade alta (placeholder Fase 4)**: notificação **agressiva** quando conversa tem `prioridade='alta'`:
  - Som persistente (3 repetições espaçadas 2s) **mesmo com aba inativa**.
  - Badge piscante vermelho.
  - Browser push **mesmo se atendente inativo**.
  - Banner persistente no topo da inbox até 1 clique para silenciar.
  - Cobre Princípio III — "detecção de urgência médica MUST escalar imediatamente".
  - **Esta fase**: apenas o mecanismo está pronto; nenhuma conversa é marcada `prioridade='alta'` automaticamente (Fase 4 IA detecta urgência). Atendente/Admin pode marcar manualmente como `alta` para testar o fluxo.
- **Q12.d — Escopo configuração**: **híbrido**:
  - **Admin Clínica define defaults** do tenant (ex.: "todos com som ON em prioridade alta").
  - **Cada usuário pode override** seu próprio perfil (on/off canais, volume, presets).
  - Configuração persiste por `(user_id, tenant_id)`.

[^nc12-uxa]: In-app + som + push cobre 99% dos casos sem custar e-mail. Som diferenciado evita confusão (msg nova vs atribuição). Prioridade alta justifica intrusão por motivo clínico (Princípio III).

### ✅ NC-13 — Filtros e busca na inbox — RESOLVIDO (2026-05-11)

**Decisão**: 7 filtros + full-text similarity em conteúdo + filtros ad-hoc compartilháveis via URL[^nc13-search].

- **Q13.a — Filtros no MVP** (7 dimensões combináveis em AND):
  1. **Canal**: `whatsapp | instagram | web` (multi-select).
  2. **Status**: `aberta | pendente | resolvida` (multi-select).
  3. **Atendente atribuído**: usuário do tenant ou "Sem atendente".
  4. **Profissional vinculado ao paciente**: lista de profissionais ativos (Fase 2 `profissional_responsavel_id`).
  5. **Tag do paciente** (Fase 2): seleção multi-tag.
  6. **Presença de mídia**: `tem_midia | sem_midia | qualquer`.
  7. **Idade da conversa**: `< 1h | 1-24h | 1-7d | > 7d` (calculada de `ultima_mensagem_at`).
  - Filtros aplicados via query string (`?canal=whatsapp&status=aberta&tag=vip`).
- **Q13.b — Busca por conteúdo**: **full-text com similaridade** usando `pg_trgm` (já habilitado na Fase 2):
  - Campos indexados: `paciente.nome`, `paciente.telefone_primario`, `mensagem.conteudo` (texto da mensagem; sem incluir mídia).
  - Query híbrida `WHERE (paciente_nome_normalizado % :q OR telefone_normalizado LIKE :q OR mensagem_conteudo_tsv @@ :q_tsquery) ORDER BY ...`.
  - **Meta de performance**: p95 < 500ms para 50.000 conversas/tenant.
  - Debounce client 350ms (igual busca de pacientes Fase 2).
  - Resultado mostra: conversa, paciente (se vinculado), trecho da mensagem com match destacado.
- **Q13.c — Filtros salvos**: **FORA do MVP**. Filtros ad-hoc são suficientes via URL com query string compartilhável (atendente copia URL e manda no Slack interno). Filtros salvos persistentes (entidade `filtros_salvos_inbox`) entram em fase posterior se demanda surgir.

[^nc13-search]: 7 filtros cobrem casos comuns de filtragem operacional (canal, atendente, urgência). Full-text com pg_trgm é reuso direto da infra da Fase 2 (extensão já habilitada) — zero custo de infra adicional. Filtros salvos é feature de poder, não de necessidade.

### ✅ NC-14 — Retenção e LGPD em mensagens — RESOLVIDO (2026-05-11)

**Decisão**: 2 anos default texto + 1 ano default mídia + anonimização granular (recebidas apagam, enviadas preservam)[^nc14-lgpd].

- **Q14.a — Retenção padrão de mensagens texto**: **2 anos**, alinhado com `audit_logs` da Fase 0 e mídia (NC-9). Configurável por tenant em `message_retention_months` (range **6–60 meses**). Job mensal move mensagens expiradas para "cold storage" (ou deleta conforme política do tenant; default é deletar conteúdo mas preservar metadata estrutural `[mensagem_arquivada]` para auditoria).
- **Q14.b — Cascateamento com anonimização do paciente (Fase 2 `anonimizado_em`)**: **anonimização granular** quando paciente é anonimizado:
  - **Mensagens recebidas** (do paciente para clínica): conteúdo é **apagado** e substituído por placeholder `[anonimizado em YYYY-MM-DD]`. Metadata estrutural (`tipo`, `timestamp`, `tem_midia`, `canal_id`) é **preservada** para rastreabilidade. Mídias recebidas são **deletadas** do S3.
  - **Mensagens enviadas** (da clínica para paciente): **preservadas integralmente** (Princípio I + legítimo interesse da clínica para defesa em casos legais). Substituição de variáveis dinâmicas (`{nome_paciente}`) que ficaram no texto **fica como está** (não retro-anonimiza dentro do texto enviado).
  - Mídias enviadas: **preservadas**.
  - Ação registrada em `audit_logs` com `action='paciente.mensagens_anonimizadas'`, payload `{paciente_id, recebidas_anonimizadas: int, enviadas_preservadas: int, midias_deletadas: int}`.
  - **Listener** que consome `PacienteAnonimizado` (Fase 2) dispara job assíncrono `AnonimizaMensagensDoPacienteJob` para aplicar a regra.
- **Q14.c — Retenção menor para mídia recebida**: **1 ano** (12 meses) default, configurável em `media_retention_months` (range 6–24).
  - Após expirar, mídia deletada do S3.
  - Referência na mensagem permanece como `[mídia removida por retenção em YYYY-MM-DD]`.
  - Mídias enviadas (da clínica) seguem a mesma regra (1 ano) — exceto se cliente configurar diferente.

[^nc14-lgpd]: 2 anos texto + 1 ano mídia balanceia operação (clínica precisa do histórico para retorno/defesa) com redução de superfície LGPD (mídia é PII densa). Anonimização granular respeita direito ao esquecimento do paciente sem destruir audit trail da clínica.

### ✅ NC-15 — Multi-número WhatsApp por tenant — RESOLVIDO (2026-05-11)

**Decisão**: permitido + limite por plano + conversas fixas no número de origem[^nc15-multi].

- **Permitido**: tenant pode conectar **múltiplos números WhatsApp** (ex.: "Agendamento", "Financeiro", "Plantão"). Cada número é um `canal_id` independente com seu próprio `messaging_service_sid` Twilio.
- **Filtro na inbox**: o filtro "canal" da NC-13 já cobre — atendente filtra entre números específicos quando quer focar.
- **Conversas NÃO migram entre números**: conversa criada no número A permanece sempre no número A. Se paciente envia mensagem para número B, **cria nova conversa** naquele canal (modelo stream contínuo da NC-2 aplica **por par `(paciente, canal_id)`**, não por par `(paciente, tipo)`). Resulta em entradas distintas na inbox.
- **Limite por plano** (extensão dos planos da Fase 0):
  - **Starter**: 1 número WhatsApp.
  - **Pro**: 3 números.
  - **Enterprise**: ilimitado.
  - Definição em `config/paciente360_plans.php` ou similar (`plan.whatsapp_numbers_max`). Conexão acima do limite retorna 402 com sugestão de upgrade.
- **Templates Meta** são por número (cada WABA tem seu próprio set aprovado). Sync separado por canal.
- **Quality Rating** é por número (Twilio surface por canal). Notificação direciona ao Admin Clínica com `canal_id` específico.

[^nc15-multi]: Modelo "cada número = canal independente" é o padrão Twilio e simplifica radicalmente a UX (sem confusão de migração). Limite por plano cria caminho de upsell natural sem bloquear casos comuns (1-3 números/clínica).

### ✅ NC-16 — Instagram multi-conta + comentários — RESOLVIDO (2026-05-11)

**Decisão**: multi-conta com limite por plano + comentários fora do MVP[^nc16-ig].

- **Q16.a — Múltiplas contas Instagram permitido** com limite por plano:
  - **Starter**: 1 conta Instagram.
  - **Pro**: 2 contas.
  - **Enterprise**: ilimitado.
  - Mesma semântica de canal independente que WhatsApp (NC-15): conversas NÃO migram entre contas.
- **Q16.b — Comentários em posts/reels/stories**: **FORA DO MVP**. Apenas **Direct Messages (DM)** nesta fase.
  - Razão: comentários envolvem semântica pública (visível a outros usuários do Instagram) vs. privada (DM), exigem moderação, podem virar viral, e têm fluxo de aprovação Meta separado.
  - Reforçar na seção "Fora de Escopo desta Fase" (já estava — mantém).
  - Item adicionado ao backlog para fase futura se demanda real surgir; provavelmente compõe com Fase 7 (Campanhas) ou módulo de "Gestão de Reputação Social" independente.

[^nc16-ig]: Multi-conta cobre casos de unidades distintas com brands separadas (raro mas existe). Comentários ficam fora — produto cresce para "atendimento clínico via DM", não para "Social Media Management".

### ✅ NC-17 — Compliance Meta operacional — RESOLVIDO (2026-05-11)

**Decisão**: cadastro pelo tenant + 2 templates pré-requisito + pausa de automáticos com manuais permitidos[^nc17-meta].

- **Q17.a — Cadastro de templates**: **responsabilidade do tenant** no Twilio Console / Meta Business Manager. CRM apenas **sincroniza read-only** via Twilio Content API e exibe lista na UI por canal (vide NC-4.c). Quickstart documenta tutorial passo-a-passo. Intermediação via API (CRUD completo de templates dentro do painel CRM) fica para **Fase 7 (Campanhas)** quando submissão programática faz sentido.
- **Q17.b — Templates pré-requisito para go-live** (não-bloqueante mas alertado):
  - **`boas_vindas`** — primeiro contato pós-janela 24h.
  - **`retorno_consulta`** — agendamento/lembrete (placeholder Fase 5).
  - **Comportamento na UI**: ao conectar canal WhatsApp, sistema verifica se ambos os templates existem aprovados no Twilio sync; se faltam, **banner amarelo persistente** na configuração do canal informa "⚠️ Recomendado cadastrar templates `boas_vindas` e `retorno_consulta` antes de operar em produção — ver tutorial". **Não bloqueia** conexão.
  - Listagem de templates **adicionais sugeridos** (não obrigatórios) também no quickstart: `confirmacao_consulta`, `cancelamento`, `recuperacao_inadimplente` (Fase 7).
- **Q17.c — Política de Quality Rating queda**:
  - **Trigger**: webhook Twilio reporta status `Low` ou `Flagged` no Quality Rating de um número.
  - **Ações automáticas**:
    1. **Notifica Admin Clínica imediatamente** (in-app + push) + e-mail (este é o único caso de e-mail no MVP de notificação).
    2. **Pausa envios automáticos**: IA da Fase 4 e disparos em massa da Fase 7 ficam desabilitados nesse canal até Quality voltar a `Medium`/`High`.
    3. **Mantém envios manuais** habilitados, mas com aviso intrusivo no campo de mensagem ao atendente: "⚠️ Quality Rating Low neste número — risco de suspensão Meta. Use **templates aprovados** sempre que possível. Evite enviar texto livre.".
    4. Marca canal com badge "⚠️ Atenção" na config para Admin ver.
  - **Recuperação**: quando Twilio reporta volta a `Medium` ou `High`, envios automáticos voltam, banner some, Admin notificado.
  - **Auditoria**: cada mudança de Quality Rating gera evento `audit_logs.action='canal.quality_rating_alterado'` com payload `{canal_id, rating_anterior, rating_novo}`.

[^nc17-meta]: Modelo "tenant cadastra direto" respeita o fato de que a aprovação Meta é externa ao CRM (1-3 dias async). Pausar só envios automáticos balanceia segurança (Princípio VI não-negociável) com operação humana — atendente humano com aviso explícito é responsabilidade compartilhada.

---

## 8. Success Criteria *(mandatory)*

Métricas mensuráveis, agnósticas de tecnologia, verificáveis sem detalhes de implementação:

- **SC-001**: Atendente envia primeira mensagem para paciente em **menos de 30 segundos** após receber notificação de nova conversa atribuída.
- **SC-002**: Mensagem enviada chega ao paciente em **p95 < 2 segundos** (lado servidor) — RNF-001.
- **SC-003**: Inbox suporta **1.000 conversas simultâneas** abertas em um tenant sem degradação perceptível — RNF-003.
- **SC-004**: 100% dos endpoints autenticados desta fase passam no `TenantIsolationTest` expandido (cross-tenant retorna 404/403, nunca 200).
- **SC-005**: 100% de webhooks duplicados (Meta retry) resultam em **0 mensagens duplicadas** persistidas — idempotência por `message_id`.
- **SC-006**: 0 envios de texto livre realizados fora da janela 24h do WhatsApp sem template aprovado — bloqueio runtime garantido (Princípio VI não-negociável).
- **SC-007**: 0 vazamentos de conteúdo de mensagem em logs estruturados de aplicação (logs contém apenas metadados).
- **SC-008**: Atendente alterna entre 3 canais (WhatsApp + Instagram + Web) sem trocar de aba/ferramenta.
- **SC-009**: Financeiro e Super Admin recebem **403** em 100% das tentativas de acessar inbox ou mensagens.
- **SC-010**: Cobertura de testes da fase ≥ 75%; cobertura global após esta fase ≥ 70%.
- **SC-011**: 0 strings hardcoded fora de pt-BR em UI, e-mail e widget novos.
- **SC-012**: Mensagem enviada por atendente A aparece para atendente B (mesma inbox) em **p95 < 2 segundos** (RNF-001 + RNF-003).

---

## 9. Definição de Pronto desta Fase

Checklist verificável antes de declarar Fase 3 entregue:

- [ ] Todas as 7 US (4.1 a 4.7) implementadas, testadas e mescladas em `main`.
- [ ] Todos os 17 NEEDS_CLARIFICATION resolvidos via `/speckit.clarify` e refletidos no spec.
- [ ] Todos os ACs numerados (AC-4.x.y) cobertos por pelo menos 1 teste automatizado.
- [ ] `TenantIsolationTest` expandido cobrindo 100% dos endpoints novos.
- [ ] 13 eventos de domínio emitidos conforme contrato e gravados em `audit_logs`.
- [ ] 8 abilities Spatie aplicadas e validadas (incluindo 403 para Financeiro/Super Admin).
- [ ] Cobertura ≥ 75%, global ≥ 70%.
- [ ] Pint clean; OpenAPI drift exit 0; Scribe gerado.
- [ ] Webhooks Meta WhatsApp + Instagram com assinatura validada e idempotência testada.
- [ ] Widget JS funciona em pelo menos 3 sites externos (smoke manual).
- [ ] Tempo real (WebSocket) testado com 2 atendentes simultâneos por > 5 minutos sem desconexão indevida.
- [ ] Pelo menos 1 jornada E2E (Playwright) cobrindo: paciente envia WhatsApp → atendente vê na inbox → responde → status leitura.
- [ ] Quickstart atualizado com seções de "Conectar canais" (Meta sandbox), "Testar webhook localmente" (ngrok), "Embedar widget".
- [ ] LGPD review: conteúdo de mensagem nunca em log de app; retenção configurada conforme NC-14.

---

## 10. Riscos e Mitigações

| Risco | Severidade | Mitigação |
|-------|------------|-----------|
| **R1 — Janela 24h WhatsApp**: envio bloqueado fora dela. Atendente perde produtividade tentando responder mensagens antigas. | 🔴 Alta | UI distingue conversas dentro/fora da janela com badge; força seleção de template aprovado; documenta no quickstart. NC-4 define UX exata. |
| **R2 — Rate limits Twilio + Meta**: Twilio cobra por segmento + Meta tem tier inicial 1.000 msgs/dia/número. Excesso → conta WABA suspensa. Twilio também tem rate limits próprios (REST API: 5 req/s por account em sandbox; mais em produção). | 🔴 Alta | Throttle interno por tenant + tier do número (rastrear quando tenant subiu de tier Meta). Buffer de fila no envio para respeitar 5 req/s Twilio. Audit + alerta em-app quando 80% do quota atingido. Documentar custo extra Twilio para admin no painel. |
| **R3 — Rate limits Instagram Graph API**: 200 chamadas/hora/usuário no início. | 🟡 Média | Backoff exponencial; queue de envio com retry; monitoramento. |
| **R4 — Latência WebSocket sob carga (1000 conversas/tenant)**: broadcast de cada msg para todos os subscribers vira gargalo. | 🔴 Alta | **Salas híbridas** (NC-11 resolvido): sala por tenant para eventos leves, sala por conversa para mensagens detalhadas; atendente só recebe payload de conversas que está olhando. Stress test obrigatório com 1000 conversas e 20 atendentes simultâneos antes do go-live. |
| **R5 — Webhook Twilio entrega duplicada**: Twilio retry quando timeout > 15s no ack (vs 5s Meta direto). | 🔴 Alta | Tabela `webhook_events` com PK `MessageSid` (Twilio) ou `message_id` (Graph); INSERT ... ON CONFLICT DO NOTHING — padrão idêntico ao Stripe webhook Fase 0. |
| **R6 — Webhook Twilio perdido**: indisponibilidade do nosso endpoint causa Twilio dar up depois de N retries. | 🟡 Média | Sync periódico via Twilio Messages API (`GET /Messages`) como reconciliação; alerta em-app se gap detectado nos últimos 30 min. |
| **R7 — LGPD em mídia recebida**: paciente envia foto de receita/exame/doc com PII de terceiros. | 🔴 Alta | Armazenamento S3 criptografado AES-256 em repouso (NC-9 resolvido); URLs assinadas 24h; controle de acesso por tenant; preview-once com aviso explícito; visualização registrada em audit_logs por (atendente, conversa); retenção 2 anos com cascateamento na anonimização (NC-14). |
| **R8 — Vazamento de conteúdo de mensagem em logs**: framework default loga payloads. | 🔴 Alta | Override de Monolog para filtrar campos de mensagem; audit em `audit_logs` registra metadata sem conteúdo (FR-037). |
| **R9 — Race condition na atribuição automática**: 2 conversas chegam simultaneamente, mesmo atendente escolhido para ambas. | 🟡 Média | Lock pessimista no service de atribuição. |
| **R10 — Quality Rating drop suspende canal**: clínica fica sem WhatsApp do dia para a noite. Twilio expõe o status via webhook próprio. | 🔴 Alta | Webhook de status do Twilio → notifica admin imediatamente; pausa envios automáticos (Fase 4+); permite envios manuais com aviso. NC-17. |
| **R11 — Widget JS afeta performance do site do cliente**: tamanho > 100KB derruba Core Web Vitals. | 🟡 Média | Tamanho-alvo do snippet < 30KB minificado; carregamento async; documentar impacto. |
| **R12 — CSP do site cliente bloqueia widget**: site usa Content Security Policy estrita. | 🟡 Média | Documentar headers necessários; oferecer modo "iframe isolado" como fallback. |
| **R13 — Mensagens sem paciente vinculado acumulam em "Sem identificação"**: opera com falta de contexto. | 🟢 Baixa | UX permite vincular manualmente; relatório de "leads não identificados". |
| **R14 — Tenant suspenso recebe mensagens em background**: pacientes mandam, ninguém responde, frustração. | 🟡 Média | Documentar para tenant; auto-resposta opcional "fora do ar" (Fase 7). |
| **R15 — Contrato com Fase 4 instável**: como IA consome `ia_pausada_ate` muda. | 🟡 Média | Definir interface formal (`ConversaIATogglingContract`); testes de contrato. |

---

## 11. Princípios da Constituição Tocados por Cada US

| US     | Princípios                                                                                                          | Como toca                                                                                                                                                            |
|--------|---------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| US-4.1 | **I (LGPD)**, **II (Multi-tenant)**, **V (Observabilidade)**, **VI (Conformidade Meta)**, **VII (Segurança)**       | Conecta canal Meta com tokens long-lived encriptados; auditoria de conexão; webhook idempotente; rate limits.                                                       |
| US-4.2 | **I**, **II**, **V**, **VI**, **VII**                                                                               | Igual US-4.1 com Graph API.                                                                                                                                          |
| US-4.3 | **I**, **II**, **VII**                                                                                              | Widget JS exposto publicamente — autenticação por chave pública + (opcional) domínio whitelist. Sem Meta nesta US.                                                  |
| US-4.4 | **I**, **II**, **V**                                                                                                | Inbox é o ponto central — isolamento entre tenants no broadcast; logs estruturados; LGPD em conteúdo de mensagem.                                                   |
| US-4.5 | **II**, **V**, **VII**                                                                                              | Atribuição/transferência cross-user dentro do tenant; audit; lock contra race condition.                                                                            |
| US-4.6 | **III (IA — preparação)**, **V**                                                                                    | Mecanismo de pausa/retomada é **contrato para Fase 4** cumprir Princípio III (auditabilidade da IA). Eventos rastreáveis.                                            |
| US-4.7 | **II**                                                                                                              | Templates escopados ao tenant.                                                                                                                                       |

A fase **não exercita ativamente** Princípios **III** (IA — só prepara o terreno) nem **IV** (Spec-Driven; já é gate de processo, não de implementação).

---

## 12. Índice de Critérios de Aceitação (referência para `tasks.md`)

Total: **47 ACs numerados** com severidade indicada.

### US-4.1 (10 ACs)
🔴 AC-4.1.1 (conexão Meta) · 🔴 AC-4.1.2 (validação Business) · 🔴 AC-4.1.3 (webhook processa) · 🔴 AC-4.1.4 (idempotência webhook) · 🟡 AC-4.1.5 (status visível) · 🟡 AC-4.1.6 (reenvio handshake) · 🟡 AC-4.1.7 (templates sync) · 🟢 AC-4.1.8 (Quality Rating notif) · 🔴 AC-4.1.9 (auditoria conexão) · 🟡 AC-4.1.10 (desconexão).

### US-4.2 (7 ACs)
🔴 AC-4.2.1 (OAuth Facebook) · 🔴 AC-4.2.2 (rejeita pessoal) · 🔴 AC-4.2.3 (webhook DM) · 🔴 AC-4.2.4 (idempotência) · 🟡 AC-4.2.5 (janela 24h doc) · 🟡 AC-4.2.6 (status idem) · 🟢 AC-4.2.7 (comentários fora).

### US-4.3 (8 ACs)
🔴 AC-4.3.1 (snippet) · 🔴 AC-4.3.2 (anônimo inicia) · 🔴 AC-4.3.3 (coleta nome+tel opcional) · 🔴 AC-4.3.4 (entra na inbox) · 🟡 AC-4.3.5 (horário) · 🟡 AC-4.3.6 (auth) · 🟡 AC-4.3.7 (tempo real widget) · 🟢 AC-4.3.8 (upload mídia).

### US-4.4 (12 ACs)
🔴 AC-4.4.1 (lista unificada) · 🔴 AC-4.4.2 (tempo real < 2s) · 🔴 AC-4.4.3 (histórico) · 🔴 AC-4.4.4 (responder) · 🔴 AC-4.4.5 (bloqueia fora janela) · 🔴 AC-4.4.6 (typing) · 🔴 AC-4.4.7 (read receipt) · 🟡 AC-4.4.8 (filtros) · 🟡 AC-4.4.9 (busca) · 🟡 AC-4.4.10 (presença atendente) · 🟢 AC-4.4.11 (cross-tab sync) · 🔴 AC-4.4.12 (isolamento broadcast).

### US-4.5 (9 ACs)
🔴 AC-4.5.1 (manual) · 🔴 AC-4.5.2 (automática) · 🔴 AC-4.5.3 (transferir+nota) · 🔴 AC-4.5.4 (histórico) · 🟡 AC-4.5.5 (notificação) · 🟡 AC-4.5.6 (cross-tenant) · 🟡 AC-4.5.7 (perfil) · 🟡 AC-4.5.8 (limite) · 🟢 AC-4.5.9 (reassign offline).

### US-4.6 (8 ACs)
🔴 AC-4.6.1 (assume manual) · 🔴 AC-4.6.2 (implícito) · 🔴 AC-4.6.3 (retomada auto) · 🔴 AC-4.6.4 (retomada manual) · 🟡 AC-4.6.5 (badge UI) · 🟡 AC-4.6.6 (audit) · 🟢 AC-4.6.7 (config duração) · 🟢 AC-4.6.8 (stub IA).

### US-4.7 (6 ACs)
🔴 AC-4.7.1 (CRUD) · 🔴 AC-4.7.2 (atalho) · 🔴 AC-4.7.3 (variáveis) · 🟡 AC-4.7.4 (Meta vs rápidas) · 🟡 AC-4.7.5 (mídia em rápidas) · 🟢 AC-4.7.6 (estatísticas).

**Distribuição de severidade**: 🔴 26 críticos · 🟡 16 importantes · 🟢 5 nice-to-have. Total **47**.

---

## 13. Assumptions

Itens decididos por inferência razoável (não levantados como NEEDS_CLARIFICATION):

- **Token Meta long-lived** criptografado em repouso usando AES-256 do banco/cofre (decisão final em `plan.md`).
- **Mensagens > 4000 chars** rejeitadas no UI (limite WhatsApp 4096) — split automático não implementado no MVP.
- **Limites de mensagem por dia (Meta tier inicial 1.000/dia/número)** documentados no quickstart; throttle interno respeita.
- **Token public do widget** rotacionável manualmente pelo admin; sem rotação automática no MVP.
- **Timezone** continua `America/Sao_Paulo` para horário de funcionamento do widget.
- **WebSocket** roda em mesmo domínio do app principal (ou subdomain `ws.*`); fallback se bloqueado é aviso UI ("modo offline").
- **Notificação browser push** opt-in pelo usuário; sem push mobile nesta fase.
- **Atendente é considerado "online"** se tem sessão ativa nos últimos 5 minutos OU se está com inbox aberta no WebSocket. Política fina em NC-6.
- **Mensagem do paciente NUNCA é sanitizada** (preserva fidelidade do que foi dito); apenas conteúdo de **logs de aplicação** filtra.
- **Mídia recebida** armazenada com URL temporária assinada (24h) na UI; backend tem URL permanente até retenção (NC-14).
- **Mensagens enviadas pela clínica** podem ser reenviadas se status `falhou`, mas não editadas após sucesso.
