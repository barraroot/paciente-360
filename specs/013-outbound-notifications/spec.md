# Feature Specification: Entrega de Notificações Outbound

**Feature Branch**: `013-outbound-notifications`
**Created**: 2026-05-24
**Status**: Draft
**Input**: User description: "Entrega de Notificações Outbound — conectar os listeners que hoje são log-stubs ao envio REAL de mensagens para o paciente via canais já conectados (WhatsApp Business Cloud API e Instagram Direct), fechando débitos das Fases 5, 7 e 8."

## Visão Geral

Hoje, vários eventos de domínio (consulta agendada, receituário próximo do vencimento, vaga aberta na lista de espera) disparam ações que **deveriam** notificar o paciente, mas que apenas **registram um log** — a mensagem nunca chega. Toda a infraestrutura de canais conectados (WhatsApp, Instagram) e o mecanismo de envio de mensagens já existem; falta a camada que **decide para qual canal enviar, com qual mensagem aprovada, respeitando as regras da plataforma e a privacidade do paciente**.

Esta feature entrega essa camada: transforma os "avisos que só logam" em **mensagens reais que chegam ao paciente**, cumprindo a promessa de "confirmações e lembretes automáticos" que sustenta a redução de no-show e a reativação de pacientes.

## Clarifications

### Session 2026-05-24

- Q: Como uma notificação "pendente de contato manual" deve aparecer e ser acionável para a equipe? → A: Reusar a inbox existente — usar/abrir a Conversa do paciente e inserir uma mensagem de sistema ("Notificação não entregue — contatar manualmente: <motivo>"), com a conversa sinalizada (prioridade/tag); sem modelo de tarefa novo.
- Q: Para envios proativos (fora da janela 24h), qual a política de canal quando o paciente não tem WhatsApp elegível? → A: Proativo fora da janela é WhatsApp-only (único canal Meta com template HSM); Instagram só dentro da janela; sem WhatsApp elegível → pendente de contato manual.
- Q: Até que ponto rastrear o estado de entrega de cada notificação? → A: Reconciliar "entregue"/"falhou" a partir dos callbacks de status do provedor já recebidos (Fase 3); falha definitiva aciona fallback manual; "lido" não é rastreado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Paciente recebe a confirmação da consulta (Priority: P1)

Quando uma consulta é agendada, o paciente recebe, no canal em que se comunica com a clínica (WhatsApp), uma mensagem de confirmação pedindo que confirme presença — nos marcos T-24h e T-2h. Hoje esse aviso só é registrado internamente e nunca sai.

**Why this priority**: É o maior gerador de valor imediato (redução de no-show) e o caminho que valida ponta-a-ponta toda a cadeia de entrega (resolução de canal + template + janela + rastreio). Entregar só isto já é um MVP que comprova a feature.

**Independent Test**: Agendar uma consulta para um paciente com WhatsApp conectado e template de confirmação configurado; verificar que a mensagem de confirmação é efetivamente enviada (sai da clínica, status "enviado"), e que a resposta do paciente reabre/atualiza a conversa na inbox.

**Acceptance Scenarios**:

1. **Given** um paciente com telefone WhatsApp válido e o template de confirmação configurado pela clínica, **When** chega o marco T-24h da consulta, **Then** a mensagem de confirmação é enviada ao paciente e a notificação fica com status "enviada".
2. **Given** a confirmação T-24h já enviada, **When** chega o marco T-2h, **Then** uma segunda confirmação é enviada (marcos distintos não são deduplicados entre si).
3. **Given** o paciente respondeu "confirmo" dentro da janela, **When** a resposta é recebida, **Then** a consulta é confirmada e nenhuma confirmação adicional redundante é enviada.
4. **Given** o paciente desativou notificações para aquele profissional, **When** chega o marco de confirmação, **Then** nenhuma mensagem é enviada e a notificação fica "ignorada" com motivo "opt-out".

---

### User Story 2 - Paciente é avisado do receituário vencendo (Priority: P2)

O paciente com receituário próximo do vencimento recebe um lembrete (nos marcos D-15, D-7, D-1) sugerindo renovar/retornar, sem expor dados clínicos sensíveis na mensagem.

**Why this priority**: Reativa pacientes e gera retorno de receita; reusa toda a cadeia de entrega da US1, mas com regras próprias de cadência, opt-out e debounce.

**Independent Test**: Ter um receituário a 7 dias do vencimento + template de alerta configurado; rodar o processamento de alertas; verificar que o lembrete é enviado, sem nome de medicamento/posologia na mensagem, e que um reenvio dentro de 4h é suprimido (debounce).

**Acceptance Scenarios**:

1. **Given** um receituário a 7 dias do vencimento e o paciente sem opt-out, **When** o alerta D-7 é processado, **Then** o lembrete é enviado e não contém dados clínicos (nome do medicamento, posologia, diagnóstico).
2. **Given** um alerta já enviado ao mesmo paciente há menos de 4h, **When** outro alerta é processado, **Then** o segundo é suprimido com motivo "debounce".
3. **Given** o paciente marcou opt-out de notificações de renovação, **When** o alerta é processado, **Then** nada é enviado (motivo "opt-out") — mas o evento de domínio ainda é registrado para auditoria.

---

### User Story 3 - Paciente recebe oferta de vaga da lista de espera (Priority: P2)

Quando uma vaga é liberada e há lista de espera, o próximo paciente da fila recebe a oferta da vaga pelo canal conectado, com janela para aceitar.

**Why this priority**: Completa o fluxo de lista de espera (FIFO) da agenda, que hoje também só loga. Mesma cadeia de entrega; regra de "próximo da fila" já existe.

**Independent Test**: Liberar uma vaga com paciente aguardando na lista; verificar que a oferta é enviada ao primeiro da fila e que, expirada a janela sem resposta, o próximo é notificado.

**Acceptance Scenarios**:

1. **Given** uma vaga liberada e um paciente no topo da lista de espera, **When** a oferta é processada, **Then** a mensagem de oferta é enviada a esse paciente.
2. **Given** a oferta enviada e a janela de resposta expirada sem aceite, **When** o ciclo de expiração roda, **Then** a oferta ao próximo paciente da fila é enviada.

---

### User Story 4 - Entrega impossível vira pendência de contato manual (Priority: P2)

Quando uma notificação não pode ser entregue automaticamente (paciente sem canal conectado, sem template configurado para aquele tipo, ou fora da janela sem template válido), o sistema **não falha silenciosamente**: registra a notificação como "pendente de contato manual" e a torna visível para a equipe agir.

**Why this priority**: Garante que nenhum paciente "suma" — a clínica precisa saber quem não foi avisado para agir manualmente. É a rede de segurança que torna a automação confiável.

**Independent Test**: Disparar uma notificação para um paciente sem WhatsApp conectado; verificar que nada é enviado, que a notificação fica "pendente manual" com o motivo correto, e que aparece para a equipe como pendência.

**Acceptance Scenarios**:

1. **Given** um paciente sem nenhum canal conectado, **When** uma notificação é disparada, **Then** ela fica "pendente de contato manual" com motivo "sem canal" e é sinalizada para a equipe.
2. **Given** um tipo de notificação sem template configurado pela clínica, **When** a notificação é disparada fora da janela de 24h, **Then** ela fica "pendente de contato manual" com motivo "sem template".
3. **Given** uma notificação pendente de contato manual, **When** a equipe abre a inbox, **Then** encontra a conversa do paciente sinalizada com uma mensagem de sistema que identifica motivo e contexto (qual consulta/receituário) para agir.

---

### User Story 5 - Clínica configura os templates de cada aviso (Priority: P3)

A clínica configura, por tipo de notificação (confirmação, alerta de receituário, oferta de vaga), qual template aprovado usar e como suas variáveis são preenchidas.

**Why this priority**: É o pré-requisito de operação real, mas pode ser inicialmente provisionado por configuração/seed; a UI de gestão é o refinamento que dá autonomia à clínica.

**Independent Test**: Configurar o template de confirmação para a clínica; verificar que a US1 passa a usar exatamente esse template; remover a configuração e verificar que cai no fluxo de contato manual (US4).

**Acceptance Scenarios**:

1. **Given** um administrador da clínica, **When** ele associa um template aprovado ao tipo "confirmação de consulta", **Then** as próximas confirmações usam esse template.
2. **Given** dois tenants distintos, **When** cada um configura seu próprio template, **Then** as notificações de um tenant nunca usam o template do outro.

---

### Edge Cases

- **Janela de 24h**: se já existe conversa ativa dentro da janela de atendimento, a mensagem pode ser livre; fora da janela, é obrigatório um template aprovado — sem template válido, vira contato manual (US4).
- **Paciente com múltiplos canais**: define-se uma ordem de preferência (ver Premissas); usa o primeiro canal elegível.
- **Telefone inválido/sem WhatsApp**: tratado como "sem canal" → contato manual.
- **Reenvio acidental**: o mesmo aviso (mesmo paciente, mesmo marco, mesma data) não é enviado em duplicidade (idempotência); marcos diferentes (T-24h vs T-2h) são envios distintos.
- **Falha de entrega no provedor**: a notificação fica "falhou"; aplica-se a política de retry existente e, esgotada, sinaliza para contato manual.
- **Opt-out parcial**: opt-out se aplica ao par (paciente, profissional) e ao tipo de notificação relevante; confirmações de consulta agendada não são suprimidas por opt-out de "renovação".
- **Resposta do paciente fora do horário**: recebimento de resposta é tratado pelos fluxos de inbox existentes; esta feature só cuida do envio.

## Requirements *(mandatory)*

### Functional Requirements

**Resolução de destino**
- **FR-001**: O sistema MUST determinar, para um paciente e um tipo de notificação, o canal de saída elegível usando apenas canais conectados do próprio tenant. Para envios **proativos (fora da janela de 24h)**, o canal elegível é **somente WhatsApp** (único canal com template HSM); Instagram só é elegível quando já existe conversa **dentro** da janela. Sem WhatsApp elegível em envio proativo → cai no fluxo de contato manual (FR-003).
- **FR-002**: O sistema MUST localizar ou abrir a conversa correspondente do paciente naquele canal para registrar e enviar a mensagem de saída.
- **FR-003**: Quando nenhum canal elegível existir para o paciente, o sistema MUST registrar a notificação como "pendente de contato manual" com motivo "sem canal" e NÃO tentar enviar.

**Catálogo de templates**
- **FR-004**: O sistema MUST permitir associar, por tenant e por tipo de notificação, um template aprovado e o mapeamento de suas variáveis.
- **FR-005**: O sistema MUST usar o template configurado do próprio tenant ao enviar uma notificação fora da janela de atendimento livre.
- **FR-006**: Quando um tipo de notificação não tiver template configurado e o envio exigir template, o sistema MUST registrar "pendente de contato manual" com motivo "sem template".

**Religamento dos avisos existentes**
- **FR-007**: O sistema MUST enviar ao paciente a confirmação de consulta nos marcos previstos (T-24h e T-2h), substituindo o comportamento atual que apenas registra log.
- **FR-008**: O sistema MUST enviar ao paciente os alertas de receituário próximo do vencimento nos marcos previstos (D-15, D-7, D-1).
- **FR-009**: O sistema MUST enviar ao paciente a oferta de vaga quando ele for o próximo elegível da lista de espera, e re-ofertar ao próximo quando a janela expirar.
- **FR-010**: O sistema MUST tratar os avisos de escalonamento (cancelamento fora de prazo, limite de reagendamento excedido, tarefa de renovação por IA) entregando-os ou roteando-os para contato manual quando não houver canal/template.

**Regras de plataforma e privacidade**
- **FR-011**: O sistema MUST respeitar a janela de 24h: dentro dela, mensagem livre é permitida; fora dela, é obrigatório um template aprovado.
- **FR-012**: O sistema MUST suprimir o envio quando o paciente tiver feito opt-out para o profissional/tipo aplicável, registrando a notificação como "ignorada" com motivo "opt-out", sem deixar de registrar o evento de domínio para auditoria.
- **FR-013**: O sistema MUST aplicar debounce por destinatário e tipo (janela de 4h), registrando o envio suprimido como "ignorado" com motivo "debounce".
- **FR-014**: O sistema MUST garantir idempotência: o mesmo aviso (paciente + tipo + marco + data) não é enviado em duplicidade.
- **FR-015**: O sistema MUST garantir que nenhuma mensagem ao paciente contenha dado clínico sensível (ex.: nome de medicamento, posologia, diagnóstico, conteúdo de prontuário).
- **FR-016**: O sistema MUST isolar resolução de canal e de template por tenant — nunca usando canal ou template de outro tenant (Princípio II).

**Rastreio e operação**
- **FR-017**: O sistema MUST registrar o estado de cada notificação ao longo do ciclo: enfileirada, enviada, entregue, falhou, pendente de contato manual, ignorada (com motivo). Os estados **"entregue"** e **"falhou"** MUST ser reconciliados a partir dos callbacks de status do provedor já recebidos pela infraestrutura existente; **"lido" NÃO é rastreado**. Uma falha **definitiva** (esgotado o retry) MUST acionar o fluxo de contato manual (FR-018).
- **FR-018**: O sistema MUST tornar as notificações "pendentes de contato manual" visíveis e acionáveis reutilizando a **inbox existente**: usar/abrir a Conversa do paciente e inserir uma **mensagem de sistema** descrevendo a notificação não entregue, paciente, motivo e contexto (qual consulta/receituário/vaga), com a conversa sinalizada (prioridade/tag). NÃO introduz um sistema de tarefas de inbox dedicado.
- **FR-019**: O sistema MUST expor métricas operacionais de entrega (enviadas, falhas, pendentes manuais, ignoradas por motivo) para observabilidade.
- **FR-020**: O sistema MUST registrar em auditoria as decisões de entrega relevantes (enviado, suprimido por opt-out/debounce, roteado para manual), sem expor dado clínico.

### Key Entities *(include if feature involves data)*

- **Notificação Outbound**: representa uma tentativa de avisar um paciente. Atributos-chave: tenant, paciente, tipo (confirmação/alerta-receituário/oferta-vaga/escalonamento), marco/ocasião, canal escolhido, template usado, estado (enfileirada/enviada/entregue/falhou/pendente-manual/ignorada), motivo (quando ignorada/pendente), referência ao objeto de origem (consulta/receituário/vaga), data/hora.
- **Configuração de Template do Tenant**: mapeia, por tenant e tipo de notificação, o template aprovado e o preenchimento de suas variáveis. Pertence a um tenant; nunca compartilhado.
- **Preferência de Notificação do Paciente**: opt-out por par (paciente, profissional) e tipo; já existe e deve ser respeitada.
- **Canal Conectado**: canal de mensageria do tenant (WhatsApp/Instagram) com sua identidade externa; já existe.
- **Conversa**: thread de comunicação com o paciente em um canal; já existe (hoje aberta por mensagem recebida) e passa a poder ser aberta para envio proativo.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das confirmações de consulta para pacientes com canal conectado e template configurado resultam em mensagem efetivamente enviada (estado "enviada"), eliminando o comportamento atual de "só log".
- **SC-002**: 0 mensagens ao paciente contêm dado clínico sensível (verificado por inspeção automatizada dos conteúdos enviados).
- **SC-003**: 100% das notificações não entregáveis automaticamente terminam em estado rastreável ("pendente de contato manual" com motivo) — nenhuma "desaparece".
- **SC-004**: 0 vazamentos cross-tenant: nenhuma notificação usa canal ou template de outro tenant (verificado por teste de isolamento).
- **SC-005**: Reenvios duplicados do mesmo aviso (mesmo paciente/tipo/marco/data) são 0; supressões por opt-out e debounce são corretamente contabilizadas.
- **SC-006**: A equipe consegue localizar e agir sobre uma pendência de contato manual em menos de 1 minuto a partir da inbox (paciente + motivo + contexto visíveis).
- **SC-007**: Redução mensurável de no-show após ativação das confirmações reais (meta a acompanhar; baseline = período pré-ativação).

## Assumptions

- **Canais e envio já existem**: a conexão de WhatsApp/Instagram (Fase 3) e o mecanismo de envio de mensagem de saída já estão disponíveis; esta feature **não** reimplementa conexão de canal nem ingestão de mensagens recebidas.
- **Templates já aprovados**: assume-se que os templates já foram aprovados na plataforma da Meta e que a clínica apenas os **referencia/configura** aqui; submissão/aprovação de templates está fora de escopo.
- **Ordem de preferência de canal**: padrão = WhatsApp como canal primário para avisos proativos (suporta templates fora da janela); Instagram é usado apenas quando já há conversa dentro da janela de atendimento (a plataforma não permite template proativo equivalente). Pacientes sem WhatsApp elegível e fora de janela caem em contato manual.
- **Marcos e cadências já definidos**: os marcos de confirmação (T-24h/T-2h), alerta de receituário (D-15/D-7/D-1) e lista de espera (FIFO + janela de resposta) vêm das Fases 5 e 7 e são reutilizados; esta feature cuida da **entrega**, não da cadência.
- **Opt-out, debounce, idempotência e marcador de "sem dado clínico"** já existem como mecanismos nas Fases 5/7/8 e devem ser **respeitados**, não recriados.
- **"Pendente de contato manual"** reutiliza o conceito já usado na Fase 5 (estado que sinaliza necessidade de ação humana) apoiado nas primitivas de conversa/inbox existentes; um sistema dedicado de tarefas de inbox completo está fora de escopo (usa-se o mínimo necessário para tornar a pendência visível e acionável).
- **Confirmação de entrega/leitura**: o estado "entregue/falhou" é atualizado a partir dos retornos de status do provedor já recebidos pela infraestrutura existente; "lido" não é requisito.
- **Retry de falhas**: reutiliza a política de retry/fila existente; esgotadas as tentativas, a notificação é sinalizada para contato manual.

## Out of Scope

- Motor de IA matricial/agêntica (qualificação de leads, agendamento por chat, RAG por clínica).
- Novos canais além de WhatsApp, Instagram e widget web.
- Fluxos de mensagens **recebidas** (inbound) — já cobertos pela Fase 3.
- Submissão e aprovação de templates na plataforma da Meta.
- Cadências/marcos novos de notificação (são herdados das Fases 5/7).
