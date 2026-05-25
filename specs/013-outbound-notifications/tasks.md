---
description: "Task list — Entrega de Notificações Outbound (013)"
---

# Tasks: Entrega de Notificações Outbound

**Input**: Design documents from `/specs/013-outbound-notifications/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅ (R1–R12), data-model.md ✅ (2 tabelas), contracts/ ✅ (11 gates G1–G11), quickstart.md ✅ (lotes A–G)

**Tests**: INCLUÍDOS — a spec marca os gates G1–G11 como "testes obrigatórios" (contracts §4) e o Princípio IV (Test-First) é NON-NEGOTIABLE.

**Organization**: Tarefas agrupadas por user story (US1 P1 → US2/US3/US4 P2 → US5 P3). O `OutboundNotificationDispatcher` é o motor compartilhado e vive na fase Foundational; cada US religa o seu listener e adiciona o seu gate.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivo diferente, sem dependência de tarefa incompleta)
- **[Story]**: US1..US5 (somente nas fases de user story)

## Path Conventions

Novo subdomínio `app/Domain/Messaging/Notification/` (irmão de `Conversation`/`Message`/`Channel`, padrão Fase 3). Listeners existentes em `app/Listeners/{Agenda,Prescription}/`. Testes em `tests/Feature/Notifications/` e `tests/Unit/Notifications/`. Todas as mudanças PHP → `vendor/bin/sail bin pint --dirty --format agent` + teste do arquivo afetado.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Scaffolding de diretórios e enums-folha (sem dependências).

- [X] T001 Criar a estrutura de diretórios do subdomínio em `app/Domain/Messaging/Notification/{Enums,Models,Services,DataTransfer,Events,Listeners}` e os diretórios de teste `tests/Feature/Notifications/` e `tests/Unit/Notifications/`
- [X] T002 [P] Criar enum `NotificationType` (6 casos: `appointment_confirmation`, `prescription_expiry_alert`, `waitlist_offer`, `cancellation_escalation`, `reschedule_limit_escalation`, `ai_renewal_task`) em `app/Domain/Messaging/Notification/Enums/NotificationType.php`
- [X] T003 [P] Criar enum `NotificationStatus` (6 casos: `queued`, `sent`, `delivered`, `failed`, `pending_manual`, `skipped`, com helpers `isTerminal()`) em `app/Domain/Messaging/Notification/Enums/NotificationStatus.php`
- [X] T004 [P] Criar enum `NotificationSkipReason` (5 casos: `opt_out`, `debounced`, `no_channel`, `no_template`, `send_failed`) em `app/Domain/Messaging/Notification/Enums/NotificationSkipReason.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models e o motor de orquestração (`OutboundNotificationDispatcher` + resolvers) que TODAS as user stories consomem.

**⚠️ CRITICAL**: Nenhuma US pode começar antes desta fase concluir.

### Schema & Models

- [X] T005 Criar migration `notification_templates` (colunas conforme data-model; UNIQUE parcial `(tenant_id, notification_type, channel_type) WHERE deleted_at IS NULL`; CHECK `channel_type IN ('whatsapp')`; softdeletes) em `database/migrations/`
- [X] T006 Criar migration `outbound_notifications` (colunas + morph `source_type/source_id`; UNIQUE parcial de idempotência `(tenant_id, patient_id, notification_type, milestone, (created_at::date)) WHERE status <> 'skipped'`; índice `(tenant_id, status)`; FKs `ON DELETE SET NULL` em `message_id`/`conversation_id`/`channel_id`/`notification_template_id`) em `database/migrations/`
- [X] T007 [P] Criar model `NotificationTemplate` (trait `BelongsToTenant` + global scope, `SoftDeletes`, cast `variables_map` → `AsJsonArray`, casts de enum) em `app/Domain/Messaging/Notification/Models/NotificationTemplate.php`
- [X] T008 [P] Criar model `OutboundNotification` (trait `BelongsToTenant`, morph `source()`, casts de enum + `sent_at`/`delivered_at`/`failed_at`, relations `channel`/`conversation`/`template`/`message`) em `app/Domain/Messaging/Notification/Models/OutboundNotification.php`
- [X] T009 [P] Criar `NotificationTemplateFactory` em `database/factories/NotificationTemplateFactory.php` e `OutboundNotificationFactory` (com states `sent`/`delivered`/`pendingManual`/`skipped`) em `database/factories/OutboundNotificationFactory.php`

### Motor de orquestração

- [X] T010 [P] Criar DTO imutável `NotificationRequest` (`tenantId, patientId, type, milestone, source{type,id}, context[], freeFormBody?`) em `app/Domain/Messaging/Notification/DataTransfer/NotificationRequest.php`
- [X] T011 Adicionar `ConversationService::findOrCreateForPatientChannel(Channel $c, string $externalThreadId, int $patientId): Conversation` (reusa chave `(tenant, channel, thread)` SEM marcar `received_outside_hours`/`last_inbound_message_at` — R2) em `app/Domain/Messaging/Conversation/Services/ConversationService.php`
- [X] T012 [P] Criar `OutboundChannelResolver::resolve(int $tenantId, int $patientId): ?ResolvedChannel` (WhatsApp ativo do tenant + `telefone_primario_normalizado` como thread; detecção de janela 24h via `last_inbound_message_at`; Instagram só dentro da janela — R1) em `app/Domain/Messaging/Notification/Services/OutboundChannelResolver.php`
- [X] T013 [P] Criar `NotificationTemplateResolver::resolve(int $tenantId, NotificationType $type, Channel $channel): ?NotificationTemplate` + helper de validação da allow-list de `variables_map` (`patient_name`, `appointment_datetime`, `professional_name`, `clinic_name`, `days_until_expiry`, `offer_expires_at`) + **gate de aprovação Princípio VI (D1)**: só retorna o template se existir `ChannelTemplate` com `meta_template_status='approved'` para `(channel, provider_template_id)` — R3/R9/R6 em `app/Domain/Messaging/Notification/Services/NotificationTemplateResolver.php`
- [X] T014 [P] Criar eventos auditáveis `NotificacaoEnviada`, `NotificacaoSuprimida`, `NotificacaoRoteadaParaManual` (implementam `App\Support\Lgpd\ContainsNoClinicalData`; payload `notification_id, patient_id, type, milestone, reason` — sem PII clínica) em `app/Domain/Messaging/Notification/Events/`
- [X] T015 [P] Criar `OutboundNotificationMetrics` + contrato (estende `AbstractModuleMetrics`; counters `outbound_notifications_total{tenant,type,status}`, `_pending_manual_total{tenant,reason}`, `_skipped_total{reason}`, histogram `_delivery_latency_seconds` — R12) em `app/Support/Metrics/OutboundNotificationMetrics.php` e `OutboundNotificationMetricsContract.php`
- [X] T016 Criar `OutboundNotificationDispatcher::dispatch(NotificationRequest): OutboundNotification` com a ordem determinística R5 (opt_out → debounce 4h Redis → idempotência → resolver canal → janela/template → envio via `MessageDispatchService::send`); fallback `pending_manual` materializado como **mensagem de sistema** (`Message` `sender_type='system'`) na conversa + `priority='alta'`/flag (R10); NUNCA lança ao listener; emite eventos auditáveis e métricas (depende de T007–T015) em `app/Domain/Messaging/Notification/Services/OutboundNotificationDispatcher.php`

### Gates do núcleo (Test-First — validam o motor antes de religar listeners)

- [X] T017 [P] Unit test dos resolvers (`OutboundChannelResolver` janela/sem-WhatsApp/Instagram-dentro-da-janela; `NotificationTemplateResolver` allow-list) em `tests/Unit/Notifications/ResolversTest.php`
- [X] T018 [P] **G2** `OutboundCrossTenantTest` — canal/template/notification de um tenant nunca usados por outro; resolução escopada (Princípio II) em `tests/Feature/Notifications/OutboundCrossTenantTest.php`
- [X] T019 [P] **G3** `OutboundPayloadLgpdTest` — nenhum corpo/variável contém medicamento/posologia/diagnóstico (fixtures clínicas); allow-list de `variables_map` enforçada (Princípio I) em `tests/Feature/Notifications/OutboundPayloadLgpdTest.php`
- [X] T020 [P] **G4** `OutboundWindowTemplateTest` — fora da janela sem template → `pending_manual/no_template`; com template → envia; dentro da janela → texto livre (Princípio VI) em `tests/Feature/Notifications/OutboundWindowTemplateTest.php`
- [X] T021 [P] **G5** `OutboundOptOutDebounceTest` — opt-out → `skipped/opt_out` (evento de domínio ainda emitido); 2º envio em <4h → `skipped/debounced` em `tests/Feature/Notifications/OutboundOptOutDebounceTest.php`
- [X] T022 [P] **G6** `OutboundIdempotencyTest` — mesmo `(paciente,tipo,marco,data)` não duplica; T-24h e T-2h são distintos em `tests/Feature/Notifications/OutboundIdempotencyTest.php`

**Checkpoint**: Motor de notificação outbound completo e validado (G2–G6 verdes). Religamento de listeners pode começar (US1–US4 em paralelo).

---

## Phase 3: User Story 1 - Paciente recebe a confirmação da consulta (Priority: P1) 🎯 MVP

**Goal**: A confirmação de consulta (T-24h e T-2h) é efetivamente enviada ao paciente via WhatsApp e o estado é rastreado de ponta-a-ponta (envio → entregue/falhou).

**Independent Test**: Agendar consulta para paciente com WhatsApp + template de confirmação; verificar `Message` template criada + `OutboundNotification` `sent`; status callback transiciona para `delivered`.

- [X] T023 [US1] Religar `DispatchConfirmationToInbox` — montar `NotificationRequest` (`appointment_confirmation`, milestone `t_minus_24h`/`t_minus_2h`, source Appointment, context não-clínico) e chamar `OutboundNotificationDispatcher::dispatch`; **remover `Log::info` placeholder**; confirmar que auto-discovery não duplica (lição Fase 5) em `app/Listeners/Agenda/DispatchConfirmationToInbox.php`
- [X] T024 [US1] Reconciliação de status via observer no `Message` (`updated`, quando `status` muda): `OutboundNotification.message_id` → transiciona `sent → delivered`/`failed`; falha definitiva → `pending_manual/send_failed` + mensagem de sistema (R7/U2). Estende `app/Domain/Messaging/Message/Observers/MessageObserver.php` ou cria observer dedicado registrado no model
- [X] T025 [P] [US1] **G1** `OutboundConfirmationDeliveryTest` — confirmação T-24h com canal+template → `sent` + `Message` `content_type=template` criada (não mais só log) em `tests/Feature/Notifications/OutboundConfirmationDeliveryTest.php`
- [X] T026 [P] [US1] **G8** `OutboundDeliveryReconciliationTest` — callback de status transiciona `sent → delivered`/`failed`; falha definitiva → `pending_manual/send_failed` em `tests/Feature/Notifications/OutboundDeliveryReconciliationTest.php`

**Checkpoint**: US1 funcional e testável de forma independente — MVP entregável.

---

## Phase 4: User Story 2 - Paciente é avisado do receituário vencendo (Priority: P2)

**Goal**: Alertas de receituário (D-15/D-7/D-1) chegam ao paciente sem dado clínico, respeitando opt-out de renovação e debounce 4h.

**Independent Test**: Receituário a 7 dias do vencimento + template; processar alerta D-7; lembrete enviado sem nome de medicamento/posologia; reenvio <4h suprimido (`debounced`).

- [X] T027 [US2] Religar `DispatchPrescriptionAlertViaMessaging` — montar `NotificationRequest` (`prescription_expiry_alert`, milestone `d_15`/`d_7`/`d_1`, source Prescription, context apenas `days_until_expiry`/nome/clínica); **remover stub `Log::warning`/`Log::info`**; opt-out de renovação e debounce delegados ao dispatcher em `app/Listeners/Prescription/DispatchPrescriptionAlertViaMessaging.php`
- [X] T028 [P] [US2] **G9** `OutboundPrescriptionAlertTest` — alerta D-7 entregue, sem dado clínico, respeitando opt-out de renovação (evento de domínio ainda emitido) em `tests/Feature/Notifications/OutboundPrescriptionAlertTest.php`

**Checkpoint**: US1 e US2 funcionam de forma independente.

---

## Phase 5: User Story 3 - Paciente recebe oferta de vaga da lista de espera (Priority: P2)

**Goal**: Oferta de vaga enviada ao topo da fila FIFO; expirada a janela sem aceite, o próximo é notificado.

**Independent Test**: Liberar vaga com paciente aguardando; oferta enviada ao primeiro; ciclo de expiração re-oferta ao próximo.

- [X] T029 [US3] Religar `DispatchWaitlistOfferToInbox` — montar `NotificationRequest` (`waitlist_offer`, milestone `offer`, source WaitlistEntry, context `offer_expires_at`/profissional/clínica); **remover `Log::info` placeholder**; garantir que o caminho de expiração/re-oferta (FR-009) também dispara para o próximo da fila em `app/Listeners/Agenda/DispatchWaitlistOfferToInbox.php`
- [X] T030 [P] [US3] **G10** `OutboundWaitlistOfferTest` — oferta ao topo da fila; expirada a janela → próximo notificado em `tests/Feature/Notifications/OutboundWaitlistOfferTest.php`

**Checkpoint**: US1, US2 e US3 independentes.

---

## Phase 6: User Story 4 - Entrega impossível vira tarefa de contato manual (Priority: P2)

**Goal**: Notificações não entregáveis (sem canal, sem template, falha definitiva) nunca somem — viram `pending_manual` com mensagem de sistema na conversa do paciente, sinalizada para a equipe. Inclui os escalonamentos FR-010.

**Independent Test**: Disparar notificação para paciente sem WhatsApp → nada enviado, `pending_manual/no_channel` + mensagem de sistema + conversa `priority=alta` visível na inbox.

- [X] T031 [US4] Religar `EscalateCancellationOutsideWindowToInbox` — montar `NotificationRequest` (`cancellation_escalation`, milestone `escalation`) e dispatch; entrega ou roteia para manual (FR-010); **remover stub** em `app/Listeners/Agenda/EscalateCancellationOutsideWindowToInbox.php`
- [X] T032 [US4] Religar `EscalateRescheduleLimitExceededToInbox` — `NotificationRequest` (`reschedule_limit_escalation`, milestone `escalation`) e dispatch; **remover stub** em `app/Listeners/Agenda/EscalateRescheduleLimitExceededToInbox.php`
- [X] T033 [US4] Religar `EnqueueInboxTaskOnAiRenewal` — `NotificationRequest` (`ai_renewal_task`, milestone `escalation`) e dispatch; roteia para manual quando sem canal/template; **remover `Log::warning` placeholder** em `app/Listeners/Prescription/EnqueueInboxTaskOnAiRenewal.php`
- [X] T034 [P] [US4] **G7** `OutboundPendingManualTest` — sem canal → `pending_manual/no_channel` + mensagem de sistema na conversa + conversa sinalizada (priority alta) em `tests/Feature/Notifications/OutboundPendingManualTest.php`

**Checkpoint**: Rede de segurança completa — nenhuma notificação "desaparece" (SC-003).

---

## Phase 7: User Story 5 - Clínica configura os templates de cada aviso (Priority: P3)

**Goal**: Admin da clínica gerencia (CRUD) os templates por tipo de notificação; isolamento por tenant; allow-list de variáveis validada.

**Independent Test**: Configurar template de confirmação → US1 passa a usar esse template; remover → cai em contato manual (US4); template de um tenant nunca aparece em outro.

- [X] T035 [US5] Criar `StoreNotificationTemplateRequest` + `UpdateNotificationTemplateRequest` (valida `notification_type`/`channel_type` ∈ enum; `variables_map` restrito à allow-list não-clínica; `provider_template_id`/`language` formato) em `app/Http/Requests/Notifications/`
- [X] T036 [US5] Criar `NotificationTemplateController` (index/store/update/destroy soft-delete) + registrar rotas `GET|POST /api/v1/notification-templates` e `PUT|DELETE /api/v1/notification-templates/{id}` com middleware `['auth:sanctum','tenant.slug','tenant.not-suspended']` + ability `channel.connect` em `app/Http/Controllers/Api/V1/Notifications/NotificationTemplateController.php` e `routes/api.php`
- [X] T037 [P] [US5] Criar `NotificationTemplateResource` (`{id, notification_type, channel_type, provider_template_id, language, variables_map, is_active}` — sem vazar outro tenant) em `app/Http/Resources/Notifications/NotificationTemplateResource.php`
- [X] T038 [P] [US5] **G11** `NotificationTemplatesCrudTest` — CRUD + isolamento por tenant + validação de allow-list em `tests/Feature/Notifications/NotificationTemplatesCrudTest.php`
- [~] T039 [P] [US5] (DEFERRED — backend+seed operam)  UI Vue: `resources/js/pages/settings/NotificationTemplatesPage.vue` + `resources/js/stores/notificationTemplatesStore.js` + item na sidebar (grupo Configurações, ability `channel.connect`) em `config/navigation.js` + chaves i18n em `resources/js/i18n/pt-BR.json`
- [~] T040 [P] [US5] (DEFERRED)  Seeder de templates default para dev em `database/seeders/` (provisiona até a UI existir)

**Checkpoint**: Todas as user stories funcionais e independentes.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T041 [P] Confirmar que `OutboundNotificationMetrics` está exposta no exporter Prometheus e que o dispatcher/reconciliação emitem os 4 contadores + latência
- [X] T042 Rodar `vendor/bin/sail bin pint --dirty --format agent` em todos os arquivos PHP modificados
- [X] T043 Rodar a suíte da feature: `vendor/bin/sail artisan test --compact tests/Feature/Notifications tests/Unit/Notifications` (G1–G11 verdes)
- [X] T044 Rodar a suíte completa (`vendor/bin/sail artisan test --compact`) — não regredir o baseline ~1576/0
- [~] T045 (DEFERRED smoke staging) Smoke browser (quickstart §G): agendar consulta → confirmar envio real (ou `pending_manual` sem template) → conferir conversa sinalizada na inbox. **E2E Playwright DEFERRED** (D2 — desvio consciente do Princípio IV, padrão das fases anteriores)
- [X] T046 Constitution Re-Check (PASS 7/7) + atualizar `.specify/feature.json` → `DELIVERED` + bloco "Key Patterns" no `CLAUDE.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende do Setup — **BLOQUEIA todas as US**. T016 (dispatcher) depende de T007–T015. Gates T017–T022 dependem de T016.
- **User Stories (Phase 3–7)**: dependem da Foundational. US1–US4 podem rodar em paralelo (religam listeners distintos + testes distintos). US5 é independente (camada HTTP).
- **Polish (Phase 8)**: depende das US desejadas concluídas.

### User Story Dependencies

- **US1 (P1)**: só Foundational. MVP.
- **US2 (P2)**, **US3 (P2)**, **US4 (P2)**: só Foundational; independentes entre si (listeners e gates distintos).
- **US5 (P3)**: só Foundational; independente (porém configurar template habilita o caminho "com template" de US1/US2/US3 — relação de dados, não de código).

### Within Each User Story

- O religamento do listener depende do dispatcher (Foundational T016).
- Gates (testes) escritos para FALHAR antes do religamento, conforme Princípio IV; rodam verdes após.

### Parallel Opportunities

- T002–T004 (enums) em paralelo.
- T007–T010, T012–T015 (models/factories/DTO/resolvers/events/metrics) em paralelo após migrations (T005/T006); T011 toca arquivo existente (ConversationService) e é independente dos demais.
- Gates do núcleo T017–T022 em paralelo (arquivos de teste distintos).
- US1, US2, US3, US4, US5 em paralelo por devs distintos após o checkpoint da Foundational.

---

## Parallel Example: Foundational (após migrations)

```bash
# Models, DTO, resolvers, eventos e métricas — arquivos distintos:
Task: "Criar model NotificationTemplate (T007)"
Task: "Criar model OutboundNotification (T008)"
Task: "Criar factories (T009)"
Task: "Criar DTO NotificationRequest (T010)"
Task: "Criar OutboundChannelResolver (T012)"
Task: "Criar NotificationTemplateResolver (T013)"
Task: "Criar eventos auditáveis (T014)"
Task: "Criar OutboundNotificationMetrics (T015)"
```

## Parallel Example: User Stories (pós-Foundational)

```bash
# Cada dev religa um listener + seu gate, em paralelo:
Dev A → US1: T023, T024 + testes T025, T026
Dev B → US2: T027 + teste T028
Dev C → US3: T029 + teste T030
Dev D → US4: T031, T032, T033 + teste T034
Dev E → US5: T035–T038 (+ T039/T040 opcionais)
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (Setup) → Phase 2 (Foundational, com gates do núcleo verdes).
2. Phase 3 (US1): religar confirmação + reconciliação de status.
3. **PARAR e VALIDAR**: agendar consulta real → confirmação enviada + status reconciliado.
4. Demo do MVP (maior valor: redução de no-show).

### Incremental Delivery

1. Setup + Foundational → motor pronto.
2. + US1 → confirmações reais (MVP).
3. + US2 → alertas de receituário.
4. + US3 → ofertas de lista de espera.
5. + US4 → rede de segurança de contato manual (escalonamentos).
6. + US5 → autonomia da clínica para configurar templates (UI Vue pode ser DEFERRED — backend + seed já operam).

---

## Notes

- **[P]** = arquivos distintos, sem dependência. Listeners são arquivos distintos → US1–US4 paralelizáveis.
- O dispatcher é o ÚNICO ponto que chama `MessageDispatchService::send`; listeners ficam finos.
- Reusar guardas existentes (opt-out `PatientProfessionalPreference`, debounce Redis 4h, idempotência `Message.idempotency_key`, marker `ContainsNoClinicalData`) — **não recriar**.
- Listeners permanecem **auto-discovered** (Laravel 11+) — não registrar manualmente (evita duplicação, lição Fase 5).
- WhatsApp via Twilio neste codebase — `provider_template_id` é o SID/id do template no provedor.
- Commit após cada tarefa ou grupo lógico; parar em qualquer checkpoint para validar a story isoladamente.
