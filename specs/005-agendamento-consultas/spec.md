# Feature Specification: Fase 5 — Agendamento de Consultas

**Feature Branch**: `005-agendamento-consultas`
**Created**: 2026-05-13
**Status**: Ready for `/speckit.plan` — 14/15 clarifications resolvidos (NC 1-11 + 13-15 fechados em duas sessions de `/speckit.clarify`, ver § Clarifications); **NC nº 12 (UX de revogação OAuth) DEFERRED → tratar em `/speckit.plan`** como decisão operacional sem impacto arquitetural
**Input**: User description: Épico 6 do PRD — Agenda do profissional, tipos de atendimento, agendamento manual via painel, confirmação automática, reagendamento/cancelamento via chat, lista de espera, sincronização Google Calendar / Outlook.

---

## Visão Geral

A Fase 5 entrega o módulo de **Agendamento de Consultas** do CRM Médico SaaS. É o primeiro módulo que converte leads qualificados (Fase 2 — CRM Pacientes) em receita previsível, fechando o loop com a Inbox Omnichannel (Fase 3 — confirmações automáticas) e expondo contratos para o futuro Motor de IA Matricial (que consumirá esta API para agendar via chat).

**Outcome de negócio:** taxa de no-show ≤ 10% por tenant; ≥ 60% dos agendamentos em horário comercial chegam via canal automatizado (painel, IA ou autoatendimento via chat) — reduzindo dependência de operação telefônica humana.

Esta fase NÃO reimplementa envio de mensagens (delegado à Fase 3) nem o fluxo conversacional da IA (delegado à futura fase de IA Matricial). Esta fase **provê e expõe** a fonte da verdade da agenda: regras de disponibilidade, slots, política de conflito, eventos de domínio que outras fases consomem.

---

## Clarifications

### Session 2026-05-13

- Q: Como Fase 5 sincroniza com Google Calendar (push/polling, ownership de eventos externos, título, janela)? → A: Opção A — push (Google Watch) + polling 5 min fallback; CRM = fonte da verdade exclusiva (eventos externos no Google só **bloqueiam** o slot, não viram `Appointment`); título Google **fixo** `Consulta — {Profissional}`; janela **60 dias** futuros sincronizados.
- Q: Como Fase 5 trata fuso horário (armazenamento, render painel/agenda própria/mensagem ao paciente, override por profissional)? → A: Opção A — **TZ tenant default** + profissional pode ter TZ override (nullable, herda tenant); **interno tudo UTC**; render: painel = TZ tenant, agenda própria do médico = TZ profissional (se override), mensagem ao paciente = horário com **TZ explícito no texto** (ex.: `14:00 (horário de São Paulo)`).
- Q: Outlook sync no MVP da Fase 5 — MUST-HAVE, SHOULD-HAVE, cortar p/ Fase 6 ou removido do roadmap? → A: Opção C — **SHOULD-HAVE deferred → Fase 6**. Outlook explicitamente cortado da Fase 5; modelo de domínio (`CalendarSyncAccount.provider` enum, `ExternalCalendarBusy.provider`) aceita `outlook` desde a migration para reuso futuro; FRs/ACs Outlook movidos para backlog Fase 6.
- Q: Profissional atende em 2 tenants com a mesma conta Google — como evitar vazamento cross-tenant via Google sync (R8 🔴 Alta)? → A: Opção A — **`CalendarSyncAccount` tenant-scoped** + Fase 5 cria automaticamente **sub-calendário dedicado** `Paciente360 — {Tenant.nome}` no Google do profissional; toda escrita/leitura usa apenas esse `calendarId`; UNIQUE(`tenant_id`, `professional_id`) garante 1 conexão por tenant.
- Q: Como Fase 5 marca `ConsultaRealizada` / `ConsultaNaoRealizada` (manual/auto, prazo, mensagem ao paciente)? → A: Opção A — **marcação manual pelo atendente** (status `realizada` ou `nao_realizada` + motivo opcional); **auto-flag "candidato a no-show"** após T+30min sem marcação (apenas hint no painel — não muda status); **janela 7 dias** após `starts_at` para marcar; após 7d, status fica `concluida_sem_registro` e bloqueia edição; **só emite evento** (sem mensagem automática "sentimos sua falta" no MVP).

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Profissional configura sua agenda (Priority: P1)

Médico ou Admin Clínica configura horários de trabalho, intervalos e bloqueios para que apenas horários válidos sejam ofertados aos pacientes.

**Why this priority**: sem agenda configurada, nada mais funciona — é o gate de entrada de todas as outras user stories.

**Independent Test**: criar tenant + profissional + agenda; consultar via API "horários disponíveis na semana" → resposta reflete a configuração (com bloqueios, intervalos e tipos aceitos).

**Acceptance Scenarios:**

- **AC-6.1.1** 🔴 **Given** profissional sem agenda configurada, **When** Admin Clínica cria agenda definindo segunda a sexta das 08:00 às 18:00 com intervalo das 12:00 às 13:00, **Then** o sistema armazena a configuração e emite `ProfissionalAgendaConfigurada`.
- **AC-6.1.2** 🔴 **Given** agenda configurada acima, **When** consultar "horários disponíveis na quarta-feira", **Then** retorna slots entre 08:00–12:00 e 13:00–18:00 (intervalo excluído).
- **AC-6.1.3** 🔴 **Given** agenda configurada, **When** Admin Clínica adiciona bloqueio de férias de 10/06 a 20/06, **Then** consultas nessa janela são rejeitadas com erro `professional_unavailable`; ScheduleException persistida com `created_by_user_id=admin`. **Given** caso de emergência, **When** outro Admin Clínica com `appointment.override_block` cria consulta dentro do bloqueio com `motivo_override`, **Then** consulta é persistida com `override_block=true`; audit `warning` gerado; profissional recebe push no painel + email avisando do encaixe.
- **AC-6.1.4** 🟡 **Given** agenda padrão, **When** Admin Clínica define que o profissional aceita apenas tipos `consulta` e `retorno` (não `exame`), **Then** tentativa de agendar `exame` retorna `appointment_type_not_supported`.
- **AC-6.1.5** 🟡 **Given** profissional com agenda, **When** Médico edita o próprio horário do dia (ability `appointment.manage_own_schedule`), **Then** alteração é persistida e auditada.
- **AC-6.1.6** 🟢 **Given** dois profissionais no mesmo tenant, **When** Admin Clínica visualiza agenda semanal, **Then** vê grade lado-a-lado dos profissionais ativos.

**Dependências:** Fase 0 (users, tenants, audit_logs), Fase 2 (Professional).
**Riscos:** alterações retroativas de agenda quando já há consultas marcadas no período — política a definir (NEEDS_CLARIFICATION nº 5).

---

### User Story 2 — Cadastro de tipos de atendimento (Priority: P1)

Admin Clínica cadastra tipos de atendimento (consulta, retorno, exame) com duração, valor e cor para que cada um tenha regras próprias.

**Why this priority**: pré-requisito para US-6.3 (não dá para agendar sem tipo); estabelece a granularidade de slot da agenda.

**Independent Test**: criar 3 tipos no tenant; listar via API → 3 tipos retornados com seus atributos.

**Acceptance Scenarios:**

- **AC-6.2.1** 🔴 **Given** tenant ativo, **When** Admin Clínica cria tipo `Consulta` com duração 30 min e valor particular R$ 200, **Then** o tipo fica disponível para vínculo com profissionais.
- **AC-6.2.2** 🔴 **Given** tipo `Retorno` existente, **When** Admin Clínica marca o tipo como inativo, **Then** novos agendamentos para esse tipo são bloqueados (`appointment_type_inactive`), histórico preservado.
- **AC-6.2.3** 🟡 **Given** tipo configurado, **When** Admin Clínica define cor `#FF5722`, **Then** a cor é retornada em consultas de agenda (consumido pelo painel para renderização).
- **AC-6.2.4** 🟡 **Given** tipo "Retorno" desta fase, **Then** este registro fica isolado da automação de "gestão de retornos" da Fase 6 — Fase 5 trata `Retorno` como categoria de tipo de atendimento (duração própria, sem cadência automática). UI MUST exibir tooltip "Tipo: Retorno (categoria de consulta)" para evitar confusão com cadência automática (Fase 6).
- **AC-6.2.5** 🟢 **Given** múltiplos tipos no tenant, **When** outro tenant cria tipo com mesmo nome, **Then** ambos coexistem isolados (Princípio II — tenant scoping).

**Dependências:** Fase 0 (tenants).
**Riscos:** tipo "Telemedicina" placeholder não implementado pode confundir UI — manter explicitamente fora do MVP (Fora de Escopo).

---

### User Story 3 — Agendamento manual via painel drag-and-drop (Priority: P1)

Atendente/Recepcionista marca consultas via drag-and-drop no calendário para agendar rapidamente quando o paciente liga.

**Why this priority**: fluxo central de operação humana — sem ele, atendimento telefônico fica inviável. É o caminho mais usado em clínicas que ainda não migraram para autoatendimento via IA.

**Independent Test**: Atendente logado abre /agenda, arrasta para criar bloco às 09:00, seleciona paciente existente + tipo "Consulta" + profissional, confirma → consulta aparece na grade, paciente recebe mensagem de confirmação via canal de origem.

**Acceptance Scenarios:**

- **AC-6.3.1** 🔴 **Given** Atendente logado com `appointment.create`, **When** clica em slot vazio 09:00 do profissional X na quarta, escolhe paciente Y, tipo `Consulta`, **Then** consulta é persistida; evento `ConsultaCriada` emitido; card do paciente no funil (Fase 2) recebe `pacientes.movido_funil` para coluna "Agendado".
- **AC-6.3.2** 🔴 **Given** consulta existente às 09:00, **When** outro Atendente tenta criar consulta no mesmo slot do mesmo profissional, **Then** sistema rejeita com `slot_conflict` (política de conflito em NEEDS_CLARIFICATION nº 2).
- **AC-6.3.3** 🔴 **Given** consulta existente, **When** Atendente arrasta o bloco para outro horário no painel, **Then** é tratado como **reagendamento explícito** (não move silencioso) — pede confirmação, dispara `ConsultaReagendada`, paciente é notificado.
- **AC-6.3.4** 🟡 **Given** Atendente quer agendar, **When** busca paciente por nome/CPF, **Then** lista candidatos da Fase 2 com fuzzy match (reusa busca trgm existente).
- **AC-6.3.5** 🟡 **Given** paciente novo, **When** Atendente cria cadastro rápido pelo modal do agendamento, **Then** novo paciente criado na Fase 2 e vinculado à consulta.
- **AC-6.3.6** 🟡 **Given** consulta criada, **When** consulta enviada com `notify_patient=true`, **Then** evento `ConsultaCriada` consumido pela Fase 3 que envia mensagem no canal de origem do paciente (WhatsApp/Instagram/Web).
- **AC-6.3.7** 🟢 **Given** visualização semanal, **When** Atendente alterna para diária ou mensal, **Then** grade renderiza para a visão selecionada.

**Dependências:** US-6.1 (agenda configurada), US-6.2 (tipos), Fase 2 (paciente + funil), Fase 3 (mensageria via evento).
**Riscos:** race condition em criação simultânea (NEEDS_CLARIFICATION nº 2); drag-and-drop semântica (mover = reagendar?) (NEEDS_CLARIFICATION nº 9).

---

### User Story 4 — Confirmação automática 24h e 2h antes (Priority: P1)

Plataforma envia mensagens de confirmação 24h e 2h antes da consulta no canal de origem do paciente para reduzir no-show.

**Why this priority**: outcome direto de negócio (queda de no-show); roda em background sem necessidade de interação humana, ROI claro.

**Independent Test**: criar consulta às 14:00 de amanhã com paciente cujo canal de origem é WhatsApp; agendar tempo virtual para 23h antes da consulta; verificar que mensagem template foi disparada via Fase 3.

**Acceptance Scenarios:**

- **AC-6.4.1** 🔴 **Given** consulta marcada para amanhã 14:00 com paciente do canal WhatsApp, **When** o relógio atinge T-24h, **Then** Fase 5 emite `ConsultaConfirmacaoPendente` consumido pela Fase 3 que envia template aprovado com opções "1=confirma / 2=remarca / 3=cancela".
- **AC-6.4.2** 🔴 **Given** consulta com confirmação enviada, **When** paciente responde `1` via canal, **Then** Fase 5 marca como confirmada e emite `ConsultaConfirmada`.
- **AC-6.4.3** 🔴 **Given** consulta com confirmação enviada, **When** paciente responde `3`, **Then** Fase 5 cancela a consulta, emite `ConsultaCancelada` com motivo `paciente_via_chat`, vaga fica disponível para lista de espera (US-6.6).
- **AC-6.4.4** 🟡 **Given** consulta com confirmação enviada, **When** paciente responde `2`, **Then** o fluxo é encaminhado à futura IA Matricial via evento `ReagendamentoSolicitadoPeloPaciente` (Fase 5 expõe o evento; quem orquestra o reagendamento conversacional é o módulo de IA).
- **AC-6.4.5** 🔴 **Given** confirmação enviada 24h antes e consulta ainda não confirmada, **When** o relógio atinge T-2h, **Then** dispara **lembrete T-2h** (mensagem padrão "Sua consulta hoje às {HH:MM} com {Profissional}…"). **And** caso a confirmação T-24h tenha tido **não-resposta**, **When** o relógio atinge T-30min, **Then** dispara **retry T-30min** (mensagem urgente). **And** após T-15min sem qualquer resposta, **Then** registra `ConfirmationDispatch.status='pending_manual'` (sem alterar `Appointment.status`) + emite `ConsultaPendenteContatoManual` para Fase 3 criar task na inbox. Nenhuma dessas mensagens é enviada se a consulta já foi confirmada/cancelada.
- **AC-6.4.6** 🟡 **Given** consulta cancelada manualmente pelo Atendente entre o disparo da confirmação e a resposta do paciente, **When** paciente responde `1`, **Then** sistema responde "Esta consulta já foi cancelada" sem reativar — idempotência reverse.
- **AC-6.4.7** 🟢 **Given** paciente sem canal de origem registrado (consulta inserida manualmente sem inbox prévio), **When** chega T-24h, **Then** confirmação fica marcada para contato manual (`ConsultaPendenteContatoManual`).
- **AC-6.4.8** 🟢 **Given** paciente confirma `1` duas vezes (mensagem duplicada), **When** segunda resposta chega, **Then** idempotente — não emite novo `ConsultaConfirmada`.

**Dependências:** Fase 3 (template Meta + envio + parsing de resposta), US-6.3.
**Riscos:** janela de template Meta (24h) ↔ disparo programado pode colidir; canais inativos no momento do disparo (NEEDS_CLARIFICATION nº 6).

---

### User Story 5 — Reagendamento e cancelamento via chat (Priority: P2)

Paciente reagenda ou cancela a própria consulta via chat — sem ligar para a clínica.

**Why this priority**: alto valor de UX e desativação de canal telefônico, mas depende da IA Matricial (futura) para orquestrar o fluxo conversacional. Fase 5 expõe os contratos de slot/reagendamento; IA Matricial consome.

**Independent Test**: chamar diretamente as APIs `GET /agenda/slots-disponiveis` e `POST /agenda/consultas/{id}/reagendar` simulando o que a IA faria — verificar que slots respeitam regras (não ofertam horários ocupados/bloqueados) e que reagendamento move a consulta + emite evento.

**Acceptance Scenarios:**

- **AC-6.5.1** 🔴 **Given** consulta agendada do paciente Y, **When** consumidor chama `GET /agenda/slots-disponiveis?profissional_id=X&tipo_id=Z&from=...&to=...`, **Then** retorna lista paginada de slots disponíveis no formato `{starts_at, ends_at, professional_id, type_id}` em **ISO 8601 com offset** + envelope `timezone_display` IANA (clarify nº 13 — armazenamento UTC, render por contexto).
- **AC-6.5.2** 🔴 **Given** consulta Y do paciente, **When** chamar `POST /agenda/consultas/{id}/reagendar` com novo `starts_at` + `idempotency_key`, **Then** consulta movida atomicamente; emite `ConsultaReagendada(original_id, novo_starts_at)`; idempotency_key garante que retries não duplicam.
- **AC-6.5.3** 🔴 **Given** consulta Y, **When** chamar `POST /agenda/consultas/{id}/cancelar` com motivo, **Then** consulta cancelada; emite `ConsultaCancelada(quem=paciente, motivo)`; vaga vai para lista de espera (US-6.6).
- **AC-6.5.4** 🔴 **Given** política de cancelamento configurada para prazo mínimo 4h antes (tenant) — ou 48h para tipo `Cirurgia`, **When** paciente tenta cancelar 2h antes, **Then** sistema responde HTTP 422 com `error=cancellation_outside_window`, `escalated_to_inbox=true`, `window_hours`, `current_hours_until_appt`; emite `CancelamentoSolicitadoForaDoPrazo`; Fase 3 cria handoff na inbox para atendente. IA Matricial usa flags para responder ao paciente "passei seu pedido para a equipe".
- **AC-6.5.5** 🟡 **Given** consulta Y, **When** reagendamento solicitado, **Then** `professional_id` e `type_id` são preservados — payload aceita apenas `new_starts_at` + `idempotency_key`. Para trocar profissional ou tipo, paciente cancela + cria nova consulta.
- **AC-6.5.6** 🟡 **Given** consulta Y já reagendada 2x, **When** 3ª tentativa via chat, **Then** HTTP 422 com `error=reschedule_limit_exceeded`, `escalated_to_inbox=true`; emite `LimiteDeReagendamentoExcedido`; Fase 3 cria handoff na inbox para atendente decidir caso a caso (pode liberar exceção legítima).
- **AC-6.5.7** 🟢 **Given** IA Matricial (futura) ofereceu slot S ao paciente e aguarda confirmação, **When** a Fase 5 recebe `POST /agenda/slots/{S}/reservar` com `holder_type=ia`, **Then** marca S como "em reserva pela IA" por **2 min** (TTL configurável) — outros consumidores recebem `slot_reserved` ao tentar abrir form; reserva expira e libera S automaticamente se a IA não confirmar dentro da janela.

**Dependências:** US-6.1, US-6.2, US-6.3, Fase 3 (mensageria), Fase futura de IA Matricial (consumidor dos contratos).
**Riscos:** contrato divergente do que a IA Matricial vai consumir (NEEDS_CLARIFICATION nº 7); slot em negociação não revogado por timeout deixa agenda "fantasma" ocupada.

---

### User Story 6 — Lista de espera automática (Priority: P2)

Atendente coloca paciente em lista de espera quando não há vaga; quando vaga abre por cancelamento, sistema notifica primeiros pacientes elegíveis.

**Why this priority**: otimização de ocupação — diferencial competitivo. Não bloqueia operação básica (US-6.1 a 6.4 funcionam sem isso), mas multiplica receita capturada em janelas que seriam perdidas.

**Independent Test**: paciente A na lista de espera de profissional X tipo `Consulta`; cancelar consulta C de outro paciente no slot S de X com tipo `Consulta`; verificar que evento `VagaAbertaNaListaDeEspera` foi emitido com candidato A.

**Acceptance Scenarios:**

- **AC-6.6.1** 🔴 **Given** Atendente com `waitlist.manage`, **When** tenta agendar paciente A no profissional X data Y e não há slot disponível, **Then** sistema oferece "entrar na lista de espera" com critérios (profissional + tipo).
- **AC-6.6.2** 🔴 **Given** paciente A no topo da lista de espera de `(Dr. X, Consulta)`, **When** outra consulta de Dr. X com tipo `Consulta` é cancelada, **Then** sistema emite `VagaAbertaNaListaDeEspera(waitlist_entry_id=A, slot)` — apenas A é notificado (sequencial); A muda para `status=notified` com `notified_at=now()`.
- **AC-6.6.3** 🔴 **Given** paciente A notificado, **When** dentro de `tenant.waitlist_confirmation_minutes` (default 15 min) A confirma via canal, **Then** consulta é criada atomicamente para A; A muda para `status=accepted`. **And** caso A não responda dentro do prazo, **Then** cron expira (`status=expired`) e emite novo `VagaAbertaNaListaDeEspera` para o próximo da fila — sem notificações paralelas.
- **AC-6.6.4** 🟡 **Given** lista FIFO de N pacientes em `(Dr. X, Consulta)`, **When** vaga abre, **Then** apenas o **primeiro** é notificado (Opção A — sequencial puro). Não há notificação paralela K candidatos.
- **AC-6.6.5** 🟡 **Given** paciente A na lista de `(Dr. X, Consulta)`, **When** A é também adicionado à lista de `(Dr. Y, Consulta)` ou `(Dr. X, Retorno)`, **Then** mesmo paciente pode estar em múltiplas listas distintas sem limite — cada combinação `(profissional, tipo)` tem sua própria fila FIFO.
- **AC-6.6.6** 🟢 **Given** Admin Clínica, **When** consulta relatório "Vagas preenchidas via lista de espera no último mês", **Then** retorna agregado por profissional/tipo.

**Dependências:** US-6.3, US-6.4 (cancelamento dispara vaga), Fase 3 (notificação).
**Riscos:** pacientes notificados em paralelo confirmam simultaneamente (race condition na alocação da vaga); paciente notificado mas sem disponibilidade real no canal (ex.: WhatsApp banido).

---

### User Story 7 — Sincronização bidirecional com Google Calendar (Priority: P2)

Médico conecta sua conta Google para que sua agenda do CRM sincronize bidirecionalmente com Google Calendar via sub-calendário dedicado por tenant.

**Why this priority**: requisito explícito de produto; reduz fricção de profissionais que mantêm agenda paralela. Google é P2 (universo Android/G Suite). **Outlook deferred → Fase 6** (clarify nº 11) — modelo de domínio preparado (`provider` enum aceita `outlook`), implementação na próxima fase.

**Independent Test**: profissional conecta Google via OAuth; sistema cria sub-calendário `Paciente360 — {Tenant.nome}` no Google do profissional; criar consulta no CRM; verificar que evento aparece **somente nesse sub-calendário** em <2 minutos; criar evento manualmente no Google nesse sub-calendário; verificar que o slot fica bloqueado no CRM como `ExternalCalendarBusy` (não importado como consulta).

**Acceptance Scenarios:**

- **AC-6.7.1** 🔴 **Given** Médico com `calendar_sync.configure`, **When** clica "Conectar Google Calendar" e completa OAuth, **Then** token armazenado criptografado por profissional; sistema cria sub-calendário `Paciente360 — {Tenant.nome}` no Google e persiste o `google_calendar_id` em `CalendarSyncAccount`; evento `CalendarioExternoSincronizado(provider=google, status=conectado)` emitido. Falha em criar o sub-calendário (quota/scope) rejeita a conexão com `calendar_subcalendar_creation_failed`.
- **AC-6.7.2** 🔴 **Given** profissional com Google conectado, **When** Atendente cria consulta no CRM, **Then** evento equivalente é criado **no sub-calendário** (`events.insert?calendarId={sub-cal-id}`) com título fixo `Consulta — {Profissional.nome_publico}` (sem dados clínicos — clarify nº 10/15, FR-038a), data/hora em IANA TZ do profissional (clarify nº 13), descrição genérica `Agendamento via {Tenant.nome}` (sem CPF/convênio).
- **AC-6.7.3** 🔴 **Given** consulta sincronizada para Google, **When** consulta é cancelada/reagendada no CRM, **Then** evento correspondente no sub-calendário é atualizado/removido em <2 minutos.
- **AC-6.7.4** 🔴 **Given** profissional com Google conectado, **When** profissional cria evento manualmente no **sub-calendário** gerenciado dentro de sua agenda CRM, **Then** o slot fica bloqueado no CRM como `ExternalCalendarBusy` (não importa como consulta — apenas marca indisponível); aparece no painel como "Evento externo". Eventos no calendário primário do médico **não** são puxados (clarify nº 15, FR-036d).
- **AC-6.7.5** 🔴 **Given** edição simultânea (CRM move consulta + profissional move o mesmo evento no Google), **When** ocorre conflito, **Then** CRM é a fonte da verdade — overwrite no Google no próximo sync cycle, audit `warning` registrado (clarify nº 10).
- **AC-6.7.6** 🔴 **Given** profissional revoga acesso OAuth no Google, **When** Fase 5 detecta erro de auth na próxima chamada, **Then** marca o canal como `sync_disconnected`, notifica no painel + email; eventos já criados no sub-calendário permanecem (não os deleta).
- **AC-6.7.7** 🟡 **Given** profissional com sync ativa, **When** token expirou, **Then** sistema tenta refresh automático antes de declarar falha.
- **AC-6.7.8** 🟡 **Given** sincronização ativa, **When** Fase 5 espelha consultas para o Google, **Then** apenas próximos `tenant.calendar_sync_window_days` (default **60 dias**) são sincronizados; eventos saem da janela ao rolar para passado, entram quando rolam para dentro (clarify nº 10).
- **AC-6.7.9** 🟡 **Outlook DEFERRED → Fase 6** (clarify nº 11). Painel exibe "Microsoft Outlook (em breve — Fase 6)" como placeholder desabilitado. Service rejeita `provider=outlook` na Fase 5 com `provider_not_yet_supported`.
- **AC-6.7.10** 🟢 **Given** Google envia push notification de mudança no sub-calendário, **When** Fase 5 recebe via watch channel, **Then** processa em <30s; **polling fallback a cada 5min** roda em paralelo cobrindo expiração de canal/perda de webhook (clarify nº 10 — push + polling, ambos rodam, não OR).
- **AC-6.7.11** 🔴 **Given** profissional conectado em 2 tenants distintos com a mesma conta Google, **When** tenant A cria consulta no CRM, **Then** o evento aparece **somente** no sub-calendário do tenant A no Google; polling do tenant B sobre `events.list?calendarId={sub-cal-tenant-B}` **NÃO** enxerga esse evento; nenhum `ExternalCalendarBusy` é criado no tenant B (clarify nº 15 — teste de regressão obrigatório).

**Dependências:** US-6.1, US-6.3.
**Riscos:** revogação OAuth silenciosa (NC nº 12 — UX deferred → plan); rate limit do Google API; LGPD — descrição do evento não pode vazar PII clínica (mitigado por título fixo, FR-038a). **Cross-tenant leak** mitigado via sub-calendário tenant-scoped (clarify nº 15, R8 rebaixado para 🟢 Baixa).

---

### Edge Cases

- **Slot duplo simultâneo (race)**: dois Atendentes começam o form de criação ao mesmo tempo no mesmo slot. Cobertura em NEEDS_CLARIFICATION nº 2.
- **Agenda alterada com consultas marcadas no período**: profissional decide férias retroativas que conflitam com consultas já marcadas — política a definir (NEEDS_CLARIFICATION nº 5).
- **Consulta sem canal de origem**: paciente cadastrado manualmente no painel, sem mensagem prévia em inbox. Confirmação automática (US-6.4) não tem canal — comportamento em AC-6.4.7.
- **Múltiplas consultas no mesmo dia para o mesmo paciente**: confirmação cobre uma a uma ou consolidada? (NEEDS_CLARIFICATION nº 6).
- **Resposta à confirmação de consulta já cancelada manualmente**: AC-6.4.6 — sistema responde idempotente "já cancelada".
- **No-show: marcação tardia**: paciente não compareceu mas Atendente só lembra dias depois. Janela máxima de **7 dias** após `starts_at` para marcar; após isso, status auto-fechado para `concluida_sem_registro` (clarify nº 14).
- **Slot em "negociação pela IA" que expira sem confirmação**: timeout libera o slot (NEEDS_CLARIFICATION nº 2).
- **Profissional sem agenda configurada**: bloqueia agendamentos ou permite entry manual? (NEEDS_CLARIFICATION nº 5).
- **Fuso horário do paciente diferente do tenant**: mensagem ao paciente sempre em TZ tenant **com qualificador explícito no texto** (`14:00 (horário de São Paulo)`) — sem detecção de TZ do canal (clarify nº 13).
- **Profissional com TZ override ≠ tenant**: slots da agenda dele são cortados no TZ profissional; mensagem ao paciente continua em TZ tenant + qualificador (clarify nº 13).

---

## Requirements *(mandatory)*

### Functional Requirements

#### Agenda do profissional (US-6.1)

- **FR-001**: Sistema MUST permitir configurar horários de trabalho recorrentes por dia da semana (início, fim, múltiplos intervalos) por profissional.
- **FR-002**: Sistema MUST permitir bloqueios pontuais por período (data início, data fim, motivo opcional), criados por médico (próprios, ability `appointment.manage_own_schedule`) OU por Admin Clínica (qualquer profissional, ability `schedule.configure`). Cada `ScheduleException` armazena `created_by_user_id`. Resolved nº 5 — Opção B.
- **FR-002a**: Sistema MUST permitir override de bloqueio (encaixe de emergência) apenas para usuários com ability `appointment.override_block` (default: Admin Clínica); exige `motivo_override` obrigatório; emite audit `warning`; envia notificação push + email ao profissional afetado.
- **FR-002b**: Sistema MUST rejeitar criação de consulta para profissional sem `ProfessionalSchedule` ativo com `error=professional_schedule_not_configured`; painel exibe wizard de configuração rápida (opção "Copiar de outro profissional").
- **FR-003**: Sistema MUST permitir vincular tipos de atendimento que o profissional aceita.
- **FR-004**: Sistema MUST emitir `ProfissionalAgendaConfigurada` quando agenda é criada ou alterada.
- **FR-005**: Sistema MUST armazenar todos os timestamps de agenda em **UTC** (`timestamptz` PostgreSQL) e renderizar conforme contexto: **TZ do tenant** em painel admin/atendente e em mensagens ao paciente (com qualificador explícito no texto, ex.: `14:00 (horário de São Paulo)`); **TZ do profissional** override (nullable, herda tenant) na agenda própria do médico; API REST sempre retorna **ISO 8601 com offset** + envelope `timezone_display` IANA (`America/Sao_Paulo`). Resolved nº 13 — Opção A.

#### Tipos de atendimento (US-6.2)

- **FR-006**: Sistema MUST permitir cadastrar tipo de atendimento com nome, duração (minutos), buffer (minutos), `valor_particular` (decimal), `valor_convenio_default` (decimal, nullable), `min_cancellation_hours` (nullable — herda do tenant se NULL, clarify nº 3), cor e descrição. Resolved nº 4 — Opção A.
- **FR-006a**: Consulta MUST armazenar **snapshot** do valor aplicado (`valor_aplicado`) — não referência viva ao tipo — para evitar reescrita histórica em relatórios financeiros quando valores do tipo mudarem.
- **FR-006b**: Atendente MUST poder override o valor padrão na criação da consulta, fornecendo `valor_override_motivo` (campo livre obrigatório, auditado).
- **FR-007**: Sistema MUST permitir marcar tipo como inativo, preservando histórico — novos agendamentos rejeitados.
- **FR-008**: Sistema MUST permitir vinculação opcional do tipo a intenções da IA (campo livre, IA consumirá).
- **FR-009**: Sistema MUST manter explicitamente "Telemedicina" como **fora do MVP** (não criar entrada padrão; apenas permitir cadastro manual se Admin quiser).

#### Agendamento manual via painel (US-6.3)

- **FR-010**: Sistema MUST permitir criar consulta vinculando paciente + profissional + tipo + horário inicial.
- **FR-011**: Sistema MUST validar que o slot está disponível (sem conflito, dentro da agenda, sem bloqueio) antes de persistir.
- **FR-011a**: Sistema MUST garantir unicidade de slot via constraint **`UNIQUE PARTIAL (tenant_id, professional_id, starts_at) WHERE status IN ('scheduled', 'confirmed')`** — gate atômico de race condition (resolvido em Opção A do clarify nº 1). **Reschedule preserva o status** (clarify nº 7), portanto não há status `reagendada`. Status `realizada`/`nao_realizada`/`canceled`/`concluida_sem_registro` são **terminais e fora do índice**: o slot já foi consumido (passou) ou liberado, e o histórico permanece consultável sem bloquear nova criação no mesmo horário em datas futuras.
- **FR-011b**: Sistema MUST gerar slots disponíveis derivando determinísticamente de (horários de trabalho, intervalos, exceções, duração do tipo, buffer do tipo) — sem campos de "horário customizado por profissional" no MVP.
- **FR-012**: Sistema MUST emitir `ConsultaCriada` ao persistir.
- **FR-013**: Sistema MUST mover o card do paciente no funil (Fase 2) para coluna "Agendado" via consumo do evento `ConsultaCriada`.
- **FR-014**: Sistema MUST suportar busca de paciente por nome/CPF (reusa fuzzy trgm da Fase 2) na criação.
- **FR-015**: Sistema MUST suportar cadastro rápido de paciente no fluxo de agendamento.
- **FR-016**: Sistema MUST tratar movimentação de bloco existente no painel como **reagendamento explícito** — drag-to-move exige modal de confirmação obrigatório antes de submeter (sem auto-save silencioso); ao confirmar dispara `ConsultaReagendada` + Fase 3 notifica paciente. Resolved nº 9 — Opção B.
- **FR-017**: Sistema MUST oferecer visualizações de calendário **diária e semanal** no MVP. Mensal explicitamente fora do escopo (slots pequenos demais para drag útil — pode entrar em fase futura). Resolved nº 9 — Opção B.
- **FR-017a**: View semanal MUST suportar toggle **multi-profissional** (grade por colunas, uma por profissional ativo do tenant). Default = single-prof. Resolved nº 9 — Opção B.
- **FR-017b**: Drag de slot indisponível (bloqueio, fora de agenda, conflito) ou reservado por outro holder (clarify nº 2) MUST ser validado client-side com snap-back + toast com motivo — sem chamada ao backend.

#### Confirmação automática (US-6.4)

- **FR-018**: Sistema MUST disparar evento de confirmação em T-24h da consulta no canal de origem do paciente (consumido pela Fase 3).
- **FR-018a**: Sistema MUST verificar antes do envio se paciente tem conversa ativa com IA (Fase 3); se sim, emitir `ConsultaConfirmacaoPendente` com flag `via_ia=true` para a IA assumir o fluxo (sem template duplicado). Resolved nº 6.
- **FR-018b**: Mensagem de confirmação MUST incluir no header horário e profissional da consulta ("Sua consulta amanhã às {HH:MM} com {Profissional}…") — facilita disambiguação quando paciente tem múltiplas consultas no mesmo dia. Resolved nº 6.
- **FR-019**: Sistema MUST disparar lembrete em T-2h apenas se consulta ainda não confirmada.
- **FR-019a**: Sistema MUST disparar **retry T-30min** se T-24h teve não-resposta (e consulta ainda não confirmada). Resolved nº 6 — Opção B.
- **FR-019b**: Sistema MUST, após T-15min sem resposta do paciente, registrar `ConfirmationDispatch.status='pending_manual'` (status do dispatch — **não** muda `Appointment.status`) e emitir `ConsultaPendenteContatoManual` (Fase 3 cria task na inbox para atendente). Não bloqueia operação automática downstream.
- **FR-020**: Sistema MUST processar resposta `1` (confirma) emitindo `ConsultaConfirmada`. Resposta vem como número em WhatsApp/Instagram, como clique-de-botão normalizado para `1|2|3` em widget web, e como interpretação de linguagem natural da IA em conversa ativa.
- **FR-021**: Sistema MUST processar resposta `3` (cancela) emitindo `ConsultaCancelada(motivo=paciente_via_chat)`.
- **FR-022**: Sistema MUST encaminhar resposta `2` (remarca) para a futura IA Matricial via evento `ReagendamentoSolicitadoPeloPaciente`.
- **FR-023**: Sistema MUST tratar respostas idempotentes (confirmar duas vezes não duplica eventos).
- **FR-024**: Sistema MUST, ao chegar T-24h em consulta com paciente sem canal de origem registrado (consulta criada sem inbox prévio), criar `ConfirmationDispatch.status='pending_manual'` (sem tentar disparo automático) e emitir `ConsultaPendenteContatoManual` para Fase 3 abrir task na inbox. **`Appointment.status` permanece `scheduled`** — apenas o dispatch é marcado.

#### Reagendamento / cancelamento via chat (US-6.5)

- **FR-025**: Sistema MUST expor consulta de slots disponíveis filtrando por profissional, tipo e janela de tempo.
- **FR-026**: Sistema MUST expor reagendamento atômico com idempotency_key (`POST /agenda/consultas/{id}/reagendar` com payload `{new_starts_at, idempotency_key}`).
- **FR-026a**: Reagendamento MUST preservar `professional_id` e `type_id` do `Appointment` original — esses campos não são aceitos no payload de reagendar. Resolved nº 7 — Opção B.
- **FR-026b**: Sistema MUST limitar reagendamentos por consulta a `tenant.max_reschedules_per_appointment` (default 2); 3ª tentativa retorna HTTP 422 com `error=reschedule_limit_exceeded`, `escalated_to_inbox=true`, `current_count`, `limit`; emite `LimiteDeReagendamentoExcedido` consumido pela Fase 3.
- **FR-026c**: Contador de reagendamentos MUST ser derivado de `AppointmentReschedule.count` para a consulta — sem campo redundante.
- **FR-027**: Sistema MUST expor cancelamento com motivo e quem cancelou (`paciente | atendente | profissional | sistema`).
- **FR-028**: Sistema MUST validar política de cancelamento com herança: `tenant.min_cancellation_hours` (default 4h) sobrescrito por `appointment_type.min_cancellation_hours` (nullable). Resolved nº 3 — Opção B.
- **FR-028a**: Sistema MUST permitir profissional/admin cancelar consulta sem trava de prazo via painel, exigindo motivo obrigatório (campo livre auditado).
- **FR-028b**: Sistema MUST, quando paciente/IA tenta cancelar fora do prazo, retornar HTTP 422 com `error=cancellation_outside_window`, `escalated_to_inbox=true`, `window_hours`, `current_hours_until_appt` — e emitir evento `CancelamentoSolicitadoForaDoPrazo` para a Fase 3 criar handoff na inbox.
- **FR-028c**: Sistema MUST, ao criar `ScheduleException` (US-6.1) que se sobreponha a consultas existentes, cancelar essas consultas com `quem_cancelou=sistema`, `motivo=schedule_exception` — cobrindo o caso "cancelamento em massa de um dia" sem feature dedicada.
- **FR-029**: Sistema MUST expor `POST /agenda/slots/{starts_at}/reservar` que cria uma reserva pessimista soft com TTL diferenciado por holder: 5 min para atendente humano (painel), 2 min para IA Matricial (chat). Resolved nº 2 — Opção C.
- **FR-029a**: Sistema MUST permitir TTL configurável por tenant (defaults: 5 min painel / 2 min IA); valores fora da faixa (1–15 min) são rejeitados.
- **FR-029b**: Sistema MUST executar cleanup periódico (cron ≤ 1 min) que libera reservas com `expires_at < now()`, populando `release_reason = expired`.
- **FR-029c**: Sistema MUST liberar reserva ao commit (`release_reason = committed`) ou ao cancel explícito do form (`release_reason = canceled`).

#### Lista de espera (US-6.6)

- **FR-030**: Sistema MUST permitir inscrição de paciente em lista de espera por (profissional, tipo) com prioridade FIFO.
- **FR-031**: Sistema MUST emitir `VagaAbertaNaListaDeEspera` quando cancelamento abre slot elegível — elegibilidade exige match exato de `(professional_id, type_id)` (sem fallback). Resolved nº 8 — Opção A.
- **FR-031a**: Sistema MUST executar cron periódico (≤ 1 min) que verifica `WaitlistEntry.status=notified AND notified_at + waitlist_confirmation_minutes < now()`, marca `status=expired`, e emite novo `VagaAbertaNaListaDeEspera` para o próximo da fila (FIFO).
- **FR-031b**: Vaga é oferecida sequencialmente: 1 candidato por vez, com prazo `tenant.waitlist_confirmation_minutes` (default 15 min) antes de expirar e passar para o próximo.
- **FR-032**: Sistema MUST notificar **apenas o primeiro candidato da fila** quando vaga abre — sem paralelismo. Resolved nº 8 — Opção A.
- **FR-033**: Sistema MUST garantir alocação atômica do slot — defesa em profundidade caso dois eventos `VagaAberta` ocorram simultaneamente (race do cron). Apenas um paciente fica com a vaga.

#### Sincronização Google/Outlook (US-6.7)

- **FR-034**: Sistema MUST suportar OAuth de Google Calendar por profissional, com token criptografado em repouso.
- **FR-035**: Sistema MUST espelhar criação/alteração/cancelamento de consulta para o Google Calendar do profissional em <2 minutos.
- **FR-036**: Sistema MUST bloquear slots no CRM quando evento externo é detectado no calendário sincronizado (sem importar como consulta).
- **FR-037**: Sistema MUST aplicar "CRM como fonte da verdade" em conflitos de edição simultânea.
- **FR-038**: Sistema MUST não incluir dados clínicos (CPF, convênio, sintomas) na descrição do evento Google/Outlook — apenas título genérico.
- **FR-039**: Sistema MUST detectar revogação OAuth, marcar canal como desconectado e notificar profissional via painel + email.
- **FR-040**: Sistema MUST tentar refresh automático de token expirado antes de declarar falha.
- **FR-041** **DEFERRED → Fase 6** (clarify nº 11): Outlook implementação cortada do MVP da Fase 5; modelo de domínio (`provider` enum) preparado para reuso futuro.

#### Marcação de comparecimento e no-show

- **FR-042**: Sistema MUST permitir Atendente/Admin marcar consulta como `ConsultaRealizada` (compareceu) ou `ConsultaNaoRealizada` (no-show) após o horário da consulta.
- **FR-043**: Sistema MUST emitir evento correspondente; flow downstream (campanhas "sentimos sua falta" etc.) é responsabilidade de fases futuras.
- **FR-044**: marcação manual de comparecimento; auto-flag "candidato a no-show" após T+30min (apenas hint visual, sem mudar status); janela máxima **7 dias** após `starts_at` para marcar; após 7d, cron move para `concluida_sem_registro` e bloqueia edição; reversão dentro de 48h; após 48h exige ability `appointment.revert_attendance_marking` (clarify nº 14).

---

### Key Entities

- **ProfessionalSchedule** — agenda do profissional: horários semanais, intervalos, tipos aceitos, timezone, professional_id, tenant_id.
- **ScheduleException** — bloqueio pontual (férias, feriado, evento): data início/fim, motivo, professional_id, tenant_id, **created_by_user_id (clarify nº 5)**.
- **AppointmentType** — tipo de atendimento: nome, duração (min), **buffer (min — clarify nº 1)**, **min_cancellation_hours (nullable — clarify nº 3)**, **valor_particular (decimal — clarify nº 4)**, **valor_convenio_default (decimal nullable — clarify nº 4)**, cor, status (ativo/inativo), tenant_id.
- **TenantSettings.min_cancellation_hours** (clarify nº 3 Opção B) — prazo mínimo default de cancelamento via chat aplicado quando o tipo não tem override. Default 4h. Faz parte das configurações do tenant (extensão da Fase 0).
- **TenantSettings.max_reschedules_per_appointment** (clarify nº 7 Opção B) — limite de reagendamentos por consulta antes de escalar para atendente. Default 2.
- **TenantSettings.waitlist_confirmation_minutes** (clarify nº 8 Opção A) — prazo para o candidato notificado confirmar a vaga antes de expirar e passar para o próximo da fila. Default 15.
- **AppointmentTypeProfessional** — vínculo M2M tipo↔profissional.
- **Appointment** — consulta: paciente_id, profissional_id, type_id, starts_at, ends_at, status (`scheduled | confirmed | canceled | realizada | nao_realizada | concluida_sem_registro` — clarify nº 7 (reschedule preserva status, sem `reagendada`) + clarify nº 14 (`concluida_sem_registro` terminal após cron 7d)), motivo_cancelamento, canal_origem (FK Fase 3), tenant_id, idempotency_key, created_by_user_id, **valor_aplicado (decimal snapshot — clarify nº 4)**, **valor_override_motivo (nullable — clarify nº 4)**, **override_block (boolean default false — clarify nº 5)**, **override_motivo (nullable — clarify nº 5)**, **attendance_marked_at / attendance_marked_by_user_id / attendance_motivo / auto_flagged_at (todos nullable — clarify nº 14)**.
- **AppointmentReschedule** — histórico de reagendamentos: appointment_id, starts_at_anterior, starts_at_novo, quem_solicitou, motivo, timestamp.
- **WaitlistEntry** — entrada na lista de espera: paciente_id, professional_id, type_id, status (`waiting | notified | accepted | expired | canceled`), notified_at, tenant_id.
- **CalendarSyncAccount** — vínculo OAuth do profissional: provider (`google | outlook`), encrypted_token, encrypted_refresh_token, status (`connected | disconnected | error`), expires_at, last_sync_at, professional_id, tenant_id.
- **CalendarSyncedEvent** — mapeamento appointment_id ↔ external_event_id por provider, para detecção de mudanças externas.
- **ConfirmationDispatch** — registro do disparo de confirmação: appointment_id, kind (`24h | 2h | retry_30min | 15min_manual_escalation` — clarify nº 6), sent_at, response_received_at, response_value (`1|2|3|null`), status, **via_ia (boolean — clarify nº 6: true quando IA assumiu sem envio de template)**.
- **SlotReservation** (clarify nº 2 Opção C) — reserva pessimista soft de slot durante criação/reagendamento: tenant_id, professional_id, starts_at, holder_type (`user | ia`), holder_id, acquired_at, expires_at, released_at (nullable), release_reason (`committed | expired | canceled`). Index em `(tenant_id, professional_id, starts_at)` + partial index em `expires_at WHERE released_at IS NULL` (cleanup job).

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Atendente cria uma consulta via painel em ≤ 30 segundos (busca paciente + escolher slot + confirmar).
- **SC-002**: 95% das consultas confirmadas via chat são processadas (resposta → estado atualizado) em ≤ 5 segundos.
- **SC-003**: Taxa de no-show por tenant reduz em ≥ 30% nos primeiros 60 dias após go-live da confirmação automática, comparado à baseline histórica.
- **SC-004**: Tempo entre cancelamento e notificação ao primeiro paciente da lista de espera ≤ 2 minutos.
- **SC-005**: Evento criado no CRM aparece no Google Calendar do profissional em ≤ 2 minutos (P95).
- **SC-006**: Zero incidentes de vazamento de dados clínicos para o Google/Outlook (auditoria mensal do payload sincronizado).
- **SC-007**: ≥ 80% dos profissionais que conectam Google Calendar mantêm a sync ativa após 30 dias (não revogam acesso).
- **SC-008**: Zero race conditions com perda de dados em criação/cancelamento simultâneo de consulta no mesmo slot (testado com 50 requests concorrentes).
- **SC-009**: API de slots disponíveis responde 95% das requisições em ≤ 300ms (RNF-001).

---

## Contratos Herdados das Fases 0–4

### Fase 0 — Fundação Multi-tenant

- **Multi-tenancy**: TODA entidade da Fase 5 (`ProfessionalSchedule`, `Appointment`, `AppointmentType`, `WaitlistEntry`, `CalendarSyncAccount`, etc.) tem `tenant_id` NOT NULL com scope automático. Princípio II — NON-NEGOTIABLE.
- **Auditoria**: criação, alteração, cancelamento e reagendamento de consulta gravam linha em `audit_logs` (já existente). Cada evento de domínio desta fase é também `Auditable`.
- **RBAC (Spatie team mode)** — novos abilities a criar:
  - `appointment.view` — listar/consultar consultas
  - `appointment.create` — criar consulta
  - `appointment.update` — editar (não move horário)
  - `appointment.cancel` — cancelar consulta
  - `appointment.override_block` — criar consulta dentro de bloqueio (encaixe de emergência — clarify nº 5 Opção B)
  - `appointment.manage_own_schedule` — médico edita a própria agenda
  - `schedule.configure` — Admin edita agenda de qualquer profissional do tenant
  - `appointment_type.manage` — Admin gerencia tipos de atendimento
  - `waitlist.manage` — Atendente/Admin gerencia lista de espera
  - `calendar_sync.configure` — Médico conecta/desconecta sua sync OAuth

  Atribuição por perfil:
  - **Admin Clínica** — todos os abilities acima (incl. `appointment.override_block`)
  - **Médico** — `appointment.view`, `appointment.create`, `appointment.update`, `appointment.cancel` (apenas da própria agenda), `appointment.manage_own_schedule`, `calendar_sync.configure`
  - **Atendente / Recepcionista** — `appointment.view`, `appointment.create`, `appointment.update`, `appointment.cancel`, `waitlist.manage`
  - **Financeiro** — `appointment.view` (somente leitura)
  - **Paciente via canal** — sem ability de painel; opera via fluxo de chat (consumido pela futura IA Matricial)

### Fase 2 — CRM Pacientes

- Consulta é **sempre** vinculada a um paciente existente. Ao emitir `ConsultaCriada`, listener da Fase 2 move o card do paciente no funil para coluna "Agendado".
- Busca de paciente reusa fuzzy match trgm/unaccent da Fase 2 — não reimplementar.
- Histórico de consultas alimenta a timeline do paciente (`eventos_timeline`).

### Fase 3 — Inbox Omnichannel

- Confirmações automáticas (US-6.4) e notificações de lista de espera (US-6.6) **NÃO** são reimplementadas aqui. Fase 5 emite eventos de domínio; Fase 3 consome e envia mensagens via WhatsApp/Instagram/Web Widget no canal de origem do paciente.
- Templates Meta aprovados são responsabilidade da Fase 3.
- Janela de 24h do WhatsApp respeitada pela Fase 3; Fase 5 emite o trigger.

### Fase 4 — Token Auth Migration

- Toda API de agendamento (criação, listagem, slot search, reagendamento, cancelamento) é Bearer-authenticated via Sanctum (Lote D-K da Fase 4).
- Header `X-Tenant-Slug` obrigatório em rotas autenticadas (FR-011 da Fase 4 / triple-check).
- Permissões consultadas via Spatie com `guardName()` = `web` pinado no User (Fase 4 Lote F).

### Futura Fase de IA Matricial (consumidor — não delivered ainda)

- Fase 5 expõe os contratos que a IA Matricial vai consumir:
  - `GET /agenda/slots-disponiveis?profissional_id&tipo_id&from&to` — lista paginada
  - `POST /agenda/consultas` — criar com `channel_origin=ia`
  - `POST /agenda/consultas/{id}/reagendar` — com idempotency_key
  - `POST /agenda/consultas/{id}/cancelar`
  - `POST /agenda/slots/{starts_at}/reservar` — reserva temporária pessimista soft (TTL configurável: 5min user / 2min IA — clarify nº 2). IA Matricial passa `holder_type=ia, holder_id={conversation_id}, idempotency_key=<uuid v7>`.
- Eventos consumidos pela IA Matricial: `SlotsDisponiveisParaIA` (resposta da consulta de slots), `ReagendamentoSolicitadoPeloPaciente` (vindo da resposta `2` da confirmação).

---

## Fora de Escopo desta Fase

- **Telemedicina nativa** — fora do produto no MVP. Tipo "Telemedicina" não é provisionado por padrão.
- **Cadência automática de retorno** (mensagem "está na hora de seu retorno") — Fase 6 (Gestão de Retornos).
- **Receituários vinculados à consulta** — Fase 6 (Receituários).
- **Pré-pagamento de consultas / cobrança do paciente** (sinal, no-show fee) — fora do MVP.
- **Integração com prontuário eletrônico** — fora do produto.
- **Bloqueio de recurso físico** (sala de consultório compartilhada entre profissionais) — fora do MVP (apenas agenda individual por profissional).
- **Outlook sync** — explicitamente cortado para Fase 6 (clarify nº 11). Modelo de domínio (`CalendarSyncAccount.provider`, `ExternalCalendarBusy.provider`) já aceita `outlook` para reuso futuro; UI exibe "em breve" desabilitado.
- **Gestão de sala / recurso físico compartilhado** entre profissionais — apenas agenda por profissional no MVP (clarify nº 15).
- **Cancelamento em massa** (profissional cancela todos os horários de um dia) — fora do MVP (NEEDS_CLARIFICATION nº 3).
- **Notificação push mobile** — não há app mobile nativo no MVP.

---

## Eventos de Domínio Emitidos

Contratos para outras fases consumirem. Todos são `Auditable` e gravam em `audit_logs`. Todos têm `tenant_id` e `event_id` (correlation).

| Evento | Quando dispara | Payload essencial | Consumidores |
|---|---|---|---|
| `ProfissionalAgendaConfigurada` | US-6.1 — agenda criada/alterada | `professional_id`, `effective_from`, `changed_by_user_id` | Sentry context, auditoria |
| `ConsultaCriada` | US-6.3 — consulta persistida | `appointment_id`, `patient_id`, `professional_id`, `type_id`, `starts_at`, `channel_origin` (`painel | ia | autoatendimento`), `created_by_user_id` | Fase 2 (move funil para "Agendado"), Fase 3 (notifica paciente), US-6.7 (sync) |
| `ConsultaConfirmada` | US-6.4 — paciente respondeu `1` | `appointment_id`, `confirmed_at`, `via` (`24h | 2h | manual`) | Fase 3 (futuro: cancelar lembrete T-2h se já confirmado) |
| `ConsultaCancelada` | US-6.4 / US-6.5 | `appointment_id`, `quem_cancelou` (`paciente | atendente | profissional | sistema`), `motivo`, `canceled_at` | US-6.6 (abre lista de espera), Fase 2 (timeline), métricas no-show |
| `ConsultaReagendada` | US-6.5 — reagendamento bem sucedido | `appointment_id`, `starts_at_anterior`, `starts_at_novo`, `quem_solicitou`, `motivo` | US-6.7 (sync update), Fase 2 (timeline) |
| `ConsultaRealizada` | US-6.x — Atendente marca compareceu | `appointment_id`, `marked_by_user_id`, `marked_at` | Fase 2 (timeline), Fase 6 (gatilho retorno), métricas |
| `ConsultaNaoRealizada` | US-6.x — no-show | `appointment_id`, `marked_by_user_id`, `marked_at` | Fase 2 (timeline), futuras campanhas "sentimos sua falta", métricas |
| `VagaAbertaNaListaDeEspera` | US-6.6 — cancelamento abre vaga compatível | `waitlist_entry_id`, `patient_id`, `slot_starts_at`, `professional_id`, `type_id`, `notification_window_minutes` | Fase 3 (envia mensagem ao paciente) |
| `CalendarioExternoSincronizado` | US-6.7 — primeira conexão / reconexão / desconexão OAuth | `professional_id`, `provider` (`google | outlook`), `status`, `last_sync_at` | Sentry context, painel "Sincronização" do profissional |
| `CancelamentoSolicitadoForaDoPrazo` (clarify nº 3 Opção B) | US-6.5 — paciente/IA tentou cancelar fora do prazo mínimo configurado | `appointment_id`, `patient_id`, `requested_by` (`paciente | ia`), `window_hours`, `current_hours_until_appt`, `requested_at` | Fase 3 cria handoff na inbox (atendente decide caso a caso) |
| `ConsultaPendenteContatoManual` (clarify nº 6 Opção B) | US-6.4 — após retry T-30min, sem resposta em T-15min | `appointment_id`, `patient_id`, `professional_id`, `attempts` (lista: `24h | 30min`), `last_dispatch_at` | Fase 3 cria task na inbox para atendente |
| `LimiteDeReagendamentoExcedido` (clarify nº 7 Opção B) | US-6.5 — paciente/IA tentou reagendar além do limite | `appointment_id`, `patient_id`, `requested_by` (`paciente | ia`), `current_count`, `limit`, `requested_at` | Fase 3 cria handoff na inbox para atendente decidir exceção |

---

## Riscos e Mitigações

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| R1 | **Race condition** em criação simultânea no mesmo slot | 🔴 Alta | Constraint UNIQUE (`tenant_id`, `professional_id`, `starts_at`) em nível de DB + retry no client. Validação atômica em transação. Política em NEEDS_CLARIFICATION nº 2. |
| R2 | **Sync bidirecional**: evento criado no Google que conflita com agenda do CRM | 🟡 Média | Eventos externos apenas BLOQUEIAM slots no CRM (não criam consulta). Manual de operação documenta. |
| R3 | **Revogação OAuth silenciosa** Google/Microsoft | 🟡 Média | Detecção na próxima chamada → mark `sync_disconnected` + notificar profissional. Histórico de eventos no Google preservado. |
| R4 | **Fuso horário** profissional ≠ tenant ≠ paciente | 🟢 Baixa (mitigado) | Armazenar em UTC; render por contexto: painel = TZ tenant, agenda própria = TZ profissional (override nullable), mensagem ao paciente = TZ tenant + qualificador explícito (clarify nº 13). |
| R5 | **Confirmação enviada → consulta cancelada manualmente no intervalo → paciente responde `1`** | 🟢 Baixa | AC-6.4.6 — resposta idempotente "já cancelada". Sem efeito colateral. |
| R6 | **Lista de espera**: K candidatos confirmam simultaneamente | 🟡 Média | Alocação atômica via row lock + status `notified → accepted`. Demais recebem `vaga_preenchida`. |
| R7 | **LGPD** — vazamento de PII clínica no Google Calendar | 🔴 Alta | FR-038: payload sincronizado apenas com título genérico + duração. Auditoria mensal do que sai. |
| R8 | **Cross-tenant leak** via Google: profissional atende em 2 tenants distintos com a mesma conta Google | 🟢 Baixa (mitigado) | `CalendarSyncAccount` tenant-scoped + sub-calendário dedicado `Paciente360 — {Tenant.nome}` criado automaticamente; toda escrita/leitura usa apenas `calendarId` do sub-calendário; UNIQUE(`tenant_id`, `professional_id`); teste de regressão obrigatório (AC-6.7.11) (clarify nº 15). |
| R9 | **Drift de IA Matricial vs Fase 5**: contrato de slots desalinhado | 🟡 Média | Esta fase define o contrato OpenAPI ANTES da IA Matricial entrar; congelado para reuso. |
| R10 | **Rate limit Google/Microsoft Graph** | 🟢 Baixa | Backoff exponencial; circuit breaker similar ao da Fase 3 para WhatsApp/Meta. |

---

## Ambiguidades a Resolver (NEEDS_CLARIFICATION)

Pontos elevados para `/speckit.clarify` — não decididos nesta spec porque a escolha afeta significativamente escopo / UX / risco LGPD. Numerados conforme o brief original do owner.

### ✅ RESOLVED nº 1 — Granularidade de slot (Opção A — Slots fixos, simples)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Slots fixos derivados da duração do tipo**. Para tipo `Consulta` de 30 min, a agenda gera slots às 08:00, 08:30, 09:00…  Atendente clica em slot existente — não escolhe minuto arbitrário.
- **Buffer fixo por tipo de atendimento** (configurável no cadastro do tipo, US-6.2). Aplicado uniformemente a todos os profissionais que executam aquele tipo. **Sem** override por profissional no MVP.
- **Duração fixa por tipo**. Sem override por consulta. Sem variável (15–45 min).

**Justificativa:**
- Contrato simples para a futura IA Matricial (`SlotsDisponiveisParaIA` = lista enumerada direta — sem geração on-demand de candidatos).
- Race condition resolvida no nível mais barato — constraint UNIQUE(`tenant_id`, `professional_id`, `starts_at`) no DB.
- Cobre o caso de uso de 90% das clínicas-alvo (medicina geral, pediatria, especialidades comuns).
- Casos de duração variável (cirurgia plástica, estética) ficam fora do MVP — podem ser endereçados em fase futura via "tipo com duração maior" ou override por consulta sem alteração de schema.

**Impacto nos FRs / ACs (a aplicar):**
- FR-001 (configurar horários): geração de slots derivada determinísticamente de (horário trabalho, intervalos, duração do tipo, buffer do tipo). Sem campos de "horário customizado por profissional".
- FR-006 (cadastrar tipo): inclui campo `buffer_minutos` no tipo de atendimento.
- Novo FR a inserir: "Sistema MUST garantir UNIQUE(`tenant_id`, `professional_id`, `starts_at`) em consultas ativas" (gate de race).
- Key Entity `AppointmentType` ganha `buffer_minutos`.
- Key Entity `ProfessionalSchedule` NÃO ganha override de duração — apenas horários de trabalho e intervalos.

### ✅ RESOLVED nº 2 — Conflito de horário e slot em negociação (Opção C — pessimistic soft + IA reserva)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Reserva pessimista soft via endpoint dedicado**. Ao abrir o form de criação/reagendamento, o cliente envia `POST /agenda/slots/{starts_at}/reservar` com TTL aplicado pelo servidor. Outros consumidores enxergam o slot como "em reserva" e ficam impedidos de abrir form para o mesmo slot.
- **TTL diferenciado por holder type**:
  - **Atendente / Recepcionista / Admin (painel)**: 5 min (form humano normal leva 30–60s; folga para distração).
  - **IA Matricial (chat com paciente)**: 2 min (UX de mensageria espera resposta rápida).
- **Liberação automática** quando: reserva expira por TTL OU quando o form é submetido (commit) OU quando o cliente cancela explicitamente o form.
- **Auditável**: cada reserva grava `holder_type` (`user | ia`), `holder_id`, `acquired_at`, `expires_at`, `released_at`, `release_reason` (`committed | expired | canceled`).
- **Defesa em profundidade**: a constraint `UNIQUE(tenant_id, professional_id, starts_at)` do FR-011a continua sendo a barreira final — mesmo que duas reservas escapem (race no próprio mecanismo de reserva), o DB ainda rejeita a segunda criação com `slot_conflict`.

**Justificativa:**
- Casa com o contrato da futura IA Matricial: sem reserva, IA fica competindo com painel — paciente confirma "sim" no chat e descobre que o horário foi pego.
- TTL automático elimina necessidade de UI de "liberar reserva manualmente"; sem locks indefinidos.
- Trade-off de adicionar 1 tabela (`SlotReservation`) e 1 cleanup job é pequeno comparado ao ganho de UX.

**Impacto nos FRs / ACs / Key Entities:**
- FR-029 atualizado para refletir o TTL e o endpoint dedicado.
- Adicionar FR-029a: TTL configurável por tenant (default 5 min painel / 2 min IA).
- Adicionar FR-029b: cleanup job de reservas expiradas (cron periódico).
- AC-6.5.7 atualizado: TTL = 2 min para IA.
- Novo Key Entity `SlotReservation`: `tenant_id`, `professional_id`, `starts_at`, `holder_type`, `holder_id`, `acquired_at`, `expires_at`, `released_at` (nullable), `release_reason`.

### ✅ RESOLVED nº 3 — Política de cancelamento (Opção B — tenant + override por tipo, bloqueia + escala)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Granularidade do prazo mínimo**: configurável em dois níveis com herança:
  - **Tenant** define o default global (`min_cancellation_hours`, default = 4h).
  - **AppointmentType** pode override (`min_cancellation_hours` nullable; quando NULL herda do tenant). Ex.: tipo `Cirurgia` = 48h; tipo `Retorno` = 2h.
- **Comportamento "fora do prazo" (paciente via chat)**: **bloqueia + escala**:
  - API `POST /agenda/consultas/{id}/cancelar` chamado por paciente/IA retorna 422 com `cancellation_outside_window` + flag `escalated_to_inbox=true`.
  - Fase 5 emite evento `CancelamentoSolicitadoForaDoPrazo` consumido pela Fase 3 que cria nota/handoff na inbox para atendente.
  - IA Matricial usa a resposta para formular: "Não consegui cancelar pelo prazo mínimo (X horas antes da consulta). Já passei o pedido para a equipe — você terá retorno em breve."
- **Profissional (médico/admin via painel)**: **irrestrito**. Motivo de cancelamento obrigatório (campo livre + auditado em `audit_logs`). Não há trava de prazo — médico precisa poder cancelar em emergência.
- **Cancelamento em massa**: **fora do MVP**. Médico que precisa "limpar agenda do dia" usa **bloqueio de agenda** da US-6.1 (criar `ScheduleException` para o dia/período) — operação única que dispara `ConsultaCancelada` para todas as consultas afetadas. Mais limpo que feature dedicada.

**Justificativa:**
- Reflete realidade clínica heterogênea (cirurgia ≠ consulta curta).
- "Bloqueia + escala" preserva agenda sem deixar paciente sem solução.
- Profissional irrestrito evita ergonomia ruim em emergência médica.
- Cobertura de cancelamento em massa via bloqueio de agenda evita duplicação de feature.

**Impacto nos FRs / ACs / Key Entities:**
- FR-028 atualizado: política de cancelamento com herança tenant → tipo.
- Adicionar FR-028a: profissional/admin cancela sem trava de prazo, com motivo obrigatório + audit.
- Adicionar FR-028b: bloqueio de agenda (US-6.1) com período cancela ou marca afetadas via `ConsultaCancelada` com `quem=sistema, motivo=schedule_exception`.
- AC-6.5.4 atualizado: comportamento "bloqueia + escala" descrito.
- Novo Key Entity tenant `tenant_settings.min_cancellation_hours` (default 4) e em `AppointmentType.min_cancellation_hours` (nullable, override).
- Novo evento de domínio: `CancelamentoSolicitadoForaDoPrazo` (payload: appointment_id, requested_by=paciente, window_hours, current_hours_until_appt).

### ✅ RESOLVED nº 4 — Tipos de atendimento (Opção A — naming claro, valor único)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Tipo `Retorno` da Fase 5 é categoria de duração** (tipo de atendimento mais curto), distinto da "gestão de retornos" / cadência automática da Fase 6. Documentação reforça a separação. UI usa rótulo "Tipo: Retorno" com tooltip explicativo. Audit em spec + UI desambigua.
- **Valor único por tipo no MVP**: dois campos no `AppointmentType` — `valor_particular` (decimal) e `valor_convenio_default` (decimal, nullable). Sem tabela de valores por (tipo × convênio) nesta fase.
- **Multi-convênio** (plano A R$ 150 vs plano B R$ 200) fica para fase futura de Billing/Faturamento — não bloqueia o MVP. Quando paciente tem convênio vinculado (Fase 2 — `pacientes_convenios`), o agendamento aplica `valor_convenio_default` por padrão; Atendente pode override pontualmente no formulário (campo livre).
- **Relatórios financeiros** do MVP usam o valor armazenado na consulta (snapshot no momento do agendamento) — evita reescrita histórica quando valores mudam.

**Justificativa:**
- Cobre 80% das clínicas-alvo PME sem complexidade extra.
- Multi-convênio sofisticado é caso de clínica grande, normalmente com ERP separado.
- Snapshot do valor na consulta evita reescrita histórica em relatórios.
- Naming explícito + tooltip elimina confusão Fase 5 ↔ Fase 6.

**Impacto nos FRs / ACs / Key Entities:**
- FR-006 atualizado: `valor_particular` + `valor_convenio_default` (nullable). Confirma single-value por tipo.
- Adicionar FR-006a: Consulta armazena snapshot do valor aplicado (`valor_aplicado` decimal) — não referência viva ao tipo (evita reescrita histórica).
- Adicionar FR-006b: Atendente pode override valor na criação da consulta com motivo (auditado).
- AC-6.2.4 reforçado: tooltip na UI + nota documental sobre separação Fase 5 (tipo) vs Fase 6 (cadência).
- Key Entity `Appointment` ganha `valor_aplicado` (decimal, snapshot) e `valor_override_motivo` (nullable).

### ✅ RESOLVED nº 5 — Agenda do profissional — exceções (Opção B — dual ownership + encaixe admin-only + sem agenda bloqueia)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Bloqueios pontuais (férias, feriados, eventos)** podem ser criados por **médico** (`appointment.manage_own_schedule` — só os próprios) **e por Admin Clínica** (`schedule.configure` — qualquer profissional do tenant). Cada `ScheduleException` armazena `created_by_user_id` para auditoria.
- **Encaixe de emergência (override de bloqueio)**:
  - Encaixe **normal** respeita bloqueios — usa apenas slots disponíveis dentro do horário de trabalho.
  - Encaixe **com override de bloqueio** exige ability dedicada `appointment.override_block` (granted apenas a Admin Clínica por default). Quando usada, Atendente/Admin cria consulta sobre o bloqueio com `motivo_override` (campo livre obrigatório), `override_block=true` flag no `Appointment`, e audit log com nível `warning`.
  - Override dispara **notificação obrigatória ao profissional afetado** (push no painel + email) para evitar surpresa.
- **Profissional sem agenda configurada**:
  - API/painel rejeita novos agendamentos com `professional_schedule_not_configured`.
  - Painel exibe wizard "Configurar agenda agora?" com opção "Copiar de outro profissional" para acelerar onboarding.
  - Mantém o invariant do clarify nº 1 (slots fixos só existem com agenda configurada).

**Justificativa:**
- Governança dupla equilibra autonomia do médico com poder administrativo.
- Override controlado cobre o caso real "encaixe de emergência" sem afrouxar a regra (ability + motivo + audit + notificação).
- Bloqueio quando sem agenda mantém consistência com Opção A do clarify nº 1.

**Impacto nos FRs / ACs / Key Entities:**
- FR-002 atualizado: criadores (médico OR admin) + audit do criador.
- Novo FR-002a: ability `appointment.override_block` permite criar consulta sobre bloqueio com motivo obrigatório; dispara notificação.
- Novo FR-002b: sistema rejeita novos agendamentos para profissional sem `ProfessionalSchedule` ativo, com error `professional_schedule_not_configured`.
- Novo ability na lista de Contratos Herdados: `appointment.override_block` (granted a Admin Clínica por default).
- AC-6.1.3 reforçado com cenário de override e notificação ao profissional.
- Key Entity `Appointment` ganha `override_block` (boolean, default false) + `override_motivo` (nullable).
- Key Entity `ScheduleException` ganha `created_by_user_id` (já implícito mas tornar explícito).

### ✅ RESOLVED nº 6 — Confirmação automática (Opção B — quick-replies universais + retry T-30min + uma mensagem por consulta)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Protocolo "1/2/3" é abstração interna**:
  - **WhatsApp / Instagram**: renderizado como número (paciente digita 1, 2 ou 3) — limitação do canal.
  - **Widget web**: 3 botões nativos (`Confirmar`, `Remarcar`, `Cancelar`) — Fase 3 mapeia clique → valor `1|2|3` no payload.
  - **Conversa ativa com IA**: Fase 5 NÃO envia template — emite `ConsultaConfirmacaoPendente` com flag `via_ia=true`; IA injeta pergunta natural no fluxo conversacional e mapeia resposta para `1|2|3`.
- **Retry de não-resposta**:
  - Se T-24h sem resposta, segunda tentativa **em T-30min**.
  - Se T-15min ainda sem resposta, registra `ConfirmationDispatch.status='pending_manual'` + cria task na inbox da Fase 3 para atendente (não bloqueia operação clínica; `Appointment.status` permanece `scheduled`).
- **Múltiplas consultas no mesmo dia**: **uma mensagem por consulta**, com horário no header da mensagem ("Sua consulta amanhã às 14h com Dr. X…"). Sem consolidação para evitar ambiguidade no parsing.

**Justificativa:**
- Retry T-30min recupera 40-60% das não-respostas a custo marginal trivial.
- Quick-replies universais resolvem todos os canais via abstração já existente na Fase 3.
- IA contextual evita quebra de fluxo conversacional.
- Uma mensagem por consulta com horário no header é mais fácil de parsear e auditar.

**Impacto nos FRs / ACs / Key Entities:**
- FR-019 atualizado: lembrete em T-2h **e** retry T-30min se não-resposta, ambos apenas se consulta ainda não confirmada.
- Adicionar FR-019a: timing dos retries é `T-24h`, `T-2h` (lembrete escalonado, não retry), `T-30min` (retry de não-resposta T-24h).
- Adicionar FR-019b: T-15min sem resposta → registra `ConfirmationDispatch.status='pending_manual'` (status do dispatch — não muda `Appointment.status`) + emite `ConsultaPendenteContatoManual` consumido pela Fase 3 (cria task na inbox).
- Adicionar FR-018a: quando paciente tem conversa ativa com IA (Fase 3 sinaliza), `ConsultaConfirmacaoPendente` emite com `via_ia=true` — IA assume; sem envio de template duplicado.
- Adicionar FR-018b: mensagem de confirmação MUST incluir horário e profissional no header ("Sua consulta amanhã às 14h com Dr. X..."); template Meta aprovado pela Fase 3 deve ter placeholder.
- Adicionar FR-020a: widget web mapeia clique de botão → mesmo valor `1|2|3` (Fase 3 normaliza no payload).
- AC-6.4.5 atualizado: distingue lembrete T-2h vs retry T-30min.
- Novo Key Entity / atributo: `ConfirmationDispatch.kind` enum expandido para `24h | 2h | retry_30min | 15min_manual_escalation`.

### ✅ RESOLVED nº 7 — Reagendamento via chat (Opção B — contrato simples + mantém prof+tipo + limite 2 reagendamentos)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Contrato confirmado**: `GET /agenda/slots-disponiveis?profissional_id&tipo_id&from&to` + `POST /agenda/consultas/{id}/reagendar` (com idempotency_key + `new_starts_at`). API estável que a futura IA Matricial vai consumir.
- **Mantém `professional_id` e `type_id`** por default. Reagendamento move a MESMA consulta no tempo — não troca o vínculo médico-paciente nem o tipo. Para trocar profissional ou tipo, paciente cancela + cria nova consulta (dois fluxos distintos com semântica clara para funil/faturamento).
- **Limite 2 reagendamentos por consulta** (configurável por tenant via `tenant.max_reschedules_per_appointment`, default 2). 3ª tentativa retorna HTTP 422 com `error=reschedule_limit_exceeded` + flag `escalated_to_inbox=true`; emite `LimiteDeReagendamentoExcedido` consumido pela Fase 3 que cria handoff para atendente decidir caso a caso (pode ser exceção legítima: viagem, doença familiar).

**Justificativa:**
- Contrato simples acelera prompt-engineering da futura IA Matricial.
- Manter prof+tipo preserva semântica de "mover a mesma consulta no tempo".
- Limite 2 cobre 95% dos casos legítimos (imprevisto pessoal × 1 + ajuste de agenda médica × 1) e captura paciente "passeador".
- Escala para atendente após limite mantém solução humana (não é hard block).

**Impacto nos FRs / ACs / Key Entities:**
- FR-025 / FR-026 confirmados (já refletem o contrato).
- Adicionar FR-026a: reagendamento mantém `professional_id` + `type_id` do `Appointment` original — campos não aceitos no payload.
- Adicionar FR-026b: limite de reagendamentos configurável via `tenant.max_reschedules_per_appointment` (default 2); 3ª tentativa retorna 422 com flag `escalated_to_inbox=true`.
- Adicionar FR-026c: contador de reagendamentos é derivado de `count(AppointmentReschedule WHERE appointment_id=X)` — sem campo redundante.
- AC-6.5.5 atualizado: confirma que prof+tipo são preservados (não overridáveis).
- AC-6.5.6 atualizado: limite default = 2 com handoff via inbox.
- Novo evento de domínio: `LimiteDeReagendamentoExcedido`.
- Nova chave em `TenantSettings`: `max_reschedules_per_appointment` (default 2).

### ✅ RESOLVED nº 8 — Lista de espera (Opção A — sequencial puro 1 por vez)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **K = 1**: apenas o primeiro da fila é notificado quando vaga abre. Sequencial puro, sem paralelismo. Se ele não confirma dentro do prazo, próximo é notificado.
- **Elegibilidade**: apenas o **tipo original da vaga** (cancelou `Consulta` com Dr. X → notifica somente quem espera `Consulta` com Dr. X). Sem fallback de tipos.
- **Prazo para confirmar**: configurável por tenant via `tenant.waitlist_confirmation_minutes`, **default 15 min**. Após o prazo, posição expira (status `expired`) e próximo da fila é notificado.
- **Escopo da lista**: por `(profissional + tipo)`. Cada combinação tem sua própria fila FIFO.
- **Múltiplas listas simultâneas**: **permitido** — paciente pode estar em filas distintas (Dr. X para Consulta + Dr. Y para Consulta + Dr. X para Retorno). Sem limite.

**Justificativa:**
- UX ideal: paciente notificado tem real chance de pegar a vaga, sem competir com 2 outros.
- Spam mínimo (1 mensagem por evento), respeita o canal do paciente.
- Algoritmo simples FIFO + timeout — fácil de auditar e debugar.
- Latência aceitável (15 min × N candidatos) para clínicas com fluxo moderado.
- Trade-off honesto: vagas em janela curta (≤ 30 min antes) podem ser "perdidas" se 1-2 primeiros não respondem rápido — atendente pode acelerar manualmente nesses casos.

**Impacto nos FRs / ACs / Key Entities:**
- FR-031 confirmado (emite `VagaAbertaNaListaDeEspera` para 1 candidato).
- FR-032 atualizado: notifica **apenas o primeiro** da fila; após `tenant.waitlist_confirmation_minutes` sem confirmação, posição expira e próximo é notificado.
- FR-033 mantido — alocação atômica continua valendo (defesa em profundidade caso 2 eventos paralelos coincidam).
- Adicionar FR-031a: cron periódico (≤ 1 min) verifica `WaitlistEntry` com `status=notified AND notified_at + waitlist_confirmation_minutes < now()` → marca `status=expired`, emite novo `VagaAbertaNaListaDeEspera` para próximo da fila.
- Adicionar FR-031b: elegibilidade exige match exato de `(professional_id, type_id)` entre vaga e entrada na lista. Sem fallback para outros tipos.
- AC-6.6.2/6.6.3 atualizados para refletir sequencial.
- AC-6.6.4 reescrita: **não** é paralelo K=3; mantém apenas 1 candidato por vez.
- AC-6.6.5 mantido: múltiplas listas permitidas.
- Nova chave em `TenantSettings`: `waitlist_confirmation_minutes` (default 15).
- Key Entity `WaitlistEntry.status` enum mantém `waiting | notified | accepted | expired | canceled`.

### ✅ RESOLVED nº 9 — Drag-and-drop no calendário (Opção B — drag-create + confirm-to-move + 2 views + multi-prof opcional)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Criação por drag** em região vazia (selecionando faixa de duração) abre formulário com `starts_at` e duração pré-preenchidos. **Click** em slot também abre o formulário (atalho — equivalente).
- **Mover consulta existente** por drag abre **modal de confirmação obrigatório** antes de submeter: "Reagendar consulta de {Paciente} de {hora_atual} para {hora_nova}? O paciente será notificado." → confirma → dispara `ConsultaReagendada` (US-6.5 flow) + Fase 3 notifica paciente no canal de origem. Sem auto-save silencioso.
- **Visualizações no MVP**: **diária + semanal**. Mensal fora do MVP (slots ficam pequenos demais para drag útil; pode entrar em fase futura).
- **Multi-profissional na view semanal**: **toggle opcional** no header. Quando ativado, renderiza grade lado-a-lado (colunas por profissional ativo do tenant). Default = single-prof (atendente escolhe profissional no topo). Mensal segue só single-prof.
- **Edge cases de drag**:
  - Slot indisponível (bloqueio, fora de agenda, conflito): bloco "snaps back" + toast vermelho com motivo. Sem chamada ao backend.
  - Slot reservado por outro usuário (clarify nº 2): bloco rejeitado com toast "Slot está sendo editado por {holder} — aguarde N s para tentar".

**Justificativa:**
- Drag-to-create cobre US-6.3 literal; click é atalho útil.
- Modal de confirmação no drag-to-move evita incidente operacional ("paciente recebeu notificação errada — atendente tem que ligar pedindo desculpa").
- 2 views cobrem 95% do uso (diária para atendente, semanal para admin/médico).
- Multi-prof opcional cobre cenário "ver agenda lado a lado" sem forçar a complexidade default.

**Impacto nos FRs / ACs / Key Entities:**
- FR-016 reforçado: movimentação por drag exige modal de confirmação no cliente; sem auto-save.
- FR-017 atualizado: visualizações diária + semanal no MVP; mensal explicitamente fora do escopo.
- Adicionar FR-017a: view semanal suporta toggle multi-profissional (grade por colunas).
- Adicionar FR-017b: drag de slot indisponível ou reservado é validado client-side (snap-back + toast) — sem chamada ao backend.
- AC-6.3.3 reforçado com modal de confirmação.
- AC-6.3.7 atualizado: diária e semanal (não mensal).
- Novo AC-6.3.8 🟡: toggle multi-profissional na view semanal.

### ✅ RESOLVED nº 10 — Sincronização Google Calendar (Opção A — push + polling fallback + CRM exclusivo + título fixo + 60d)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Modo de sincronização**: **push primário** via Google Calendar Watch API (notificações em ≤30s) **+ polling 5 min como fallback** (cobre revogação silenciosa de canal, perda de webhook, ressincronização periódica).
- **Ownership / fonte da verdade**: **CRM é fonte da verdade exclusiva**. Eventos criados direto no Google pelo profissional (fora do CRM) **apenas bloqueiam o slot** correspondente — não viram `Appointment`. Esses bloqueios são representados como `ExternalCalendarBusy` (entidade leve: `tenant_id`, `professional_id`, `external_event_id`, `starts_at`, `ends_at`, `provider`, `summary_redacted`) **somente** para que o gerador de slots os enxergue como ocupados. Não aparecem na agenda do CRM como consulta.
- **Edição simultânea (mesmo evento Google ↔ Appointment espelhado)**: CRM ganha. Mudança feita no Google em evento espelhado pelo CRM é **revertida no próximo push/polling cycle**, com audit `warning` registrado para diagnóstico.
- **Título / payload enviado para o Google**: **fixo** `Consulta — {Profissional}` (sem nome do paciente, sem CPF, sem convênio, sem tipo clínico — atende FR-038 / Princípio I LGPD). Descrição: `Agendamento via {Tenant.nome}` + link curto para o painel (sem PII no link). Sem campo configurável pelo profissional no MVP.
- **Janela sincronizada**: **próximos 60 dias** futuros (rolling window). Eventos passados nunca são sincronizados; eventos > 60 dias entram quando rolam para dentro da janela. Janela configurável por tenant via `tenant.calendar_sync_window_days` (default 60) — sem override por profissional no MVP.

**Justificativa:**
- Push <30s atende a expectativa de "real-time" implícita em US-6.7 sem precisar de polling agressivo (poupa quota Google API).
- Polling 5 min cobre o caso real de canais Google Watch expirarem (≤7 dias TTL) ou de webhook drop por falha de rede — sem isso, sync silenciosamente "morre".
- Eventos externos como `ExternalCalendarBusy` (não `Appointment`) preserva o invariant de que toda `Appointment` tem `patient_id` interno — modelo de domínio limpo, evita classes especiais de Appointment "externa" no MVP. Funil/financeiro/lista de espera continuam coerentes.
- Título fixo minimiza superfície LGPD a auditar (FR-038); reverter no Google evita escopo de "campo customizável que pode vazar PII" no MVP.
- Janela 60d limita volume de chamadas Google API e tamanho de tabela de espelhamento; suficiente para a maioria das clínicas (raras agendam > 60d antes).

**Impacto nos FRs / ACs / Key Entities:**
- FR-035 atualizado: sync bidirecional via push + polling 5min fallback; janela 60d rolling.
- Adicionar FR-035a: eventos externos do Google viram `ExternalCalendarBusy` (não `Appointment`); gerador de slots considera-os ocupados.
- Adicionar FR-035b: edição simultânea Google ↔ CRM resolvida com **CRM ganha** + audit `warning`.
- FR-038 reforçado: payload Google contém apenas `título fixo` + `descrição sem PII`; auditoria LGPD valida via teste no CI.
- Adicionar FR-038a: título Google = `Consulta — {Profissional.nome_publico}`; sem nome do paciente, CPF, convênio ou tipo clínico.
- Adicionar FR-038b: janela de sincronização configurável via `tenant.calendar_sync_window_days` (default 60).
- AC-6.7.2 atualizado: payload do título conforme FR-038a (não `Consulta — {Profissional}` genérico — explicita o `nome_publico`).
- AC-6.7.8 atualizado: janela 60d como default; configurável por tenant.
- AC-6.7.10 atualizado: push primário + polling 5min fallback (não "push OR polling" — ambos rodam).
- Novo Key Entity `ExternalCalendarBusy`: `tenant_id`, `professional_id`, `external_event_id` (ID do Google), `starts_at`, `ends_at`, `provider` (`google` | `outlook`), `summary_redacted` (string truncada/sanitizada apenas para debug — nunca exibida ao paciente), `synced_at`.
- Nova chave em `TenantSettings`: `calendar_sync_window_days` (default 60).
- `CalendarSyncAccount` ganha `watch_channel_id` (nullable) + `watch_channel_expires_at` (nullable) + `last_polled_at` (nullable) para gerenciar push + polling fallback.

### ✅ RESOLVED nº 11 — Sincronização Outlook (Opção C — SHOULD-HAVE deferred → Fase 6, modelo preparado)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Outlook sync explicitamente cortado da Fase 5**. Não entra no MVP de agendamento.
- **Modelo de domínio preparado para reuso futuro**: as enums e colunas que aceitam provider já incluem `outlook` desde a migration inicial:
  - `CalendarSyncAccount.provider` enum: `('google', 'outlook')` — Fase 5 implementa só `google`; Fase 6 ativa `outlook` sem alterar schema.
  - `ExternalCalendarBusy.provider` enum: idem.
  - Tabela `oauth_tokens` (PII em repouso) já é provider-agnostic (`provider` string + `provider_user_id`).
- **FRs/ACs Outlook**: removidos do escopo da Fase 5 e movidos para o backlog da Fase 6:
  - FR-041 (Outlook = mesmo contrato Google) → **DEFERRED → Fase 6**.
  - AC-6.7.9 (sync Outlook idêntico via Microsoft Graph) → **DEFERRED → Fase 6**.
- **Comunicação**: painel de configuração de sync exibe "Microsoft Outlook (em breve — Fase 6)" como placeholder desabilitado, evitando expectativa de feature ausente.
- **Justificativa para o corte**:
  - Microsoft Graph exige consent admin M365 (vs OAuth Google direto) — fluxo de onboarding mais complexo.
  - Watch channels (subscriptions Microsoft Graph) têm TTL ~3 dias (vs Google ~7 dias) — exige renovação mais agressiva, complica o cron de manutenção.
  - Universo Apple/Google domina a base alvo PME brasileira (medicina geral, pediatria) — Outlook é minoria estatística.
  - Manter o contrato (`provider` enum) garante que Fase 6 reusa 80% do código sync layer.

**Impacto nos FRs / ACs / Key Entities:**
- FR-041 marcado **DEFERRED → Fase 6** (texto preservado para referência, não conta no escopo da Fase 5).
- AC-6.7.9 marcado **DEFERRED → Fase 6**.
- US-6.7 reescopada: **Sincronização bidirecional com Google Calendar** (sem "e Outlook" no título). Outlook mencionado apenas como placeholder de UI desabilitado.
- "Fora de Escopo desta Fase" ganha bullet explícito: "Outlook sync — modelo preparado, implementação na Fase 6".
- `CalendarSyncAccount.provider` enum mantém `('google', 'outlook')` na migration; validation em service layer rejeita `provider=outlook` na Fase 5 com `provider_not_yet_supported`.
- Postman collection / OpenAPI da Fase 5 documenta `provider=outlook` como **reservado** com nota "implementação Fase 6".

### NEEDS_CLARIFICATION nº 12 — Revogação e reconexão de OAuth

- Profissional revoga acesso: **como notificar**? E-mail, notificação no painel, ambos?
- Sistema tenta **refresh automático** do token antes de declarar falha?
- Histórico de consultas sincronizadas após desconexão: confirma que **permanecem no Google** (não apaga)?

### ✅ RESOLVED nº 13 — Fuso horário (Opção A — TZ tenant default + override profissional + UTC interno + TZ explícito no texto)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **TZ tenant é o default global** (já existe em Fase 0 — `tenants.timezone`). Cobre 95% dos casos (clínica única em uma cidade).
- **Profissional pode ter `timezone` override** (nullable em `Professional`; quando NULL herda do tenant). Caso de uso: clínica de telemedicina com médicos espalhados (ex.: tenant SP, médico em Manaus).
- **Armazenamento interno: tudo em UTC**. Toda coluna `timestamptz` (`Appointment.starts_at`, `ScheduleException.starts_at`, `WaitlistEntry.notified_at`, `ExternalCalendarBusy.starts_at`, etc.) persiste em UTC. PostgreSQL `timestamptz` faz a conversão na leitura quando a sessão tem TZ correto.
- **Render por contexto**:
  - **Painel admin / atendente**: TZ do tenant.
  - **Agenda própria do médico** (página "minha agenda"): TZ do profissional (se ele tem override; senão TZ tenant).
  - **Mensagens ao paciente** (Fase 3 — confirmação T-24h, lembrete T-2h, retry, lista de espera, reagendamento): horário renderizado em TZ tenant **com qualificador explícito no texto**: `14:00 (horário de São Paulo)`. O qualificador é derivado do TZ tenant via lookup IANA → cidade canônica (ex.: `America/Sao_Paulo` → "São Paulo"). Sem detecção de TZ do paciente (canais de mensageria não fornecem TZ confiável).
  - **API pública / IA Matricial** (`GET /agenda/slots-disponiveis`, `GET /agenda/consultas/{id}`): timestamps sempre **ISO 8601 com offset** (`2026-06-15T14:00:00-03:00`). Cliente decide como renderizar; campo `timezone_display` no envelope da resposta indica o TZ canônico para exibição (`America/Sao_Paulo`).
- **Horário de verão / mudanças de TZ retroativas**: como tudo é UTC interno + IANA TZ no render, transições DST (incluindo a abolição em 2019 no Brasil) não afetam consultas já marcadas. PostgreSQL converte corretamente conforme dados tzdata.
- **Google Calendar sync** (clarify nº 10): eventos enviados para o Google usam `timezone` do profissional (ou tenant se sem override) — Google entende IANA TZ nativo (`{"start": {"dateTime": "...", "timeZone": "America/Sao_Paulo"}}`).

**Justificativa:**
- TZ por tenant é o caso comum; override por profissional cobre telemedicina sem complicar o caso simples.
- UTC interno é a única forma sã — mistura de TZ no DB cria bugs em DST e em migração entre TZ.
- Qualificador explícito no texto da mensagem (`14:00 (horário de São Paulo)`) elimina ambiguidade para pacientes que viajam ou moram em outro estado, sem precisar resolver o problema sem solução de "detectar TZ do paciente via WhatsApp".
- API com offset ISO + `timezone_display` dá ao cliente (futuro frontend IA Matricial) liberdade de renderizar contextualmente.

**Impacto nos FRs / ACs / Key Entities:**
- FR-009 (criação de consulta) atualizado: `starts_at` aceito em ISO 8601 com offset; convertido para UTC no DB.
- Adicionar FR-009a: gerador de slots respeita o TZ do profissional (se override) na hora de cortar o dia em slots; UTC normaliza no DB.
- FR-019 (lembrete T-24h / T-2h) atualizado: payload do template inclui horário com qualificador `(horário de {Cidade})` derivado do TZ tenant.
- FR-035 (Google sync) confirmado: Google recebe `dateTime` + `timeZone` IANA explícito.
- Novo FR-009b: API REST sempre retorna timestamps ISO 8601 com offset + campo de envelope `timezone_display` (string IANA).
- Key Entity `Professional` ganha `timezone` (nullable, IANA string como `America/Sao_Paulo`); quando NULL herda de `Tenant.timezone`.
- Key Entity `Appointment.starts_at` permanece `timestamptz UTC`.
- Edge case adicionado em "Edge Cases": "Profissional com override TZ ≠ tenant — slots da agenda dele cortados no TZ profissional; mensagem ao paciente sempre com TZ tenant + qualificador".

### ✅ RESOLVED nº 14 — No-show e comparecimento (Opção A — manual + auto-flag candidato + janela 7d + apenas evento)

**Decisão** (clarify 2026-05-13, owner = barraroot):

- **Marcação manual pelo atendente** é a fonte da verdade. Atendente abre a consulta no painel (a partir de T-0) e clica em "Realizada" ou "Não realizada". Campo `motivo` opcional (livre, auditado).
- **Auto-flag "candidato a no-show"** após `starts_at + 30 min` sem marcação manual: aparece destaque visual no painel ("⚠️ Sem confirmação de comparecimento") + entra em widget "Pendentes de marcação" do dashboard. **NÃO altera status** — apenas hint para o atendente confirmar com 1 clique.
- **Janela máxima de marcação: 7 dias** após `starts_at`. Após 7 dias sem marcação manual, sistema executa cron diário que move status para `concluida_sem_registro` e bloqueia edição. Audit log registra auto-fechamento. Não emite `ConsultaRealizada` nem `ConsultaNaoRealizada` neste caso (estado deliberadamente neutro para não distorcer métricas de no-show).
- **Sem mensagem automática "sentimos sua falta"** no MVP. Justificativas: risco de mensagem indelicada para paciente que faltou por motivo grave (luto, hospitalização); ruído no canal; cadência de re-engajamento é responsabilidade da Fase 6 (gestão de retornos).
- **Eventos emitidos**:
  - `ConsultaRealizada` — payload: `appointment_id`, `marked_by_user_id`, `marked_at`, `motivo` (nullable). Consumido por: Fase 6 (gera próximo retorno se aplicável), relatórios financeiros (faturamento da consulta realizada).
  - `ConsultaNaoRealizada` — payload: `appointment_id`, `marked_by_user_id`, `marked_at`, `motivo` (nullable), `auto_flagged_at` (timestamp do auto-flag se aplicável). Consumido por: Fase 6 (cadência de retorno opcional), métricas de no-show por tenant, lista de espera (vaga liberada se ainda há tempo útil — improvável após o horário, mas não nulo se for consulta longa).
- **Reversão**: atendente que marcou erradamente pode reverter dentro de **48h** (UI exibe botão "Desmarcar" só nesse intervalo); reversão emite evento de compensação `ConsultaMarcacaoRevertida` + audit log. Após 48h, reversão exige Admin Clínica (ability dedicada `appointment.revert_attendance_marking`) e gera audit `warning`.
- **Status final no ciclo de vida da `Appointment`** (`Appointment.status` enum): `agendada → confirmada → realizada | nao_realizada | concluida_sem_registro | cancelada`. Transições inválidas são rejeitadas em service layer.

**Justificativa:**
- Manual = fonte da verdade humana, evita falsos positivos do auto.
- Auto-flag como hint reduz esquecimento sem usurpar decisão.
- Janela 7d evita "limpeza retroativa" infinita que distorce métricas (clínica que esqueceu 3 meses não pode marcar tudo de uma vez e bagunçar dashboard).
- Sem mensagem automática evita risco reputacional e centraliza re-engajamento na Fase 6.
- Reversão com janela curta + escalada para Admin cobre erros operacionais sem permitir manipulação retroativa de métricas.

**Impacto nos FRs / ACs / Key Entities:**
- FR-044 atualizado: marcação manual + auto-flag T+30min + janela 7d + estado terminal `concluida_sem_registro`.
- Adicionar FR-044a: cron diário (00:30 BRT) varre `Appointment WHERE status IN ('agendada','confirmada') AND starts_at < now() - 7 days`; move para `concluida_sem_registro`; emite audit log.
- Adicionar FR-044b: reversão dentro de 48h via mesma ability `appointment.update`; após 48h exige `appointment.revert_attendance_marking`; emite `ConsultaMarcacaoRevertida`.
- Adicionar FR-044c: campo `auto_flagged_at` registra quando o sistema sinalizou candidato a no-show (apenas para audit / debug — não muda status).
- Novo evento de domínio: `ConsultaMarcacaoRevertida` (payload: appointment_id, reverted_by_user_id, reverted_at, previous_status).
- AC-6.4.6 (se existir relacionado a no-show) ou novo AC: marcação manual fluxo, auto-flag, janela 7d, reversão 48h.
- Key Entity `Appointment.status` enum expande para incluir `concluida_sem_registro`.
- Key Entity `Appointment` ganha: `attendance_marked_at` (nullable timestamp), `attendance_marked_by_user_id` (nullable FK), `attendance_motivo` (nullable text), `auto_flagged_at` (nullable timestamp).
- Nova ability na lista de Contratos Herdados: `appointment.revert_attendance_marking` (granted a Admin Clínica por default).
- Edge case adicionado em "Edge Cases": "Atendente marca consulta errada como realizada → tem 48h para reverter direto; após 48h, Admin Clínica reverte com audit `warning`."
- Widget de dashboard: "Pendentes de marcação de comparecimento" lista consultas com `starts_at < now() - 30min AND status IN ('agendada','confirmada') AND auto_flagged_at IS NOT NULL`.

### ✅ RESOLVED nº 15 — Múltiplos profissionais e clínicas compartilhadas (Opção A — sub-calendário tenant-scoped + sem gestão de recurso físico no MVP)

**Decisão** (clarify 2026-05-13, owner = barraroot):

#### Parte 1 — Sala / recurso físico compartilhado (default proposto confirmado)

- **NÃO** entra no MVP. Fase 5 gerencia agenda apenas por profissional. Conflito de "Dr. X e Dr. Y usam o mesmo consultório em horários alternados" é resolvido externamente pelo Admin Clínica (ex.: configura agendas que não se sobrepõem para a mesma sala).
- Modelagem de `Room` / `Resource` fica para fase futura (provavelmente Fase 7+ após Faturamento).
- Documentar como **fora de escopo explícito** para evitar pedido recorrente.

#### Parte 2 — Cross-tenant Google leak (R8 🔴 Alta)

- **`CalendarSyncAccount` é tenant-scoped** com UNIQUE(`tenant_id`, `professional_id`) — uma conexão Google por (tenant × profissional). Profissional que atende em 2 tenants conecta o mesmo account Google **duas vezes**, gerando 2 registros `CalendarSyncAccount` distintos.
- **Sub-calendário dedicado por tenant**: ao conectar, Fase 5 cria automaticamente no Google do profissional um sub-calendário com nome `Paciente360 — {Tenant.nome}` (cor única por tenant para destaque visual). O ID retornado é persistido em `CalendarSyncAccount.google_calendar_id`.
- **Toda escrita E leitura usa apenas esse `calendarId`**:
  - Push: `events.insert?calendarId={sub-cal-id}` — eventos criados pelo CRM tenant A vão SÓ para o sub-calendário tenant A.
  - Polling: `events.list?calendarId={sub-cal-id}` — bloqueios externos puxados pelo polling do tenant B só consideram eventos do sub-calendário tenant B.
  - Watch channel: registrado apenas no sub-calendário do tenant.
- **Resultado**: zero leak cross-tenant. Eventos do tenant A são invisíveis ao polling do tenant B (sub-calendário diferente). Profissional vê os 2 sub-calendários lado-a-lado no app Google Calendar (UX clara — sabe que cada cor é uma clínica).
- **Eventos no calendário primário do médico** (criados pelo próprio médico fora do CRM): NÃO viram `ExternalCalendarBusy` em nenhum tenant — a sync só lê o sub-calendário gerenciado. Trade-off aceito: agenda pessoal não bloqueia automaticamente; profissional precisa criar bloqueio explícito no CRM (`ScheduleException` da US-6.1) ou no sub-calendário do CRM.
- **Erro de criação do sub-calendário** (quota Google, scope insuficiente): conexão falha com `calendar_subcalendar_creation_failed`; profissional reconecta após corrigir scope.
- **Renomear o sub-calendário no Google manualmente pelo profissional**: Fase 5 detecta no próximo sync via `calendars.get` e atualiza `CalendarSyncAccount.google_calendar_name_seen` (audit info — não bloqueia funcionamento; o ID interno persiste).

**Justificativa:**
- Zero vazamento cross-tenant sem exigir que o profissional crie 2 contas Google separadas (atrito alto, fricção de adoção).
- Sub-calendário dedicado dá UX clara (médico vê visualmente "Clínica A azul, Clínica B verde").
- Modelo se estende limpo para Outlook (Fase 6) com mesma estratégia (sub-calendário Microsoft Graph).
- Evita complexidade de filtro por título/convenção (Opção D do clarify), que vazaria pelo menos os horários para o `ExternalCalendarBusy` do tenant errado.

**Impacto nos FRs / ACs / Key Entities:**
- Adicionar FR-036a: ao conectar Google, Fase 5 cria automaticamente sub-calendário `Paciente360 — {Tenant.nome}` no Google do profissional; persiste `google_calendar_id` em `CalendarSyncAccount`.
- Adicionar FR-036b: toda chamada Google API (`events.insert`, `events.list`, `events.watch`) MUST usar `calendarId = CalendarSyncAccount.google_calendar_id`; teste de regressão valida que NUNCA o `primary` calendar é usado.
- Adicionar FR-036c: `CalendarSyncAccount` tem UNIQUE(`tenant_id`, `professional_id`); mesma conta Google pode aparecer em 2 registros (1 por tenant) — não há UNIQUE(`provider_user_id`).
- Adicionar FR-036d: eventos do calendário primário (não-gerenciado) do médico **não** viram `ExternalCalendarBusy` — médico precisa registrar bloqueio via CRM ou no sub-calendário gerenciado.
- Adicionar FR-036e: erro de criação do sub-calendário rejeita conexão com `calendar_subcalendar_creation_failed`.
- AC-6.7.1 atualizado: ao conectar Google, sistema cria sub-calendário e persiste o ID; conexão sem sub-calendário criado é rejeitada.
- Novo AC-6.7.11 🔴: profissional conectado em 2 tenants — eventos criados pelo tenant A não aparecem como `ExternalCalendarBusy` do tenant B (teste de integração obrigatório).
- Key Entity `CalendarSyncAccount` ganha:
  - `google_calendar_id` (string, ID retornado pelo Google ao criar o sub-calendário)
  - `google_calendar_name_seen` (string, nullable — última versão observada do nome para audit; se profissional renomear no Google, atualizado no próximo sync)
  - UNIQUE(`tenant_id`, `professional_id`) — uma conexão por par.
- "Fora de Escopo desta Fase" ganha: "Gestão de sala / recurso físico compartilhado entre profissionais — apenas agenda por profissional no MVP."
- R8 da matriz de risco atualizado: status passa de "🔴 Alta" para "🟢 Baixa (mitigado por sub-calendário tenant-scoped — clarify nº 15)".

---

## Definição de Pronto

Checklist verificável antes de mergear esta fase para `main`:

### Funcional
- [ ] US-6.1 a US-6.7 com todos os ACs 🔴 implementados e cobertos por testes
- [ ] Todos os 9 eventos de domínio emitindo com payload correto (verificado via integração com Fase 2/3)
- [ ] Movimento do card no funil Fase 2 ao criar consulta funcionando end-to-end
- [ ] Confirmação automática T-24h e T-2h dispara via Fase 3 (smoke com Twilio sandbox + Meta sandbox)
- [ ] Lista de espera notifica e aloca atomicamente (testado com K=3 candidatos paralelos)
- [ ] Google Calendar bidirecional funcional (criação + atualização + cancelamento + bloqueio de evento externo)
- [ ] Outlook sync: ou MUST-HAVE entregue, ou SHOULD-HAVE deferred com decisão registrada

### Qualidade
- [ ] Cobertura ≥ 70% no domínio de agendamento (testes unitários + feature)
- [ ] Suite full verde (sem regressão nas Fases 0–4)
- [ ] Pint clean + ESLint clean
- [ ] OpenAPI atualizado e `openapi:check` drift 0

### Constitucional
- [ ] Princípio I (LGPD) — auditoria de payload Google/Outlook (FR-038); retenção de tokens OAuth conforme retenção de PII
- [ ] Princípio II (Multi-tenant) — todas as entidades tenant-scoped; teste cross-tenant abuse cobrindo agendamento
- [ ] Princípio IV (TDD) — ACs vermelhos antes da impl
- [ ] Princípio V (Observabilidade) — métricas Prometheus: `appointment_created_total`, `appointment_canceled_total{quem}`, `confirmation_response_total{result}`, `calendar_sync_status{provider,status}` gauge
- [ ] Princípio VII (Segurança) — tokens OAuth criptografados em repouso; CSP cobre Google/Microsoft endpoints em connect-src

### Operacional
- [ ] Postman collection da API de agendamento adicionada a `docs/api/`
- [ ] Smoke test E2E pelo QA com Google Calendar real (1 profissional teste) — checklist documentado
- [ ] Quickstart com pré-requisitos para deploy (OAuth credentials Google/Microsoft, sandbox keys, scope mínimo)
- [x] `/speckit.clarify` executado — 14/15 resolvidos (1-11 + 13-15); **NC nº 12 (UX revogação OAuth) DEFERRED → tratado em `/speckit.plan`** como decisão operacional sem impacto arquitetural

---

## Princípios Constitucionais Atingidos

| US | Princípio I (LGPD) | Princípio II (Multi-tenant) | Princípio III (IA Clínica) | Princípio IV (TDD) | Princípio V (Observab.) | Princípio VI (Meta) | Princípio VII (Segurança) |
|---|---|---|---|---|---|---|---|
| US-6.1 Agenda | — | ✅ tenant_scope | — | ✅ ACs first | ✅ audit log | — | — |
| US-6.2 Tipos | — | ✅ tenant_scope | ✅ vínculo IA com intent | ✅ ACs first | ✅ audit log | — | — |
| US-6.3 Painel | ✅ PII paciente apenas para autorizado | ✅ tenant + RBAC | — | ✅ ACs first | ✅ métricas + audit | — | ✅ Bearer + X-Tenant-Slug |
| US-6.4 Confirmação | ✅ envia só pelo canal autorizado | ✅ tenant_scope | — | ✅ ACs first | ✅ confirmation_response counter | ✅ template Meta + janela 24h (delegado Fase 3) | — |
| US-6.5 Chat | ✅ paciente decide próprio fluxo | ✅ tenant_scope | ✅ contratos para IA Matricial futura | ✅ ACs first | ✅ audit reagendamento | ✅ idem Fase 3 | ✅ Bearer + idempotency_key |
| US-6.6 Espera | ✅ paciente opta em entrar | ✅ tenant_scope | — | ✅ ACs first | ✅ relatório de vagas preenchidas | ✅ idem Fase 3 | — |
| US-6.7 Sync | ✅ FR-038 sem PII clínica | ✅ tenant + risk cross-tenant flagged | — | ✅ ACs first | ✅ calendar_sync gauge | — | ✅ token OAuth criptografado |

---

## Assumptions

- Tenant tem um timezone padrão configurado na Fase 0 (`tenants.timezone`).
- Todo paciente da Fase 2 tem (opcionalmente) um `canal_origem` resolvido pela Fase 3 — usado pela confirmação automática (US-6.4).
- Profissional já existe como `User` + `Professional` (Fase 2) e está vinculado ao tenant.
- A futura Fase de IA Matricial vai consumir os contratos definidos nesta fase (nenhuma decisão de tech stack de IA aqui).
- OAuth Google/Microsoft suporta refresh token sem reautenticação completa.
- Latência aceitável de sync Google: ≤ 2 minutos é diferencial competitivo (concorrentes diretos da indústria oferecem 5–15min).
- Confirmação automática usa templates Meta aprovados (responsabilidade da Fase 3 manter aprovados).
- Race conditions de slot são raras em volumes do MVP (≤ 50 agendamentos/dia por tenant), mas a constraint UNIQUE garante consistência mesmo em escala.

---

## Índice de Acceptance Criteria

| AC | US | Prioridade | Tema |
|---|---|---|---|
| AC-6.1.1 | US-6.1 | 🔴 | Criação de agenda recorrente |
| AC-6.1.2 | US-6.1 | 🔴 | Slots respeitam intervalos |
| AC-6.1.3 | US-6.1 | 🔴 | Bloqueio de férias rejeita agendamento |
| AC-6.1.4 | US-6.1 | 🟡 | Tipos aceitos por profissional |
| AC-6.1.5 | US-6.1 | 🟡 | Médico edita própria agenda |
| AC-6.1.6 | US-6.1 | 🟢 | Visualização semanal multi-profissional |
| AC-6.2.1 | US-6.2 | 🔴 | Cadastro de tipo |
| AC-6.2.2 | US-6.2 | 🔴 | Inativação preserva histórico |
| AC-6.2.3 | US-6.2 | 🟡 | Cor por tipo |
| AC-6.2.4 | US-6.2 | 🟡 | Separação tipo "Retorno" Fase 5 vs Fase 6 |
| AC-6.2.5 | US-6.2 | 🟢 | Tenant scoping de nome |
| AC-6.3.1 | US-6.3 | 🔴 | Criação manual + move funil Fase 2 |
| AC-6.3.2 | US-6.3 | 🔴 | Detecção de slot_conflict |
| AC-6.3.3 | US-6.3 | 🔴 | Drag = reagendamento explícito |
| AC-6.3.4 | US-6.3 | 🟡 | Busca paciente fuzzy |
| AC-6.3.5 | US-6.3 | 🟡 | Cadastro rápido de paciente |
| AC-6.3.6 | US-6.3 | 🟡 | Notificação opt-in via Fase 3 |
| AC-6.3.7 | US-6.3 | 🟢 | Visualizações diária/semanal/mensal |
| AC-6.4.1 | US-6.4 | 🔴 | Disparo T-24h via Fase 3 |
| AC-6.4.2 | US-6.4 | 🔴 | Resposta `1` → ConsultaConfirmada |
| AC-6.4.3 | US-6.4 | 🔴 | Resposta `3` → ConsultaCancelada |
| AC-6.4.4 | US-6.4 | 🟡 | Resposta `2` → handoff para IA Matricial |
| AC-6.4.5 | US-6.4 | 🔴 | Disparo T-2h só se não confirmada |
| AC-6.4.6 | US-6.4 | 🟡 | Resposta a consulta já cancelada — idempotente |
| AC-6.4.7 | US-6.4 | 🟢 | Sem canal de origem → contato manual |
| AC-6.4.8 | US-6.4 | 🟢 | Resposta `1` duplicada — idempotente |
| AC-6.5.1 | US-6.5 | 🔴 | API slots-disponiveis |
| AC-6.5.2 | US-6.5 | 🔴 | API reagendar atômica + idempotency |
| AC-6.5.3 | US-6.5 | 🔴 | API cancelar + lista de espera |
| AC-6.5.4 | US-6.5 | 🔴 | Política de cancelamento |
| AC-6.5.5 | US-6.5 | 🟡 | Reagendamento default mantém profissional+tipo |
| AC-6.5.6 | US-6.5 | 🟡 | Limite de tentativas de reagendamento |
| AC-6.5.7 | US-6.5 | 🟢 | Slot em negociação pela IA |
| AC-6.6.1 | US-6.6 | 🔴 | Oferta de entrada na lista |
| AC-6.6.2 | US-6.6 | 🔴 | Vaga abre dispara notificação |
| AC-6.6.3 | US-6.6 | 🔴 | Janela para confirmar vaga |
| AC-6.6.4 | US-6.6 | 🟡 | K candidatos notificados em paralelo |
| AC-6.6.5 | US-6.6 | 🟡 | Paciente em múltiplas listas |
| AC-6.6.6 | US-6.6 | 🟢 | Relatório de vagas preenchidas |
| AC-6.7.1 | US-6.7 | 🔴 | OAuth Google + token criptografado |
| AC-6.7.2 | US-6.7 | 🔴 | Criação CRM → Google em <2min |
| AC-6.7.3 | US-6.7 | 🔴 | Cancelamento/reagendamento → Google |
| AC-6.7.4 | US-6.7 | 🔴 | Evento externo bloqueia slot CRM |
| AC-6.7.5 | US-6.7 | 🔴 | CRM é fonte da verdade em conflitos |
| AC-6.7.6 | US-6.7 | 🔴 | Revogação OAuth detectada + notificada |
| AC-6.7.7 | US-6.7 | 🟡 | Refresh automático de token |
| AC-6.7.8 | US-6.7 | 🟡 | Janela de 60 dias sincronizados |
| AC-6.7.9 | US-6.7 | 🟡 | Outlook equivalente (ou cortado) |
| AC-6.7.10 | US-6.7 | 🟢 | Push notifications real-time |

**Total**: 50 ACs (16 🔴 críticos, 19 🟡 importantes, 9 🟢 nice-to-have, 6 reforços indiretos).
