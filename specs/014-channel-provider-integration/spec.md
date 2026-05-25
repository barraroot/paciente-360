# Feature Specification: Integração de Canal WhatsApp — Twilio (Oficial) ou Evolution API (Não Oficial)

**Feature Branch**: `014-channel-provider-integration`
**Created**: 2026-05-25
**Status**: Draft
**Input**: User description: "precisamos criar a tela de configuração da integração do twilio para cada clínica e além de ter essa integração do twilio vamos dar a opção da clínica integrar com a evolution api 2 (...) o cliente poderá escolher entre integração oficial com whatsapp através da twilio ou integração não oficial através da evolution api, além do serviço novo no backend para evolution api precisamos do front para ele configurar essas conexões."

## Visão Geral

Hoje a conexão de WhatsApp da clínica depende exclusivamente do provedor **oficial** (Twilio / WhatsApp Business Cloud API) e não há uma tela dedicada onde a própria clínica configure essa conexão de forma autônoma. Esta feature entrega: (1) uma **tela de configuração de canal** onde cada clínica gerencia sua conexão de WhatsApp; e (2) a **liberdade de escolher o provedor** — manter a integração **oficial via Twilio** ou optar por uma integração **não oficial via Evolution API 2** (conexão por leitura de QR Code, sem custo de plataforma oficial).

O valor: clínicas com restrição de custo ou sem conta WhatsApp Business aprovada podem operar o canal imediatamente pela via não oficial; clínicas que exigem conformidade e estabilidade seguem na via oficial. A escolha é da clínica, com os trade-offs apresentados de forma transparente.

## Clarifications

### Session 2026-05-25

- Q: Conformidade na via não oficial (Evolution/Baileys não possui templates HSM aprovados pela Meta): como as notificações outbound (confirmações, alertas) se comportam quando o provedor ativo é o não oficial? → **A: Conservador — fora da janela de 24h, o provedor não oficial NÃO envia proativos; a notificação cai em "pendente de contato manual" (mesmo espírito do Princípio VI). Dentro da janela, envio de texto livre é permitido. Opt-out e debounce continuam valendo. Não se burla a regra da via oficial.**
- Q: Coexistência de provedores: uma clínica pode ter Twilio e Evolution ativos ao mesmo tempo, ou apenas um provedor de WhatsApp ativo por vez? → **A: Um provedor de WhatsApp ativo por clínica por vez. Trocar de provedor exige desconectar o atual antes de conectar o outro — elimina ambiguidade de roteamento.**
- Q: Escopo de mensagens nesta feature: entregar apenas a configuração/ciclo de vida da conexão, ou já incluir o tráfego completo (recebimento + envio) de mensagens pelo provedor não oficial integrado à inbox existente? → **A: Paridade completa — o provedor não oficial já recebe (inbound → inbox) e envia (outbound + notificações) integrado à mensageria existente nesta feature.**
- Q: A "paridade completa" inclui mídia ou só texto? → **A: Texto + mídia (imagem/áudio/documento), recebida e enviada — paridade com o que o canal oficial (Twilio) já suporta hoje. O adapter Evolution espelha a capacidade de mídia existente da inbox (Fase 3).**
- Q: Comportamento das mensagens de saída "em voo" quando a sessão cai ou o provedor é trocado? → **A: Reusar a fila/retry existente; se a sessão estiver desconectada no envio, a tentativa falha e (sendo notificação) cai em `pending_manual` — nada é perdido em silêncio. NÃO há re-roteamento automático entre provedores (coerente com "um provedor ativo por vez").**

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Clínica conecta WhatsApp oficial (Twilio) pela tela de configuração (Priority: P1)

A administradora da clínica acessa a tela de Configurações → Canais, escolhe "WhatsApp Oficial (Twilio)", informa as credenciais da sua conta e conecta. A tela confirma o status "conectado" e o número fica pronto para enviar/receber.

**Why this priority**: Formaliza e dá autonomia ao que hoje é o único caminho suportado, validando ponta-a-ponta o modelo de "canal com provedor" e a tela de configuração — base para os demais provedores.

**Independent Test**: Em uma clínica sem canal, escolher Twilio, inserir credenciais válidas e verificar que a conexão fica "conectada" e aparece na lista de canais; credenciais inválidas exibem erro claro sem conectar.

**Acceptance Scenarios**:

1. **Given** uma clínica sem canal de WhatsApp, **When** a admin escolhe "WhatsApp Oficial (Twilio)" e informa credenciais válidas, **Then** o canal é criado com status "conectado" e identidade do número exibida.
2. **Given** credenciais inválidas, **When** a admin tenta conectar, **Then** nenhuma conexão é criada e uma mensagem de erro orientativa é exibida.
3. **Given** um canal Twilio conectado, **When** a admin abre a tela de canais, **Then** vê o provedor, o status e a data da última verificação de saúde.

---

### User Story 2 - Clínica conecta WhatsApp não oficial (Evolution API) por QR Code (Priority: P1)

A administradora escolhe "WhatsApp Não Oficial (Evolution API)", a tela exibe um **QR Code**, ela o escaneia no aplicativo WhatsApp do celular da clínica e a conexão muda para "conectado" automaticamente assim que o pareamento conclui.

**Why this priority**: É a principal novidade de produto — habilita clínicas sem conta oficial. Entrega valor imediato e independente da US1.

**Independent Test**: Escolher Evolution API, ver o QR Code renderizado, simular o pareamento e confirmar que o status transiciona para "conectado"; QR expirado pode ser regenerado.

**Acceptance Scenarios**:

1. **Given** uma clínica escolhendo o provedor não oficial, **When** a conexão é iniciada, **Then** um QR Code válido é exibido com instruções de escaneamento.
2. **Given** o QR Code exibido, **When** o pareamento é concluído no celular, **Then** o status da conexão transiciona para "conectado" sem ação adicional do usuário.
3. **Given** um QR Code expirado sem pareamento, **When** a admin solicita novo código, **Then** um QR Code novo é gerado e o anterior é invalidado.
4. **Given** uma sessão não oficial conectada, **When** a admin visualiza o canal, **Then** vê claramente o aviso de que é uma integração **não oficial** e seus riscos (possível bloqueio pelo WhatsApp).

---

### User Story 3 - Clínica acompanha status e reconecta/desconecta o canal (Priority: P2)

A administradora vê, em tempo quase real, o estado da conexão (conectado, conectando, desconectado) e pode **desconectar** ou **reconectar** o canal quando necessário — por exemplo, quando a sessão não oficial cai e precisa reparear.

**Why this priority**: Conexões não oficiais caem com frequência; sem visibilidade e ação de reconexão, o canal fica "morto" silenciosamente. Vale para ambos os provedores.

**Independent Test**: Com um canal conectado, desconectar e verificar status "desconectado"; reconectar e verificar retorno ao fluxo de pareamento/conexão apropriado ao provedor.

**Acceptance Scenarios**:

1. **Given** um canal conectado, **When** a sessão é perdida, **Then** a tela reflete o status "desconectado" e oferece a ação de reconectar.
2. **Given** um canal conectado, **When** a admin escolhe desconectar, **Then** a conexão é encerrada e o status fica "desconectado".
3. **Given** um canal não oficial desconectado, **When** a admin reconecta, **Then** um novo QR Code é apresentado para reparear.

---

### User Story 4 - Mensagens fluem pelo provedor conectado (Priority: P2)

Independentemente do provedor escolhido pela clínica, as mensagens recebidas dos pacientes chegam na inbox existente e as mensagens enviadas (incluindo as notificações automáticas) saem pelo provedor ativo daquela clínica.

**Why this priority**: A conexão só tem valor se as mensagens efetivamente trafegam. Reaproveita a inbox e o mecanismo de notificações já existentes, agora com provedor selecionável.

**Independent Test**: Com cada provedor conectado, enviar uma mensagem de teste e confirmar que sai pelo provedor correto; simular o recebimento e confirmar que aparece na inbox da clínica certa.

**Acceptance Scenarios**:

1. **Given** uma clínica com provedor não oficial conectado, **When** um paciente envia mensagem, **Then** ela aparece na inbox da clínica vinculada ao canal correto.
2. **Given** uma clínica com provedor X ativo, **When** o sistema envia uma mensagem de saída, **Then** ela é roteada pelo provedor X (nunca pelo provedor de outra clínica).
3. **Given** uma notificação automática a ser enviada **fora** da janela de 24h, **When** o provedor ativo é o não oficial, **Then** o envio proativo é bloqueado e a notificação vira "pendente de contato manual" (dentro da janela, texto livre é enviado normalmente).

---

### Edge Cases

- **Sessão não oficial cai sozinha**: o status reflete "desconectado" e a clínica é alertada para reparear. Mensagens de saída em voo seguem a fila/retry existente; se a sessão estiver desconectada no momento do envio, a tentativa falha e (sendo notificação) cai em "pendente de contato manual" — nada é perdido em silêncio; não há re-roteamento automático para outro provedor.
- **Troca de provedor**: como só há um provedor ativo por vez, trocar exige desconectar o provedor atual antes de conectar o novo; a tela orienta esse fluxo.
- **Credenciais/QR vazando entre clínicas**: credenciais e sessões são estritamente isoladas por clínica — nunca reutilizadas ou visíveis por outra clínica.
- **QR Code expira**: regenerável; o anterior deixa de valer.
- **Provedor não oficial indisponível** (serviço fora do ar): a tela mostra estado degradado/erro e não promete falsamente "conectado".
- **Número já conectado em outra sessão**: o sistema informa o conflito em vez de falhar de forma opaca.
- **Envio fora da janela de 24h no não oficial**: proativos são bloqueados → "pendente de contato manual"; sem burlar as regras da via oficial.

## Requirements *(mandatory)*

### Functional Requirements

**Tela de configuração e escolha de provedor**
- **FR-001**: O sistema MUST oferecer, por clínica, uma tela onde a administradora visualiza, cria e gerencia a(s) conexão(ões) de WhatsApp.
- **FR-002**: O sistema MUST permitir escolher o provedor da conexão entre **oficial (Twilio)** e **não oficial (Evolution API)**.
- **FR-003**: O sistema MUST apresentar, no momento da escolha do provedor não oficial, um aviso claro de que se trata de integração **não oficial**, com seus riscos (possível suspensão pelo WhatsApp) e ausência de garantias do canal oficial.

**Conexão via provedor oficial (Twilio)**
- **FR-004**: O sistema MUST permitir conectar o canal oficial mediante as credenciais da conta da clínica, validando-as antes de marcar como "conectado".
- **FR-005**: O sistema MUST exibir o status da conexão oficial e a identidade do número conectado.

**Conexão via provedor não oficial (Evolution API)**
- **FR-006**: O sistema MUST permitir iniciar uma conexão não oficial que apresente um **QR Code** (e/ou código de pareamento) para a clínica escanear no WhatsApp.
- **FR-007**: O sistema MUST atualizar o status da conexão não oficial automaticamente conforme o pareamento progride (ex.: aguardando, conectando, conectado, desconectado).
- **FR-008**: O sistema MUST permitir regenerar o QR Code quando o anterior expirar sem pareamento.

**Ciclo de vida e status**
- **FR-009**: O sistema MUST exibir o estado atual de cada conexão (conectado, conectando, desconectado, erro) com atualização tempestiva.
- **FR-010**: O sistema MUST permitir desconectar e reconectar uma conexão pela tela.
- **FR-011**: O sistema MUST detectar a queda de uma sessão (especialmente não oficial) e refletir o estado real, alertando para reconexão.

**Mensagens**
- **FR-012**: O sistema MUST rotear as mensagens de saída de uma clínica pelo provedor ativo daquela clínica.
- **FR-013**: O sistema MUST entregar as mensagens recebidas pelo provedor à inbox da clínica correta, vinculadas ao canal de origem.
- **FR-014**: No provedor não oficial, o sistema MUST permitir envio de texto livre **dentro** da janela de 24h e MUST **bloquear** envios proativos **fora** da janela, roteando a notificação para "pendente de contato manual" (consistente com o tratamento do canal oficial sem template). Opt-out e debounce MUST continuar sendo aplicados. Cada tentativa e seu resultado MUST ser registrados.

**Isolamento, segurança e privacidade**
- **FR-015**: O sistema MUST isolar credenciais, sessões, QR Codes e canais estritamente por clínica — nunca compartilhados ou visíveis entre clínicas.
- **FR-016**: O sistema MUST armazenar credenciais de provedor e segredos de sessão de forma protegida (cifrados em repouso), nunca expostos em logs ou na interface após o cadastro.
- **FR-017**: O sistema MUST restringir a configuração de canais a perfis autorizados da clínica (administração do canal).

**Coexistência e escopo**
- **FR-018**: O sistema MUST permitir apenas **um provedor de WhatsApp ativo por clínica por vez**; iniciar a conexão de um novo provedor MUST exigir que o provedor anterior esteja desconectado.
- **FR-019**: Esta feature MUST entregar **paridade completa** de tráfego para o provedor não oficial: recebimento (inbound → inbox) e envio (outbound + notificações automáticas) integrados à mensageria existente, incluindo **mídia** (imagem/áudio/documento) na mesma medida em que o canal oficial já a suporta.

**Observabilidade e operação**
- **FR-020**: O sistema MUST registrar eventos auditáveis de conexão/desconexão/troca de provedor por clínica (sem expor segredos).
- **FR-021**: O sistema MUST expor métricas operacionais do estado das conexões (ex.: conexões ativas, quedas, reconexões) para acompanhamento.

### Key Entities *(include if feature involves data)*

- **Canal de WhatsApp da Clínica**: a conexão de mensageria de uma clínica. Atributos-chave: provedor (oficial/não oficial), status (conectado/conectando/desconectado/erro), identidade do número, datas de saúde/conexão. Já existe e passa a carregar a dimensão de **provedor**.
- **Credenciais/Sessão do Provedor**: segredos necessários para operar a conexão (credenciais da conta oficial; chave/identificador e segredo de sessão do não oficial). Pertencem a uma clínica; cifrados; nunca compartilhados.
- **Instância/Sessão Não Oficial**: a sessão de WhatsApp pareada via QR Code no provedor não oficial, com seu estado de conexão. Pertence a uma clínica.
- **Conversa / Mensagem**: threads e mensagens já existentes; passam a poder originar/trafegar por qualquer provedor conectado da clínica.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Uma clínica consegue conectar um canal de WhatsApp (oficial ou não oficial) pela tela, sem suporte técnico, em menos de 5 minutos.
- **SC-002**: 100% das conexões não oficiais exibem o aviso de "integração não oficial e riscos" antes de conectar.
- **SC-003**: 0 vazamentos entre clínicas: nenhuma credencial, sessão, QR Code ou mensagem de uma clínica é acessível/roteada por outra (verificado por teste de isolamento).
- **SC-004**: 100% das mensagens de saída de uma clínica são roteadas pelo provedor ativo correto daquela clínica.
- **SC-005**: A queda de uma sessão é refletida na tela em até 1 minuto, com ação de reconexão disponível.
- **SC-006**: 0 segredos de provedor aparecem em logs ou são retornados à interface após o cadastro (verificado por inspeção automatizada).
- **SC-007**: O estado real da conexão (conectado/desconectado) corresponde ao estado exibido em ≥ 99% das verificações.

## Assumptions

- **Reuso da inbox e do envio existentes**: a inbox omnichannel e o mecanismo de envio/notificações já existem; esta feature adiciona um **provedor selecionável**, não reimplementa inbox.
- **Provedor oficial já operante**: a integração oficial (Twilio) já funciona no backend; esta feature adiciona a **tela de configuração** e o modelo de provedor, sem quebrar canais oficiais existentes (que assumem provedor "oficial" por padrão).
- **Ambiente de teste do provedor não oficial**: para desenvolvimento/QA, o serviço não oficial será provisionado no ambiente de testes (containerizado), sem afetar produção — detalhe de ambiente tratado no planejamento, não é requisito de produto.
- **Documentação do provedor não oficial**: as capacidades e o modelo de conexão do provedor não oficial (conexão por QR Code/sessão, eventos de status) seguem a documentação oficial do produto, consultada no planejamento.
- **Perfil autorizado**: a administração de canais é restrita ao perfil já usado para conectar canais (administração da clínica).
- **Conformidade da via oficial intacta**: nada nesta feature relaxa as regras de conformidade do canal **oficial** (janela de 24h + template aprovado permanecem como hoje).

## Dependencies

- Domínio de mensageria existente (canais, conversas, mensagens, inbox, envio de saída e notificações outbound).
- Mecanismo de credenciais cifradas e isolamento por clínica já existentes.
- Disponibilidade de um serviço do provedor não oficial acessível pelo backend (provisionado em teste/produção conforme planejamento).

## Out of Scope

- Submissão/aprovação de templates na plataforma oficial (Meta) — permanece como hoje.
- Novos canais além de WhatsApp (Instagram, web) nesta feature.
- Motor de IA / fluxos conversacionais.
- Migração de dados históricos entre provedores.
- Garantia de estabilidade/uptime da via não oficial (é, por natureza, best-effort e sujeita a bloqueio pelo WhatsApp).
