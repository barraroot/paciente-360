# Smoke E2E — Fase 5 (Agendamento de Consultas)

**Branch**: `005-agendamento-consultas`
**Build alvo**: staging
**Estimativa**: 2-3 horas
**Pré-requisitos QA**: acesso staging admin, conta Google teste, Postman, navegador moderno, acesso `vendor/bin/sail` (ou equivalente em staging) para tinker se necessário

---

## Sumário

| § | Sessão | Tempo |
|---|---|---|
| 1 | Pré-requisitos de ambiente | 20 min |
| 2 | Smoke US-6.1 (config agenda + bloqueios) | 15 min |
| 3 | Smoke US-6.2 (tipos de atendimento) | 10 min |
| 4 | Smoke US-6.3 (criar consulta + drag-and-drop) | 25 min |
| 5 | Smoke US-6.4 (confirmação automática + comparecimento) | 30 min |
| 6 | Smoke US-6.5 (reagendamento/cancelamento via chat) | 20 min |
| 7 | Smoke US-6.6 (lista de espera FIFO) | 15 min |
| 8 | Smoke US-6.7 (Google Calendar sync) | 30 min |
| 9 | **Gate cross-tenant Google leak** (clarify nº 15) | 20 min |
| 10 | Sign-off final | 5 min |

---

## 1. Pré-requisitos de ambiente

### 1.1 — Variáveis de ambiente (staging `.env`)

Confirmar com DevOps que estão setadas:

- [ ] `GOOGLE_CALENDAR_CLIENT_ID` (criar OAuth Client em GCP — quickstart §2.4)
- [ ] `GOOGLE_CALENDAR_CLIENT_SECRET`
- [ ] `GOOGLE_CALENDAR_REDIRECT_URI` (ex.: `https://api-staging.paciente360.com.br/api/v1/agenda/calendar-sync/google/callback`)
- [ ] `GOOGLE_CALENDAR_WEBHOOK_BASE_URL` (ex.: `https://api-staging.paciente360.com.br/webhooks/google-calendar`) — **HTTPS público obrigatório**
- [ ] `AGENDA_CALENDAR_SYNC_WINDOW_DAYS=60`
- [ ] `AGENDA_WATCH_CHANNEL_RENEW_HOURS=48`
- [ ] `CSP_GOOGLE_HOSTS="https://accounts.google.com https://oauth2.googleapis.com https://www.googleapis.com"`

### 1.2 — Migrations + seeders aplicados

- [ ] `vendor/bin/sail artisan migrate` — confirmar que as 14 migrations Fase 5 (`2026_05_14_000001` a `2026_05_14_000014`) aparecem em `migrations` table
- [ ] `vendor/bin/sail artisan db:seed --class=AgendaPermissionsSeeder` — confirma 11 abilities globais (`appointment.view`, `appointment.create`, `schedule.configure`, `calendar_sync.configure`, etc.)
- [ ] `vendor/bin/sail artisan db:seed --class=AppointmentTypeSeeder` (opcional — para tenant teste ter 3 tipos default)

### 1.3 — Schedule (cron) ativo

Confirmar que **6 commands** estão no scheduler:

```bash
vendor/bin/sail artisan schedule:list | grep agenda:
```

Esperado:
- [ ] `agenda:cleanup-expired-reservations` — everyMinute
- [ ] `agenda:expire-waitlist-notifications` — everyMinute
- [ ] `agenda:dispatch-confirmations` — everyFiveMinutes
- [ ] `agenda:auto-close-stale-appointments` — daily 00:30 BRT
- [ ] `agenda:google-poll-fallback` — everyFiveMinutes
- [ ] `agenda:google-renew-watch-channels` — daily 02:00 BRT

### 1.4 — Dados de teste

Criar via UI ou tinker:

- [ ] **Tenant teste**: `clinica-smoke` (slug)
- [ ] **Admin Clínica**: `admin-smoke@p360.test` / senha conhecida
- [ ] **Médico teste**: `dr-smoke@p360.test` (com `Professional` vinculado, `user_id` populado)
- [ ] **Atendente teste**: `atendente-smoke@p360.test`
- [ ] **5 Pacientes teste** com nomes "Paciente Smoke 01" a "Paciente Smoke 05" (todos com `telefone_primario` preenchido)

### 1.5 — Postman collection

- [ ] Importar `docs/api/Paciente360-Agenda-Fase5.postman_collection.json` no Postman
- [ ] Importar também `Paciente360-API-v1.postman_collection.json` (Fase 4) — login está nela
- [ ] No Postman, setar variável `base_url = https://api-staging.paciente360.com.br/api/v1`
- [ ] Rodar `POST /auth/login` da collection Fase 4 com credenciais Admin Clínica → confirma `{{token}}` + `{{tenant_slug}}` salvos

---

## 2. Smoke US-6.1 — Configurar agenda + bloqueios

**Ability necessária**: `schedule.configure` (Admin) ou `appointment.manage_own_schedule` (Médico próprio).

### 2.1 — Admin configura agenda recorrente

- [ ] Logar como **Admin Clínica** no painel staging
- [ ] Navegar até `/agenda/configurar` (ou via Postman `PUT /agenda/professionals/{id}/schedules`)
- [ ] Configurar Médico teste: Mon-Fri 08:00-12:00 + 13:00-18:00
- [ ] Vincular tipo "Consulta"
- [ ] **Salvar**
- [ ] **Esperado**: 5 rows em `professional_schedules` (1 por dia útil); evento `ProfissionalAgendaConfigurada` em `audit_logs`
- [ ] **Validar via tinker**: `\App\Models\Agenda\ProfessionalSchedule::where('professional_id', X)->count()` → 5

### 2.2 — Admin cria bloqueio (férias 10/06-20/06)

- [ ] Postman → `POST /agenda/professionals/{id}/schedule-exceptions` com payload férias
- [ ] **Esperado**: 201 Created; `cascaded_cancellations` no envelope da resposta (se houver consultas no período); audit log gravado
- [ ] **Validar**: `\App\Models\Agenda\ScheduleException::count()` → 1

### 2.3 — Médico edita SUA própria agenda

- [ ] Logout Admin → login como Médico teste
- [ ] Tentar `PUT /agenda/professionals/{seu_id}/schedules` → **200 OK**
- [ ] Tentar `PUT /agenda/professionals/{outro_id}/schedules` → **403 Forbidden** (ability `appointment.manage_own_schedule` só permite o próprio)

### 2.4 — Override de bloqueio (encaixe emergência — clarify nº 5)

- [ ] Login como Admin
- [ ] Postman → `POST /agenda/consultas` com `override_block=true` + `override_motivo="Emergência cardíaca"` em horário de bloqueio
- [ ] **Esperado**: 201 Created; consulta gravada com `override_block=true`; **audit log nível `warning`**
- [ ] Tentar mesma operação como Atendente (sem ability `appointment.override_block`) → **403 Forbidden**

**Critério de aprovação US-6.1**: ✅ todos os 4 itens passaram

---

## 3. Smoke US-6.2 — Tipos de atendimento

### 3.1 — Admin cria 3 tipos via UI

- [ ] Navegar `/agenda/tipos` → criar:
  - **Consulta** — 30min, R$ 200, cor azul
  - **Retorno** — 15min, R$ 100, cor verde (verificar tooltip "Tipo: Retorno (categoria)")
  - **Cirurgia** — 60min, R$ 1500, `min_cancellation_hours=48` (override do tenant)
- [ ] **Esperado**: 3 rows em `appointment_types`, `is_active=true`

### 3.2 — Inativar tipo preserva histórico (FR-007)

- [ ] Inativar tipo "Cirurgia"
- [ ] Tentar criar consulta com tipo Cirurgia → **422** com `error=appointment_type_inactive`
- [ ] Consulta histórica com Cirurgia continua acessível na lista

### 3.3 — Multi-tenant naming (Princípio II)

- [ ] Em outro tenant teste, criar tipo "Consulta" (mesmo nome) → **OK** (slugs únicos por tenant)

**Critério de aprovação US-6.2**: ✅ todos os 3 itens passaram

---

## 4. Smoke US-6.3 — Criar consulta + drag-and-drop

### 4.1 — Atendente cria consulta via painel

- [ ] Login como Atendente
- [ ] Abrir `/agenda` → view semanal
- [ ] Click em slot vazio 09:00 quarta → modal abre
- [ ] Buscar "Paciente Smoke 01" via PatientAutocomplete (validar fuzzy trgm)
- [ ] Selecionar tipo "Consulta" + profissional Médico teste
- [ ] Marcar `notify_patient=true` → confirmar
- [ ] **Esperado**:
  - Bloco aparece na grade com cor azul (do tipo Consulta)
  - Em outra aba `/pacientes/{paciente_id}` → coluna do funil moveu para "Agendado"
  - `audit_logs` tem entry `ConsultaCriada`
  - Em `/inbox` (Fase 3 staging) — paciente recebe template HSM com horário no header

### 4.2 — Race condition gate (FR-011a / SC-008)

- [ ] Em 2 abas Postman simultâneas, fazer `POST /agenda/consultas` no MESMO slot/professional
- [ ] **Esperado**: 1 retorna 201 Created; outra retorna **409 `slot_conflict`**

### 4.3 — Drag-to-move (clarify nº 9)

- [ ] Arrastar consulta existente para outro horário no calendário
- [ ] **Esperado**: modal de confirmação obrigatório aparece com texto "Reagendar consulta de {Paciente} de {hora_atual} para {hora_nova}? O paciente será notificado."
- [ ] Confirmar → `ConsultaReagendada` emitido + Fase 3 dispara mensagem
- [ ] Cancelar → snap-back, sem mudanças

### 4.4 — Idempotency replay (R8)

- [ ] Postman → `POST /agenda/consultas` com `idempotency_key` UUID v7
- [ ] Repetir o MESMO request 3 vezes
- [ ] **Esperado**: 1ª retorna 201; 2ª e 3ª retornam **200 OK** com `idempotent_replay=true` e mesmo `appointment.id`
- [ ] DB tem apenas 1 row para essa idempotency_key

### 4.5 — Slot reservation (clarify nº 2)

- [ ] Postman → `POST /agenda/slots/{starts_at}/reservar` com `holder_type=user`
- [ ] **Esperado**: 201 Created; `expires_at = now() + 5min`
- [ ] Repetir mesmo request → **409** `slot_already_reserved` com `holder_type=user`
- [ ] Aguardar 5min OU disparar manual `vendor/bin/sail artisan agenda:cleanup-expired-reservations` → reserva libera (release_reason=expired)

**Critério de aprovação US-6.3**: ✅ todos os 5 itens passaram

---

## 5. Smoke US-6.4 — Confirmação automática + comparecimento

### 5.1 — Dispatch T-24h em virtual time

Em staging, usar tinker para forçar tempo:

```bash
vendor/bin/sail artisan tinker --execute '
  \Carbon\CarbonImmutable::setTestNow(now()->subDay()->setTime(14, 0));
  \App\Models\Agenda\Appointment::factory()->create([
    "tenant_id" => 1,
    "professional_id" => 1,
    "appointment_type_id" => 1,
    "paciente_id" => 1,
    "starts_at" => now()->addDay()->setTime(14, 0),
    "ends_at" => now()->addDay()->setTime(14, 30),
    "status" => "scheduled",
    "channel_origin" => "painel",
  ]);
'
```

- [ ] Avançar virtual time para T-24h (entre `now()+23h` e `now()+24h`):
  ```bash
  vendor/bin/sail artisan tinker --execute '\Carbon\CarbonImmutable::setTestNow(now()->addDay()->subMinutes(45));'
  ```
- [ ] Disparar manual: `vendor/bin/sail artisan agenda:dispatch-confirmations`
- [ ] **Esperado**:
  - 1 row em `confirmation_dispatches` com `kind='24h'`, `status='dispatched'`, `via_ia=false`
  - Evento `ConsultaConfirmacaoPendente` emitido com payload `horario_brasilia` e `tz_label='horário de São Paulo'`
  - Listener `DispatchConfirmationToInbox` logado em `storage/logs/laravel.log` (até MessagingDispatcher integrar real)

### 5.2 — Resposta "1" → confirmed

- [ ] Postman → `POST /agenda/consultas/{id}/confirmar-resposta` com `response_value=1, dispatch_kind=24h`
- [ ] **Esperado**: `Appointment.status='confirmed'`; `confirmed_at` setado; evento `ConsultaConfirmada` emitido (broadcast Reverb canal `tenant.X.agenda`)

### 5.3 — Resposta "3" → canceled

- [ ] Criar nova consulta + dispatch confirmação 24h
- [ ] Postman → `POST /agenda/consultas/{id}/confirmar-resposta` com `response_value=3, dispatch_kind=24h`
- [ ] **Esperado**: `Appointment.status='canceled'`, `quem_cancelou='paciente'`, `motivo_cancelamento='paciente_via_chat'`

### 5.4 — Marcação manual de comparecimento (clarify nº 14)

- [ ] Consulta no passado (use tinker para criar com `starts_at` ontem)
- [ ] Postman → `POST /agenda/consultas/{id}/marcar-comparecimento` com `status=realizada`
- [ ] **Esperado**: `Appointment.status='realizada'`, `attendance_marked_at` setado, evento `ConsultaRealizada` emitido

### 5.5 — Reverter dentro de 48h

- [ ] No mesmo appointment recém-marcado: `POST /agenda/consultas/{id}/reverter-comparecimento`
- [ ] **Esperado**: `status` volta para `scheduled`, evento `ConsultaMarcacaoRevertida` emitido

### 5.6 — Janela 7d (auto-close)

- [ ] Criar consulta com `starts_at` 8 dias atrás (status `scheduled`)
- [ ] Disparar `vendor/bin/sail artisan agenda:auto-close-stale-appointments`
- [ ] **Esperado**: status muda para `concluida_sem_registro`; audit log nível `warning`
- [ ] Tentar marcar comparecimento → **422 `appointment_too_old_for_marking`**

### 5.7 — Reset virtual time

```bash
vendor/bin/sail artisan tinker --execute '\Carbon\CarbonImmutable::setTestNow();'
```

**Critério de aprovação US-6.4**: ✅ todos os 7 itens passaram

---

## 6. Smoke US-6.5 — Reagendamento + cancelamento via chat

### 6.1 — Reagendamento via API (IA Matricial — futuro)

- [ ] Postman → `POST /agenda/consultas/{id}/reagendar` com `new_starts_at` futuro + `idempotency_key`
- [ ] **Esperado**: 200 OK; `starts_at`/`ends_at` atualizados; row em `appointment_reschedules`; `professional_id` e `appointment_type_id` PRESERVADOS

### 6.2 — Limite 2 reagendamentos (clarify nº 7)

- [ ] Reagendar a mesma consulta uma 3ª vez
- [ ] **Esperado**: 422 com `error=reschedule_limit_exceeded`, `escalated_to_inbox=true`, `current_count=2`, `limit=2`
- [ ] Evento `LimiteDeReagendamentoExcedido` emitido (log no listener Escalate*)

### 6.3 — Cancelamento dentro do prazo (paciente)

- [ ] Consulta com `starts_at` em 24h (acima dos 4h default tenant)
- [ ] Postman → `POST /agenda/consultas/{id}/cancelar` com `quem_cancelou=paciente`, `motivo`
- [ ] **Esperado**: 200 OK; `Appointment.status='canceled'`; evento `ConsultaCancelada` emitido

### 6.4 — Cancelamento FORA do prazo (paciente) — clarify nº 3

- [ ] Consulta com `starts_at` em 2h (abaixo do default 4h)
- [ ] Postman → mesmo request
- [ ] **Esperado**: **422** com:
  - `error=cancellation_outside_window`
  - `escalated_to_inbox=true`
  - `window_hours=4`
  - `current_hours_until_appt=2.X`
- [ ] Evento `CancelamentoSolicitadoForaDoPrazo` emitido
- [ ] `Appointment.status` permanece `scheduled` (NÃO cancelou)

### 6.5 — Override por tipo (Cirurgia 48h)

- [ ] Consulta tipo Cirurgia (min_cancellation_hours=48), `starts_at` em 24h
- [ ] Postman → cancelar como paciente
- [ ] **Esperado**: 422 `cancellation_outside_window`, `window_hours=48` (override do tipo)

### 6.6 — Profissional cancela irrestrito

- [ ] Consulta a 1h do `starts_at` (paciente não conseguiria)
- [ ] Postman → cancelar com `quem_cancelou=profissional`
- [ ] **Esperado**: 200 OK (irrestrito); audit log com motivo

**Critério de aprovação US-6.5**: ✅ todos os 6 itens passaram

---

## 7. Smoke US-6.6 — Lista de espera FIFO sequencial

### 7.1 — Inscrever 3 pacientes

- [ ] Como Atendente, inscrever Paciente 02, 03, 04 na lista de espera de (Médico teste × Consulta)
- [ ] **Esperado**: 3 rows em `waitlist_entries` com positions 1, 2, 3 (FIFO)

### 7.2 — Cancelar abre vaga (notifica APENAS o 1º)

- [ ] Cancelar uma consulta existente do Médico teste tipo Consulta com `starts_at` futuro
- [ ] **Esperado**:
  - Apenas 1 entry em `waitlist_entries` ficou `status='notified'` (Paciente 02 — position 1)
  - Pacientes 03 e 04 continuam `waiting`
  - `notified_for_slot_starts_at` populado com o slot liberado
  - `expires_at = now() + 15min` (default tenant)
  - Evento `VagaAbertaNaListaDeEspera` emitido

### 7.3 — Expira → re-notifica próximo

- [ ] Aguardar 15min OU forçar via tinker:
  ```bash
  vendor/bin/sail artisan tinker --execute '
    \App\Models\Agenda\WaitlistEntry::where("status", "notified")->first()->update(["expires_at" => now()->subMinute()]);
  '
  vendor/bin/sail artisan agenda:expire-waitlist-notifications
  ```
- [ ] **Esperado**: Paciente 02 → `expired`; Paciente 03 → `notified`

### 7.4 — Aceitar vaga (cria appointment atomicamente)

- [ ] Como Paciente 03 (ou via Postman direto), `POST /agenda/waitlist/{id}/aceitar`
- [ ] **Esperado**:
  - `WaitlistEntry.status='accepted'`, `accepted_appointment_id` populado
  - Novo `Appointment` criado com `channel_origin='autoatendimento'`, mesmo slot
  - Resposta retorna `data` (waitlist) + `appointment`

### 7.5 — Múltiplas listas simultâneas (AC-6.6.5)

- [ ] Mesmo Paciente 02 inscrito em (Dr. Y × Consulta) e (Dr. X × Retorno) ao mesmo tempo
- [ ] **Esperado**: 2 entries distintas no `waitlist_entries`, ambas waiting

**Critério de aprovação US-6.6**: ✅ todos os 5 itens passaram

---

## 8. Smoke US-6.7 — Google Calendar sync

⚠️ **Pré-requisito**: conta Google de teste real (`smoke-tester@gmail.com` ou similar) e OAuth Client configurado no GCP.

### 8.1 — Conectar Google (cria sub-calendário automaticamente — clarify nº 15)

- [ ] Login como Médico teste no painel staging
- [ ] Navegar `/agenda/sincronizacao` → click "Conectar Google Calendar"
- [ ] Autorizar consent screen no Google (scopes: `calendar`, `calendar.events`, `openid email profile`)
- [ ] Voltar ao painel — confirmar:
  - **UI mostra**: "✓ Conectado como smoke-tester@gmail.com"
  - **DB**: `CalendarSyncAccount` com `status='connected'`, `google_calendar_id` populado, tokens encrypted
  - **Audit**: evento `CalendarioExternoSincronizado(status=connected)` em `audit_logs`
  - **Google Calendar**: abrir `https://calendar.google.com` → ver sub-calendário **"Paciente360 — {Tenant.nome}"** na lateral esquerda

### 8.2 — Criar consulta no CRM espelha no Google em <30s (push)

- [ ] Como Atendente, criar consulta no painel para o Médico teste teste
- [ ] Aguardar até 30s
- [ ] Abrir Google Calendar manualmente → confirmar:
  - Evento aparece **NO sub-calendário** (não no primary)
  - **Título**: `Consulta — {Médico teste.nome}` (FIXO, sem nome do paciente — gate LGPD FR-038a)
  - **Descrição**: `Agendamento via {Tenant.nome}` (genérico)
  - **Sem CPF, sem telefone, sem tipo clínico, sem notes** (gate Princípio I)

### 8.3 — Reagendamento espelha em <2min

- [ ] Reagendar a consulta criada para outro horário no painel
- [ ] Aguardar até 2min
- [ ] Confirmar no Google: evento moveu para o novo horário (mesmo título, mesma descrição)

### 8.4 — Cancelamento remove evento

- [ ] Cancelar consulta no painel
- [ ] Aguardar até 2min
- [ ] Confirmar no Google: evento foi deletado do sub-calendário

### 8.5 — Evento criado manualmente no sub-cal vira `ExternalCalendarBusy`

- [ ] No Google Calendar (UI Google), criar evento manual no **sub-calendário** Paciente360 (terça 14:00-15:00)
- [ ] Aguardar até 30s (push) OU disparar `vendor/bin/sail artisan agenda:google-poll-fallback` (5min cron, mas pode forçar)
- [ ] Confirmar:
  - DB tem 1 row em `external_calendar_busy` com `external_event_id` matching o evento Google
  - Em `/agenda/slots-disponiveis?...&from=...&to=...`: slot 14:00 da terça **NÃO aparece** na lista
  - No painel agenda, slot aparece como bloqueado/ocupado

### 8.6 — Evento no calendário PRIMÁRIO do médico NÃO bloqueia (clarify nº 15)

- [ ] No Google Calendar, criar evento no calendário **primário** do médico (não no sub-cal Paciente360)
- [ ] Aguardar 5min (poll fallback)
- [ ] **Esperado**: NÃO aparece em `external_calendar_busy`; slot continua disponível
- [ ] Comportamento documentado: médico precisa criar bloqueio via CRM ou no sub-cal gerenciado

### 8.7 — Refresh automático de token (R5)

- [ ] Aguardar até 1h (token Google expira em ~1h) OU forçar via tinker:
  ```bash
  vendor/bin/sail artisan tinker --execute '
    \App\Models\Agenda\CalendarSyncAccount::first()->update(["expires_at" => now()->subHour()]);
  '
  ```
- [ ] Criar nova consulta → sync deve continuar funcionando (auto-refresh transparente)
- [ ] DB: `expires_at` atualizado (>now())

### 8.8 — Disconnect via UI

- [ ] No painel `/agenda/sincronizacao` → click "desconectar"
- [ ] **Esperado**:
  - `CalendarSyncAccount.status='disconnected'`, `last_disconnect_at` setado
  - Watch channel parado no Google
  - Email enviado ao médico (verificar em `mailpit` staging ou inbox real)
  - Sub-calendário continua existindo no Google (não deleta — clarify nº 10)
  - Banner amber aparece no painel para reconectar (R5)

### 8.9 — Reconectar reusa sub-calendário (R5)

- [ ] Click "Reconectar" no banner ou na página
- [ ] Completar OAuth novamente
- [ ] **Esperado**: `CalendarSyncAccount.status='connected'`, `last_reconnect_at` setado, **mesmo `google_calendar_id`** (não cria sub-cal duplicado)

**Critério de aprovação US-6.7**: ✅ todos os 9 itens passaram

---

## 9. 🔴 GATE — Cross-tenant Google leak (clarify nº 15 / AC-6.7.11)

**Este é o gate crítico de segurança/LGPD.**

### 9.1 — Setup 2 tenants

- [ ] Tenant A: `clinica-a-smoke` com Médico A (`dr-a@p360.test`)
- [ ] Tenant B: `clinica-b-smoke` com Médico B (`dr-b@p360.test`)

### 9.2 — Mesma conta Google em 2 tenants

- [ ] Médico A conecta `smoke-tester@gmail.com` em Tenant A
- [ ] Logout, login como Médico B
- [ ] Médico B conecta a **MESMA** `smoke-tester@gmail.com` em Tenant B

### 9.3 — Validar isolamento

- [ ] Abrir Google Calendar manualmente — confirmar: **2 sub-calendários distintos** ("Paciente360 — Clínica A Smoke" + "Paciente360 — Clínica B Smoke") com cores diferentes
- [ ] Em DB:
  ```bash
  vendor/bin/sail artisan tinker --execute '
    foreach(\App\Models\Agenda\CalendarSyncAccount::withoutTenantScope()->where("provider_email", "smoke-tester@gmail.com")->get() as $a) {
      echo "Tenant {$a->tenant_id} | google_calendar_id={$a->google_calendar_id}\n";
    }
  '
  ```
  Esperado: **2 rows com `google_calendar_id` DIFERENTES**

### 9.4 — Criar consulta em Tenant A NÃO vaza para Tenant B

- [ ] Como Atendente Tenant A, criar consulta para Médico A
- [ ] Aguardar 30s
- [ ] **Validar Tenant A**: evento aparece em "Paciente360 — Clínica A Smoke" ✓
- [ ] **Validar Tenant B (CRÍTICO)**:
  - Forçar polling: `vendor/bin/sail artisan agenda:google-poll-fallback`
  - Em DB: `vendor/bin/sail artisan tinker --execute '\App\Models\Agenda\ExternalCalendarBusy::withoutTenantScope()->where("tenant_id", $tenantBId)->count()'`
  - **Esperado: 0** (Tenant B não enxerga o evento do Tenant A)

### 9.5 — UNIQUE constraint bloqueia

- [ ] Tentar conectar segunda vez no MESMO Médico A no MESMO Tenant A → **422 ou erro DB** (UNIQUE `(tenant_id, professional_id)`)

### 9.6 — Cleanup pós-teste

- [ ] Disconnect ambas as contas via UI
- [ ] No Google Calendar, deletar manualmente os 2 sub-calendários (limpeza)

**Critério de aprovação Gate Cross-tenant**: ✅ TODOS os itens 9.1-9.5 passaram (especialmente 9.4 — 0 rows em Tenant B)

---

## 10. Sign-off final

### Validação geral

- [ ] **Sem erros 5xx** durante toda a sessão (verificar `storage/logs/laravel.log` + Sentry staging)
- [ ] **Métricas Prometheus** populadas: `curl https://api-staging.paciente360.com.br/metrics | grep paciente360_appointment` retorna contadores não-zero
- [ ] **Cron jobs rodando**: `vendor/bin/sail artisan schedule:work` em background sem erros
- [ ] **CSP em prod**: response headers contêm `connect-src` com Google hosts
- [ ] **Tokens encrypted em DB**: `SELECT encrypted_access_token FROM calendar_sync_accounts LIMIT 1;` retorna string Crypt-encoded (começando com `eyJpdiI6...`)

### Smoke aprovado por

- **QA Owner**: __________________________ Data: ___/___/______
- **Backend Lead**: __________________________ Data: ___/___/______
- **DevOps**: __________________________ Data: ___/___/______
- **Product Owner**: __________________________ Data: ___/___/______

### Bloqueadores encontrados (preencher se houver)

| # | Severidade | Descrição | Owner | Status |
|---|---|---|---|---|
| | | | | |

---

## Apêndice A — Comandos úteis durante smoke

```bash
# Tail logs
vendor/bin/sail artisan pail

# Tinker rápido
vendor/bin/sail artisan tinker

# Disparar manualmente os crons
vendor/bin/sail artisan agenda:cleanup-expired-reservations
vendor/bin/sail artisan agenda:expire-waitlist-notifications
vendor/bin/sail artisan agenda:dispatch-confirmations
vendor/bin/sail artisan agenda:auto-close-stale-appointments
vendor/bin/sail artisan agenda:google-poll-fallback
vendor/bin/sail artisan agenda:google-renew-watch-channels

# Verificar schedule registrado
vendor/bin/sail artisan schedule:list

# Verificar abilities seedadas
vendor/bin/sail artisan tinker --execute 'echo \App\Models\Permission::whereIn("name", ["appointment.view", "calendar_sync.configure"])->count();'

# Reset virtual time (após smoke US-6.4)
vendor/bin/sail artisan tinker --execute '\Carbon\CarbonImmutable::setTestNow();'

# Limpar reservas órfãs
vendor/bin/sail artisan tinker --execute '\App\Models\Agenda\SlotReservation::query()->whereNull("released_at")->update(["released_at" => now(), "release_reason" => "canceled"]);'
```

## Apêndice B — Critérios de bloqueio para merge

⛔ **NÃO mergear `005-agendamento-consultas` → `main` se**:

1. Item 9.4 (cross-tenant Google leak) falha — Tenant B vê dados de A → **CVE de segurança LGPD**
2. Item 8.2 (payload Google) contém PII clínica → **violação Princípio I**
3. Race condition (item 4.2) não retorna 409 — múltiplas consultas no mesmo slot → **inconsistência de dados**
4. Migrations falham na aplicação em staging
5. Mais de 5 erros 5xx durante smoke (excluindo intencionais como 9.5)
6. Algum cron job não executa ou throw exception não tratada

✅ **OK mergear se**:

- Todos os critérios de aprovação por sessão (§§ 2-9) verdes
- Sem bloqueadores na tabela §10
- Sign-off de pelo menos QA Owner + Backend Lead

---

## Apêndice C — Referências

- Spec: [`specs/005-agendamento-consultas/spec.md`](../../specs/005-agendamento-consultas/spec.md)
- Plan: [`specs/005-agendamento-consultas/plan.md`](../../specs/005-agendamento-consultas/plan.md)
- Quickstart: [`specs/005-agendamento-consultas/quickstart.md`](../../specs/005-agendamento-consultas/quickstart.md)
- Postman: [`docs/api/Paciente360-Agenda-Fase5.postman_collection.json`](../api/Paciente360-Agenda-Fase5.postman_collection.json)
- OpenAPI: [`specs/005-agendamento-consultas/contracts/openapi.yaml`](../../specs/005-agendamento-consultas/contracts/openapi.yaml)
