# Research — Entrega de Notificações Outbound (013)

Decisões técnicas consolidadas. Toda integração reusa a infra de Messaging (Fase 3) e as guardas das Fases 5/7/8.

## R1 — Resolução paciente → canal de saída

- **Decisão**: para envio proativo, o canal elegível é o **WhatsApp ativo do tenant** (`Channel::ofType('whatsapp')->ativo()` escopado ao tenant). O `external_thread_id` é o **`paciente.telefone_primario_normalizado`** (coluna GENERATED já existente). Instagram só é elegível quando já existe `Conversation` ativa **dentro da janela** (`last_inbound_message_at` < 24h).
- **Rationale**: WhatsApp é o único canal com template HSM para fora da janela (Princípio VI); telefone normalizado é o identificador estável de thread no WhatsApp. Confirma clarificação Q2.
- **Múltiplos canais WhatsApp no tenant**: usar o primeiro `ativo` (ordenado por `id`); cobrir com config futura se necessário. Sem WhatsApp ativo OU paciente sem `telefone_primario` → `pending_manual` (motivo `no_channel`).
- **Alternativas rejeitadas**: tentar Instagram proativo (viola plataforma — sem template); ordem configurável (adia decisão sem resolver limite técnico).

## R2 — Find-or-create de conversa para envio outbound

- **Decisão**: adicionar `ConversationService::findOrCreateForPatientChannel(Channel, string $externalThreadId, int $patientId): Conversation` que reusa a lógica de `findOrCreateForInbound` (mesma chave `(tenant, channel, external_thread_id_normalized)`), garantindo `patient_id` setado e **sem** marcar `received_outside_hours`/`last_inbound_message_at` (não é inbound).
- **Rationale**: a unicidade de conversa por `(tenant, channel, thread)` (decisão NC-2 da Fase 3) já cobre o caso; só falta um entry-point que não trate o evento como recebido.
- **Alternativas rejeitadas**: reusar `findOrCreateForInbound` diretamente (poluiria sinais de “mensagem recebida”).

## R3 — Catálogo de templates por tenant (`notification_templates`)

- **Decisão**: tabela `notification_templates` com `(tenant_id, notification_type, channel_type)` UNIQUE, contendo `provider_template_id`, `language`, `variables_map` (JSONB — mapeia variável do template → fonte de dado não-clínico), `is_active`. Resolução via `NotificationTemplateResolver::resolve(tenant, type, channelType): ?NotificationTemplate`.
- **Rationale**: tabela (vs JSON em `tenant.settings`) permite UNIQUE, gestão via Filament/SPA (US5) e isolamento por tenant explícito (Princípio II).
- **`variables_map`**: só admite chaves de uma allow-list não-clínica (nome do paciente, data/hora da consulta, nome do profissional, nome da clínica, dias até vencimento) — gate LGPD.

## R4 — Entidade de rastreio (`outbound_notifications`)

- **Decisão**: tabela `outbound_notifications` (ver data-model). Estado em enum `NotificationStatus`: `queued → sent → delivered | failed`, mais terminais `pending_manual` e `skipped` (com `skip_reason`). Liga `message_id` (quando enviado) e `source_type/source_id` (morph para Appointment/Prescription/WaitlistEntry).
- **Rationale**: rastreabilidade exigida (FR-017, SC-003) + base para métricas e reconciliação de status.

## R5 — Ordem de orquestração no `OutboundNotificationDispatcher`

Sequência determinística (curto-circuita no primeiro bloqueio, registrando estado/motivo):

1. **Opt-out** (`PatientProfessionalPreference`, por tipo aplicável) → `skipped/opt_out` (evento de domínio ainda auditado).
2. **Debounce 4h** (Redis, chave por paciente+tipo — reuso Fase 7) → `skipped/debounced`.
3. **Idempotência** (já enviado mesmo paciente+tipo+marco+data) → retorna o registro existente.
4. **Resolver canal/conversa** (R1/R2) → sem canal → `pending_manual/no_channel`.
5. **Janela 24h + template** (R6): dentro da janela → texto livre permitido; fora → exige template do tenant; sem template → `pending_manual/no_template`.
6. **Enviar** via `MessageDispatchService::send` → grava `message_id`, estado `sent`.
7. **Falha de envio** (exceção) → `failed`; retry da fila; esgotado → `pending_manual/send_failed`.

- **Rationale**: ordem coloca as supressões baratas/legais antes da resolução cara; garante que nenhuma notificação “suma” (SC-003).

## R6 — Princípio VI: aprovação de template (defesa em profundidade)

- **Decisão**: (a) **config-time** — o tenant só cadastra `provider_template_id` de templates já aprovados (premissa da spec; validação básica de formato no cadastro); (b) **runtime** — `MessageDispatchService` já permite `contentType='template'` fora da janela; se o provedor rejeitar (template não aprovado/ inexistente), o callback de status marca `failed` → cai em `pending_manual/send_failed`; (c) **futuro/opcional** — consultar o status do template no provedor antes do disparo (documentado como melhoria, não-bloqueante).
- **Rationale**: cumpre o espírito do Princípio VI (não enviar fora da janela sem template + auditar bloqueios) sem exigir, neste momento, integração de consulta de status de template na Meta/Twilio. Bloqueios (no_channel/no_template/opt_out) geram evento auditável.
- **Transacional vs marketing**: estas notificações são vinculadas a uma consulta/receituário/vaga do próprio paciente (transacionais) — não são disparo de marketing em massa (que é a Fase 8/Campanhas). Logo, as cláusulas de opt-in de marketing e “/sair” do Princípio VI são satisfeitas pelo opt-out já existente, sem novo gate de massa.

## R7 — Reconciliação de status de entrega

- **Decisão**: ligar `OutboundNotification.message_id → Message`. O `TwilioStatusCallbackController` (já existente) atualiza `Message.status`; um listener/observer propaga a transição para `OutboundNotification` (`sent → delivered` ou `→ failed`). “Lido” não é rastreado (clarificação Q3). Falha definitiva → `pending_manual/send_failed`.
- **Rationale**: reusa o caminho de status já recebido; nenhuma nova ingestão de webhook.

## R8 — Idempotência

- **Decisão**: chave composta `notif:{tenant}:{type}:{patient}:{milestone}:{yyyy-mm-dd}` reaproveitada como `Message.idempotency_key` (dedup nativo do `MessageDispatchService`) + UNIQUE parcial em `outbound_notifications`. Marcos distintos (T-24h vs T-2h) têm `milestone` diferente → envios distintos (FR-014, AC US1.2).
- **Rationale**: dupla camada (DB UNIQUE + dedup do dispatcher) — padrão já usado nas Fases 5/7.

## R9 — LGPD: zero dado clínico

- **Decisão**: os eventos de origem já implementam `ContainsNoClinicalData` (Fase 7). O `variables_map` é restrito a uma allow-list não-clínica. Gate test (`OutboundPayloadLgpdTest`) valida, por reflexão + inspeção do conteúdo montado, que nenhuma variável/corpo contém medicamento, posologia, diagnóstico ou conteúdo de prontuário (assertStringNotContains em fixtures clínicas).
- **Rationale**: Princípio I — defesa no design + teste.

## R10 — Fallback de contato manual (clarificação Q1)

- **Decisão**: `pending_manual` materializa-se como **mensagem de sistema** (`Message` `sender_type='system'`, `content_type='text'`) na conversa do paciente (find-or-create), descrevendo motivo + contexto (consulta/receituário/vaga), e **sinaliza a conversa** (`priority='alta'` + tag/flag). Não há sistema de tarefas dedicado.
- **Rationale**: reusa a inbox e seus filtros/atribuição (clarificação Q1); torna a pendência acionável em <1min (SC-006).

## R11 — Religamento dos listeners (remoção dos stubs)

- **Decisão**: cada listener stub passa a montar um `NotificationRequest` (paciente, tipo, marco, source, contexto não-clínico) e chamar `OutboundNotificationDispatcher::dispatch($request)`. Remove-se o `Log::info` placeholder. Listeners permanecem auto-discovered (Laravel 11+) — não registrar manualmente (evita duplicação, lição da Fase 5).
- **Listeners**: `DispatchConfirmationToInbox`, `EscalateCancellationOutsideWindowToInbox`, `EscalateRescheduleLimitExceededToInbox`, `DispatchWaitlistOfferToInbox` (Agenda), `DispatchPrescriptionAlertViaMessaging`, `EnqueueInboxTaskOnAiRenewal` (Prescription).

## R12 — Métricas (Prometheus)

- `outbound_notifications_total{tenant, type, status}`
- `outbound_notifications_pending_manual_total{tenant, reason}`
- `outbound_notifications_skipped_total{reason}`
- `outbound_notifications_delivery_latency_seconds` (sent → delivered)
- Alerta sugerido: `pending_manual` spike por tenant (possível canal caído).
