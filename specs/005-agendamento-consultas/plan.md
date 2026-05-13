# Implementation Plan: Fase 5 — Agendamento de Consultas

**Branch**: `005-agendamento-consultas` | **Date**: 2026-05-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/005-agendamento-consultas/spec.md` (Status: 14/15 clarifications resolvidos; NC nº 12 = UX revogação OAuth resolvido neste plan via Phase 0 R5)

## Summary

Entrega o módulo de **Agendamento de Consultas** — primeira fase que converte leads qualificados (Fase 2) em consultas marcadas e dispara o ciclo de confirmação automática (Fase 3) que reduz no-show. Sete user stories: agenda do profissional (US-6.1), tipos de atendimento (US-6.2), agendamento via painel drag-and-drop (US-6.3), confirmação automática T-24h/T-2h/retry T-30min (US-6.4), reagendamento/cancelamento via chat (US-6.5), lista de espera FIFO sequencial (US-6.6), sincronização bidirecional Google Calendar via sub-calendário tenant-scoped (US-6.7). Outlook explicitamente deferred → Fase 6 (modelo `provider` enum preparado).

**14 entidades novas** com `tenant_id`, **11 eventos de domínio** (todos `Auditable`), **~50 endpoints REST** sob `/api/v1/agenda/*` (Bearer + X-Tenant-Slug, Fase 4), **frontend Vue 3** com 4 áreas (Agenda drag-and-drop, Configuração, Tipos, Sync). Reusa Reverb, Spatie Permissions, audit_logs, listener pattern Fase 2/3. Constitution Check passa nos 7 princípios sem amendment (gates I/II/IV/V/VII verificados).

Decisões consolidadas via `/speckit.clarify` (sessions 2026-05-13):
- **Slots fixos** derivados de `(horário trabalho, intervalos, duração+buffer do tipo)`; UNIQUE(`tenant_id`, `professional_id`, `starts_at`) (clarify nº 1)
- **Reserva pessimista soft** com TTL diferenciado: 5min painel / 2min IA (clarify nº 2)
- **Cancelamento**: tenant default 4h + override por tipo, bloqueia + escala via inbox (clarify nº 3)
- **Tipos**: valor único snapshot na consulta + override com motivo auditado (clarify nº 4)
- **Agenda**: dual ownership (médico próprio / Admin qualquer); override de bloqueio com ability `appointment.override_block` + push (clarify nº 5)
- **Confirmação**: quick-replies universais 1/2/3, retry T-30min, T-15min escala manual; uma mensagem por consulta com horário no header (clarify nº 6)
- **Reagendamento**: contrato simples, mantém prof+tipo, limite 2 reagendamentos por consulta (clarify nº 7)
- **Lista de espera**: K=1 sequencial, prazo 15min, escopo (prof × tipo) sem fallback (clarify nº 8)
- **Drag-and-drop**: drag-create + confirm-to-move modal + 2 views (diária/semanal) + multi-prof opcional (clarify nº 9)
- **Google sync**: push (Watch) + polling 5min fallback + CRM exclusivo + título fixo + janela 60d (clarify nº 10)
- **Outlook**: DEFERRED → Fase 6, modelo preparado (clarify nº 11)
- **OAuth revoke UX**: notificação dual (in-app banner + email) + refresh automático antes de declarar falha + reconectar 1-clique (decidido em Phase 0 R5 deste plan, antes era NC nº 12)
- **Timezone**: TZ tenant default + override profissional + UTC interno + qualificador no texto da mensagem (clarify nº 13)
- **No-show**: manual + auto-flag T+30min + janela 7d + reversão 48h (clarify nº 14)
- **Cross-tenant Google**: sub-calendário `Paciente360 — {Tenant.nome}` + UNIQUE(`tenant_id`, `professional_id`); sala física FORA do MVP (clarify nº 15)

## Technical Context

**Language/Version**: PHP 8.5 (backend), JavaScript ES2023 (frontend)

**Primary Dependencies (NOVAS nesta fase)**:
- Backend:
  - **`google/apiclient` ^2.18** (composer) — cliente oficial Google API para Calendar v3 (events, watch channels, sub-calendars). Justificativa: SDK oficial, mantido pelo Google, suporta resumable HTTP, retry exponencial nativo. Alternativa rejeitada: `spatie/laravel-google-calendar` (opinionated demais para nosso modelo de sub-calendário tenant-scoped).
  - **`nesbot/carbon` ^3** (já presente via Laravel) — extensão de uso para conversão IANA TZ profissional ↔ tenant ↔ UTC.
- Frontend:
  - **`@fullcalendar/core` ^6.1.20 + `@fullcalendar/vue3` ^6.1.20 + `@fullcalendar/daygrid` + `@fullcalendar/timegrid` + `@fullcalendar/interaction` + `@fullcalendar/resource-timegrid`** (npm — instalar com `--legacy-peer-deps` por conflict pré-existente vite^8 ↔ @vitejs/plugin-vue^5.2.1) — widget de calendário drag-and-drop. Alternativas rejeitadas: `vue-cal` (sem suporte a drag de criação a partir de área vazia em range), `@schedule-x/calendar` (suporte Vue 3 imaturo em out-2025), implementação custom (R&D excessivo para entregar US-6.3 a tempo).
  - **`luxon` ^3** (npm) — conversão TZ no cliente (ISO 8601 com offset → render no TZ do contexto). Alternativa rejeitada: `date-fns-tz` (API menos ergonômica para parse com IANA explícito).
- DevOps:
  - **Cron Laravel scheduler** — 4 novos jobs schedulados (`agenda:cleanup-expired-reservations` cada 1min, `agenda:expire-waitlist-notifications` cada 1min, `agenda:dispatch-confirmations` cada 5min, `agenda:auto-close-stale-appointments` diário 00:30 BRT, `agenda:google-poll-fallback` cada 5min, `agenda:google-renew-watch-channels` diário 02:00 BRT).

**Primary Dependencies (REUSADAS)**:
- Laravel 13 + PHP 8.5 + PostgreSQL 18 + Redis 7
- **Sanctum Bearer + X-Tenant-Slug** (Fase 4 Lote D-K) — toda nova rota `/api/v1/agenda/*` reusa middleware `auth:sanctum` + `tenant.slug`
- **Spatie Permission team mode** (Fase 0) — 9 novos abilities; `User::guardName()` pinned em `web` (Fase 4 Lote F)
- **AuditLog + EventoTimeline** (Fase 0/2) — todos os 11 eventos da Fase 5 são `Auditable`; listener `RegistraEventoTimelineListener` (Fase 2) automaticamente projeta para timeline do paciente
- **Reverb** (Fase 0/3) — broadcast de `ConsultaCriada`, `ConsultaReagendada`, `ConsultaCancelada` em canal privado `tenant.{tenant_id}.agenda` para sync de UI multi-aba
- **Filament 5 super admin** — sem mudança (Fase 5 é tenant-only)
- **Echo + pusher-js + axios** (Fase 4 Lote E) — store Pinia da agenda usa o `api` instance reconfigurada com Bearer
- **Trgm/unaccent** (Fase 2) — busca de paciente no modal de agendamento reusa fuzzy match
- **Mensageria Fase 3** — Fase 5 NÃO envia mensagens, apenas emite eventos consumidos por `MessagingDispatcher`
- **PHPUnit 12 + Sanctum::actingAs** (Fase 4 Lote I) — convenção de teste preservada

**Storage**:
- **PostgreSQL 18**: 14 novas tabelas (ver `data-model.md`); extensão de `tenants.settings` (JSONB) com 3 chaves novas (`min_cancellation_hours`, `max_reschedules_per_appointment`, `waitlist_confirmation_minutes`, `calendar_sync_window_days`); extensão de `professionals` com coluna `timezone` (nullable, IANA string). Sem `pg_trgm` novo (não há fuzzy de domínio em agenda).
- **Redis 7**: cache para `SlotReservation` index quente (TTL 5min); presence channel para multi-aba sync (eco do Reverb).
- **S3 / object storage**: nenhum upload nesta fase.
- **Encryption at rest**: tokens OAuth (`CalendarSyncAccount.encrypted_access_token`, `encrypted_refresh_token`) via `Crypt::encryptString()` com `APP_KEY`. Justificativa LGPD/Princípio I: tokens são credenciais de terceiros, equivalentes a senhas — exigem mesmo nível de proteção.

**Testing**: PHPUnit 12 (feature dominante). Cobertura alvo ≥ 70% no domínio `agenda/*` (`Services/Agenda/*`, `Models/{Appointment,...}`). Testes obrigatórios:
- **Race condition**: `tests/Feature/Agenda/SlotConflictRaceTest.php` — 50 requests paralelos no mesmo slot, exatamente 1 sucesso (FR-011a, SC-008).
- **Cross-tenant isolation**: `tests/Feature/Agenda/CrossTenantAgendaTest.php` — tenant A não enxerga `Appointment`/`SlotReservation`/`CalendarSyncAccount` do tenant B (Princípio II — gate obrigatório).
- **Cross-tenant Google leak**: `tests/Feature/Agenda/CrossTenantGoogleSyncTest.php` — eventos do sub-calendário tenant A não aparecem no `events.list?calendarId={sub-cal-tenant-B}` (clarify nº 15, AC-6.7.11).
- **OAuth flow**: `tests/Feature/Agenda/CalendarSyncOAuthTest.php` — fluxo completo de connect → criar sub-calendário → emitir token → refresh → revoke.
- **Confirmação flow E2E**: `tests/Feature/Agenda/ConfirmationFlowTest.php` — virtual time T-24h → T-2h → T-30min retry → T-15min escala (FR-018..024).
- **Lista de espera FIFO**: `tests/Feature/Agenda/WaitlistSequentialTest.php` — K=1, expira após 15min, próximo notificado (FR-030..033).

**Target Platform**: Linux server (Sail Docker dev; staging/prod Laravel Cloud). SPA navegador moderno ES2023.

**Project Type**: Web application multi-tenant SaaS (API REST + SPA Vue 3 + Filament super admin **inalterado**).

**Performance Goals**:
- Listar slots disponíveis (`GET /agenda/slots-disponiveis`) p95 ≤ 300ms (SC-009 / Princípio V) — query indexada em `(tenant_id, professional_id, starts_at)` + cache Redis 60s para janela de 7 dias
- Criar consulta (POST `/agenda/consultas`) p95 ≤ 500ms — inclui validação de slot + insert + emit eventos
- Reservar slot (POST `/agenda/slots/{starts_at}/reservar`) p95 ≤ 100ms — operação lock-free pessimista soft
- Sync Google CRM → Calendar p95 ≤ 2 minutos (SC-005) — job assíncrono dispatched no listener de `ConsultaCriada`
- Push notification Google → CRM processado em ≤ 30s (AC-6.7.10)
- Polling fallback Google: cada 5min, eventos > 30 dias antigos não polled
- Tempo entre cancelamento e notificação waitlist: p95 ≤ 2 minutos (SC-004)
- Confirmação chat → estado: p95 ≤ 5 segundos (SC-002) — listener síncrono em-thread

**Constraints**:
- **Princípio I (LGPD) NON-NEGOTIABLE**: tokens OAuth criptografados em repouso; descrição/título Google sem PII clínica (FR-038/038a — gate `tests/Feature/Agenda/GoogleEventPayloadLgpdTest.php`); audit log de cada sync error; retenção de tokens revogados ≥ 1 ano para auditoria.
- **Princípio II (Multi-tenant) NON-NEGOTIABLE**: TODA tabela com `tenant_id` NOT NULL + global scope; UNIQUE(`tenant_id`, `professional_id`) em `CalendarSyncAccount`; sub-calendário Google tenant-scoped (clarify nº 15, gate `CrossTenantGoogleSyncTest`).
- **Princípio IV (TDD/Spec-driven)**: ACs 🔴 vermelhos (PHPUnit) antes da implementação; OpenAPI atualizado na mesma PR que adiciona endpoint.
- **Princípio V (Observabilidade)**: 6 métricas Prometheus novas (ver Constitution Check); audit log estruturado com `correlation_id` em sync flows.
- **Princípio VI (Conformidade Meta)**: NÃO se aplica diretamente (Fase 5 emite eventos; Fase 3 dispara mensagens via templates aprovados).
- **Princípio VII (Segurança)**: tokens OAuth via `Crypt::encryptString` (APP_KEY rotation-safe); CSP `connect-src` adicionado para `accounts.google.com` + `oauth2.googleapis.com` + `www.googleapis.com`; webhooks Google validam via header `X-Goog-Channel-Token` (HMAC equivalente).
- **Janela de template Meta** (consumido por Fase 3, mencionado para contexto): confirmação T-24h/T-2h sempre via template HSM (consulta agendada > 24h fora de janela ativa). Fase 3 já implementa.

**Scale/Scope**:
- 100 tenants ativos no MVP, ~10 profissionais por tenant em média
- ~500 consultas/dia/tenant em pico (clínica grande), ~50 em média
- ~5.000 reservas de slot/dia (TTL 2-5min, cleanup contínuo)
- Lista de espera: ~50 entradas ativas/tenant em pico
- Google sync: ~200 conexões ativas inicialmente; ~5.000 events/min sincronizados em pico
- Janela 60d × 50 consultas/dia × 100 tenants = ~300k events em sync window simultâneos

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Status | Como atende |
|---|---|---|
| **I. LGPD (NON-NEGOTIABLE)** | ✅ PASS | Tokens OAuth criptografados (`Crypt::encryptString`); título/descrição Google fixos sem PII clínica (FR-038/038a, teste obrigatório); audit log estruturado em toda criação/edição/cancelamento/reagendamento de consulta (já via `Auditable` listener Fase 0); retenção tokens ≥ 1 ano; right-to-erasure: anonimização em `Appointment.notas` e `notes/observacoes` opcionais quando paciente exerce direito (cascata via `PacienteAnonymizationService` Fase 2). |
| **II. Multi-tenant (NON-NEGOTIABLE)** | ✅ PASS | TODAS as 14 tabelas com `tenant_id` NOT NULL + global scope `BelongsToTenant`; UNIQUE(`tenant_id`, `professional_id`) em `CalendarSyncAccount`; sub-calendário Google tenant-scoped (`CrossTenantGoogleSyncTest` obrigatório); jobs em fila reusa `TenantAwareJob` (Fase 2); presence Reverb canal `tenant.{id}.agenda` valida ownership. |
| **III. IA Clínica (NON-NEGOTIABLE)** | ✅ PASS (sem aplicação direta) | Fase 5 expõe contratos para futura IA Matricial (`GET /agenda/slots-disponiveis`, `POST /agenda/slots/{}/reservar`, `POST /agenda/consultas/{id}/reagendar` com idempotency_key). Sem chamada LLM nesta fase; quando IA Matricial integrar (futura fase), TTL de reserva 2min para holder=ia (clarify nº 2) e flag `via_ia=true` em `ConsultaConfirmacaoPendente` (clarify nº 6) garantem o pause-on-handover do Princípio III. |
| **IV. Spec-driven Test-first** | ✅ PASS | Spec aprovada em `specs/005-*/spec.md` com 14/15 clarifications; ACs 🔴 mapeados para tasks PHPUnit (próximo `/speckit.tasks`); OpenAPI atualizado na mesma PR que adiciona endpoint (`contracts/openapi.yaml`); migrations imutáveis (não tocam Fases 0-4); cobertura alvo ≥ 70% no domínio `agenda/*`. |
| **V. Observabilidade** | ✅ PASS | 6 novas métricas Prometheus (`AgendaMetrics`): `appointment_created_total{type,channel_origin}`, `appointment_canceled_total{quem}`, `appointment_no_show_total`, `confirmation_response_total{kind,result}`, `waitlist_notification_total{result}`, `calendar_sync_status{provider,status}` (gauge), `calendar_sync_latency_seconds{operation}` (histogram); todos os 11 eventos de domínio gravam em `audit_logs` com `correlation_id`; logs estruturados JSON (já default Fase 0); Sentry tags adicionados em jobs Google sync. |
| **VI. Meta Compliance (NON-NEGOTIABLE)** | ✅ PASS (não-impactante) | Fase 5 emite eventos; envio efetivo de mensagens (templates HSM, opt-in) é responsabilidade da Fase 3 (`MessagingDispatcher` já valida). FR-018 documenta: confirmação T-24h dispara `ConsultaConfirmacaoPendente` → Fase 3 valida template aprovado antes de enviar. |
| **VII. Segurança Operacional (NON-NEGOTIABLE)** | ✅ PASS | (a) Toda rota `/api/v1/agenda/*` Bearer + `tenant.slug` (Fase 4 Lote D-K); (b) tokens OAuth criptografados via `Crypt::encryptString` (APP_KEY rotation-safe); (c) CSP `connect-src` estendido em `config/csp.php` (env `CSP_GOOGLE_HOSTS=https://accounts.google.com https://oauth2.googleapis.com https://www.googleapis.com`); (d) webhooks Google `POST /webhooks/google-calendar/{channel_id}` validam header `X-Goog-Channel-Token` (HMAC equivalente, gerado pelo Fase 5 ao registrar watch channel) + `X-Goog-Resource-State`; (e) rate limit em `/agenda/slots/{}/reservar` (60/min/user) + `/agenda/consultas` (120/min/user) configurado em `RouteServiceProvider`. |

**Conclusion**: PASS. **Sem amendment necessário** (constitution v1.4.0 cobre Bearer + criptografia + CSP — todos os gates novos da Fase 5 são aplicação dos princípios existentes, não adição/redefinição). Re-check pós-design programado para o final do Phase 1.

## Project Structure

### Documentation (this feature)

```text
specs/005-agendamento-consultas/
├── plan.md              # Este arquivo (output /speckit.plan)
├── research.md          # Phase 0 — 8 decisões técnicas com rationale
├── data-model.md        # Phase 1 — 14 entidades + ER
├── contracts/
│   └── openapi.yaml     # Phase 1 — ~50 endpoints REST sob /api/v1/agenda/*
├── quickstart.md        # Phase 1 — DoR para deploy (OAuth Google credentials, env vars, smoke E2E)
├── checklists/
│   └── requirements.md  # Já existente — atualizado pelo /speckit.clarify
├── spec.md              # Já existente — 14/15 clarifications resolvidos
└── tasks.md             # Phase 2 (output /speckit.tasks — NÃO criado por /speckit.plan)
```

### Source Code (repository root)

Reusa estrutura Laravel/Vue padrão preservada nas Fases 0-4. Novos arquivos da Fase 5:

```text
app/
├── Console/Commands/
│   ├── AgendaCleanupExpiredReservationsCommand.php          [novo]
│   ├── AgendaExpireWaitlistNotificationsCommand.php         [novo]
│   ├── AgendaDispatchConfirmationsCommand.php               [novo]
│   ├── AgendaAutoCloseStaleAppointmentsCommand.php          [novo]
│   ├── AgendaGooglePollFallbackCommand.php                  [novo]
│   └── AgendaGoogleRenewWatchChannelsCommand.php            [novo]
├── Events/Agenda/
│   ├── ProfissionalAgendaConfigurada.php                    [novo]
│   ├── ConsultaCriada.php                                   [novo]
│   ├── ConsultaConfirmada.php                               [novo]
│   ├── ConsultaCancelada.php                                [novo]
│   ├── ConsultaReagendada.php                               [novo]
│   ├── ConsultaRealizada.php                                [novo]
│   ├── ConsultaNaoRealizada.php                             [novo]
│   ├── ConsultaMarcacaoRevertida.php                        [novo — clarify nº 14]
│   ├── VagaAbertaNaListaDeEspera.php                        [novo]
│   ├── CalendarioExternoSincronizado.php                    [novo]
│   ├── CancelamentoSolicitadoForaDoPrazo.php                [novo — clarify nº 3]
│   ├── ConsultaPendenteContatoManual.php                    [novo — clarify nº 6]
│   ├── LimiteDeReagendamentoExcedido.php                    [novo — clarify nº 7]
│   ├── ConsultaConfirmacaoPendente.php                      [novo]
│   └── ReagendamentoSolicitadoPeloPaciente.php              [novo]
├── Http/Controllers/Api/V1/Agenda/
│   ├── AppointmentController.php                            [novo — CRUD/reagendar/cancelar/marcar]
│   ├── AppointmentTypeController.php                        [novo — CRUD tipos]
│   ├── ProfessionalScheduleController.php                   [novo — config agenda]
│   ├── ScheduleExceptionController.php                      [novo — bloqueios]
│   ├── SlotController.php                                   [novo — slots-disponiveis + reservar]
│   ├── WaitlistController.php                               [novo — inscrever/cancelar]
│   ├── CalendarSyncController.php                           [novo — OAuth Google + status]
│   └── GoogleCalendarWebhookController.php                  [novo — push notifications]
├── Http/Requests/Agenda/
│   ├── StoreAppointmentRequest.php                          [novo]
│   ├── UpdateAppointmentRequest.php                         [novo]
│   ├── RescheduleAppointmentRequest.php                     [novo]
│   ├── CancelAppointmentRequest.php                         [novo]
│   ├── MarkAttendanceRequest.php                            [novo]
│   ├── StoreAppointmentTypeRequest.php                      [novo]
│   ├── ListAvailableSlotsRequest.php                        [novo]
│   ├── ReserveSlotRequest.php                               [novo]
│   ├── StoreWaitlistEntryRequest.php                        [novo]
│   └── ConnectGoogleCalendarRequest.php                     [novo]
├── Http/Resources/Agenda/
│   ├── AppointmentResource.php                              [novo]
│   ├── AppointmentTypeResource.php                          [novo]
│   ├── ProfessionalScheduleResource.php                     [novo]
│   ├── SlotResource.php                                     [novo]
│   ├── WaitlistEntryResource.php                            [novo]
│   └── CalendarSyncAccountResource.php                      [novo]
├── Models/Agenda/
│   ├── Appointment.php                                      [novo]
│   ├── AppointmentType.php                                  [novo]
│   ├── AppointmentTypeProfessional.php                      [novo — pivot]
│   ├── AppointmentReschedule.php                            [novo]
│   ├── ProfessionalSchedule.php                             [novo]
│   ├── ScheduleException.php                                [novo]
│   ├── SlotReservation.php                                  [novo]
│   ├── WaitlistEntry.php                                    [novo]
│   ├── ConfirmationDispatch.php                             [novo]
│   ├── CalendarSyncAccount.php                              [novo]
│   ├── ExternalCalendarBusy.php                             [novo — clarify nº 10]
│   └── CalendarSyncedEvent.php                              [novo — mapping appointment ↔ external_event_id]
├── Policies/
│   ├── AppointmentPolicy.php                                [novo]
│   ├── AppointmentTypePolicy.php                            [novo]
│   ├── ProfessionalSchedulePolicy.php                       [novo]
│   ├── WaitlistEntryPolicy.php                              [novo]
│   └── CalendarSyncAccountPolicy.php                        [novo]
├── Services/Agenda/
│   ├── AppointmentService.php                               [novo — criar, reagendar, cancelar, marcar]
│   ├── SlotGeneratorService.php                             [novo — gerar slots determinísticos]
│   ├── SlotReservationService.php                           [novo — reservar/liberar com TTL]
│   ├── ScheduleConfigurationService.php                     [novo — agenda + exceções]
│   ├── ConfirmationDispatcherService.php                    [novo — emite ConsultaConfirmacaoPendente]
│   ├── ConfirmationResponseProcessor.php                    [novo — processa 1/2/3]
│   ├── WaitlistService.php                                  [novo — FIFO sequencial]
│   ├── AttendanceMarkingService.php                         [novo — realizada / nao_realizada / reverter]
│   ├── TimezoneResolverService.php                          [novo — TZ tenant/profissional/UTC]
│   └── Calendar/
│       ├── GoogleCalendarOAuthService.php                   [novo — connect, refresh, revoke]
│       ├── GoogleSubCalendarManager.php                     [novo — cria sub-calendário tenant-scoped]
│       ├── GoogleCalendarSyncService.php                    [novo — escreve eventos no sub-cal]
│       ├── GoogleCalendarWatchService.php                   [novo — subscribes channel push notifications]
│       └── GoogleCalendarPollFallbackService.php            [novo — polling 5min]
├── Listeners/Agenda/
│   ├── MoveCardToAgendadoColumn.php                         [novo — Fase 2 funil mover card]
│   ├── UpdatePacienteTimelineForAppointment.php             [novo — Fase 2 timeline (já listener genérico Auditable)]
│   ├── BroadcastAppointmentChangeToAgendaChannel.php        [novo — Reverb canal tenant.X.agenda]
│   ├── SyncAppointmentToGoogleCalendar.php                  [novo — Job dispatch para US-6.7]
│   ├── DispatchVagaListaEsperaToInbox.php                   [novo — emite para Fase 3]
│   └── DispatchConfirmationToInbox.php                      [novo — emite ConsultaConfirmacaoPendente para Fase 3]
├── Jobs/Agenda/
│   ├── SyncAppointmentToGoogleCalendarJob.php               [novo — extends TenantAwareJob]
│   ├── ProcessGoogleCalendarPushJob.php                     [novo — recebe push, atualiza ExternalCalendarBusy]
│   ├── PollGoogleCalendarFallbackJob.php                    [novo — fallback poll por profissional]
│   └── RenewGoogleWatchChannelJob.php                       [novo — renova canal antes do TTL]
└── Support/
    ├── AgendaMetrics.php                                    [novo — 6 contadores/gauges Prometheus]
    └── IanaTimezoneCity.php                                 [novo — IANA → cidade canônica para texto da mensagem]

database/migrations/
├── 2026_05_14_000001_create_appointment_types_table.php                         [novo]
├── 2026_05_14_000002_create_appointment_type_professional_table.php             [novo — pivot]
├── 2026_05_14_000003_create_professional_schedules_table.php                    [novo]
├── 2026_05_14_000004_create_schedule_exceptions_table.php                       [novo]
├── 2026_05_14_000005_create_appointments_table.php                              [novo — UNIQUE composite]
├── 2026_05_14_000006_create_appointment_reschedules_table.php                   [novo]
├── 2026_05_14_000007_create_slot_reservations_table.php                         [novo — partial index expires_at]
├── 2026_05_14_000008_create_waitlist_entries_table.php                          [novo]
├── 2026_05_14_000009_create_confirmation_dispatches_table.php                   [novo]
├── 2026_05_14_000010_create_calendar_sync_accounts_table.php                    [novo — UNIQUE composite]
├── 2026_05_14_000011_create_calendar_synced_events_table.php                    [novo — mapping]
├── 2026_05_14_000012_create_external_calendar_busy_table.php                    [novo — clarify nº 10]
├── 2026_05_14_000013_add_timezone_to_professionals_table.php                    [novo — clarify nº 13]
└── 2026_05_14_000014_extend_tenants_settings_with_agenda_keys.php               [novo — JSONB merge keys]

database/seeders/
├── AppointmentTypeSeeder.php                                [novo — 3 tipos default por tenant: Consulta 30min, Retorno 15min, Exame 60min]
└── AgendaPermissionsSeeder.php                              [novo — 9 abilities + atribuição por role]

routes/
├── api.php                                                  [edit — adiciona group `prefix=v1/agenda` middleware=auth:sanctum,tenant.slug]
├── channels.php                                             [edit — adiciona canal `tenant.{tenantId}.agenda`]
└── console.php                                              [edit — agenda 6 novos cron commands]

config/
├── csp.php                                                  [edit — adiciona env CSP_GOOGLE_HOSTS no array connect-src]
└── services.php                                             [edit — adiciona bloco google.calendar com client_id/secret/redirect]

resources/js/
├── pages/agenda/
│   ├── AgendaPage.vue                                       [novo — calendário FullCalendar drag-and-drop, views diária/semanal, multi-prof toggle]
│   ├── AppointmentTypesPage.vue                             [novo — CRUD tipos]
│   ├── ScheduleConfigPage.vue                               [novo — config horários + exceções por profissional]
│   ├── CalendarSyncPage.vue                                 [novo — connect Google + status]
│   └── WaitlistPage.vue                                     [novo — listagem + inscrição manual]
├── components/agenda/
│   ├── AppointmentFormModal.vue                             [novo — criar/editar consulta]
│   ├── RescheduleConfirmModal.vue                           [novo — drag-to-move confirm]
│   ├── PatientAutocomplete.vue                              [novo — busca trgm Fase 2 com cadastro rápido]
│   ├── SlotPicker.vue                                       [novo — picker de slots disponíveis]
│   ├── ScheduleExceptionForm.vue                            [novo — criar bloqueio]
│   ├── AppointmentTypeForm.vue                              [novo]
│   └── AttendanceMarkButton.vue                             [novo — marca realizada/no-show + reverter]
├── stores/
│   └── agendaStore.js                                       [novo — Pinia: state slots/appointments/reservations/syncStatus, sync via Reverb canal tenant.X.agenda]
├── composables/
│   ├── useAgendaCalendar.js                                 [novo — wrapper FullCalendar com drag handlers]
│   ├── useTimezoneRenderer.js                               [novo — Luxon ISO+offset → render TZ contextual]
│   └── useSlotReservation.js                                [novo — POST /reservar + heartbeat]
└── lib/
    └── agendaApi.js                                         [novo — fetch helpers para /api/v1/agenda/*]

tests/Feature/Agenda/                                         [novo — 7 user stories cobertas]
├── ProfessionalScheduleTest.php
├── AppointmentTypeTest.php
├── AppointmentCreationTest.php
├── SlotConflictRaceTest.php                                 [obrigatório SC-008]
├── SlotReservationTest.php
├── ConfirmationFlowTest.php
├── RescheduleViaChatTest.php
├── CancellationPolicyTest.php
├── WaitlistSequentialTest.php
├── CalendarSyncOAuthTest.php
├── CrossTenantAgendaTest.php                                [obrigatório Princípio II]
├── CrossTenantGoogleSyncTest.php                            [obrigatório clarify nº 15 / AC-6.7.11]
├── GoogleEventPayloadLgpdTest.php                           [obrigatório Princípio I / FR-038]
├── AttendanceMarkingTest.php                                [obrigatório clarify nº 14]
├── TimezoneRenderTest.php                                   [obrigatório clarify nº 13]
└── AgendaPolicyAuthorizationTest.php                        [obrigatório Princípio II / RBAC]

tests/Unit/Agenda/                                            [novo — services isolados]
├── SlotGeneratorServiceTest.php
├── SlotReservationServiceTest.php
├── TimezoneResolverServiceTest.php
└── IanaTimezoneCityTest.php
```

**Structure Decision**: Web application multi-tenant (Option 2) — backend Laravel + frontend Vue 3 SPA, sem mudança estrutural. Novos arquivos seguem convenções estabelecidas nas Fases 0-4 (`app/Models/Agenda/*`, `app/Services/Agenda/*`, `resources/js/pages/agenda/*`). Filament super admin **não é tocado** (Fase 5 é tenant-only). Estrutura `app/Http/Controllers/Api/V1/Agenda/*` segue padrão Fase 3 (`Api/V1/Inbox/*`).

## Complexity Tracking

> **Constitution Check passou sem violações.** Esta seção documenta apenas **trade-offs deliberados** que merecem registro para futuros revisores, não desvios do princípio.

| Trade-off | Por quê | Alternativa simpler rejeitada porque |
|---|---|---|
| **2 novas tabelas para Google sync (`CalendarSyncAccount` + `CalendarSyncedEvent` + `ExternalCalendarBusy`)** | Separação clara de concerns: account = OAuth state, synced_event = mapping bidirecional, busy = bloqueio externo (clarify nº 10 — não vira Appointment) | Tabela única `calendar_integrations` com colunas opcionais funcionou em POC mas vazou conceitos no domínio (`type=account` vs `type=event` vs `type=busy` com 80% dos campos nullable) — refatoração inevitável depois. |
| **Sub-calendário Google dedicado por (tenant, profissional)** vs conta Google separada (clarify nº 15) | Eliminar atrito de adoção (médico não cria nova conta Gmail) sem comprometer isolamento LGPD | Conta Google totalmente separada por tenant rejeitada porque Médico que atende em 2 clínicas não vai criar 2 contas — provável que abandonem o sync, perdendo o ROI. Sub-calendário dá UX clara com cores distintas. |
| **`SlotReservation` como tabela separada** vs lock pessimista no DB ou Redis-only | Auditabilidade (clarify nº 2 exige `holder_type`, `release_reason`, audit log); persistência sobrevive a restart de Redis; fluxo de IA Matricial precisa rastrear reservas para debugging | Lock SELECT FOR UPDATE no `Appointment` rejeitado por bloquear leituras concorrentes (matar UX da agenda); Redis-only rejeitado por perder auditoria pós-restart. |
| **Push (Watch) + polling 5min fallback ambos rodando** vs apenas push (clarify nº 10) | Watch channels Google têm TTL ~7 dias e podem expirar silenciosamente; polling fallback cobre o caso (sem ele, sync silenciosamente "morre" e médico não percebe até dias depois) | Apenas push rejeitado por confiabilidade insuficiente em produção; apenas polling rejeitado por latência (>5min para refletir mudança no Google) — quebra SC-005. |
| **`AppointmentReschedule` como tabela histórica** vs JSONB column em `Appointment.history` | Queries de "quem reagendou esta consulta?" + contagem para limite (FR-026b/c) ficam triviais com SQL; histórico imutável | JSONB column rejeitada por ambiguidade de schema (cada reagendamento tem campos próprios), queries com `->>` são lentas, e contagem para enforce de limite vira `jsonb_array_length(...)` (não indexável). |
| **6 cron jobs novos** | Cada um tem cadência própria: cleanup reservas (1min), expire waitlist (1min), dispatch confirmações (5min), auto-close stale (diário), poll Google fallback (5min), renew watch channels (diário) | Job único com switch interno rejeitado: cadências diferentes obrigam o job a rodar na frequência mais agressiva (1min) e fazer trabalho desnecessário; falha em uma sub-rotina derruba todas. |

**Conclusion**: Trade-offs documentados são **complexidade essencial** do domínio (agendamento + sync externo), não complexidade acidental. Constitution Check post-design (final do Phase 1) confirmará que nenhum princípio foi violado durante o design das entidades e contratos.

---

## Phase 0 — Outline & Research

**Status**: ✅ Concluído. Output: [research.md](./research.md)

8 decisões técnicas pesquisadas e consolidadas:

1. **R1 — Cliente Google API**: `google/apiclient ^2.18` vs `spatie/laravel-google-calendar`
2. **R2 — Widget de calendário Vue 3**: `@fullcalendar/vue3` vs `vue-cal` vs `@schedule-x/calendar`
3. **R3 — Estratégia de Watch channels Google + Pub/Sub**: webhook direto vs Pub/Sub topic
4. **R4 — Reserva pessimista soft de slot**: tabela própria + cleanup cron
5. **R5 — UX de revogação OAuth (resolve NC nº 12)**: notificação dual (in-app + email) + refresh automático + reconectar 1-clique
6. **R6 — Conversão TZ (clarify nº 13)**: `Carbon` server-side + `Luxon` client-side com IANA explícito
7. **R7 — Geração determinística de slots**: algoritmo + cache invalidation
8. **R8 — Idempotência de eventos de domínio**: `idempotency_key` UUID v7 + UNIQUE constraint

## Phase 1 — Design & Contracts

**Status**: ✅ Concluído. Outputs:

- [data-model.md](./data-model.md) — 14 entidades + ER + state transitions
- [contracts/openapi.yaml](./contracts/openapi.yaml) — ~50 endpoints REST sob `/api/v1/agenda/*` + `/webhooks/google-calendar/*`
- [quickstart.md](./quickstart.md) — DoR para deploy: OAuth Google credentials, escopo mínimo, env vars, smoke E2E
- Agent context: `CLAUDE.md` atualizado entre `<!-- SPECKIT START -->` / `<!-- SPECKIT END -->` para apontar este `plan.md`

### Constitution Re-check post-design

| Princípio | Status pós-design |
|---|---|
| I. LGPD | ✅ PASS — `CalendarSyncAccount.encrypted_access_token` + `encrypted_refresh_token` via `Crypt`; `Appointment.notas` `encrypted` cast; sync payload Google fixo via test gate |
| II. Multi-tenant | ✅ PASS — todas 14 tabelas têm `tenant_id`; UNIQUE composites bloqueiam conflito cross-tenant; `CrossTenantGoogleSyncTest` cobre AC-6.7.11 |
| III. IA Clínica | ✅ PASS — sem chamada LLM nesta fase; contratos reservados (TTL 2min IA, flag `via_ia`) |
| IV. Spec-driven | ✅ PASS — 50 endpoints documentados em OpenAPI antes de qualquer código; ACs 🔴 mapeados |
| V. Observabilidade | ✅ PASS — 6 métricas Prometheus + 11 audit events + correlation_id em jobs |
| VI. Meta | ✅ PASS — não-impactante; events emitted para Fase 3 |
| VII. Segurança | ✅ PASS — Bearer reusado; OAuth tokens encrypted; CSP estendido; webhooks validados via X-Goog-Channel-Token; rate limit configurado |

**Re-check conclusion**: PASS — design alinhado com Constitution v1.4.0 sem violações. Pronto para `/speckit.tasks`.

---

## Phase 2 — Tasks

**Out of scope for `/speckit.plan`.** Próximo comando: `/speckit.tasks` que vai consumir spec.md + plan.md + data-model.md + contracts/openapi.yaml para gerar `tasks.md` dependency-ordered (estimado: ~8 lotes A-H, ~120 tasks).
