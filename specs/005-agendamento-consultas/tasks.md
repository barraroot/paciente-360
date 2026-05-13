---
description: "Task list for Fase 5 — Agendamento de Consultas"
---

# Tasks: Fase 5 — Agendamento de Consultas

**Input**: Design documents from `/specs/005-agendamento-consultas/`
**Prerequisites**: spec.md (14/15 clarifications), plan.md (Constitution Check PASS), research.md (R1-R8), data-model.md (14 entidades), contracts/openapi.yaml (~30 endpoints), quickstart.md (DoR)

**Tests**: REQUIRED — Constituição v1.4.0 Princípio IV (Spec-driven Test-First) torna ACs 🔴 vermelhos antes da implementação **mandatório**. Toda feature task é precedida pela test task correspondente.

**Organization**: 10 fases — Setup, Foundational, 7 User Stories (US1-US7) na ordem de prioridade do spec, Polish.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Pode rodar em paralelo (arquivo distinto, sem dependência pendente)
- **[Story]**: US1..US7 mapeia para User Stories do spec.md
- Caminhos absolutos quando ambíguo

## Path Conventions

Web app multi-tenant Laravel + Vue (Option 2 do plan):
- **Backend**: `app/`, `database/`, `routes/`, `config/`, `tests/`
- **Frontend**: `resources/js/`
- **Specs**: `specs/005-agendamento-consultas/`
- Comandos sempre prefixados `vendor/bin/sail` (Restrição constitucional)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Dependências, configs, CSP. Roda antes de qualquer migration.

- [X] T001 Adicionar `google/apiclient ^2.18` ao composer (R1) executando `vendor/bin/sail composer require google/apiclient:^2.18`; verificar `composer.json` e `composer.lock` commitados
- [X] T002 [P] Adicionar deps npm **FullCalendar v6.1.20** (corrigido de v7 — última estável out-2025) + Luxon executando `vendor/bin/sail npm install --legacy-peer-deps @fullcalendar/core@^6 @fullcalendar/vue3@^6 @fullcalendar/daygrid@^6 @fullcalendar/timegrid@^6 @fullcalendar/interaction@^6 @fullcalendar/resource-timegrid@^6 luxon@^3` (R2, R6); `--legacy-peer-deps` por conflict pré-existente vite ^8 vs @vitejs/plugin-vue ^5.2.1 (não causado pela Fase 5); `package.json` e `package-lock.json` atualizados
- [X] T003 [P] Adicionar bloco `google_calendar` em `config/services.php` lendo `GOOGLE_CALENDAR_CLIENT_ID/SECRET/REDIRECT_URI/WEBHOOK_BASE_URL`, `AGENDA_CALENDAR_SYNC_WINDOW_DAYS` (default 60), `AGENDA_WATCH_CHANNEL_RENEW_HOURS` (default 48) — ver quickstart § 3
- [X] T004 [P] Estender `config/csp.php` adicionando `google_hosts` array (default: 3 endpoints Google OAuth+API) lido de `env('CSP_GOOGLE_HOSTS')`; **atualizado também `app/Http/Middleware/SetSecurityHeaders.php`** para incluir `google_hosts` no header `connect-src` em produção (Princípio VII — quickstart § 3)
- [X] T005 [P] Atualizar `.env.example` com 5 novas vars Fase 5 (GOOGLE_CALENDAR_CLIENT_ID/SECRET/REDIRECT_URI/WEBHOOK_BASE_URL, AGENDA_CALENDAR_SYNC_WINDOW_DAYS=60, AGENDA_WATCH_CHANNEL_RENEW_HOURS=48, CSP_GOOGLE_HOSTS) — quickstart § 3

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Migrations + abilities + suporte transversal MUST estar verde antes de qualquer User Story começar.

### Migrations (14 — ordem obrigatória)

- [X] T006 Migration 01 `database/migrations/2026_05_14_000001_create_appointment_types_table.php` (entidade 1; UNIQUE `(tenant_id, slug)`, INDEX `(tenant_id, is_active)`)
- [X] T007 Migration 02 `database/migrations/2026_05_14_000002_create_appointment_type_professional_table.php` (pivot M2M, PK composite)
- [X] T008 Migration 03 `database/migrations/2026_05_14_000003_create_professional_schedules_table.php` (UNIQUE `(tenant_id, professional_id, day_of_week, effective_from)`)
- [X] T009 Migration 04 `database/migrations/2026_05_14_000004_create_schedule_exceptions_table.php` (GiST tsrange index)
- [X] T010 Migration 05 `database/migrations/2026_05_14_000005_create_appointments_table.php` (UNIQUE PARTIAL `(tenant_id, professional_id, starts_at) WHERE status IN ('scheduled', 'confirmed')` — gate FR-011a / SC-008; encrypted cast em `notes`)
- [X] T011 Migration 06 `database/migrations/2026_05_14_000006_create_appointment_reschedules_table.php` (FK appointment_id, INDEX `(appointment_id, created_at DESC)`)
- [X] T012 Migration 07 `database/migrations/2026_05_14_000007_create_slot_reservations_table.php` (PARTIAL UNIQUE `(tenant_id, professional_id, starts_at) WHERE released_at IS NULL` + PARTIAL INDEX em `expires_at WHERE released_at IS NULL`) — clarify nº 2 / R4
- [X] T013 Migration 08 `database/migrations/2026_05_14_000008_create_waitlist_entries_table.php` (PARTIAL INDEX `(status, expires_at) WHERE status='notified'`)
- [X] T014 Migration 09 `database/migrations/2026_05_14_000009_create_confirmation_dispatches_table.php` (UNIQUE `(appointment_id, kind)`)
- [X] T015 Migration 10 `database/migrations/2026_05_14_000010_create_calendar_sync_accounts_table.php` (UNIQUE `(tenant_id, professional_id)` — gate FR-036c / clarify nº 15; encrypted cast em `encrypted_access_token`/`encrypted_refresh_token`)
- [X] T016 Migration 11 `database/migrations/2026_05_14_000011_create_calendar_synced_events_table.php` (UNIQUE `(appointment_id, calendar_sync_account_id)`)
- [X] T017 Migration 12 `database/migrations/2026_05_14_000012_create_external_calendar_busy_table.php` (UNIQUE `(calendar_sync_account_id, external_event_id)` — clarify nº 10)
- [X] T018 Migration 13 `database/migrations/2026_05_14_000013_add_timezone_to_professionals_table.php` (`timezone VARCHAR(64) NULLABLE` — clarify nº 13)
- [X] T019 Migration 14 `database/migrations/2026_05_14_000014_extend_tenants_settings_with_agenda_keys.php` (UPDATE jsonb_set com defaults `agenda.{min_cancellation_hours:4, max_reschedules_per_appointment:2, waitlist_confirmation_minutes:15, calendar_sync_window_days:60, slot_reservation_ttl_user_minutes:5, slot_reservation_ttl_ia_minutes:2, auto_close_stale_appointments_days:7, attendance_revert_window_hours:48}`)
- [X] T020 Rodar `vendor/bin/sail artisan migrate` em ambiente local; validar 14 migrations aplicadas (`migrations` table tem novos rows)

### Abilities + Permissions

- [X] T021 Criar `database/seeders/AgendaPermissionsSeeder.php` com 9 abilities (`appointment.view`, `appointment.create`, `appointment.update`, `appointment.cancel`, `appointment.override_block`, `appointment.manage_own_schedule`, `appointment.revert_attendance_marking`, `schedule.configure`, `appointment_type.manage`, `waitlist.manage`, `calendar_sync.configure`) + atribuição por role (Admin Clínica/Médico/Atendente/Recepcionista/Financeiro) conforme spec § Contratos Herdados
- [X] T022 Rodar `vendor/bin/sail artisan db:seed --class=AgendaPermissionsSeeder` e atualizar `DatabaseSeeder.php` para incluir o seeder em `run()`

### Base support classes (reutilizadas por todas as US)

- [X] T023 [P] Criar `app/Support/IanaTimezoneCity.php` (mapping IANA → cidade canônica BR + fallback genérico) com método `canonicalLabel(string $iana): string` — clarify nº 13 / R6
- [X] T024 [P] Criar `app/Support/AgendaMetrics.php` com 6 contadores/gauges Prometheus (`appointment_created_total{type,channel_origin}`, `appointment_canceled_total{quem}`, `appointment_no_show_total`, `confirmation_response_total{kind,result}`, `waitlist_notification_total{result}`, `calendar_sync_status{provider,status}`, `calendar_sync_latency_seconds{operation}` histogram); registrar em `MetricsRegistry` da Fase 0
- [X] T025 [P] Criar `app/Services/Agenda/TimezoneResolverService.php` com método `resolve(Professional $prof): string` retornando IANA TZ (override do prof OU tenant) — clarify nº 13

### Routing + Middleware (group base)

- [X] T026 Editar `routes/api.php` adicionando group `Route::prefix('v1/agenda')->middleware(['auth:sanctum', 'tenant.slug'])->group(...)` com rotas vazias (filled per US); confirmar prefix Fase 4 ainda funciona
- [X] T027 Editar `routes/channels.php` adicionando canal `Broadcast::channel('tenant.{tenantId}.agenda', fn($user, $tenantId) => $user->tenant_id === $tenantId)` (Reverb broadcast multi-aba)
- [X] T028 [P] Criar middleware opcional `app/Http/Middleware/EnsureAgendaModuleEnabled.php` (feature flag `config('features.agenda_module', true)` — quickstart § 11.2); registrar em `bootstrap/app.php`

### Schedule (cron — preparado, jobs criados nas US correspondentes)

- [X] T029 Editar `routes/console.php` registrando 6 schedule entries: `agenda:cleanup-expired-reservations` (everyMinute), `agenda:expire-waitlist-notifications` (everyMinute), `agenda:dispatch-confirmations` (everyFiveMinutes), `agenda:auto-close-stale-appointments` (dailyAt 00:30), `agenda:google-poll-fallback` (everyFiveMinutes), `agenda:google-renew-watch-channels` (dailyAt 02:00); todos `->onOneServer()`

### Test infrastructure

- [X] T030 [P] Criar `database/factories/Agenda/` com factories para `Appointment`, `AppointmentType`, `ProfessionalSchedule`, `ScheduleException`, `SlotReservation`, `WaitlistEntry`, `ConfirmationDispatch`, `CalendarSyncAccount`, `ExternalCalendarBusy` — usados por todos os testes
- [X] T031 [P] Criar `tests/Feature/Agenda/CrossTenantAgendaTest.php` com 5 cenários (Princípio II — gate obrigatório): tenant A não enxerga `Appointment`/`SlotReservation`/`WaitlistEntry`/`AppointmentType`/`CalendarSyncAccount` do tenant B. Test deve estar 🔴 RED no momento da escrita.

**Checkpoint**: 14 migrations verdes + 9 abilities + suport classes + routing scaffold + schedule registrado + factories + cross-tenant test 🔴 — pronto para US começarem em paralelo.

---

## Phase 3: User Story 1 — Profissional configura sua agenda (Priority: P1) 🎯 MVP

**Goal**: Médico ou Admin Clínica configura horários de trabalho, intervalos e bloqueios para que apenas horários válidos sejam ofertados aos pacientes.

**Independent Test**: criar tenant + profissional + agenda; consultar via API "horários disponíveis na semana" → resposta reflete a configuração (com bloqueios, intervalos e tipos aceitos).

### Tests for User Story 1 ⚠️ (Princípio IV — RED before GREEN)

- [X] T032 [P] [US1] Test `tests/Feature/Agenda/ProfessionalScheduleTest.php` cobrindo AC-6.1.1, AC-6.1.2, AC-6.1.5, AC-6.1.6 (configurar agenda, listar slots respeitando intervalo, médico edita própria agenda, visualização lado-a-lado)
- [X] T033 [P] [US1] Test `tests/Feature/Agenda/ScheduleExceptionTest.php` cobrindo AC-6.1.3 (bloqueio 10/06-20/06 + override de bloqueio com `appointment.override_block` + notificação push+email — clarify nº 5)
- [X] T034 [P] [US1] Test `tests/Feature/Agenda/ProfessionalSchedulePolicyAuthorizationTest.php` validando `appointment.manage_own_schedule` (médico só edita a própria) vs `schedule.configure` (admin qualquer prof)

### Models for User Story 1

- [X] T035 [P] [US1] Criar `app/Models/Agenda/ProfessionalSchedule.php` com relations (`professional`, `tenant`, `acceptedAppointmentTypes` via pivot), cast `blocks` AsJsonArray, scope `BelongsToTenant`
- [X] T036 [P] [US1] Criar `app/Models/Agenda/ScheduleException.php` com relations (`professional`, `creator`), scope `BelongsToTenant`
- [X] T037 [P] [US1] Criar `app/Models/Agenda/AppointmentTypeProfessional.php` (pivot model — necessário porque carrega `tenant_id` denormalizado, cast `created_at`)

### Form Requests + Policy + Service for US1

- [X] T038 [US1] Criar `app/Policies/ProfessionalSchedulePolicy.php` com `viewAny`, `update` (verifica `appointment.manage_own_schedule` próprio OU `schedule.configure` qualquer); registrar em `AuthServiceProvider`
- [X] T039 [P] [US1] Criar `app/Http/Requests/Agenda/UpdateProfessionalScheduleRequest.php` validando `schedules[].day_of_week`, `blocks[]`, `accepted_appointment_type_ids[]`, `timezone` IANA; autorização via policy
- [X] T040 [P] [US1] Criar `app/Http/Requests/Agenda/StoreScheduleExceptionRequest.php` validando `starts_at`, `ends_at` (after starts_at), `reason` opcional
- [X] T041 [US1] Criar `app/Services/Agenda/ScheduleConfigurationService.php` com métodos `updateSchedule(Professional, array $data, User $actor)`, `createException(Professional, array, User)`; emite `ProfissionalAgendaConfigurada` (Auditable); ao criar exceção que sobrepõe consultas, chama `AppointmentService::cancelOverlappingAppointments(...)` (FR-028c)
- [X] T042 [P] [US1] Criar evento `app/Events/Agenda/ProfissionalAgendaConfigurada.php` implementando `Auditable` (Fase 0)

### Controllers + Resources + Routes for US1

- [X] T043 [P] [US1] Criar `app/Http/Resources/Agenda/ProfessionalScheduleResource.php` (envelope com `professional_id`, `timezone`, `schedules[]`, `accepted_appointment_type_ids[]`)
- [X] T044 [US1] Criar `app/Http/Controllers/Api/V1/Agenda/ProfessionalScheduleController.php` (`show`, `update` apenas — não cria/deleta; é PUT batch)
- [X] T045 [US1] Criar `app/Http/Controllers/Api/V1/Agenda/ScheduleExceptionController.php` (`index`, `store`, `destroy`)
- [X] T046 [US1] Editar `routes/api.php` adicionando rotas: `GET /agenda/professionals/{p}/schedules`, `PUT /agenda/professionals/{p}/schedules`, `GET /agenda/professionals/{p}/schedule-exceptions`, `POST /agenda/professionals/{p}/schedule-exceptions`, `DELETE /agenda/schedule-exceptions/{id}`

### Frontend for US1

- [X] T047 [P] [US1] Criar `resources/js/pages/agenda/ScheduleConfigPage.vue` (config horários por dia da semana + lista de exceções + wizard "Configurar agenda agora?" / "Copiar de outro profissional" — clarify nº 5)
- [X] T048 [P] [US1] Criar `resources/js/components/agenda/ScheduleExceptionForm.vue` (modal criar bloqueio com `starts_at`, `ends_at`, `reason`)
- [X] T049 [P] [US1] Criar `resources/js/lib/agendaApi.js` com helpers `getSchedule(profId)`, `updateSchedule(profId, payload)`, `listExceptions(profId, range)`, `createException(profId, payload)`, `deleteException(id)`

**Checkpoint**: US-6.1 verde — agenda configurável, bloqueios + override, abilities respeitadas. Cross-tenant test e Schedule policy test passam.

---

## Phase 4: User Story 2 — Cadastro de tipos de atendimento (Priority: P1)

**Goal**: Admin Clínica cadastra tipos com duração, valor, buffer e cor para que cada um tenha regras próprias.

**Independent Test**: criar 3 tipos no tenant; listar via API → 3 tipos retornados com seus atributos.

### Tests for User Story 2 ⚠️

- [X] T050 [P] [US2] Test `tests/Feature/Agenda/AppointmentTypeTest.php` cobrindo AC-6.2.1, AC-6.2.2, AC-6.2.3, AC-6.2.5 (CRUD, inativação preserva histórico, cor retornada, multi-tenant scoping)

### Implementation for US2

- [X] T051 [P] [US2] Criar `app/Models/Agenda/AppointmentType.php` com casts (`valor_particular` decimal:2, `valor_convenio_default` decimal:2 nullable, `buffer_minutes` integer, `min_cancellation_hours` integer nullable, `is_active` boolean), scope `BelongsToTenant`, scope `active()`, relation `professionals` via pivot
- [X] T052 [P] [US2] Criar `app/Policies/AppointmentTypePolicy.php` (ability `appointment_type.manage` para create/update/delete; `appointment.view` para viewAny/view); registrar em `AuthServiceProvider`
- [X] T053 [P] [US2] Criar `app/Http/Requests/Agenda/StoreAppointmentTypeRequest.php` + `UpdateAppointmentTypeRequest.php` (validação per OpenAPI schema `AppointmentTypeCreate`)
- [X] T054 [P] [US2] Criar `app/Http/Resources/Agenda/AppointmentTypeResource.php`
- [X] T055 [US2] Criar `app/Http/Controllers/Api/V1/Agenda/AppointmentTypeController.php` (`index`, `store`, `show`, `update`, `destroy` — destroy faz soft inactivate `is_active=false`, FR-007)
- [X] T056 [US2] Criar `database/seeders/AppointmentTypeSeeder.php` com 3 tipos default (Consulta 30min/R$200, Retorno 15min/R$100, Exame 60min/R$300) idempotentes (usar `firstOrCreate`)
- [X] T057 [US2] Editar `routes/api.php` adicionando rotas REST `/agenda/appointment-types/*` (5 endpoints)

### Frontend for US2

- [X] T058 [P] [US2] Criar `resources/js/pages/agenda/AppointmentTypesPage.vue` (lista + criar/editar via modal)
- [X] T059 [P] [US2] Criar `resources/js/components/agenda/AppointmentTypeForm.vue` (campos nome, duration, buffer, valores, min_cancellation_hours, cor color picker, descricao, intent_ia); tooltip "Tipo: Retorno (categoria de consulta)" para evitar confusão com cadência Fase 6 (clarify nº 4 — AC-6.2.4)

**Checkpoint**: US-6.2 verde — tipos CRUD, inativação, cor, RBAC. Independente de US1 (não bloqueia).

---

## Phase 5: User Story 3 — Agendamento manual via painel drag-and-drop (Priority: P1)

**Goal**: Atendente marca consultas via drag-and-drop no calendário (criar, mover, listar) com gate de slot conflict, busca de paciente e notificação ao paciente.

**Independent Test**: Atendente logado abre /agenda, arrasta para criar bloco às 09:00, seleciona paciente existente + tipo "Consulta" + profissional, confirma → consulta aparece na grade, paciente recebe mensagem de confirmação via canal de origem.

### Tests for User Story 3 ⚠️

- [ ] T060 [P] [US3] Test `tests/Feature/Agenda/AppointmentCreationTest.php` cobrindo AC-6.3.1 (criar consulta + funil mover) + AC-6.3.4 (busca paciente trgm) + AC-6.3.5 (cadastro rápido paciente) + AC-6.3.6 (notify_patient via Fase 3)
- [ ] T061 [P] [US3] Test `tests/Feature/Agenda/SlotConflictRaceTest.php` (50 requests paralelos no mesmo slot, exatamente 1 sucesso → resto 409 `slot_conflict`) — gate SC-008 / FR-011a
- [ ] T062 [P] [US3] Test `tests/Feature/Agenda/SlotReservationTest.php` (TTL 5min user / 2min IA, conflict 409, commit/expired/canceled) — clarify nº 2
- [ ] T063 [P] [US3] Test `tests/Unit/Agenda/SlotGeneratorServiceTest.php` (15+ cenários: intervalo, bloqueio, buffer, conflito, externo, reserva ativa) — R7
- [ ] T064 [P] [US3] Test `tests/Feature/Agenda/AppointmentReschedulingDragTest.php` cobrindo AC-6.3.3 (drag-to-move dispara `ConsultaReagendada` + Fase 3 notifica)

### Models + Events for US3

- [ ] T065 [P] [US3] Criar `app/Models/Agenda/Appointment.php` com casts (UUID v7, status enum, encrypted `notes`, decimal `valor_aplicado`), scope `BelongsToTenant`, scope `active()`, accessor `reschedule_count` (count de `appointment_reschedules`)
- [ ] T066 [P] [US3] Criar `app/Models/Agenda/AppointmentReschedule.php` (FK appointment_id, scope tenant)
- [ ] T067 [P] [US3] Criar `app/Models/Agenda/SlotReservation.php` com scope `active()` (released_at IS NULL AND expires_at > now()), scope `expired()`
- [ ] T068 [P] [US3] Criar evento `app/Events/Agenda/ConsultaCriada.php` (Auditable + ShouldBroadcast em `tenant.{id}.agenda`)
- [ ] T069 [P] [US3] Criar evento `app/Events/Agenda/ConsultaReagendada.php` (Auditable + ShouldBroadcast)

### Services for US3

- [ ] T070 [P] [US3] Criar `app/Services/Agenda/SlotGeneratorService.php` com método pure `generate(Professional, AppointmentType, CarbonPeriod): Collection` + `forApi(...)` com cache Redis 60s — R7
- [ ] T071 [P] [US3] Criar `app/Services/Agenda/SlotReservationService.php` com `reserve(...)`, `release(...)`, `cleanupExpired()`; trata 23505 unique_violation → 409 — R4
- [ ] T072 [US3] Criar `app/Services/Agenda/AppointmentService.php` com `create(...)` (valida slot via SlotGenerator + Reservation commit + emite `ConsultaCriada`), `reschedule(...)` (cria `AppointmentReschedule` + valida limit FR-026b + emite `ConsultaReagendada` + cancela watch para reschedulings consecutivos), `cancelOverlappingAppointments(ScheduleException)` (FR-028c)

### Listeners for US3

- [ ] T073 [P] [US3] Criar `app/Listeners/Agenda/MoveCardToAgendadoColumn.php` (Fase 2 funil — escuta `ConsultaCriada` → muda coluna do paciente para "Agendado"); bind em `EventServiceProvider`
- [ ] T074 [P] [US3] Criar `app/Listeners/Agenda/BroadcastAppointmentChangeToAgendaChannel.php` (Reverb canal `tenant.X.agenda` para sync multi-aba); bind em `EventServiceProvider`

### Form Requests + Resource + Policy + Controller for US3

- [ ] T075 [US3] Criar `app/Policies/AppointmentPolicy.php` com `viewAny` (ability `appointment.view`), `view` (tenant scope), `create` (`appointment.create` + slot disponível), `update` (`appointment.update` — restrito a notas/valor — não move horário), `cancel` (`appointment.cancel`); registrar em `AuthServiceProvider`
- [ ] T076 [P] [US3] Criar `app/Http/Requests/Agenda/StoreAppointmentRequest.php` (validation per OpenAPI `AppointmentCreate` + ability check `appointment.override_block` se `override_block=true` — clarify nº 5)
- [ ] T077 [P] [US3] Criar `app/Http/Requests/Agenda/UpdateAppointmentRequest.php` (apenas notas/valor_override; rejeita campos de horário)
- [ ] T078 [P] [US3] Criar `app/Http/Requests/Agenda/RescheduleAppointmentRequest.php` (idempotency_key + new_starts_at + motivo opcional; rejeita `professional_id`/`appointment_type_id` — clarify nº 7)
- [ ] T079 [P] [US3] Criar `app/Http/Requests/Agenda/ReserveSlotRequest.php` (holder_type, holder_id, idempotency_key)
- [ ] T080 [P] [US3] Criar `app/Http/Requests/Agenda/ListAvailableSlotsRequest.php` (professional_id, appointment_type_id, from, to ISO 8601, page, per_page max 200)
- [ ] T081 [P] [US3] Criar `app/Http/Resources/Agenda/AppointmentResource.php` + `SlotResource.php` + `SlotReservationResource.php` (envelope com `timezone_display` IANA — clarify nº 13)
- [ ] T082 [US3] Criar `app/Http/Controllers/Api/V1/Agenda/AppointmentController.php` (`index`, `store`, `show`, `update`, `reschedule`, `destroy` aliasado para `cancel`) — `cancel` separado em US5
- [ ] T083 [US3] Criar `app/Http/Controllers/Api/V1/Agenda/SlotController.php` (`index` slots-disponiveis, `reservar`, `releaseReservation`)
- [ ] T084 [US3] Editar `routes/api.php` adicionando rotas `/agenda/consultas/*`, `/agenda/slots-disponiveis`, `/agenda/slots/{starts_at}/reservar`, `/agenda/slot-reservations/{id}` (5 + 3 endpoints); rate limit `/reservar` 60/min/user e `/consultas` 120/min/user (Princípio VII)

### Cron command for US3

- [ ] T085 [US3] Criar `app/Console/Commands/AgendaCleanupExpiredReservationsCommand.php` (escaneia reservas WHERE released_at IS NULL AND expires_at < now() em batch de 1000, marca released_at + release_reason='expired' + emite eventos auditados); confirmar Schedule registrado em T029

### Frontend for US3 (FullCalendar drag-and-drop)

- [ ] T086 [P] [US3] Criar `resources/js/composables/useTimezoneRenderer.js` (Luxon parse ISO+offset → render TZ contextual) — clarify nº 13 / R6
- [ ] T087 [P] [US3] Criar `resources/js/composables/useSlotReservation.js` (POST /reservar + heartbeat antes de TTL expirar)
- [ ] T088 [P] [US3] Criar `resources/js/composables/useAgendaCalendar.js` (wrapper FullCalendar com drag handlers — drag-create + drag-to-move com confirm modal)
- [ ] T089 [US3] Criar `resources/js/stores/agendaStore.js` (Pinia: state slots/appointments/reservations, actions fetch/create/reschedule/cancel, sync via Reverb canal `tenant.X.agenda` — invalida cache local em mudança)
- [ ] T090 [P] [US3] Criar `resources/js/components/agenda/PatientAutocomplete.vue` (busca trgm Fase 2 com cadastro rápido — AC-6.3.4/AC-6.3.5)
- [ ] T091 [P] [US3] Criar `resources/js/components/agenda/AppointmentFormModal.vue` (criar/editar consulta — campos: paciente, profissional, tipo, slot, override_block + motivo, valor_override + motivo)
- [ ] T092 [P] [US3] Criar `resources/js/components/agenda/RescheduleConfirmModal.vue` (drag-to-move confirm — texto "Reagendar consulta de {Paciente} de {hora_atual} para {hora_nova}? O paciente será notificado." — clarify nº 9)
- [ ] T093 [P] [US3] Criar `resources/js/components/agenda/SlotPicker.vue` (picker visual de slots disponíveis com TZ render via `useTimezoneRenderer`)
- [ ] T094 [US3] Criar `resources/js/pages/agenda/AgendaPage.vue` (FullCalendar v7 com views diária + semanal + toggle multi-prof; render TZ contextual; integra modais + autocomplete + reservation heartbeat — AC-6.3.7 / clarify nº 9)
- [ ] T095 [US3] Adicionar rotas Vue Router para `/agenda`, `/agenda/configurar`, `/agenda/tipos`; lazy-load chunks

**Checkpoint**: US-6.3 verde — drag-and-drop funcional, slot reservation TTL, race condition gate, RBAC, paciente notificado via Fase 3, sync multi-aba via Reverb. **MVP MÍNIMO** entregável aqui (US1+US2+US3 = agenda operacional via painel humano sem confirmação automática).

---

## Phase 6: User Story 4 — Confirmação automática 24h e 2h antes (Priority: P1)

**Goal**: Plataforma envia mensagens de confirmação T-24h, T-2h, retry T-30min e escala T-15min para reduzir no-show. Processa respostas 1/2/3.

**Independent Test**: criar consulta às 14:00 de amanhã; virtual time T-24h → verificar evento `ConsultaConfirmacaoPendente` emitido com horário no header; simular resposta "1" → status muda para `confirmed`.

### Tests for User Story 4 ⚠️

- [ ] T096 [P] [US4] Test `tests/Feature/Agenda/ConfirmationFlowTest.php` cobrindo AC-6.4.1..6.4.6 (T-24h, T-2h, retry T-30min, T-15min escala manual, idempotência respostas, paciente sem canal) — clarify nº 6 / FR-018..024
- [ ] T097 [P] [US4] Test `tests/Feature/Agenda/AttendanceMarkingTest.php` cobrindo clarify nº 14: marcação manual realizada/no-show, auto-flag T+30min, janela 7d → `concluida_sem_registro`, reversão 48h, ability `appointment.revert_attendance_marking` após 48h

### Models + Events for US4

- [ ] T098 [P] [US4] Criar `app/Models/Agenda/ConfirmationDispatch.php` com casts (kind enum, via_ia bool, response_value enum), scope `BelongsToTenant`
- [ ] T099 [P] [US4] Criar evento `app/Events/Agenda/ConsultaConfirmacaoPendente.php` (Auditable; payload inclui `via_ia`, `horario_brasilia`, `tz_label` derivado de `IanaTimezoneCity::canonicalLabel()` — clarify nº 6/13)
- [ ] T100 [P] [US4] Criar evento `app/Events/Agenda/ConsultaConfirmada.php` (Auditable + ShouldBroadcast `tenant.X.agenda`)
- [ ] T101 [P] [US4] Criar evento `app/Events/Agenda/ConsultaCancelada.php` (Auditable + ShouldBroadcast)
- [ ] T102 [P] [US4] Criar evento `app/Events/Agenda/ConsultaPendenteContatoManual.php` (Auditable — clarify nº 6 retry T-15min)
- [ ] T103 [P] [US4] Criar evento `app/Events/Agenda/ConsultaRealizada.php` + `ConsultaNaoRealizada.php` + `ConsultaMarcacaoRevertida.php` (Auditable — clarify nº 14)

### Services for US4

- [ ] T104 [P] [US4] Criar `app/Services/Agenda/ConfirmationDispatcherService.php` com `dispatchPending()` (varre consultas elegíveis e emite `ConsultaConfirmacaoPendente` com kind correto: 24h/2h/retry_30min/15min_manual_escalation; checa `via_ia` flag se Fase 3 indica conversa ativa; cria `ConfirmationDispatch` row idempotent)
- [ ] T105 [US4] Criar `app/Services/Agenda/ConfirmationResponseProcessor.php` com `process(Appointment, value: 1|2|3, kind, received_at)` — emite `ConsultaConfirmada` (1) ou `ConsultaCancelada` motivo=paciente_via_chat (3) ou `ReagendamentoSolicitadoPeloPaciente` (2 — feature evento) — clarify nº 6 + idempotente FR-023
- [ ] T106 [P] [US4] Criar `app/Services/Agenda/AttendanceMarkingService.php` com `mark(Appointment, status, motivo, User $actor)` (valida janela 7d, ability `appointment.update`), `revert(Appointment, User $actor)` (valida 48h ou ability `appointment.revert_attendance_marking`); emite eventos `ConsultaRealizada`/`ConsultaNaoRealizada`/`ConsultaMarcacaoRevertida` — clarify nº 14

### Listeners for US4

- [ ] T107 [P] [US4] Criar `app/Listeners/Agenda/DispatchConfirmationToInbox.php` (escuta `ConsultaConfirmacaoPendente` → encaminha para `MessagingDispatcher` da Fase 3 com payload formatado); bind em `EventServiceProvider`

### Cron commands for US4

- [ ] T108 [US4] Criar `app/Console/Commands/AgendaDispatchConfirmationsCommand.php` (chama `ConfirmationDispatcherService::dispatchPending()`); confirmar registrado em T029
- [ ] T109 [US4] Criar `app/Console/Commands/AgendaAutoCloseStaleAppointmentsCommand.php` (escaneia `Appointment WHERE status IN ('scheduled','confirmed') AND starts_at < now() - 7d`; muda status `concluida_sem_registro`; audit warning) — clarify nº 14

### Endpoints for US4

- [ ] T110 [P] [US4] Criar `app/Http/Requests/Agenda/ConfirmResponseRequest.php` (response_value 1|2|3, dispatch_kind, received_at) — endpoint interno para Fase 3 ingestar
- [ ] T111 [P] [US4] Criar `app/Http/Requests/Agenda/MarkAttendanceRequest.php` (status realizada|nao_realizada, attendance_motivo opcional)
- [ ] T112 [US4] Adicionar métodos ao `AppointmentController.php`: `confirmResponse(...)`, `markAttendance(...)`, `revertAttendance(...)`
- [ ] T113 [US4] Editar `routes/api.php` adicionando: `POST /agenda/consultas/{id}/confirmar-resposta`, `POST /agenda/consultas/{id}/marcar-comparecimento`, `POST /agenda/consultas/{id}/reverter-comparecimento`

### Frontend for US4

- [ ] T114 [P] [US4] Criar `resources/js/components/agenda/AttendanceMarkButton.vue` (botão com 3 estados: marcar realizada / nao_realizada, mostrar "auto-flag" após T+30min, botão reverter dentro de 48h ou Admin com ability — clarify nº 14)
- [ ] T115 [P] [US4] Criar widget `resources/js/components/agenda/PendingAttendanceWidget.vue` no dashboard (lista consultas com `auto_flagged_at IS NOT NULL AND status IN ('scheduled','confirmed')` — clarify nº 14)

**Checkpoint**: US-6.4 verde — confirmação T-24h/T-2h/retry T-30min disparam, respostas 1/2/3 processadas, marcação de comparecimento + reversão funcionais.

---

## Phase 7: User Story 5 — Reagendamento e cancelamento via chat (Priority: P2)

**Goal**: Paciente reagenda/cancela via chat com IA Matricial usando contrato API estável (slots-disponiveis + reagendar/cancelar com idempotency_key + política de prazo).

**Independent Test**: chamar `GET /agenda/slots-disponiveis` + `POST /agenda/consultas/{id}/reagendar` (mesmo idempotency_key 2x → 1 reschedule); chamar `POST /agenda/consultas/{id}/cancelar` fora do prazo → 422 com `escalated_to_inbox=true`.

### Tests for User Story 5 ⚠️

- [ ] T116 [P] [US5] Test `tests/Feature/Agenda/RescheduleViaChatTest.php` cobrindo AC-6.5.1..6.5.7 (slots-disponiveis, idempotency_key, manter prof+tipo, limite 2 reagendamentos → 422 escalated, mantém status, reservar/release) — clarify nº 7
- [ ] T117 [P] [US5] Test `tests/Feature/Agenda/CancellationPolicyTest.php` cobrindo política tenant→tipo, "bloqueia + escala" via inbox, profissional irrestrito com motivo audit, override admin — clarify nº 3

### Events for US5

- [ ] T118 [P] [US5] Criar evento `app/Events/Agenda/CancelamentoSolicitadoForaDoPrazo.php` (Auditable — clarify nº 3 — payload: appointment_id, requested_by, window_hours, current_hours_until_appt)
- [ ] T119 [P] [US5] Criar evento `app/Events/Agenda/LimiteDeReagendamentoExcedido.php` (Auditable — clarify nº 7)
- [ ] T120 [P] [US5] Criar evento `app/Events/Agenda/ReagendamentoSolicitadoPeloPaciente.php` (Auditable — futura IA Matricial consome resposta "2")

### Services + Endpoints for US5

- [ ] T121 [US5] Estender `app/Services/Agenda/AppointmentService.php` com `cancel(Appointment, motivo, quem_cancelou, User $actor)` (valida policy tenant→tipo `min_cancellation_hours`; se paciente/IA + fora do prazo → 422 + emit `CancelamentoSolicitadoForaDoPrazo`; profissional irrestrito; FR-028/028a/028b/028c)
- [ ] T122 [P] [US5] Criar `app/Http/Requests/Agenda/CancelAppointmentRequest.php` (motivo, quem_cancelou enum, idempotency_key)
- [ ] T123 [US5] Adicionar método `AppointmentController::cancel(...)` que delega `AppointmentService::cancel(...)` e retorna 422 estruturado quando bloqueado fora do prazo
- [ ] T124 [US5] Editar `routes/api.php` adicionando `POST /agenda/consultas/{id}/cancelar`

### Listener for US5 (escala via inbox)

- [ ] T125 [P] [US5] Criar `app/Listeners/Agenda/EscalateCancellationOutsideWindowToInbox.php` (escuta `CancelamentoSolicitadoForaDoPrazo` → cria handoff/note na inbox da Fase 3); bind em `EventServiceProvider`
- [ ] T126 [P] [US5] Criar `app/Listeners/Agenda/EscalateRescheduleLimitExceededToInbox.php` (escuta `LimiteDeReagendamentoExcedido` → cria handoff na inbox); bind em `EventServiceProvider`

**Checkpoint**: US-6.5 verde — contrato API simples para IA Matricial estável, idempotency, política de prazo + escala via inbox.

---

## Phase 8: User Story 6 — Lista de espera automática (Priority: P2)

**Goal**: Lista de espera FIFO sequencial K=1 por (profissional × tipo); cancelamento abre vaga → primeiro candidato notificado com prazo 15min; expirado → próximo da fila.

**Independent Test**: 3 pacientes inscritos na lista de espera para Dr. X / Consulta; cancelar consulta existente → apenas o 1º notificado; aguardar 15min sem aceitar → 2º notificado; aceitar → consulta criada atomicamente.

### Tests for User Story 6 ⚠️

- [ ] T127 [P] [US6] Test `tests/Feature/Agenda/WaitlistSequentialTest.php` cobrindo AC-6.6.1..6.6.5 (K=1, prazo 15min, expira → próximo, alocação atômica, múltiplas listas permitidas) — clarify nº 8 / FR-030..033

### Models + Events for US6

- [ ] T128 [P] [US6] Criar `app/Models/Agenda/WaitlistEntry.php` com casts (status enum, position int), scope `BelongsToTenant`, scope `waiting()`, scope `notified()`, scope `nextInQueue(profId, typeId)`
- [ ] T129 [P] [US6] Criar evento `app/Events/Agenda/VagaAbertaNaListaDeEspera.php` (Auditable — payload: waitlist_entry_id, patient_id, slot_starts_at, professional_id, type_id, notification_window_minutes)

### Services for US6

- [ ] T130 [P] [US6] Criar `app/Services/Agenda/WaitlistService.php` com `enroll(Patient, Professional, AppointmentType)` (cria entry, calcula position FIFO), `notifyNext(Professional, AppointmentType, slot_starts_at)` (atômico — UPDATE ... WHERE status='waiting' ORDER BY position ASC LIMIT 1; emite `VagaAbertaNaListaDeEspera`), `accept(WaitlistEntry, idempotency_key)` (cria Appointment via `AppointmentService::create()`; muda status accepted), `expireNotifications()` (cron — marca status=expired, chama notifyNext do próximo)

### Listeners for US6

- [ ] T131 [P] [US6] Criar `app/Listeners/Agenda/OpenWaitlistOnCancellation.php` (escuta `ConsultaCancelada` → chama `WaitlistService::notifyNext(...)` para o slot liberado se houver elegível); bind em `EventServiceProvider`
- [ ] T132 [P] [US6] Criar `app/Listeners/Agenda/DispatchWaitlistOfferToInbox.php` (escuta `VagaAbertaNaListaDeEspera` → encaminha para Fase 3 enviar mensagem ao paciente notificado); bind em `EventServiceProvider`

### Cron command for US6

- [ ] T133 [US6] Criar `app/Console/Commands/AgendaExpireWaitlistNotificationsCommand.php` (chama `WaitlistService::expireNotifications()`); confirmar Schedule registrado em T029

### Endpoints for US6

- [ ] T134 [P] [US6] Criar `app/Http/Requests/Agenda/StoreWaitlistEntryRequest.php` + `AcceptWaitlistOfferRequest.php`
- [ ] T135 [P] [US6] Criar `app/Http/Resources/Agenda/WaitlistEntryResource.php`
- [ ] T136 [US6] Criar `app/Http/Controllers/Api/V1/Agenda/WaitlistController.php` (`index`, `store`, `destroy`, `accept`)
- [ ] T137 [US6] Editar `routes/api.php` adicionando rotas `/agenda/waitlist/*` (4 endpoints)

### Frontend for US6

- [ ] T138 [P] [US6] Criar `resources/js/pages/agenda/WaitlistPage.vue` (listagem por (prof × tipo) com filtro de status; CTA inscrever paciente)

**Checkpoint**: US-6.6 verde — FIFO sequencial K=1 funcionando, prazo 15min configurável por tenant, expira → próximo, alocação atômica.

---

## Phase 9: User Story 7 — Sincronização Google Calendar via sub-calendário tenant-scoped (Priority: P2)

**Goal**: Médico conecta Google → sub-calendário dedicado criado → consultas sincronizam bidirecionalmente; eventos externos no sub-cal viram `ExternalCalendarBusy`. Outlook DEFERRED → Fase 6.

**Independent Test**: profissional conecta Google staging; CRM cria consulta → evento aparece no sub-cal em <2min; criar evento manual no sub-cal → slot fica indisponível em `/agenda/slots-disponiveis`. Smoke 8.4 do quickstart.

### Tests for User Story 7 ⚠️

- [ ] T139 [P] [US7] Test `tests/Feature/Agenda/CalendarSyncOAuthTest.php` (fluxo connect → criar sub-calendário → emit token → refresh → revoke) — clarify nº 10/15 + R5
- [ ] T140 [P] [US7] Test `tests/Feature/Agenda/CrossTenantGoogleSyncTest.php` (eventos do sub-cal tenant A não aparecem em `events.list?calendarId=sub-cal-B`) — gate AC-6.7.11 / clarify nº 15
- [ ] T141 [P] [US7] Test `tests/Feature/Agenda/GoogleEventPayloadLgpdTest.php` (payload Google sem PII clínica: título fixo `Consulta — {nome}`, descrição genérica, sem CPF/convênio) — gate Princípio I / FR-038/038a
- [ ] T142 [P] [US7] Test `tests/Feature/Agenda/TimezoneRenderTest.php` (TZ tenant default, override profissional, TZ explícito no texto da mensagem) — clarify nº 13

### Models + Events for US7

- [ ] T143 [P] [US7] Criar `app/Models/Agenda/CalendarSyncAccount.php` com casts (encrypted access_token + refresh_token, status enum, expires_at datetime), scope `BelongsToTenant`, scope `connected()`, scope `needsWatchRenewal()` (watch_channel_expires_at < now() + 48h)
- [ ] T144 [P] [US7] Criar `app/Models/Agenda/CalendarSyncedEvent.php` (mapping appointment ↔ external_event_id), scope `BelongsToTenant`
- [ ] T145 [P] [US7] Criar `app/Models/Agenda/ExternalCalendarBusy.php` (clarify nº 10), scope `BelongsToTenant`, scope `coveringSlot(starts_at, ends_at)`
- [ ] T146 [P] [US7] Criar evento `app/Events/Agenda/CalendarioExternoSincronizado.php` (Auditable — payload: provider, status, last_sync_at)

### Services for US7

- [ ] T147 [US7] Criar `app/Services/Agenda/Calendar/GoogleCalendarOAuthService.php` com `connect(Professional)` (gera authorize_url + state CSRF), `handleCallback(code, state)` (troca code por token + cria sub-calendário via `GoogleSubCalendarManager` + persiste account), `tryRefresh(CalendarSyncAccount)` (R5 — auto-refresh antes de declarar falha), `disconnect(CalendarSyncAccount)` (revoga token + stop watch channel; eventos no Google permanecem)
- [ ] T148 [US7] Criar `app/Services/Agenda/Calendar/GoogleSubCalendarManager.php` com `createSubCalendar(CalendarSyncAccount, Tenant)` retorna `google_calendar_id`; `verifyExists(CalendarSyncAccount)` (chamado em reconexão — clarify nº 15 / R5)
- [ ] T149 [US7] Criar `app/Services/Agenda/Calendar/GoogleCalendarSyncService.php` com `syncAppointment(Appointment, action: create|update|delete)` (chama Google API com `calendarId={sub_cal_id}`, payload com título fixo, descrição genérica, IANA TZ — FR-038/038a) — gate test T141
- [ ] T150 [US7] Criar `app/Services/Agenda/Calendar/GoogleCalendarWatchService.php` com `subscribe(CalendarSyncAccount)` (chama `events.watch?calendarId={sub_cal_id}` com webhook URL + token HMAC; persiste `watch_channel_id`/`expires_at`), `unsubscribe(CalendarSyncAccount)` (chama `channels.stop`); `renewIfNearExpiry()` chamado pelo cron — R3
- [ ] T151 [US7] Criar `app/Services/Agenda/Calendar/GoogleCalendarPollFallbackService.php` com `pollAccount(CalendarSyncAccount)` (chama `events.list?calendarId={sub_cal_id}&updatedMin={last_polled_at}`, atualiza/cria `ExternalCalendarBusy`); chamado por cron 5min — R3

### Jobs for US7

- [ ] T152 [P] [US7] Criar `app/Jobs/Agenda/SyncAppointmentToGoogleCalendarJob.php` extends `TenantAwareJob` (Fase 2) — invocado via listener em `ConsultaCriada`/`ConsultaReagendada`/`ConsultaCancelada` para profissionais com `CalendarSyncAccount` connected
- [ ] T153 [P] [US7] Criar `app/Jobs/Agenda/ProcessGoogleCalendarPushJob.php` (recebe push notification → atualiza/cria `ExternalCalendarBusy` para o sub-cal afetado)
- [ ] T154 [P] [US7] Criar `app/Jobs/Agenda/PollGoogleCalendarFallbackJob.php` (executa `GoogleCalendarPollFallbackService::pollAccount(...)` por account)
- [ ] T155 [P] [US7] Criar `app/Jobs/Agenda/RenewGoogleWatchChannelJob.php` (chama `GoogleCalendarWatchService::renewIfNearExpiry()` por account)
- [ ] T156 [P] [US7] Criar `app/Jobs/Agenda/DetectGoogleSyncFailureJob.php` (R5 — wrapper invocado em qualquer chamada Google API; ao detectar 401/invalid_grant 2x em window 1min → marca account.status=disconnected + dispatch notification job)

### Listeners for US7

- [ ] T157 [P] [US7] Criar `app/Listeners/Agenda/SyncAppointmentToGoogleCalendar.php` (escuta `ConsultaCriada`/`ConsultaReagendada`/`ConsultaCancelada` → dispatch `SyncAppointmentToGoogleCalendarJob` para profissionais com sync ativa); bind em `EventServiceProvider`
- [ ] T158 [P] [US7] Criar `app/Listeners/Agenda/SendCalendarSyncDisconnectNotification.php` (escuta `CalendarioExternoSincronizado(status=disconnected)` → envia email `CalendarSyncDisconnectedMail` deduplicado via `last_disconnect_notified_at`); bind em `EventServiceProvider`

### Cron commands for US7

- [ ] T159 [US7] Criar `app/Console/Commands/AgendaGooglePollFallbackCommand.php` (lista todos accounts connected → dispatcha `PollGoogleCalendarFallbackJob` por account); confirmar Schedule registrado em T029
- [ ] T160 [US7] Criar `app/Console/Commands/AgendaGoogleRenewWatchChannelsCommand.php` (lista accounts com `needsWatchRenewal()` → dispatcha `RenewGoogleWatchChannelJob`); confirmar Schedule registrado em T029

### Mail for US7

- [ ] T161 [P] [US7] Criar `app/Mail/CalendarSyncDisconnectedMail.php` (Markdown template com link para `/agenda/sincronizacao` e CTA "Reconectar") — R5

### Webhook + Middleware for US7

- [ ] T162 [P] [US7] Criar `app/Http/Middleware/ValidateGoogleChannelToken.php` (valida HMAC `X-Goog-Channel-Token` contra `tenant_id+professional_id+channel_id` com `APP_KEY` — R3); registrar alias `validate.google.channel.token` em `bootstrap/app.php`
- [ ] T163 [US7] Criar `app/Http/Controllers/Api/V1/Agenda/GoogleCalendarWebhookController.php` (`store(channelId, Request)` — sempre retorna HTTP 200; valida token → dispatch `ProcessGoogleCalendarPushJob`; erros logados Sentry — R3)
- [ ] T164 [US7] Editar `routes/web.php` (não API — webhook externo) adicionando `POST /webhooks/google-calendar/{channelId}` com middleware `validate.google.channel.token`

### Endpoints OAuth + status for US7

- [ ] T165 [P] [US7] Criar `app/Http/Resources/Agenda/CalendarSyncAccountResource.php` (omite tokens encrypted)
- [ ] T166 [P] [US7] Criar `app/Policies/CalendarSyncAccountPolicy.php` (ability `calendar_sync.configure`); registrar em `AuthServiceProvider`
- [ ] T167 [US7] Criar `app/Http/Controllers/Api/V1/Agenda/CalendarSyncController.php` (`connect`, `callback`, `disconnect`, `show`)
- [ ] T168 [US7] Editar `routes/api.php` adicionando: `POST /agenda/calendar-sync/google/connect`, `GET /agenda/calendar-sync/google/callback`, `POST /agenda/calendar-sync/google/disconnect`, `GET /agenda/calendar-sync`

### Frontend for US7

- [ ] T169 [P] [US7] Criar `resources/js/pages/agenda/CalendarSyncPage.vue` (botão Conectar Google, status conectado/desconectado/erro, info do sub-calendário, painel "Outlook em breve — Fase 6" desabilitado — clarify nº 11)
- [ ] T170 [P] [US7] Criar `resources/js/components/agenda/CalendarSyncDisconnectBanner.vue` (banner persistente com CTAs Reconectar/Dispensar — R5); montar em `App.vue` para profissionais com `calendar_sync.configure`

**Checkpoint**: US-6.7 verde — Google Calendar bidirecional funcional via sub-cal tenant-scoped, push + polling fallback, OAuth UX completa, eventos passados/clínicos sem PII, smoke 8.4 do quickstart passa em staging.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Métricas, OpenAPI sync, postman, performance gates, smoke regression, atualização CLAUDE.md / README.

### Métricas + Observabilidade

- [ ] T171 [P] Validar `app/Support/AgendaMetrics.php` exposto em `/metrics` (Prometheus); rodar `curl /metrics | grep agenda_` em local; ajustar labels conforme convenções Fase 0/4 (`AuthMetrics`)
- [ ] T172 [P] Adicionar Sentry tags em jobs Google sync (`Sentry::configureScope(...) sync.provider, sync.account_id, sync.operation`)

### OpenAPI + API Docs

- [ ] T173 [P] Rodar `vendor/bin/sail artisan openapi:check` (Fase 3 já configurado) e validar drift 0 entre `contracts/openapi.yaml` (spec) e endpoints reais; corrigir divergências
- [ ] T174 [P] Gerar Postman collection `docs/api/agenda-fase5.postman_collection.json` a partir do OpenAPI (`vendor/bin/sail artisan postman:export agenda-fase5` ou manual)

### Documentação

- [ ] T175 [P] Atualizar `CLAUDE.md` adicionando seção "Agendamento (Fase 5) — Key Patterns" com 5-7 padrões críticos (slot UNIQUE composite gate, sub-calendário tenant-scoped, SlotReservation TTL, idempotency_key UUID v7, TZ resolução, payload Google LGPD, Outlook DEFERRED) — seguir convenção das fases 2/4
- [ ] T176 [P] Atualizar `README.md` adicionando módulo "Agendamento" no overview da plataforma (1 parágrafo) com link para `specs/005-agendamento-consultas/`

### Cleanup migration de Outlook (clarify nº 11)

- [ ] T177 [P] Validar que UI exibe placeholder "Microsoft Outlook (em breve — Fase 6)" desabilitado em `CalendarSyncPage.vue`; service rejeita `provider=outlook` com `provider_not_yet_supported`

### Performance gates

- [ ] T178 Test performance `tests/Feature/Agenda/SlotListPerformanceTest.php` validando p95 ≤ 300ms para `GET /agenda/slots-disponiveis` em janela 7d × 50 profissionais com cache aquecido (SC-009)
- [ ] T179 Rodar `vendor/bin/sail artisan test --filter=SlotConflictRaceTest` confirmando 50 requests paralelos → exatamente 1 sucesso (SC-008)

### Final regression + smoke gate

- [ ] T180 Rodar suite full `vendor/bin/sail artisan test --compact` confirmando 0 regressão nas Fases 0-4 + cobertura ≥ 70% no domínio `app/Services/Agenda/*` e `app/Models/Agenda/*` (relatório `coverage:html`)
- [ ] T181 Rodar `vendor/bin/sail bin pint --dirty --format agent` clean
- [ ] T182 Rodar `vendor/bin/sail npm run lint` clean
- [ ] T183 Smoke E2E pelo QA com conta Google real em staging (4 cenários do quickstart §§ 8.1-8.4); checklist documentado e arquivado
- [ ] T184 Validar 9 itens do "Constitucional" no Definition of Done do quickstart § 10 (LGPD test verde, multi-tenant tests verdes, TDD git log, métricas Prometheus, OAuth encrypted, CSP estendido, webhooks validados, rate limit ativo)
- [ ] T185 Atualizar header de status em `specs/005-agendamento-consultas/spec.md` para "Implementado — pronto para merge em main" + linhas de status no `MEMORY.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sem dependências — começa imediatamente
- **Foundational (Phase 2)**: Depende de Setup completo — **BLOQUEIA todas as US**
- **US1 (Phase 3)**: Depende de Foundational; pré-requisito para US3+ (slots precisam de schedule configurado), mas **independente em escopo de teste isolado** com fixtures
- **US2 (Phase 4)**: Depende de Foundational; **independente** de US1; pré-requisito de US3+ (consultas precisam de tipos)
- **US3 (Phase 5)**: Depende de Foundational + US1 + US2 (na prática — slots precisam de agenda+tipos); **MVP MÍNIMO entregável aqui**
- **US4 (Phase 6)**: Depende de US3 (consultas existem)
- **US5 (Phase 7)**: Depende de US3 (reagenda/cancela consultas existentes)
- **US6 (Phase 8)**: Depende de US3 (cancelar abre vaga; aceitar cria consulta) + US5 (cancela emite evento que listener consome)
- **US7 (Phase 9)**: Depende de US3 (consultas para sincronizar); **independente** de US4/US5/US6 em escopo de teste
- **Polish (Phase 10)**: Depende de US-alvo entregues (US1+US2+US3 = MVP mínimo; US1..US7 = entrega completa)

### User Story Dependencies (resumo)

```
Foundational
    ├── US1 (Schedule)         ──┐
    ├── US2 (Types)            ──┼──► US3 (Appointments) ──┬── US4 (Confirmations)
    │                            │                          ├── US5 (Reschedule/Cancel) ──► US6 (Waitlist)
    │                            │                          └── US7 (Google Sync)
    └── (US1, US2 independent paralelos)
```

### Within Each User Story

- Tests RED escritos PRIMEIRO (Princípio IV NON-NEGOTIABLE)
- Models antes de Services
- Services antes de Controllers/Endpoints
- Endpoints antes de Frontend
- Cron commands criados junto com Service correspondente
- Listeners criados após Events (mesma fase)

---

## Parallel Opportunities

### Setup (Phase 1)

```bash
# T002, T003, T004, T005 podem rodar em paralelo (arquivos distintos)
Task: "Adicionar deps npm FullCalendar v7 + Luxon"
Task: "Adicionar bloco google_calendar em config/services.php"
Task: "Estender config/csp.php"
Task: "Atualizar .env.example"
```

### Foundational (Phase 2) — após T020 (migrate)

```bash
# Migrations (T006-T019) NÃO paralelizáveis — ordem importa
# Após migrate verde:
Task: "Criar IanaTimezoneCity (T023)"
Task: "Criar AgendaMetrics (T024)"
Task: "Criar TimezoneResolverService (T025)"
Task: "Criar EnsureAgendaModuleEnabled middleware (T028)"
Task: "Criar factories (T030)"
Task: "Criar CrossTenantAgendaTest 🔴 RED (T031)"
```

### User Stories em paralelo (após Phase 2)

Times com 3+ devs podem distribuir:
- **Dev A**: US1 (Phase 3) → US3 (Phase 5)
- **Dev B**: US2 (Phase 4) → US4 (Phase 6)
- **Dev C**: US7 (Phase 9 backend Google sync — independente até precisar testar com consultas reais de US3)

### Within US3 (após T060-T064 RED tests + T065-T067 models)

```bash
Task: "Criar SlotGeneratorService (T070)"
Task: "Criar SlotReservationService (T071)"
Task: "Criar Listener MoveCardToAgendadoColumn (T073)"
Task: "Criar Listener BroadcastAppointmentChange (T074)"
Task: "Criar StoreAppointmentRequest (T076)"
Task: "Criar ListAvailableSlotsRequest (T080)"
Task: "Criar AppointmentResource + SlotResource (T081)"
Task: "Criar useTimezoneRenderer composable (T086)"
Task: "Criar useSlotReservation composable (T087)"
Task: "Criar PatientAutocomplete (T090)"
Task: "Criar AppointmentFormModal (T091)"
Task: "Criar RescheduleConfirmModal (T092)"
Task: "Criar SlotPicker (T093)"
```

---

## Implementation Strategy

### MVP MÍNIMO (Setup + Foundational + US1 + US2 + US3)

1. Phase 1: Setup (T001-T005)
2. Phase 2: Foundational (T006-T031) — **BLOQUEIA tudo**
3. Phase 3: US1 — Schedule (T032-T049)
4. Phase 4: US2 — Types (T050-T059)
5. Phase 5: US3 — Appointments com drag-and-drop (T060-T095)
6. **STOP & VALIDATE**: agenda operacional via painel humano sem confirmação automática
7. Deploy/demo MVP em staging com 1 clínica beta

### Incremental delivery

- **MVP** entregue → adicionar **US4** (confirmação automática) → outcome no-show ↓ → demo
- → adicionar **US5** (reagenda/cancela via chat IA) → outcome reduzir overhead atendente → demo
- → adicionar **US6** (lista de espera) → outcome ocupação ↑ → demo
- → adicionar **US7** (Google sync) → outcome adoção médico ↑ → demo

### Parallel Team Strategy (3 devs)

- Sprint 1 (Setup + Foundational): time inteiro pareando
- Sprint 2-3:
  - Dev A: US1 + US2
  - Dev B: US3 (parallel com US1+US2 do Dev A — usa fixtures iniciais)
  - Dev C: setup OAuth Google + research integration de US7 (mock externo)
- Sprint 4: US4 (Dev A), US5 (Dev B), US6 (Dev A após US5), US7 conclusão (Dev C)
- Sprint 5: Polish + smoke + DoD

### Total estimate

- **185 tasks** numeradas T001-T185
- **10 fases**
- **MVP mínimo**: ~95 tasks (Phases 1-5)
- **Entrega completa**: 185 tasks (Phases 1-10)
- Estimativa de esforço: 4-6 sprints com 3 devs (paralelizando US correctamente)

---

## Notes

- [P] tasks = arquivos distintos, sem dependência pendente — paralelizar agressivamente
- [Story] label maps task to specific user story for traceability ↔ spec.md ACs
- Each user story should be independently completable and testable (Princípio IV — TDD enforced)
- Verify tests fail (🔴 RED) before implementing (Princípio IV gate)
- Commit after each task or logical group (convenção: `[Spec Kit] feat(implement TXXX): Fase 5 Lote N — descrição`)
- Stop at any checkpoint to validate story independently
- **Cross-cutting tests obrigatórios** (Phase 2 + Phase 9 + Polish): `CrossTenantAgendaTest`, `CrossTenantGoogleSyncTest`, `GoogleEventPayloadLgpdTest`, `SlotConflictRaceTest`, `TimezoneRenderTest` — gates de Princípio I/II + SC-008
- Avoid: tarefas vagas, conflitos no mesmo arquivo, dependências cross-story que quebram independência
