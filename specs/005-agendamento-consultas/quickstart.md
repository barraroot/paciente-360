# Quickstart — Fase 5 Agendamento de Consultas

**Feature**: 005-agendamento-consultas
**Date**: 2026-05-13

Pré-requisitos para começar a desenvolver E para entregar em staging/prod. Gates obrigatórios para o merge.

---

## 1 — Pré-requisitos de plataforma (DoR antes de codar)

### 1.1 — Constitution check (✅ já passado)

- Constituição v1.4.0 cobre todos os gates desta fase. **Sem amendment necessário** (auditado em `plan.md` Constitution Check).

### 1.2 — Branch + spec + plan ✅

- Branch `005-agendamento-consultas` criada
- `specs/005-agendamento-consultas/spec.md` aprovada (14/15 clarifications resolvidos; NC nº 12 resolvido em `research.md` R5)
- `specs/005-agendamento-consultas/plan.md` aprovado
- `specs/005-agendamento-consultas/research.md` aprovado
- `specs/005-agendamento-consultas/data-model.md` aprovado
- `specs/005-agendamento-consultas/contracts/openapi.yaml` aprovado

---

## 2 — Google Cloud Console: provisionar OAuth app

Antes de implementar US-6.7 (Google Calendar sync).

### 2.1 — Criar projeto

1. Acessar https://console.cloud.google.com/projectcreate
2. Nome: `Paciente360 Calendar Sync` (livre, mas consistente).
3. Anotar o `Project ID`.

### 2.2 — Habilitar Google Calendar API

```bash
# Via gcloud CLI (preferível) ou Console UI
gcloud services enable calendar-json.googleapis.com --project=<PROJECT_ID>
```

### 2.3 — OAuth consent screen

1. APIs & Services → OAuth consent screen
2. User Type: **External** (público beta, sem domínio fixo)
3. App name: `Paciente360`
4. User support email: `suporte@paciente360.com.br`
5. Logo: upload do logo do produto (PNG quadrado 120x120 mínimo)
6. App domain → Application home page: `https://paciente360.com.br`
7. Authorized domains: `paciente360.com.br`
8. Developer contact: equipe@paciente360.com.br
9. **Scopes**:
   - `https://www.googleapis.com/auth/calendar` (para criar sub-calendário — clarify nº 15)
   - `https://www.googleapis.com/auth/calendar.events` (para CRUD de eventos no sub-cal)
   - `openid`, `email`, `profile` (identificar a conta para gravar `provider_email`)
10. Test users: adicionar emails dos 5 profissionais teste internos durante beta
11. Solicitar **App verification** quando passar de 100 usuários (~3 meses após lançamento)

### 2.4 — Criar OAuth Client ID

1. APIs & Services → Credentials → Create credentials → OAuth client ID
2. Application type: **Web application**
3. Name: `Paciente360 Backend`
4. Authorized JavaScript origins:
   - `http://api.paciente360.test` (local Sail)
   - `https://api-staging.paciente360.com.br`
   - `https://api.paciente360.com.br`
5. Authorized redirect URIs:
   - `http://api.paciente360.test/api/v1/agenda/calendar-sync/google/callback`
   - `https://api-staging.paciente360.com.br/api/v1/agenda/calendar-sync/google/callback`
   - `https://api.paciente360.com.br/api/v1/agenda/calendar-sync/google/callback`
6. **Anotar `Client ID` e `Client Secret`** (sensíveis — guardar no Vault da equipe).

### 2.5 — Webhook (push notifications) — domínio HTTPS público

Google Watch channels só aceitam HTTPS público válido. Para dev local:

- **Opção A** — ngrok: `ngrok http 80 --domain=paciente360-dev.ngrok-free.app` + registrar domínio em "Authorized domains" do OAuth consent.
- **Opção B** — staging direto (recomendado): testar sync apenas em staging onde HTTPS já está configurado.

---

## 3 — Variáveis de ambiente

`.env.example` ganha:

```dotenv
# Google Calendar OAuth (Fase 5)
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI=${APP_URL}/api/v1/agenda/calendar-sync/google/callback
GOOGLE_CALENDAR_WEBHOOK_BASE_URL=${APP_URL}/webhooks/google-calendar
# Window of synced events (clarify nº 10)
AGENDA_CALENDAR_SYNC_WINDOW_DAYS=60
# Watch channel renewal (R3 — antes do TTL 7d)
AGENDA_WATCH_CHANNEL_RENEW_HOURS=48

# CSP — adicionar Google endpoints (Princípio VII)
CSP_GOOGLE_HOSTS="https://accounts.google.com https://oauth2.googleapis.com https://www.googleapis.com"
```

Em `config/services.php`:

```php
'google_calendar' => [
    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    'webhook_base_url' => env('GOOGLE_CALENDAR_WEBHOOK_BASE_URL'),
    'sync_window_days' => (int) env('AGENDA_CALENDAR_SYNC_WINDOW_DAYS', 60),
    'watch_channel_renew_hours' => (int) env('AGENDA_WATCH_CHANNEL_RENEW_HOURS', 48),
],
```

Em `config/csp.php` (já existente Fase 4):

```php
'connect_src' => [
    // ... existentes (Reverb, S3, API host)
    ...explode(' ', env('CSP_GOOGLE_HOSTS', '')),
],
```

---

## 4 — Dependências composer + npm

### 4.1 — Composer

```bash
vendor/bin/sail composer require google/apiclient:^2.18
```

### 4.2 — NPM

```bash
vendor/bin/sail npm install --legacy-peer-deps \
  @fullcalendar/core@^6 \
  @fullcalendar/vue3@^6 \
  @fullcalendar/daygrid@^6 \
  @fullcalendar/timegrid@^6 \
  @fullcalendar/interaction@^6 \
  @fullcalendar/resource-timegrid@^6 \
  luxon@^3
```

---

## 5 — Migration order (rodar in-order)

```bash
vendor/bin/sail artisan migrate
```

Vai executar as 14 migrations da Fase 5 na ordem `2026_05_14_000001` → `2026_05_14_000014` (ver `data-model.md`). Migrations seguem o padrão da Fase 4 (idempotentes, reversíveis).

**Pré-flight check** (rodar antes da migrate em staging/prod):

```bash
vendor/bin/sail artisan agenda:preflight-check
# Verifica: extensão pgcrypto habilitada, tabelas Fase 0-4 íntegras, settings JSONB existe em tenants
```

---

## 6 — Seeders

```bash
vendor/bin/sail artisan db:seed --class=AppointmentTypeSeeder
vendor/bin/sail artisan db:seed --class=AgendaPermissionsSeeder
```

- `AppointmentTypeSeeder` cria 3 tipos default por tenant ativo: **Consulta** (30min, R$ 200), **Retorno** (15min, R$ 100), **Exame** (60min, R$ 300). Idempotente.
- `AgendaPermissionsSeeder` cria as 9 abilities Spatie + atribui aos roles correspondentes (ver spec § Contratos Herdados / Fase 0).

---

## 7 — Schedule (cron commands)

`routes/console.php` ganha:

```php
Schedule::command('agenda:cleanup-expired-reservations')->everyMinute()->onOneServer();
Schedule::command('agenda:expire-waitlist-notifications')->everyMinute()->onOneServer();
Schedule::command('agenda:dispatch-confirmations')->everyFiveMinutes()->onOneServer();
Schedule::command('agenda:auto-close-stale-appointments')->dailyAt('00:30')->onOneServer();  // BRT — Carbon::setLocale('pt_BR') já configurado Fase 0
Schedule::command('agenda:google-poll-fallback')->everyFiveMinutes()->onOneServer();
Schedule::command('agenda:google-renew-watch-channels')->dailyAt('02:00')->onOneServer();
```

Confirmar que `app/Console/Kernel.php` (ou `bootstrap/app.php` no Laravel 13) tem `withSchedule(...)` ativo.

---

## 8 — Smoke E2E (manual, antes do merge)

### 8.1 — Smoke US-6.1 + US-6.2 (config básica)

1. Login Admin Clínica.
2. Navegar até `/agenda/tipos` → confirmar 3 tipos default seedados.
3. Editar tipo `Consulta` → `valor_particular = 250`, `min_cancellation_hours = 12`.
4. Navegar até `/agenda/configurar` → selecionar profissional teste → configurar Mon-Fri 08:00-18:00 com intervalo 12:00-13:30.
5. Salvar. Verificar audit log em `audit_logs` tabela: 2 entries (`ProfissionalAgendaConfigurada`, `AppointmentTypeAtualizado`).

### 8.2 — Smoke US-6.3 (criar consulta + funil + notificação)

1. Como Atendente, abrir `/agenda` → view semanal.
2. Click no slot 09:00 Quarta → modal abre → buscar paciente "Maria" (deve ter 1 paciente da Fase 2) → escolher tipo `Consulta`.
3. Confirmar criação.
4. Verificar:
   - Bloco aparece no calendário com cor do tipo.
   - Em outra aba, abrir `/pacientes/{maria_id}` → coluna do funil moveu para `Agendado`.
   - Em `/inbox`, paciente recebeu mensagem template "Sua consulta amanhã às 09:00 (horário de São Paulo) com Dr. X" (clarify nº 6, Fase 3).
   - `audit_logs`: entry `ConsultaCriada` com payload completo.

### 8.3 — Smoke US-6.4 (confirmação T-24h E2E em virtual time)

1. Em ambiente teste com `Carbon::setTestNow(now()->subDay()->setTime(9, 0))` (criar consulta marcada para amanhã 09:00):
   ```bash
   vendor/bin/sail artisan tinker --execute 'App\Models\Agenda\Appointment::factory()->create(["starts_at" => now()->addDay()->setTime(9, 0)]);'
   ```
2. Avançar virtual time para `now()->addDay()->subDay()->subHour()->subMinutes(1)` (T-25h, sai do gatilho).
3. Rodar `vendor/bin/sail artisan agenda:dispatch-confirmations`:
   - Verificar `ConfirmationDispatch` com `kind=24h` criado.
   - Verificar `ConsultaConfirmacaoPendente` emitido (audit_logs).
   - Verificar Fase 3 dispatch (mock ou sandbox WhatsApp): mensagem enviada com horário no header.
4. Simular resposta "1" via endpoint interno `/agenda/consultas/{id}/confirmar-resposta`:
   - Verificar `Appointment.status = confirmed`, `confirmed_at` setado.
   - `audit_logs`: entry `ConsultaConfirmada`.

### 8.4 — Smoke US-6.7 (Google sync end-to-end com 1 conta real)

⚠️ **Necessita conta Google real e ambiente staging com HTTPS**. Não rodar em CI.

1. Login Médico no staging.
2. Navegar até `/agenda/sincronizacao` → click "Conectar Google Calendar".
3. Completar OAuth no Google.
4. Voltar para painel → confirmar:
   - `CalendarSyncAccount.status = connected`.
   - `google_calendar_id` preenchido.
   - Abrir Google Calendar manualmente → ver sub-calendário `Paciente360 — {Tenant.nome}` na lista lateral.
   - `audit_logs`: `CalendarioExternoSincronizado(status=connected)`.
5. Criar consulta no CRM → esperar 30s → verificar evento aparece no sub-calendário Google com título fixo `Consulta — {Profissional.nome}`.
6. No Google, criar evento manual no sub-calendário 14:00 da segunda → esperar 30s (push) → verificar `ExternalCalendarBusy` criado + slot 14:00 fica indisponível em `/agenda/slots-disponiveis`.
7. Cancelar consulta no CRM → verificar evento removido do Google em 30s.
8. **Smoke cross-tenant** (clarify nº 15 / AC-6.7.11):
   - Conectar Tenant A com conta `dr.silva@gmail.com`.
   - Conectar Tenant B com mesma conta `dr.silva@gmail.com`.
   - Criar consulta no Tenant A.
   - Verificar `events.list?calendarId={sub-cal-B}` retorna vazio (eventos do A invisíveis).
   - Verificar no Google: 2 sub-calendários distintos (`Paciente360 — Tenant A`, `Paciente360 — Tenant B`).

---

## 9 — Performance gates

Antes do merge, rodar suite de carga local:

```bash
vendor/bin/sail artisan test --filter=SlotConflictRaceTest
vendor/bin/sail artisan test --filter=CrossTenantGoogleSyncTest
vendor/bin/sail artisan test --filter=GoogleEventPayloadLgpdTest
```

- **SC-008**: `SlotConflictRaceTest` valida 50 requests paralelos → exatamente 1 sucesso → resto 409 `slot_conflict`.
- **SC-009**: `tests/Feature/Agenda/SlotListPerformanceTest.php` valida p95 ≤ 300ms em janela 7 dias × 50 profissionais.

---

## 10 — Definition of Done (gate para merge)

Antes de criar PR para `main`:

### Funcional
- [ ] US-6.1..US-6.7 com todos os ACs 🔴 implementados e cobertos por testes
- [ ] 11 eventos de domínio emitindo com payload correto (verificado via testes de integração com Fase 2/3)
- [ ] Movimento do card no funil Fase 2 ao criar consulta funcionando end-to-end (smoke 8.2)
- [ ] Confirmação automática T-24h, T-2h, retry T-30min dispara via Fase 3 (smoke 8.3)
- [ ] Lista de espera notifica sequencialmente K=1 com prazo 15min (clarify nº 8)
- [ ] Google Calendar bidirecional funcional (smoke 8.4)
- [ ] Cross-tenant leak test verde (AC-6.7.11)

### Qualidade
- [ ] Cobertura ≥ 70% no domínio `app/Services/Agenda/*` e `app/Models/Agenda/*`
- [ ] Suite full verde (sem regressão nas Fases 0-4)
- [ ] `vendor/bin/sail bin pint --dirty --format agent` clean
- [ ] ESLint clean (`vendor/bin/sail npm run lint`)
- [ ] OpenAPI atualizado + `vendor/bin/sail artisan openapi:check` drift 0

### Constitucional
- [ ] Princípio I (LGPD) — `GoogleEventPayloadLgpdTest` verde; tokens encrypted; retenção 1+ ano
- [ ] Princípio II (Multi-tenant) — `CrossTenantAgendaTest` + `CrossTenantGoogleSyncTest` verdes
- [ ] Princípio IV (TDD) — ACs vermelhos antes da impl (verificado em git log)
- [ ] Princípio V (Observabilidade) — 6 métricas Prometheus expostas em `/metrics`; verificável via curl
- [ ] Princípio VII (Segurança) — OAuth tokens encrypted; CSP estendido (`config/csp.php`); webhooks Google validados via X-Goog-Channel-Token; rate limit ativo nas rotas `/reservar` e `/consultas`

### Operacional
- [ ] Postman collection atualizada em `docs/api/agenda-fase5.postman_collection.json`
- [ ] Smoke test E2E pelo QA com conta Google real em staging (checklist seções 8.1-8.4)
- [ ] Quickstart com pré-requisitos para deploy revisado (OAuth credentials criadas em GCP, secrets no Vault)
- [ ] Schedule cron commands em `routes/console.php` registrados

---

## 11 — Disaster recovery / rollback

### 11.1 — Rollback de migrations

Cada migration tem `down()` que faz `Schema::dropIfExists(...)`. Ordem inversa de criação. Testar em staging antes do deploy de prod:

```bash
vendor/bin/sail artisan migrate:rollback --step=14
```

### 11.2 — Desabilitar feature em runtime (sem rollback de DB)

Feature flag opcional via `config/features.php`:

```php
'agenda_module' => env('AGENDA_MODULE_ENABLED', true),
```

Middleware `EnsureAgendaModuleEnabled` aplicado em group `/api/v1/agenda/*` retorna 503 se flag off. Rotas Filament (não há nesta fase) e listeners (Fase 2/3) NÃO são impactados.

### 11.3 — Watch channels Google órfãos após rollback

Se rollback ocorrer com watch channels ativos, eles seguem disparando webhooks (404 da nossa parte → Google desativa em ~7 dias). Procedimento:

```bash
# Listar canais ativos antes do rollback (persistir o output!)
vendor/bin/sail artisan tinker --execute 'App\Models\Agenda\CalendarSyncAccount::whereNotNull("watch_channel_id")->get(["id","watch_channel_id","watch_channel_resource_id"])->toJson();'

# Após rollback, rodar script manual de cleanup contra Google (script ad-hoc, não migration)
vendor/bin/sail artisan agenda:cleanup-google-watch-channels --from-file=backup.json
```

---

## 12 — Stakeholder communication

Antes de soltar em prod:

- [ ] Aviso 7 dias antes para a 1ª clínica beta: "feature de agenda chegando, vocês são parte do piloto, smoke test com seu QA está agendado"
- [ ] Treinamento (vídeo 5min) para Atendentes e Admin Clínica — focar em: configurar agenda + criar consulta drag-and-drop + cancelar/reagendar
- [ ] Doc curta no Help Center: "Como conectar seu Google Calendar"
