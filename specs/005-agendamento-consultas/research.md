# Phase 0 — Research: Fase 5 Agendamento de Consultas

**Feature**: 005-agendamento-consultas
**Date**: 2026-05-13
**Status**: ✅ Concluído. 8 decisões técnicas resolvidas. **NC nº 12** (UX revogação OAuth) tratado em R5.

---

## R1 — Cliente Google Calendar API

### Decision

Adotar **`google/apiclient ^2.18`** (SDK oficial Google, mantido pelo próprio Google) como dependência composer.

### Rationale

- **SDK oficial e mantido pelo provedor** — fixes de breaking changes na Google API chegam primeiro aqui.
- **Suporta todas as operações que precisamos** sem rodeios: `Calendar::events.insert/list/patch/delete`, `Calendar::calendars.insert` (para criar sub-calendário tenant-scoped — clarify nº 15), `Calendar::events.watch` (para push notifications — clarify nº 10), `Calendar::channels.stop` (para revogar watch ao desconectar).
- **Retry/backoff exponencial nativo** via `Google\Http\Batch` ou config global — alinha com R10 do spec (rate limit Google).
- **Compatível com PHP 8.5** (versão 2.18+).
- **Gerenciamento de OAuth tokens** built-in (`Google\Client::setAccessToken()` aceita array com `access_token`, `refresh_token`, `expires_in`) — funciona com nosso `Crypt::encryptString` para persistir em `CalendarSyncAccount`.

### Alternatives considered

- **`spatie/laravel-google-calendar` (v3)**: opinionated demais. Assume **1 conta Google = 1 calendar primary** — incompatível com nosso modelo de sub-calendário tenant-scoped. Custo de override era maior que usar SDK oficial direto.
- **HTTP raw via `Http::` facade**: viável tecnicamente, mas reinventaríamos OAuth refresh, parse de payload, retry, batch — risco e tempo inaceitáveis.
- **`thephpleague/oauth2-google`**: cobre apenas OAuth (não Calendar API) — exigiria combinar com HTTP raw. Sem ganho sobre o SDK oficial.

### Impact

- **`composer.json`**: adicionar `"google/apiclient": "^2.18"`.
- **`config/services.php`**: bloco `google.calendar` com `client_id`, `client_secret`, `redirect_uri` (env-driven).
- **`.env.example`**: adicionar `GOOGLE_CALENDAR_CLIENT_ID`, `GOOGLE_CALENDAR_CLIENT_SECRET`, `GOOGLE_CALENDAR_REDIRECT_URI`.
- **Quickstart**: documentar criação do OAuth app no Google Cloud Console + escopos (`https://www.googleapis.com/auth/calendar.events` + `https://www.googleapis.com/auth/calendar` para criar sub-calendário).

---

## R2 — Widget de calendário Vue 3 com drag-and-drop

### Decision

Adotar **`@fullcalendar/vue3 ^6.1.20`** (última estável publicada em out-2025; v7 ainda não lançado em mai-2026) + plugins `daygrid`, `timegrid`, `interaction`, `resource-timegrid`.

### Rationale

- **Suporte nativo a drag-create de área vazia** (clarify nº 9 — drag para criar consulta selecionando faixa de duração).
- **Drag-to-move com handler de `eventChange`** que pode ser cancelado client-side (clarify nº 9 — abrir modal de confirmação antes de submeter — chave para evitar auto-save silencioso).
- **Views diária + semanal nativas** (`timeGridDay`, `timeGridWeek`) — exatamente o escopo MVP (mensal explicitamente fora — clarify nº 9).
- **Multi-resource view** (`resourceTimeGridWeek`) cobre o toggle multi-profissional (clarify nº 9 — colunas por profissional ativo).
- **TimeZone-aware**: aceita `timeZone` IANA prop, renderiza eventos no TZ correto (clarify nº 13).
- **Acessibilidade WAI-ARIA decente** out of the box.
- **License MIT** (compatível com nosso projeto comercial).
- **Vue 3 Composition API support** desde v6 — v6.1.20 é a estável recomendada em mai-2026 (v7 não lançado ainda).

### Alternatives considered

- **`vue-cal`**: drag-to-move ok, mas drag-create a partir de área vazia exige hack (escutar mouse events manualmente). Sem multi-resource view. Não consideramos.
- **`@schedule-x/calendar`**: visual moderno, mas suporte Vue 3 ainda imaturo em out-2025 (`@schedule-x/vue` v2 lançado em ago/2025). Risco de bugs / API changes durante a fase. Adiar.
- **Implementação custom (HTML grid + drag-and-drop API)**: 3-5 sprints de R&D só para reimplementar features que `@fullcalendar` já entrega. Rejeitado.

### Impact

- **`package.json`**: adicionar `@fullcalendar/core ^6.1.20`, `@fullcalendar/vue3 ^6.1.20`, `@fullcalendar/daygrid ^6.1.20`, `@fullcalendar/timegrid ^6.1.20`, `@fullcalendar/interaction ^6.1.20`, `@fullcalendar/resource-timegrid ^6.1.20` (para multi-prof). Instalar com `--legacy-peer-deps` por conflict pré-existente vite ^8 ↔ @vitejs/plugin-vue ^5.2.1 no projeto.
- **Tamanho do bundle**: ~250KB minified+gzipped — aceitável (a página de agenda é uma rota lazy-loaded; outras páginas do tenant não pagam o custo).
- **Tema Tailwind**: customizar via CSS variables que FullCalendar expõe (`--fc-event-bg-color`, etc.) para alinhar à paleta do produto.

---

## R3 — Estratégia de Watch channels Google + endpoint webhook

### Decision

Webhook direto em **`POST /webhooks/google-calendar/{channel_id}`** (Laravel route público + middleware validação HMAC equivalente). **Sem** intermediação via Google Cloud Pub/Sub.

Watch channels (`events.watch`) registrados por profissional via `GoogleCalendarWatchService`:
- `channel.token` = HMAC-SHA256 de `{tenant_id}.{professional_id}.{channel_id}` com `APP_KEY` — validado no recebimento via header `X-Goog-Channel-Token`.
- `channel.id` = UUID gerado pelo Fase 5; persistido em `CalendarSyncAccount.watch_channel_id`.
- `channel.expiration` = ~7 dias (Google default máximo); persistido em `CalendarSyncAccount.watch_channel_expires_at`.
- **Renew**: cron diário `agenda:google-renew-watch-channels` 02:00 BRT re-registra canais que expiram nas próximas 48h.

### Rationale

- **Latência mínima**: push direto Google → nossa API (sem hop via Pub/Sub) — atende `<30s` da AC-6.7.10.
- **Operacional simples**: sem dependência de GCP project setup para Pub/Sub (cliente que não usa GCP não precisa habilitar nada extra).
- **Segurança equivalente**: header `X-Goog-Channel-Token` validado contra HMAC — defesa contra requests forjados.
- **Defesa em profundidade**: polling fallback 5min (R3 dispatches `PollGoogleCalendarFallbackJob`) — se push falha por qualquer motivo (DNS, firewall, certificate), polling pega.

### Alternatives considered

- **Pub/Sub topic intermediário**: GCP topic recebe notification, Cloud Function → nossa API. Adiciona latência (~1-2s extra), adiciona dependência de GCP project, custos extras (~$0.40/M messages). Sem ganho de confiabilidade observado.
- **Polling only (sem watch channel)**: latência alta (5min mínimo), quebra SC-005 (≤2min). Rejeitado.

### Impact

- **`routes/web.php`** (não API porque é externo Google → não usa Bearer): rota pública `POST /webhooks/google-calendar/{channel_id}` com middleware `validate.google.channel.token` (validação HMAC + lookup do channel_id em `CalendarSyncAccount`).
- **CSP**: rota está fora do `/api/*` — não impacta CSP do SPA.
- **Headers Google enviados** (validar): `X-Goog-Channel-Id`, `X-Goog-Channel-Token`, `X-Goog-Channel-Expiration`, `X-Goog-Resource-Id`, `X-Goog-Resource-Uri`, `X-Goog-Resource-State` (sync | exists | not_exists), `X-Goog-Message-Number`.
- **Response**: HTTP 200 sempre (mesmo se invalido) para evitar retry storms do Google. Erros são logados Sentry + alert.
- **Watch channel cleanup**: quando profissional desconecta, `Calendar::channels.stop` é chamado para revogar imediatamente — evita receber pushes órfãos.

---

## R4 — Reserva pessimista soft de slot (clarify nº 2)

### Decision

Tabela **`slot_reservations`** com índice composto `(tenant_id, professional_id, starts_at)` + partial index `expires_at WHERE released_at IS NULL`. Cron `agenda:cleanup-expired-reservations` cada 1min.

Algoritmo:
1. Cliente abre form de criação/reagendamento → emite `POST /agenda/slots/{starts_at}/reservar` com `{holder_type: user|ia, holder_id}`.
2. Service tenta `INSERT INTO slot_reservations (...)` com `expires_at = now() + TTL` (TTL: 5min user, 2min ia).
3. UNIQUE constraint `(tenant_id, professional_id, starts_at, released_at IS NULL)` impede 2 reservas ativas no mesmo slot.
   - Implementação: PostgreSQL partial unique index `(tenant_id, professional_id, starts_at) WHERE released_at IS NULL`.
4. Em conflito de INSERT (23505 unique_violation): service retorna 409 `slot_already_reserved` com `{holder_type, holder_until}` (para UI mostrar "Slot está sendo editado por X — aguarde N s").
5. Em commit (criar consulta), service atualiza `released_at=now()`, `release_reason='committed'`.
6. Em cancel explícito (paciente fecha form), service atualiza `released_at=now()`, `release_reason='canceled'`.
7. Em expiração (TTL atingido sem commit/cancel), cron marca `released_at=now()`, `release_reason='expired'`.

### Rationale

- **Auditável** — clarify nº 2 exige `holder_type`, `holder_id`, `acquired_at`, `expires_at`, `released_at`, `release_reason` rastreáveis.
- **Sobrevive a restart de Redis** — persistência em PostgreSQL.
- **Defesa em profundidade**: mesmo que duas reservas escapem (race no próprio mecanismo), o `UNIQUE(tenant_id, professional_id, starts_at)` em `appointments` (FR-011a) ainda rejeita a segunda criação.
- **Partial index** evita inflar índice com reservas históricas (released_at NOT NULL); apenas reservas ativas são indexadas para conflito.

### Alternatives considered

- **Lock SELECT FOR UPDATE em appointments**: bloquearia leituras concorrentes da agenda → quebra UX (slots aparecem "lentos" para outros usuários). Rejeitado.
- **Redis SETNX com TTL**: rápido (<1ms) mas perde auditabilidade pós-restart e dificulta debugging do flow de IA (clarify nº 2 deixou explícito que precisamos rastrear holder_type).
- **In-memory por servidor**: morre em multi-instance (Laravel Horizon + Reverb em hosts distintos não compartilham state).

### Impact

- **Migration**: `2026_05_14_000007_create_slot_reservations_table.php` com partial unique index.
- **Cleanup cron**: `App\Console\Commands\AgendaCleanupExpiredReservationsCommand` — escaneia `WHERE released_at IS NULL AND expires_at < now() LIMIT 1000` em batch, dispara eventos auditados.
- **Reverb broadcast**: quando uma reserva é criada/liberada, broadcast em canal `tenant.{id}.agenda` para invalidar slots em outras abas (cliente sem reload manual).

---

## R5 — UX de revogação OAuth (resolve NC nº 12)

### Decision (resolve o último clarify pendente — NC nº 12)

Tripla camada de notificação + recuperação automática:

1. **Refresh automático antes de declarar falha**: ao receber 401/invalid_grant da Google API, `GoogleCalendarOAuthService::tryRefresh()` tenta uma vez antes de qualquer escalada. Se sucesso → token rotacionado, sync prossegue sem ruído.
2. **Notificação dual** quando refresh falha (revoke manual pelo usuário no Google, ou refresh token expirado):
   - **In-app banner persistente** no painel do profissional (canto superior) com 2 CTAs: "Reconectar" (1 clique → OAuth flow) ou "Dispensar" (oculta por 24h).
   - **Email transacional** uma vez por evento de desconexão (de-duplica via `CalendarSyncAccount.last_disconnect_notified_at`) com link direto para a página de Sincronização.
3. **Consultas já criadas no Google permanecem** (não deleta — clarify nº 10) — o vínculo `CalendarSyncedEvent.external_event_id` é preservado para retomada quando reconectar; eventos órfãos no Google ficam visíveis ao profissional como "Paciente360 (desconectado)".
4. **Reconexão 1-clique** atualiza tokens, **não cria novo sub-calendário** (reusa `google_calendar_id` existente em `CalendarSyncAccount` se ainda existe no Google; cria novo sub apenas se o profissional deletou o anterior manualmente).

Detection job: `App\Jobs\Agenda\DetectGoogleSyncFailureJob` invocado pelo wrapper de cada chamada Google API; ao detectar 401/invalid_grant duas vezes seguidas em window <1min, marca `CalendarSyncAccount.status='disconnected'` + dispatch notification job.

### Rationale

- **Refresh automático** reduz "ruído de UX" (a maioria das falhas são tokens expirados, não revogação manual).
- **Notificação dual** equilibra "imediato" (banner in-app, visto na próxima sessão) com "atinge usuário ausente" (email — para profissionais que não logam no painel todo dia).
- **Sem auto-deletar eventos no Google** — clarify nº 10 já decidiu: eventos no Google permanecem mesmo após desconexão (médico decide o que fazer com eles).
- **Reconexão 1-clique** evita atrito; reuso de sub-calendário evita poluir o Google do médico com calendários duplicados a cada reconexão.

### Alternatives considered

- **Apenas email** (sem banner in-app): risco de profissional não ver o email; banner persistente é fricção mínima e visível.
- **Apenas banner in-app**: profissional ausente do painel por 7+ dias perde o aviso → sync silenciosamente parada.
- **Notificação SMS / WhatsApp**: caro e exagerado para a severidade do evento (não é urgência clínica).
- **Auto-deletar eventos no Google ao desconectar**: rejeitado em clarify nº 10. Cliente decide.

### Impact

- **`config/services.php`**: nada novo (reusa SMTP existente).
- **`CalendarSyncAccount`** ganha colunas: `last_disconnect_at` (nullable), `last_disconnect_notified_at` (nullable, dedup email), `last_reconnect_at` (nullable).
- **Novo Mailable**: `App\Mail\CalendarSyncDisconnectedMail` com template Markdown.
- **Componente Vue**: `<CalendarSyncDisconnectBanner />` montado em `App.vue` (visível em todas as rotas para profissionais com `calendar_sync.configure`).
- **Update spec**: NC nº 12 marcado como **RESOLVED via plan R5** (atualizar status no header do spec — não precisa mais ser deferred).

---

## R6 — Conversão TZ (clarify nº 13)

### Decision

**Server-side (Laravel)**:
- Persistir tudo em **`timestamptz UTC`** no PostgreSQL.
- `Carbon` para conversão; sessão PHP com `date.timezone=UTC`.
- `Professional.timezone` (nullable IANA string) override; quando NULL herda de `Tenant.timezone` (já existente Fase 0).
- API REST sempre retorna **ISO 8601 com offset** (ex.: `2026-06-15T14:00:00-03:00`); envelope da resposta carrega `timezone_display` IANA (`America/Sao_Paulo`).

**Client-side (Vue 3)**:
- **`luxon ^3`** para conversão. Parse ISO+offset → `DateTime`; render no TZ do contexto (tenant ou professional).
- Compose: `useTimezoneRenderer({ contextTz })` retorna funções `format`, `formatDate`, `formatTime`.

**Mensagens ao paciente (template Meta via Fase 3)**:
- Fase 5 emite evento com payload `{horario_brasilia: "14:00", tz_label: "horário de São Paulo"}` (label derivada via `IanaTimezoneCity::canonicalLabel('America/Sao_Paulo')`).
- Fase 3 monta o texto: `"Sua consulta amanhã às {{horario_brasilia}} ({{tz_label}}) com Dr. X"`.

### Rationale

- **UTC interno** elimina bugs em DST (horário de verão Brasil foi abolido em 2019 mas pode voltar; outros países afetam telemedicina futura).
- **Luxon** > `date-fns-tz` pela ergonomia de IANA explícito (`DateTime.fromISO(iso, { zone: 'America/Sao_Paulo' })` é mais direto).
- **Qualificador no texto** (`(horário de São Paulo)`) evita ambiguidade sem precisar resolver TZ do paciente (canais de mensageria não fornecem TZ confiável).

### Alternatives considered

- **`date-fns-tz`**: API menos ergonômica; precisa `zonedTimeToUtc` + `format` separado.
- **`dayjs` + `timezone` plugin**: plugin opcional, configuração manual; menos plugins = menos surface de bug.
- **Tudo no fuso do tenant** (sem UTC): bombas-relógio em DST e profissionais multi-TZ. Rejeitado.

### Impact

- **Migration**: `2026_05_14_000013_add_timezone_to_professionals_table.php` adiciona `timezone VARCHAR(64) NULLABLE`.
- **`config/app.php`**: `'timezone' => 'UTC'` (já é default Laravel; confirmar não foi alterado).
- **`package.json`**: adicionar `luxon ^3`.
- **Novo support class**: `App\Support\IanaTimezoneCity` com mapping IANA → label canônica (São Paulo, Manaus, Fortaleza, Belém, Cuiabá, Rio Branco, Noronha — cobertura completa do Brasil + fallback `Carbon::createFromTimestampUTC(0, $tz)->format('e')` para casos exóticos).

---

## R7 — Geração determinística de slots

### Decision

Algoritmo puro em `SlotGeneratorService::generate(Professional $prof, AppointmentType $type, CarbonPeriod $window): Collection`:

1. Carregar `ProfessionalSchedule` por dia da semana (Mon=1..Sun=7).
2. Para cada dia do window:
   - Aplicar `ScheduleException` (excluir períodos bloqueados).
   - Cortar em slots de `type.duration + type.buffer` min começando no início do bloco de trabalho.
   - Filtrar slots `WHERE NOT EXISTS Appointment WITH status IN (scheduled, confirmed, reagendada)` (LEFT JOIN antianti-pattern: usar `NOT EXISTS` subquery).
   - Filtrar slots `WHERE NOT EXISTS ExternalCalendarBusy` (clarify nº 10).
   - Filtrar slots `WHERE NOT EXISTS SlotReservation WITH released_at IS NULL AND expires_at > now()`.
3. Output: `Collection<{starts_at, ends_at, professional_id, type_id, timezone_display}>` paginado.

**Cache**:
- Cache Redis 60s key `agenda:slots:{tenant_id}:{professional_id}:{type_id}:{date}` para o **caso quente** (consulta de slots de hoje + amanhã, mais frequente — pacientes confirmam).
- Invalidação: dispatch em listener de `ConsultaCriada`, `ConsultaCancelada`, `SlotReservationCreated`, `ExternalCalendarBusyCreated` → cache forget.

### Rationale

- **Determinístico** (clarify nº 1): mesma agenda + mesmo dia + mesmo tipo = mesmos slots, sempre.
- **Pure function** sobre os dados → testável em unit test sem DB (mockando coleções).
- **Cache 60s** suporta SC-009 (p95 ≤ 300ms) sem invalidação pesada (invalidação granular por chave).
- **Não usa stored procedure** — algoritmo em PHP/Carbon é mais maintainable e testável; performance não exige.

### Alternatives considered

- **SQL recursivo** (`WITH RECURSIVE slots AS (...)`): mais rápido mas opaco, difícil de debugar; performance ganha é marginal (consultas atingem cache em 95% dos casos).
- **Geração on-the-fly sem cache**: viável mas joga o p95 acima de 300ms em tenants com agenda densa (50+ profissionais × 7 dias).

### Impact

- **Service**: `App\Services\Agenda\SlotGeneratorService` com método `generate()` pure (sem DB no método; recebe collections já carregadas) + helper `forApi()` que orquestra com cache.
- **Test**: `tests/Unit/Agenda/SlotGeneratorServiceTest.php` cobre 15+ cenários (intervalo, bloqueio, buffer, conflito, externo, reserva ativa).

---

## R8 — Idempotência de eventos de domínio

### Decision

Toda mutação importante carrega `idempotency_key` (UUID v7 — sortable temporal) gerada pelo cliente:

- `POST /agenda/consultas` (clarify nº 7 — IA usa para retry-safe)
- `POST /agenda/consultas/{id}/reagendar`
- `POST /agenda/consultas/{id}/cancelar`
- `POST /agenda/slots/{starts_at}/reservar`

Implementação:
- Coluna `idempotency_key UUID UNIQUE` em `appointments`, `appointment_reschedules`, `slot_reservations`.
- Service tenta INSERT; em 23505 unique_violation no `idempotency_key`, retorna o registro existente com HTTP 200 (não 201) + flag `idempotent_replay=true`.
- TTL implícito: registros não expiram. Cliente é responsável por usar chaves únicas (UUID v7 é trivial).

### Rationale

- **Retry-safe para IA Matricial**: IA pode falhar no meio de "criando consulta" e retentar — segundo POST com mesma chave devolve o mesmo `appointment_id` sem duplicação.
- **UUID v7** vs UUID v4: v7 é temporal-sortable, ajuda em índices B-tree e debug visual.
- **Sem TTL** evita complexidade de cleanup; volume é baixo (idempotency_key apenas em mutações importantes).

### Alternatives considered

- **`idempotency-key` header + Redis cache**: padrão Stripe. Funciona, mas perde auditabilidade pós-restart Redis. Em nosso modelo (mutações poucas, multi-tenant), persistir no DB é mais robusto.
- **Sem idempotency**: aceitar duplicação como "responsabilidade do cliente". Rejeitado — clarify nº 7 explicita que IA usa idempotency_key.

### Impact

- **Migrations**: `appointments`, `appointment_reschedules`, `slot_reservations` ganham `idempotency_key UUID UNIQUE NULLABLE` (NULLABLE para entries criados pelo painel humano que não exigem retry-safety, mas IA SEMPRE envia).
- **Form Requests**: validação `idempotency_key` ∈ {nullable, uuid v7}; quando ausente, gerar server-side.

---

## Decisões consolidadas — quick reference

| ID | Decisão | Dependência adicionada |
|---|---|---|
| R1 | `google/apiclient ^2.18` | composer |
| R2 | `@fullcalendar/vue3 ^7` + plugins | npm |
| R3 | Webhook direto `/webhooks/google-calendar/{channel_id}` + HMAC token | nenhuma |
| R4 | Tabela `slot_reservations` + partial unique index + cleanup cron 1min | nenhuma |
| R5 | OAuth revoke UX: refresh auto → banner in-app + email + reconectar 1-clique (resolve NC nº 12) | Mailable novo |
| R6 | UTC interno + Carbon server + Luxon client + IanaTimezoneCity label | npm `luxon ^3` |
| R7 | Algoritmo puro `SlotGeneratorService` + cache Redis 60s + invalidação granular | nenhuma |
| R8 | UUID v7 idempotency_key persistido no DB com UNIQUE | nenhuma |

**Status**: 8/8 decisões fechadas. NC nº 12 resolvido em R5. **Plan pronto para Phase 1.**
