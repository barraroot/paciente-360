# Phase 1 — Data Model: Fase 5 Agendamento de Consultas

**Feature**: 005-agendamento-consultas
**Date**: 2026-05-13
**Status**: ✅ Concluído. 14 entidades novas + 2 extensões (`tenants.settings`, `professionals.timezone`).

---

## ER Diagram (high-level)

```
                 +-------------+         +----------------+
                 |   tenants   |         |    users       |
                 +-------------+         +----------------+
                       ^                         ^
                       | tenant_id               | created_by
                       |                         |
+---------+    +-------+--------+   +------------+---------+
| Patient |    | Professional   |   |  AuditLog (Fase 0)   |
| (Fase 2)|    | + timezone     |   +----------------------+
+----+----+    +---+------------+
     |             |
     | patient_id  | professional_id
     |             |
     v             v
+-----------+    +----------------------+
|Appointment+--->|  AppointmentType     |
|           |    +----------------------+
+--+-+------+         ^      ^
   | |                |      |
   | +-------------+  |      |
   |               |  |      |
   | type_id       |  | M2M  |
   |               +--+------+ AppointmentTypeProfessional
   |                          |
   v                          v
+----------------------+    +-----------------------+
|AppointmentReschedule |    |ProfessionalSchedule   |
+----------------------+    +-----------------------+
                                       ^
                                       | cascades blocks
+---------------------+        +-------+----------------+
|  SlotReservation    |        |   ScheduleException    |
|  (TTL pessimista)   |        |   + created_by_user_id |
+---------------------+        +------------------------+

+----------------------+    +----------------------+
| WaitlistEntry (FIFO) |    |ConfirmationDispatch  |
+----------------------+    |+ via_ia, kind        |
                            +----------------------+
                                       ^
                                       | belongsTo
                                       | (appointment_id)
                                       |

+---------------------------------+    +---------------------------------+
|     CalendarSyncAccount         |    |     CalendarSyncedEvent         |
| UNIQUE(tenant_id, prof_id)      |    | (mapping appt ↔ ext_event_id)   |
| google_calendar_id (sub-cal)    +--->|                                 |
| watch_channel_id, expires_at    |    +---------------------------------+
+---------------------------------+
                                       +---------------------------------+
                                       |     ExternalCalendarBusy        |
                                       | (clarify nº 10 — bloqueio ext)  |
                                       +---------------------------------+
```

---

## 1. AppointmentType

Tipo de atendimento configurado pelo Admin Clínica (clarify nº 4).

| Coluna | Tipo | Constraint | Origem |
|---|---|---|---|
| `id` | UUID v7 | PK | — |
| `tenant_id` | UUID | FK tenants(id), NOT NULL, INDEX | Fase 0 |
| `nome` | VARCHAR(100) | NOT NULL | spec |
| `slug` | VARCHAR(100) | NOT NULL, UNIQUE(tenant_id, slug) | derivado |
| `duration_minutes` | SMALLINT | NOT NULL, CHECK > 0 AND <= 600 | clarify nº 1 |
| `buffer_minutes` | SMALLINT | NOT NULL DEFAULT 0, CHECK >= 0 AND <= 120 | clarify nº 1 |
| `valor_particular` | DECIMAL(10,2) | NOT NULL DEFAULT 0 | clarify nº 4 |
| `valor_convenio_default` | DECIMAL(10,2) | NULLABLE | clarify nº 4 |
| `min_cancellation_hours` | SMALLINT | NULLABLE (herda tenant), CHECK >= 0 | clarify nº 3 |
| `cor` | VARCHAR(7) | DEFAULT '#3B82F6', CHECK match `#[0-9A-Fa-f]{6}` | spec |
| `descricao` | TEXT | NULLABLE | spec |
| `intent_ia` | VARCHAR(100) | NULLABLE | FR-008 |
| `is_active` | BOOLEAN | NOT NULL DEFAULT TRUE | FR-007 |
| `created_at`, `updated_at` | TIMESTAMPTZ | | std |

**Indexes**: `(tenant_id, is_active)`, `(tenant_id, slug)` UNIQUE.

**Soft delete**: NÃO. Inativação via `is_active=false` preserva FK integrity para appointments existentes (FR-007).

---

## 2. AppointmentTypeProfessional (pivot M2M)

| Coluna | Tipo | Constraint |
|---|---|---|
| `appointment_type_id` | UUID | FK, NOT NULL |
| `professional_id` | UUID | FK, NOT NULL |
| `tenant_id` | UUID | NOT NULL (denormalizado, gate Princípio II) |
| `created_at` | TIMESTAMPTZ | |

**PK composite**: `(appointment_type_id, professional_id)`.

---

## 3. ProfessionalSchedule

Horários de trabalho recorrentes por dia da semana (clarify nº 1, nº 5).

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL, INDEX |
| `professional_id` | UUID | FK, NOT NULL |
| `day_of_week` | SMALLINT | NOT NULL, CHECK BETWEEN 1 AND 7 (1=Mon, 7=Sun) |
| `blocks` | JSONB | NOT NULL — array `[{start: "08:00", end: "12:00"}, {start: "13:30", end: "18:00"}]` |
| `effective_from` | DATE | NOT NULL DEFAULT CURRENT_DATE |
| `effective_until` | DATE | NULLABLE (open-ended) |
| `created_by_user_id` | UUID | FK users, NOT NULL |
| `created_at`, `updated_at` | TIMESTAMPTZ | |

**Indexes**: `(tenant_id, professional_id, day_of_week, effective_from)` UNIQUE — uma agenda por dia por janela temporal.

**Validação JSONB**: `blocks` valida em service layer — array não-vazio, blocks ordenados, sem sobreposição, formato HH:MM 24h.

---

## 4. ScheduleException

Bloqueios pontuais (férias, feriados, eventos) (clarify nº 5).

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL, INDEX |
| `professional_id` | UUID | FK, NOT NULL |
| `starts_at` | TIMESTAMPTZ | NOT NULL |
| `ends_at` | TIMESTAMPTZ | NOT NULL, CHECK > starts_at |
| `reason` | VARCHAR(255) | NULLABLE |
| `created_by_user_id` | UUID | FK users, NOT NULL |
| `created_at`, `updated_at` | TIMESTAMPTZ | |

**Indexes**: `(tenant_id, professional_id, starts_at, ends_at)` GiST com `tsrange` para overlap queries.

**Behavior**: ao criar `ScheduleException` com período sobreposto a `Appointment` ativos, listener cancela esses appointments com `quem_cancelou='sistema'`, `motivo='schedule_exception'` (FR-028c).

---

## 5. Appointment

Consulta agendada (entidade central).

| Coluna | Tipo | Constraint | Origem |
|---|---|---|---|
| `id` | UUID v7 | PK | — |
| `tenant_id` | UUID | FK, NOT NULL, INDEX | Fase 0 |
| `idempotency_key` | UUID | UNIQUE NULLABLE | R8 |
| `patient_id` | UUID | FK patients, NOT NULL | Fase 2 |
| `professional_id` | UUID | FK professionals, NOT NULL | Fase 2 |
| `appointment_type_id` | UUID | FK appointment_types, NOT NULL | clarify nº 1 |
| `starts_at` | TIMESTAMPTZ | NOT NULL | UTC (clarify nº 13) |
| `ends_at` | TIMESTAMPTZ | NOT NULL, CHECK > starts_at | derivado de duration+buffer |
| `status` | VARCHAR(32) | NOT NULL DEFAULT 'scheduled' | enum (ver state machine abaixo) |
| `channel_origin` | VARCHAR(32) | NOT NULL | enum: `painel \| ia \| autoatendimento` |
| `created_by_user_id` | UUID | FK users, NULLABLE (NULL se IA criou) | clarify nº 5 |
| `valor_aplicado` | DECIMAL(10,2) | NOT NULL | clarify nº 4 (snapshot) |
| `valor_override_motivo` | TEXT | NULLABLE | clarify nº 4 |
| `override_block` | BOOLEAN | NOT NULL DEFAULT FALSE | clarify nº 5 |
| `override_motivo` | TEXT | NULLABLE | clarify nº 5 |
| `motivo_cancelamento` | TEXT | NULLABLE | FR-027 |
| `quem_cancelou` | VARCHAR(32) | NULLABLE | enum: `paciente \| atendente \| profissional \| sistema` |
| `canceled_at` | TIMESTAMPTZ | NULLABLE | |
| `confirmed_at` | TIMESTAMPTZ | NULLABLE | FR-020 |
| `attendance_marked_at` | TIMESTAMPTZ | NULLABLE | clarify nº 14 |
| `attendance_marked_by_user_id` | UUID | FK users, NULLABLE | clarify nº 14 |
| `attendance_motivo` | TEXT | NULLABLE | clarify nº 14 |
| `auto_flagged_at` | TIMESTAMPTZ | NULLABLE | clarify nº 14 (T+30min) |
| `notes` | TEXT (encrypted via cast) | NULLABLE | LGPD/Princípio I |
| `created_at`, `updated_at` | TIMESTAMPTZ | | |

### Indexes

- **`(tenant_id, professional_id, starts_at)` UNIQUE PARTIAL WHERE status IN ('scheduled', 'confirmed')** — gate atômico de race (FR-011a, SC-008).
- `(tenant_id, patient_id, starts_at DESC)` — listagem de consultas do paciente.
- `(tenant_id, status, starts_at)` — varredura de cron jobs (auto-close, dispatch confirmações).
- `(tenant_id, professional_id, starts_at)` — listagem agenda do profissional.
- `idempotency_key` UNIQUE.

### State Machine (`status`)

```
   ┌───────────────┐
   │   scheduled   │ ◄── created via POST /agenda/consultas
   └───────┬───────┘
           │
           ├──→ confirmed (paciente respondeu "1" → ConsultaConfirmada)
           │       │
           │       ├──→ realizada (atendente marca + ConsultaRealizada)
           │       └──→ nao_realizada (atendente marca + ConsultaNaoRealizada)
           │
           ├──→ canceled (quem_cancelou + motivo + ConsultaCancelada)
           │
           ├──→ realizada (idem confirmed → realizada)
           ├──→ nao_realizada (idem confirmed → nao_realizada)
           │
           └──→ concluida_sem_registro (cron T+7d sem marcação) [terminal]

   reschedule (POST /reagendar): NÃO muda status; cria AppointmentReschedule + atualiza starts_at/ends_at
```

Transições inválidas (`canceled → confirmed`, `realizada → scheduled`) **rejeitadas em service layer** + `assert` em test obrigatório.

---

## 6. AppointmentReschedule

Histórico de reagendamentos (clarify nº 7 — base para enforcement de limite via `count`).

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `appointment_id` | UUID | FK appointments, NOT NULL |
| `idempotency_key` | UUID | UNIQUE NULLABLE |
| `starts_at_anterior` | TIMESTAMPTZ | NOT NULL |
| `starts_at_novo` | TIMESTAMPTZ | NOT NULL |
| `quem_solicitou` | VARCHAR(32) | NOT NULL — enum: `paciente \| atendente \| profissional \| ia` |
| `motivo` | TEXT | NULLABLE |
| `created_at` | TIMESTAMPTZ | NOT NULL |

**Indexes**: `(appointment_id, created_at DESC)`, `(tenant_id, created_at)`.

---

## 7. SlotReservation (clarify nº 2)

Reserva pessimista soft de slot durante criação/reagendamento.

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `professional_id` | UUID | FK, NOT NULL |
| `appointment_type_id` | UUID | FK appointment_types, NOT NULL |
| `starts_at` | TIMESTAMPTZ | NOT NULL |
| `holder_type` | VARCHAR(8) | NOT NULL — enum: `user \| ia` |
| `holder_id` | UUID | NOT NULL — user_id ou conversation_id |
| `idempotency_key` | UUID | UNIQUE NULLABLE |
| `acquired_at` | TIMESTAMPTZ | NOT NULL DEFAULT now() |
| `expires_at` | TIMESTAMPTZ | NOT NULL |
| `released_at` | TIMESTAMPTZ | NULLABLE |
| `release_reason` | VARCHAR(16) | NULLABLE — enum: `committed \| expired \| canceled` |

### Indexes

- **`(tenant_id, professional_id, starts_at) UNIQUE PARTIAL WHERE released_at IS NULL`** — gate de "uma reserva ativa por slot".
- **`expires_at PARTIAL WHERE released_at IS NULL`** — eficiente para cron `cleanup-expired-reservations`.
- `(holder_type, holder_id)` — histórico do user/IA.

### Behavior

- INSERT em conflito → 409 + `release_reason` do holder atual.
- Commit → `released_at=now(), release_reason='committed'`.
- TTL: 5 min (user) / 2 min (ia) — configurável via `tenant.settings`.

---

## 8. WaitlistEntry (clarify nº 8)

Lista de espera FIFO sequencial K=1.

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `patient_id` | UUID | FK, NOT NULL |
| `professional_id` | UUID | FK, NOT NULL |
| `appointment_type_id` | UUID | FK, NOT NULL |
| `status` | VARCHAR(16) | NOT NULL DEFAULT 'waiting' — enum: `waiting \| notified \| accepted \| expired \| canceled` |
| `position` | INTEGER | NOT NULL — derivado FIFO |
| `notified_at` | TIMESTAMPTZ | NULLABLE |
| `notified_for_slot_starts_at` | TIMESTAMPTZ | NULLABLE — slot oferecido na notificação |
| `expires_at` | TIMESTAMPTZ | NULLABLE — `notified_at + tenant.waitlist_confirmation_minutes` |
| `accepted_appointment_id` | UUID | FK appointments, NULLABLE |
| `created_at`, `updated_at` | TIMESTAMPTZ | |

### Indexes

- `(tenant_id, professional_id, appointment_type_id, status, position)` — query "próximo da fila".
- `(status, expires_at) PARTIAL WHERE status='notified'` — cron `expire-waitlist-notifications`.
- `(tenant_id, patient_id)` — query "minhas posições".

---

## 9. ConfirmationDispatch (clarify nº 6)

Registro do envio de cada disparo de confirmação automática.

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `appointment_id` | UUID | FK, NOT NULL |
| `kind` | VARCHAR(32) | NOT NULL — enum: `24h \| 2h \| retry_30min \| 15min_manual_escalation` |
| `via_ia` | BOOLEAN | NOT NULL DEFAULT FALSE |
| `dispatched_at` | TIMESTAMPTZ | NOT NULL |
| `response_received_at` | TIMESTAMPTZ | NULLABLE |
| `response_value` | VARCHAR(8) | NULLABLE — `1 \| 2 \| 3` |
| `status` | VARCHAR(16) | NOT NULL — enum: `dispatched \| confirmed \| reschedule_requested \| canceled \| pending_manual` |

**Indexes**: `(appointment_id, kind)` UNIQUE — uma de cada kind por consulta. `(tenant_id, status, dispatched_at)`.

---

## 10. CalendarSyncAccount (clarify nº 10, nº 15)

Vínculo OAuth do profissional com Google Calendar (Outlook DEFERRED Fase 6).

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `professional_id` | UUID | FK, NOT NULL |
| `provider` | VARCHAR(16) | NOT NULL — enum `google \| outlook` (outlook DEFERRED) |
| `provider_user_id` | VARCHAR(128) | NOT NULL — Google account ID (sub claim) |
| `provider_email` | VARCHAR(255) | NOT NULL — apenas para exibir no painel "Conectado como X@gmail.com" |
| `encrypted_access_token` | TEXT | NOT NULL — `Crypt::encryptString` |
| `encrypted_refresh_token` | TEXT | NULLABLE — Google nem sempre retorna refresh em re-auth |
| `expires_at` | TIMESTAMPTZ | NULLABLE — access_token expiration |
| `google_calendar_id` | VARCHAR(255) | NULLABLE — ID do sub-calendário criado (clarify nº 15) |
| `google_calendar_name_seen` | VARCHAR(255) | NULLABLE — última versão observada do nome (audit) |
| `watch_channel_id` | UUID | NULLABLE — ID do canal de push (R3) |
| `watch_channel_resource_id` | VARCHAR(255) | NULLABLE — Google resourceId |
| `watch_channel_expires_at` | TIMESTAMPTZ | NULLABLE — TTL Google ~7d |
| `last_polled_at` | TIMESTAMPTZ | NULLABLE — fallback polling tracker |
| `last_synced_at` | TIMESTAMPTZ | NULLABLE |
| `last_disconnect_at` | TIMESTAMPTZ | NULLABLE | R5 |
| `last_disconnect_notified_at` | TIMESTAMPTZ | NULLABLE — dedup email | R5 |
| `last_reconnect_at` | TIMESTAMPTZ | NULLABLE | R5 |
| `status` | VARCHAR(16) | NOT NULL — enum: `connected \| disconnected \| error` |
| `created_at`, `updated_at` | TIMESTAMPTZ | |

### Indexes

- **`(tenant_id, professional_id) UNIQUE`** — gate de "uma conexão por par tenant×prof" (clarify nº 15, FR-036c).
- `(provider, watch_channel_id)` UNIQUE PARTIAL WHERE watch_channel_id IS NOT NULL — lookup do webhook.
- `(provider, status)` — query "todas conexões disconnected" para painel.
- `(watch_channel_expires_at) PARTIAL WHERE status='connected'` — cron renovar canais.

### Validation rules

- Provider `outlook` rejeitado em service layer com `provider_not_yet_supported` (clarify nº 11).
- Connect só permitido se `professional_id` pertence ao `tenant_id` autenticado (Princípio II).

---

## 11. CalendarSyncedEvent

Mapping entre `Appointment` interno e `external_event_id` (Google).

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `appointment_id` | UUID | FK, NOT NULL |
| `calendar_sync_account_id` | UUID | FK, NOT NULL |
| `external_event_id` | VARCHAR(255) | NOT NULL — Google event ID |
| `last_synced_at` | TIMESTAMPTZ | NOT NULL |
| `etag` | VARCHAR(128) | NULLABLE — Google ETag para deduplicação |
| `created_at`, `updated_at` | TIMESTAMPTZ | |

### Indexes

- `(appointment_id, calendar_sync_account_id)` UNIQUE — um vínculo por (consulta × conta).
- `(calendar_sync_account_id, external_event_id)` — lookup quando push notification chega.

---

## 12. ExternalCalendarBusy (clarify nº 10)

Bloqueio de slot derivado de evento externo no sub-calendário Google. NÃO vira `Appointment`.

| Coluna | Tipo | Constraint |
|---|---|---|
| `id` | UUID v7 | PK |
| `tenant_id` | UUID | FK, NOT NULL |
| `professional_id` | UUID | FK, NOT NULL |
| `calendar_sync_account_id` | UUID | FK, NOT NULL |
| `external_event_id` | VARCHAR(255) | NOT NULL |
| `starts_at` | TIMESTAMPTZ | NOT NULL |
| `ends_at` | TIMESTAMPTZ | NOT NULL |
| `provider` | VARCHAR(16) | NOT NULL — `google \| outlook` |
| `summary_redacted` | VARCHAR(255) | NULLABLE — sanitizado, apenas para debug interno |
| `synced_at` | TIMESTAMPTZ | NOT NULL |

### Indexes

- `(calendar_sync_account_id, external_event_id)` UNIQUE — gate de deduplicação.
- `(tenant_id, professional_id, starts_at, ends_at)` GiST tsrange — query "esse slot está ocupado externamente?".

### Behavior

- Criado/atualizado pelo `ProcessGoogleCalendarPushJob` ou `PollGoogleCalendarFallbackJob` quando detecta evento externo.
- Removido se evento externo é deletado no Google.
- **Nunca** exibido como `Appointment` na UI — apenas `Evento externo` no painel da agenda.

---

## 13. Extensões em entidades existentes

### `tenants.settings` (JSONB existente Fase 0)

Merge das chaves novas:

```json
{
  "agenda": {
    "min_cancellation_hours": 4,
    "max_reschedules_per_appointment": 2,
    "waitlist_confirmation_minutes": 15,
    "calendar_sync_window_days": 60,
    "slot_reservation_ttl_user_minutes": 5,
    "slot_reservation_ttl_ia_minutes": 2,
    "auto_close_stale_appointments_days": 7,
    "attendance_revert_window_hours": 48
  }
}
```

Migration **`2026_05_14_000014_extend_tenants_settings_with_agenda_keys.php`** faz `UPDATE tenants SET settings = jsonb_set(settings, '{agenda}', $defaults)` em DDL backfill.

### `professionals.timezone` (clarify nº 13)

| Coluna | Tipo | Constraint |
|---|---|---|
| `timezone` | VARCHAR(64) | NULLABLE — IANA string `America/Sao_Paulo` |

Migration **`2026_05_14_000013_add_timezone_to_professionals_table.php`** adiciona coluna; default NULL (herda `tenant.timezone`).

---

## 14. Eventos de Domínio (não persistidos como tabela — emit + audit)

Já listados na § "Eventos de Domínio Emitidos" do spec. Cada um:
- Implementa `Auditable` (Fase 0) → grava em `audit_logs` automaticamente.
- Implementa `ShouldBroadcast` quando relevante → emite em canal Reverb `tenant.{tenant_id}.agenda` (apenas eventos UI-relevantes: `ConsultaCriada`, `ConsultaCancelada`, `ConsultaReagendada`).
- Listener `RegistraEventoTimelineListener` (Fase 2) projeta automaticamente para `eventos_timeline` quando `auditableModel()` é `Appointment` → timeline do paciente.

---

## State Transitions críticas

### `Appointment.status`

```
scheduled ───────────────────────────────────────► canceled
   │                                                  ▲
   │                                                  │
   ├──► confirmed ───────────────────────────────────►┤
   │       │                                          │
   │       ├──► realizada ────────────────────────────┤
   │       └──► nao_realizada ───────────────────────►┤
   │                                                  │
   ├──► realizada (skip confirmed)                    │
   ├──► nao_realizada (skip confirmed)                │
   │                                                  │
   └──► concluida_sem_registro (cron 7d) [terminal]   │
```

Reagendamento NÃO muda `status` — apenas `starts_at`/`ends_at` + insert em `appointment_reschedules`.

### `WaitlistEntry.status`

```
waiting ──► notified ──► accepted (vira Appointment)
                  │
                  └──► expired (cron 15min) ──► próximo da fila notified
                  │
                  └──► canceled (paciente desiste)
```

### `SlotReservation.release_reason`

```
acquired (released_at IS NULL) ──► committed (form submetido)
                            │
                            ├──► expired (cron TTL atingido)
                            │
                            └──► canceled (form fechado)
```

### `CalendarSyncAccount.status`

```
connected ──► error (transient: 5xx Google) ──► connected (next sync)
       │
       └──► disconnected (revoke / refresh failed) ──► connected (reconectar 1-clique)
```

---

## Validation rules (cross-cutting)

1. **Tenant scoping**: TODA query Eloquent usa `BelongsToTenant` global scope (Fase 0). Tabelas pivot (`AppointmentTypeProfessional`) carregam `tenant_id` denormalizado.
2. **Soft delete**: NÃO usado nesta fase. Inativação via flag (`AppointmentType.is_active`); cancelamento via `Appointment.status='canceled'`. Auditoria preservada.
3. **Cascade deletion**: nunca `ON DELETE CASCADE` para entidades de agenda — deletar profissional / paciente é `RESTRICT` (FK ON DELETE RESTRICT). Anonimização (LGPD direito ao esquecimento) via `Anonymizer` service que substitui campos PII sem deletar a row.
4. **Encrypted columns**: `Appointment.notes`, `CalendarSyncAccount.encrypted_access_token`, `encrypted_refresh_token` via Eloquent cast `encrypted` (Laravel default).
5. **JSONB validation**: `ProfessionalSchedule.blocks` validado em service layer (não em DB); test obrigatório `tests/Unit/Agenda/ProfessionalScheduleBlocksValidatorTest.php`.

---

## Migrations summary

| # | Arquivo | Descrição |
|---|---|---|
| 01 | `create_appointment_types_table` | Entidade 1 |
| 02 | `create_appointment_type_professional_table` | Pivot M2M (entidade 2) |
| 03 | `create_professional_schedules_table` | Entidade 3 |
| 04 | `create_schedule_exceptions_table` | Entidade 4 |
| 05 | `create_appointments_table` | Entidade 5 + UNIQUE composite |
| 06 | `create_appointment_reschedules_table` | Entidade 6 |
| 07 | `create_slot_reservations_table` | Entidade 7 + partial unique |
| 08 | `create_waitlist_entries_table` | Entidade 8 |
| 09 | `create_confirmation_dispatches_table` | Entidade 9 |
| 10 | `create_calendar_sync_accounts_table` | Entidade 10 + UNIQUE composite |
| 11 | `create_calendar_synced_events_table` | Entidade 11 |
| 12 | `create_external_calendar_busy_table` | Entidade 12 |
| 13 | `add_timezone_to_professionals_table` | Extensão clarify nº 13 |
| 14 | `extend_tenants_settings_with_agenda_keys` | Backfill `tenant.settings.agenda.*` |

**Total**: 14 migrations. Todas aditivas, idempotentes, reversíveis (`down()` faz `dropIfExists`/`dropColumn` correspondente).

---

## Storage estimates (180-day projection at MVP scale)

- 100 tenants × 10 profissionais × 50 consultas/dia × 180 dias = **9M rows em `appointments`** → tabela ~3 GB com índices.
- ~5k `slot_reservations`/dia × 180 = ~900k rows ativas + cleanup → **rotação contínua**, ~50 MB ativo.
- ~50 `waitlist_entries` × 100 tenants ativas em qualquer momento → ~5k rows ativas.
- ~200 `calendar_sync_accounts` total no MVP.
- ~300k `external_calendar_busy` (60d janela × 5/dia × 1000 prof) ativos.

PostgreSQL 18 lida tranquilo. **Particionamento de `appointments` por `starts_at`** considerado mas DEFERRED para Fase 7+ (volume MVP não exige).
