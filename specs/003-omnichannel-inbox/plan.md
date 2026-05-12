# Implementation Plan: Fase 3 — Atendimento Omnichannel (Inbox Unificada)

**Branch**: `003-omnichannel-inbox` | **Date**: 2026-05-11 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-omnichannel-inbox/spec.md` (Status: Clarified — 17/17 NCs resolvidos)

## Summary

Construir o módulo de atendimento omnichannel do Paciente360 sobre as fundações das Fases 0 (multi-tenancy, auth, audit, Spatie) e 2 (Paciente, deduplicação por telefone, timeline). Esta fase entrega:

- **3 canais externos**: WhatsApp (via Twilio Programmable Messaging), Instagram Direct (via Meta Graph API direta — Twilio não suporta IG DM), Widget web embutível (bundle JS standalone hospedado pelo CRM).
- **Inbox unificada** com listagem em tempo real, filtros, busca full-text e atualização via Reverb (WebSocket).
- **Modelo stream contínuo de conversa** (1 conversa por par `paciente × canal`) com máquina de estados `aberta → pendente → resolvida → reaberta`, auto-resolve 72h configurável.
- **Atribuição** (manual e automática round-robin/profissional vinculado) e transferência com nota obrigatória.
- **Modo Humano Assume** (mecanismo de pausa/retomada da IA — IA real só chega na Fase 4; aqui só o estado + eventos).
- **Respostas Rápidas** (texto livre + 6 variáveis) com escopo dual (tenant + privadas) e dispatch por atalho `/`.
- **Compliance Meta (Princípio VI não-negociável)**: bloqueio runtime de envio fora da janela 24h sem template aprovado; templates sync read-only do Twilio.
- **LGPD em mídia**: S3 + AES-256, URLs assinadas 24h, retenção 1 ano (mídia) / 2 anos (texto), anonimização granular.
- **15 eventos de domínio** publicados como contrato público para Fases 4 (IA), 5 (Agenda) e 7 (Campanhas) consumirem.
- **8 abilities Spatie** novas (inbox.view/respond/assign/transfer/takeover_ai + channel.connect/disconnect + quick_reply.manage).

Abordagem técnica privilegia **reuso da Fase 0** (Auditable interface, TenantAwareJob, ResolveTenant middleware, AuditAttributesBuilder com mascaramento de CPF) e da **Fase 2** (`PacienteService`, `DedupService`, `eventos_timeline`, listener wildcard que popula timeline). Decisão arquitetural marcante: **Twilio substitui Meta Cloud API direta como provedor de WhatsApp Business API** (NC-1/Q1). Instagram Direct permanece via Meta Graph API direta porque Twilio não tem produto equivalente para IG DM em escala B2B.

## Technical Context

**Language/Version**: PHP 8.5 (backend), JavaScript ES2023 (frontend e widget — sem TypeScript)

**Primary Dependencies (NOVAS nesta fase)**:
- **Backend**:
  - `twilio/sdk` (^8.x) — cliente PHP oficial Twilio para WhatsApp (Programmable Messaging + Content API).
  - `guzzlehttp/guzzle` com middleware de retry (já presente como dep transitiva do Laravel; adicionar wrapper de retry exponencial).
  - **Decisão research R6**: implementação **própria de Circuit Breaker** (`CircuitBreakerService`) baseada em cache Redis. `stechstudio/laravel-circuit-breaker` rejeitado por não ser mantida em Laravel 13.
  - `league/csv` (já instalado Fase 2) — reuso eventual para exports de auditoria.
- **Frontend painel**:
  - `laravel-echo` + `pusher-js` (já presentes da Fase 0 stack) — clientes Reverb.
  - Sem libs novas no painel.
- **Widget web** (bundle separado):
  - **Zero deps runtime**. Bundle via Vite library mode com Tailwind embutido (~30KB gzip alvo). Apenas helpers JS nativos (fetch, EventSource para SSE fallback se Reverb cross-origin não fechar).

**Primary Dependencies (REUSADAS das Fases 0–2)**:
- Laravel 13, PHP 8.5, Sanctum SPA stateful, Horizon, **Reverb** (já configurado no Docker do projeto — esta fase é a primeira a exercitar broadcast real), Filament 5 (super admin), Spatie Permission (team mode), Cashier, Telescope, Sentry, Pail, PHPUnit 12.
- Vue 3 + Pinia + Vue Router + vue-i18n + Tailwind v4 + Vite (painel).
- Stack frontend de US1/US2 da Fase 2 reusada (ConfirmModal, AuthHeroPanel, useI18nFormat, api.js axios).
- **Spatie team mode** (`PermissionRegistrar::setPermissionsTeamId`) — listeners `TenantResolved` e `Authenticated` da Fase 0 já cobrem injeção do tenant.
- **AuditAttributesBuilder** (Fase 2 com CPF mask) — estender para mascarar **conteúdo de mensagem** em logs estruturados.
- **EventoTimeline** (Fase 2) — listener wildcard `RegistraEventoTimelineListener` consumirá eventos `MensagemRecebida`/`MensagemEnviada` desta fase para popular timeline do paciente automaticamente.

**Storage**:
- **PostgreSQL 18** (existente). Extensões `pg_trgm`, `unaccent`, `btree_gin` já habilitadas (Fase 2). Esta fase reusa `pg_trgm` para busca full-text em mensagens (research R5 — trigram vs tsvector).
- **Redis 7** (cache/queue/session — existente). Reverb também usa Redis como driver de broadcasting.
- **S3-compatível** (MinIO em dev/test via Docker; AWS S3 ou Cloudflare R2 em produção) via flysystem — **NOVO disk `media`** configurado em `config/filesystems.php`. Criptografia AES-256 em repouso (server-side encryption do bucket). URLs pre-assinadas com TTL 24h para acesso a mídia por atendente (research R4).

**Testing**: PHPUnit 12 (feature/unit/contract); Playwright (1 jornada E2E nova: paciente envia WhatsApp simulado → atendente vê na inbox → responde → status leitura aparece via Reverb). **Stress test** para RNF-003 (1000 conversas/tenant simultâneas) com Artillery ou k6 (research R8).

**Target Platform**: Linux server (Sail Docker); SPA navegador moderno; widget JS embutível em qualquer site moderno (ES2020+).

**Project Type**: Web application multi-tenant (SaaS B2B) — Laravel 13 backend + Vue 3 SPA + Filament 5 painel super admin + Widget JS standalone embutível em sites de terceiros.

**Performance Goals**:
- Mensagem chega ao paciente em **p95 < 2s** (lado servidor enfileira → API Twilio/Meta retorna `accepted`) — SC-002, RNF-001.
- Mensagem entre atendentes do mesmo tenant em **p95 < 2s** (Reverb broadcast) — SC-012.
- Inbox suporta **1000 conversas simultâneas/tenant** sem degradação — SC-003, RNF-003.
- Busca full-text em mensagens **p95 < 500ms** para 50k conversas/tenant — NC-13.
- Resposta de webhook Twilio/Meta em **HTTP 200 < 5s** (limite dos providers) — webhook handler enfileira em job e responde imediatamente.

**Constraints**:
- **Multi-tenancy obrigatório em TODA entidade nova** (Princípio II não-negociável). Validado por extensão do `TenantIsolationTest` da Fase 0.
- **Princípio VI (Conformidade Meta) NÃO-NEGOCIÁVEL**: envio fora da janela 24h sem template aprovado **bloqueado em runtime** no domain service (não só UI).
- **LGPD (Princípio I)**: conteúdo de mensagem **nunca** em log de aplicação; mascarado em logs estruturados; criptografia em repouso (`encrypted` cast Laravel) em `messages.body`.
- **Stack fixada** das Fases 0–2; sem novas libs core sem aprovação. Twilio SDK e wrapper de circuit breaker próprio são as únicas adições aprovadas.
- Cobertura ≥ 75% local; ≥ 70% global mantida.
- Documentação OpenAPI atualizada para todos os endpoints novos (Princípio IV — gate de PR).
- pt-BR em toda UI, e-mails e widget. Mensagens de erro de webhook em logs ficam em inglês (padrão técnico).

**Scale/Scope**:
- **MVP**: 1.000 conversas simultâneas/tenant, 10k mensagens/dia/tenant, 50k mensagens/mês/tenant.
- **Tamanhos**: ~28 controllers novos, ~15 services, ~12 migrations, ~25 Vue pages/components (inbox), 1 bundle Vite separado (widget), ~8 jobs, ~150 testes novos.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Princípio I — LGPD (NON-NEGOTIABLE)

- ✅ **Criptografia em repouso**: `messages.body` via cast `encrypted` Laravel (AES-256-CBC); mídia em S3 com SSE; credenciais Twilio/Meta criptografadas em `channels.credentials_encrypted` (research R4).
- ✅ **Pseudonimização em logs**: middleware `LogStructuredRequestData` (Fase 0) estendido para mascarar `body` de mensagens; AuditAttributesBuilder (Fase 2 com CPF mask) ganha regra equivalente para conteúdo de mensagem.
- ✅ **Retenção**: `messages` 2 anos texto / mídia 1 ano (configurável por tenant; NC-14). Job mensal arquiva/deleta conforme política.
- ✅ **Direito ao esquecimento**: listener `AnonimizaMensagensDoPacienteJob` consome `PacienteAnonimizado` (Fase 2) e aplica anonimização granular (recebidas apagam conteúdo, enviadas preservam; mídia recebida deleta do S3).
- ✅ **Log de auditoria** em `audit_logs` com retenção 1 ano (cobertura Fase 0).

### Princípio II — Isolamento Multi-Tenant (NON-NEGOTIABLE)

- ✅ **Global scope `BelongsToTenant`** aplicado a todas as 12 entidades novas (channels, conversations, messages, message_media, conversation_assignments, quick_replies, web_widget_configs, web_widget_sessions, assignment_rules, user_presence, channel_templates, webhook_events).
- ✅ **Reverb canais autorizados por tenant**: `tenant.{id}.inbox` e `tenant.{id}.conversa.{cid}` validam pertencimento em `routes/channels.php`.
- ✅ **Jobs `TenantAwareJob`**: `ProcessInboundMessageJob`, `SendOutboundMessageJob`, `ProcessMediaJob`, etc.
- ✅ **Cache prefixado por tenant**: já automático via `TenantResolved` listener Fase 0.
- ✅ **`InboxTenantIsolationTest`**: 100% dos endpoints autenticados novos cobertos.
- ✅ **Webhook público** (`/api/v1/webhooks/twilio/whatsapp`, `/api/v1/webhooks/instagram`, `/widget/v1/messages`) **NÃO** carrega tenant via subdomain. Tenant resolvido **dentro do job** via lookup do `channel_id` (Twilio carrega `MessagingServiceSid`; widget carrega `tenant_public_key`).

### Princípio III — Segurança Clínica e Auditabilidade da IA (NON-NEGOTIABLE)

- ⚠️ **N/A ativamente nesta fase, mas com preparação contratual**:
  - Esta fase **não emite** mensagens automáticas, diagnóstico, prescrição ou orientação clínica. **Nenhuma IA está ativa**.
  - **MAS** entrega o **contrato `ConversaIATogglingContract`** + campo `ai_paused_until` + eventos `ConversaAssumidaPorHumano` / `ConversaRetomadaPelaIA` que a Fase 4 consumirá para implementar guardrails de IA.
  - **Mitigação**: contrato versionado + teste de unit independente (`ConversaIATogglingContractTest`) congela API; subscriber dummy nos testes da Fase 3 valida que evento dispara com payload correto. Fase 4 plugará subscriber real sem retrofit no histórico.

### Princípio IV — Spec-Driven Test-First

- ✅ **Spec aprovado**: `spec.md` Status `Clarified` (17/17 NCs resolvidos com footnotes).
- ✅ **TDD respeitado**: cada AC do spec terá teste falhando antes da implementação (orquestrado em `tasks.md`).
- ✅ **Cobertura ≥ 75% local / ≥ 70% global** mantida.
- ✅ **E2E**: 1 jornada nova Playwright (paciente WhatsApp → atendente responde via Reverb).
- ✅ **Migrations imutáveis**: arquivos com prefixo `2026_MM_DD_HHMMSS_create_messaging_*`.
- ✅ **OpenAPI Scribe**: atualizado; `openapi:check` continua exit 0.
- ✅ **Pint + test**: gate de merge inalterado.

### Princípio V — Observabilidade

- ✅ **Logs estruturados** com `tenant_id, user_id, request_id, conversation_id, channel_id, message_id` em toda request HTTP, job de fila e callback de webhook.
- ✅ **Eventos auditáveis**: 15 eventos `Auditable` publicados → listener wildcard grava em `audit_logs` automaticamente.
- ✅ **Métricas Prometheus novas** (research R7):
  - `paciente360_webhook_received_total{provider, status}` — counter de webhooks por provider.
  - `paciente360_webhook_processing_duration_seconds{provider}` — histogram `webhook recebido → mensagem visível na UI`.
  - `paciente360_outbound_message_total{provider, status}` — counter de envios outbound.
  - `paciente360_queue_size{queue}` — gauge de filas `webhooks-meta`, `outbound-messages`, `media-processing`, `reverb`.
  - `paciente360_circuit_breaker_state{provider}` — gauge 0=fechado, 1=meio-aberto, 2=aberto.
  - `paciente360_conversations_active{tenant_id, channel}` — gauge para alerta de degradação.
- ✅ **Sentry**: contexto estendido com `conversation_id`, `channel_id` em erros.
- ✅ **SLA**: nada nesta fase compromete os 99,5%.

### Princípio VI — Conformidade Meta nos Disparos (NON-NEGOTIABLE)

- ✅ **Janela 24h validada em domain service** `MessageDispatchService::send(Conversation $c, OutboundMessage $m)`:
  - Checa `c.last_inbound_message_at`. Se > 24h e `m.content_type != template` → lança `WindowExpiredWithoutTemplateException` (mapeada para 422 + audit `mensagem.bloqueada_fora_janela`).
  - Backend rejeita mesmo se UI permitir; UI é defense-in-depth.
- ✅ **Templates aprovados sync read-only** via `ChannelTemplateSyncJob` periódico (Twilio Content API; armazena em `channel_templates`).
- ✅ **Quality Rating drop** dispara `CanalDegradado` event e desabilita envios automáticos (preparação Fase 4/7); envios manuais permitidos com aviso intrusivo (NC-17).
- ✅ **Cada bloqueio gera evento auditável** com motivo explícito.

### Princípio VII — Segurança Operacional (NON-NEGOTIABLE)

- ✅ **Hash de senhas**: já cobertos (argon2id Fase 0).
- ✅ **TLS 1.3** em produção (cobertura infra).
- ✅ **Rate limit por tenant + endpoint** (RNF-009): 3 limiters novos em `RouteServiceProvider`:
  - `inbox` (60 req/min/user+tenant) — endpoints de inbox painel.
  - `widget-public` (30 req/min/IP) — endpoints widget público sem auth.
  - `webhook-meta` (1000 req/min/global) — defesa contra ataque; Meta/Twilio fazem retry sob esse limite.
- ✅ **Bloqueio temporário login**: já ativo (Fase 0).
- ✅ **Webhook signature validation**: `X-Twilio-Signature` (HMAC SHA-256 com Auth Token Twilio); `X-Hub-Signature-256` (HMAC SHA-256 com Meta App Secret); widget assina com `tenant_public_key` + nonce. Rejeita 403 se assinatura inválida.
- ✅ **Credenciais Twilio/Meta** em vault: campo `channels.credentials_encrypted` JSONB cifrado via cast `encrypted`. Nunca em logs.

### Resultado do gate

**✅ APROVADO com 1 ressalva documentada** (Princípio III ⚠️ — preparação contratual, sem violação). Prosseguir para Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/003-omnichannel-inbox/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — 9 decisões técnicas
├── data-model.md        # Phase 1 — 12 entidades + Mermaid
├── quickstart.md        # Phase 1 — provisioning Twilio/Meta + ngrok (gerado em sessão separada)
├── contracts/
│   └── openapi.yaml     # Phase 1 — endpoints inbox + webhooks (sessão separada)
├── checklists/
│   └── requirements.md  # Já existe (do /speckit.specify + /speckit.clarify)
└── tasks.md             # Phase 2 — gerado pelo /speckit.tasks
```

### Source Code (repository root)

**Bounded context** `app/Domain/Messaging/` agrupa todo o domínio omnichannel. Subnamespaces por agregado:

```text
app/Domain/Messaging/
├── Channel/
│   ├── Models/                    # Channel, ChannelTemplate
│   ├── Adapters/                  # ChannelAdapter interface + WhatsAppCloudAdapter (Twilio) + InstagramGraphAdapter (Meta) + WebWidgetAdapter
│   ├── Services/                  # ChannelService, ChannelTemplateSyncService, QualityRatingMonitor
│   └── Events/                    # CanalConectado, CanalDesconectado, CanalComFalha, CanalDegradado
├── Conversation/
│   ├── Models/                    # Conversation, ConversationAssignment
│   ├── Services/                  # ConversationService, ConversationResolverService, AutoResolveJob, HumanTakeoverService
│   ├── StateMachine/              # ConversationStatusTransitions (enum + state machine)
│   ├── Contracts/                 # ConversaIATogglingContract (interface para Fase 4)
│   └── Events/                    # ConversaCriada, ConversaAssumidaPorHumano, ConversaRetomadaPelaIA, ConversaResolvida, ConversaReaberta, ConversaVinculadaAPaciente
├── Message/
│   ├── Models/                    # Message, MessageMedia
│   ├── Services/                  # MessageDispatchService, MessageReceiveService, MediaUploadService, RetentionPurgeService
│   ├── Exceptions/                # WindowExpiredWithoutTemplateException, ChannelDegradedException, UnsupportedMediaTypeException
│   └── Events/                    # MensagemRecebida, MensagemEnviada
├── Assignment/
│   ├── Models/                    # AssignmentRule
│   ├── Services/                  # AssignmentService, RoundRobinStrategy, PatientOwnerStrategy, TransferService
│   └── Events/                    # ConversaAtribuida, ConversaTransferida
├── QuickReply/
│   ├── Models/                    # QuickReply
│   └── Services/                  # QuickReplyService, VariableSubstitutor
├── Presence/
│   ├── Models/                    # UserPresence
│   └── Services/                  # PresenceTrackerService
├── Widget/
│   ├── Models/                    # WebWidgetConfig, WebWidgetSession
│   └── Services/                  # WidgetAuthService, WidgetSessionService
└── Infrastructure/
    ├── CircuitBreaker/            # CircuitBreakerService (Redis-backed; research R6)
    ├── Webhook/                   # WebhookEvent model + TwilioWebhookValidator + MetaWebhookValidator + WidgetWebhookValidator
    └── Listeners/                 # RegistraMensagemNaTimelineListener, AnonimizaMensagensDoPacienteListener

app/Http/
├── Controllers/Api/V1/Inbox/      # ~10 controllers — channels, conversations, messages, assignments, takeover, quick_replies, presence, channel_templates
├── Controllers/Webhooks/          # TwilioWhatsAppWebhookController, MetaInstagramWebhookController
├── Controllers/Widget/            # WidgetConfigController, WidgetSessionController, WidgetMessageStreamController (SSE fallback)
├── Requests/Inbox/                # ~10 form requests
├── Resources/V1/                  # ~10 resources
└── Policies/                      # ChannelPolicy, ConversationPolicy, MessagePolicy, AssignmentPolicy, QuickReplyPolicy

app/Jobs/Messaging/                # ProcessInboundMessageJob, SendOutboundMessageJob, ProcessMediaUploadJob, SyncChannelTemplatesJob, AutoResolveConversationsJob, AnonimizaMensagensDoPacienteJob, PurgeExpiredMediaJob, PurgeExpiredMessagesJob

database/migrations/               # 13 migrations prefixadas `2026_05_11_create_messaging_*`
config/messaging.php               # NOVO — limites, retenção, defaults
routes/api.php                     # endpoints `/api/v1/inbox/...`, `/api/v1/webhooks/...`, `/widget/v1/...`
routes/channels.php                # Reverb auth `tenant.{id}.inbox` + `tenant.{id}.conversa.{cid}`

resources/js/                      # painel Vue 3 — pages/inbox + components/inbox + composables (useReverbConnection, useInboxFilters, useNotifications) + stores Pinia (inbox, presence)
resources/widget/                  # NOVO bundle separado — Vite library mode (~30KB gzip)
├── src/widget.js, ui.js, transport.js, auth.js, i18n.js
└── vite.config.widget.js

tests/Feature/Fase3/               # Channels, Webhooks, Conversations, Messages, Assignment, Takeover, QuickReplies, Inbox, Widget
tests/Unit/Messaging/              # ChannelAdapterContractTest, MessageDispatchServicePolicyTest, CircuitBreakerServiceTest, VariableSubstitutorTest, ConversationStatusTransitionsTest
tests/e2e/                         # inbox-whatsapp-roundtrip.spec.ts
```

**Structure Decision**: **Bounded context em `app/Domain/Messaging/`** com subnamespaces por agregado (Channel, Conversation, Message, Assignment, QuickReply, Presence, Widget, Infrastructure). Quebra o padrão "tudo em `app/Services/Dominio/`" das Fases 0–2 para acomodar a **complexidade real desta fase** (3 adapters, máquina de estados, circuit breaker, contratos com Fase 4). Aceito como **complexidade justificada** (vide tabela abaixo). Demais convenções (Controllers em `app/Http/Controllers/Api/V1/`, Jobs em `app/Jobs/`, etc.) seguem a Fase 0.

## Phase 0 / Phase 1 Reference

- **Phase 0 — Research**: [research.md](./research.md) — 9 decisões técnicas (Reverb stack, Twilio onboarding, Instagram Graph API direto, idempotência webhook, criptografia repouso, trigram, circuit breaker próprio, versionamento Graph API, plano de carga).
- **Phase 1 — Data Model**: [data-model.md](./data-model.md) — 12 entidades + diagrama Mermaid + índices justificados.
- **Phase 1 — Contracts**: [contracts/openapi.yaml](./contracts/openapi.yaml) — endpoints inbox + webhooks providers + widget public. **Gerado em sessão separada após este plan.**
- **Phase 1 — Quickstart**: [quickstart.md](./quickstart.md) — provisioning Twilio/Meta + env vars + ngrok ports. **Gerado em sessão separada após este plan** (esboço inicial abaixo na seção "Plano de provisionamento").

## Convenções de implementação (recap + adições)

Reforço das Fases 0–2 + 10 convenções específicas desta fase:

1. **Sail obrigatório**: `vendor/bin/sail artisan/composer/npm`.
2. **Pint clean** antes de PR.
3. **TDD**: cada AC tem teste antes da implementação.
4. **Multi-tenant isolation**: cada endpoint entra em `InboxTenantIsolationTest`.
5. **i18n pt-BR**: nada hardcoded em outras línguas.
6. **OpenAPI Scribe + drift check**: gate de CI.
7. **Eventos `Auditable`**: cada ação sensível dispara evento → audit + timeline (via listener Fase 2).
8. **Reuso de UI Fase 0/2**: `ConfirmModal`, `useI18nFormat`, etc.
9. **Migrations idempotentes e imutáveis**: prefixadas `2026_05_11_create_messaging_*`.
10. **Jobs em filas dedicadas**: `webhooks-meta`, `outbound-messages`, `media-processing`, `reverb`.

**Adições da Fase 3**:

11. **Bounded context `app/Domain/Messaging/`** com subnamespaces por agregado.
12. **`ChannelAdapter` interface** + 3 implementações isoladas (WhatsApp/Instagram/Web).
13. **Idempotência webhook**: tabela `webhook_events` com UNIQUE `(provider, external_id)` + INSERT ... ON CONFLICT DO NOTHING.
14. **Idempotency key na saída**: cada `SendOutboundMessageJob` gera `idempotency_key = sha256(tenant_id + conversation_id + body + timestamp_ms)` enviada ao Twilio como header.
15. **Reverb canais autorizados** em `routes/channels.php` validam Spatie ability + tenant pertencimento.
16. **Form Requests para webhook payloads**: validam assinatura + schema antes de enfileirar job.
17. **Resources V1**: shape estável; mudanças exigem `V2`.
18. **Domain services puros** (sem acesso a request HTTP) — facilita reuso em jobs.
19. **Circuit breaker via Redis** envolve todas chamadas Twilio + Meta.
20. **Mensagens cifradas em repouso**: `messages.body` cast `encrypted` (AES-256-CBC); RNF-007.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| **Bounded context `app/Domain/Messaging/`** (novo padrão vs. `app/Services/Dominio/` das Fases 0–2) | 3 adapters + máquina de estados + circuit breaker + contratos Fase 4 + 12 entidades em 1 fase. Acoplamento alto entre Conversation/Message/Channel/Adapter justifica isolamento físico. | Manter em `app/Services/Messaging/`: ficaria com 15+ classes na mesma pasta, mistura responsabilidades (adapters próximos de services próximos de jobs). Fragiliza navegabilidade. |
| **Bundle JS separado para widget** (Vite library mode, fora do bundle do painel) | Widget embarca em sites de **terceiros**. Não pode arrastar Pinia, Vue Router, vue-i18n, Tailwind do painel (>500KB). | Reusar bundle do painel: inviável — peso destrói Core Web Vitals do site do cliente. Sem widget: perde US-4.3 inteira. |
| **`webhook_events` tabela dedicada** (vs. usar audit_logs) | Webhooks chegam em volume alto (~10x mensagens diretas). Audit precisa imutabilidade; webhook_events precisa UNIQUE constraint + dedup + retry tracking. | Usar audit_logs: trigger de imutabilidade impede UPDATE de status retry. Misturar concerns: audit é "o que humano fez"; webhook_events é "o que provider entregou". |
| **Implementação própria de Circuit Breaker** (vs. lib externa) | `stechstudio/laravel-circuit-breaker` não mantida para Laravel 13. Pattern simples (~150 linhas) com Redis. | Lib desatualizada exige fork; outras libs maiores (Hystrix port) carregam dependências e abstrações desnecessárias para 2 providers. |

## Verificação Constitucional pós-design

Reavaliação após produção de `research.md`, `data-model.md` e `contracts/openapi.yaml`:

| Princípio | Status | Como atendido |
|---|---|---|
| **I — LGPD** | ✅ | `messages.body` cifrado (`encrypted` cast); URLs S3 assinadas 24h; `AnonimizaMensagensDoPacienteJob` consome `PacienteAnonimizado` Fase 2; retenção 2y/1y em `config/messaging.php`; logs estruturados mascarados via override de `LogStructuredRequestData`. |
| **II — Multi-tenant** | ✅ | `BelongsToTenant` em 12 tabelas; Reverb auth em `routes/channels.php`; `InboxTenantIsolationTest` extensão obrigatória; webhooks resolvem tenant via lookup de `channel_id`. |
| **III — IA Auditável** | ⚠️ **Preparação contratual sem violação** | Contrato `ConversaIATogglingContract` + `ai_paused_until` + eventos `ConversaAssumidaPorHumano`/`ConversaRetomadaPelaIA` prontos para Fase 4 plugar subscriber. Sem geração automática nesta fase. **Mitigação**: teste de contrato isolado congela API. |
| **IV — Spec-Driven Test-First** | ✅ | TDD em todos os 47 ACs; cobertura ≥ 75%; OpenAPI Scribe; Pint clean; migrations imutáveis. |
| **V — Observabilidade** | ✅ | 6 métricas Prometheus novas; logs estruturados; Sentry contextualizado; eventos `Auditable` em `audit_logs`. |
| **VI — Conformidade Meta** | ✅ **NÃO-NEGOCIÁVEL ATENDIDO** | Janela 24h validada em `MessageDispatchService`; `WindowExpiredWithoutTemplateException` 422 + audit; templates sync read-only; `QualityRatingMonitor` desabilita auto em `Low`/`Flagged`. |
| **VII — Segurança Operacional** | ✅ | 3 rate limiters novos; webhook signature validation HMAC; credenciais cifradas em `channels.credentials_encrypted`; circuit breaker contra provider abuse. |

**Resultado**: ✅ **APROVADO** com Princípio III flagado como preparação contratual. Pronto para `/speckit.tasks`.

---

## Plano de provisionamento — env vars + ngrok ports (referência rápida)

Quando a fase de implementação chegar nas tasks de **integração externa** (US-4.1/4.2/4.3), **você precisa provisionar antes**. Te aviso explicitamente em cada lote. Detalhe completo virá em `quickstart.md`; abaixo é o resumo para você começar a separar credenciais.

### Variáveis de ambiente novas (acrescentar ao `.env.example`)

**Twilio (WhatsApp via NC-1/Q1)**:
```env
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_WHATSAPP_FROM_DEFAULT=whatsapp:+5511XXXXXXXXX
TWILIO_WEBHOOK_SIGNING_KEY=${TWILIO_AUTH_TOKEN}    # mesmo valor; documenta intenção
TWILIO_CONTENT_API_VERSION=2010-04-01
```

**Meta/Facebook (Instagram via Graph API direta)**:
```env
META_APP_ID=xxxxxxxxxxxxxxxx
META_APP_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
META_GRAPH_API_VERSION=v21.0
META_WEBHOOK_VERIFY_TOKEN=<token-aleatório-256-bits-gerado-por-você>
```

**Widget público**:
```env
WIDGET_PUBLIC_DOMAIN=widget.crm.com.br          # prod
WIDGET_PUBLIC_DOMAIN_DEV=widget.lvh.me          # dev
WIDGET_PUBLIC_PROTOCOL=https
```

**Storage S3 (NOVO disk `media`)**:
```env
FILESYSTEM_DISK_MEDIA=s3
AWS_BUCKET_MEDIA=paciente360-media              # bucket dedicado (separado dos imports Fase 2)
AWS_REGION_MEDIA=us-east-1
AWS_ACCESS_KEY_ID_MEDIA=...
AWS_SECRET_ACCESS_KEY_MEDIA=...
AWS_USE_PATH_STYLE_ENDPOINT=true                # MinIO dev
AWS_ENDPOINT_MEDIA=http://minio:9000            # dev
```

**Reverb (confirmar valores no container Docker existente)**:
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...                               # ler do .env atual ou compose
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=reverb                              # nome do container
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Exposição de portas via ngrok (para testes locais com Twilio + Meta reais)

```bash
# Tunnel único para o app Laravel (Sail nginx na porta 80)
ngrok http --domain=paciente360-dev.ngrok-free.app 80

# Configurar no painel Twilio (Console → Messaging → Settings → WhatsApp Sandbox):
#   "When a message comes in":
#     POST https://paciente360-dev.ngrok-free.app/api/v1/webhooks/twilio/whatsapp
#   "Status callback URL":
#     POST https://paciente360-dev.ngrok-free.app/api/v1/webhooks/twilio/status

# Configurar no painel Meta for Developers (Apps → seu App → Webhooks → Instagram):
#   Callback URL:    https://paciente360-dev.ngrok-free.app/api/v1/webhooks/instagram
#   Verify Token:    <valor de META_WEBHOOK_VERIFY_TOKEN>
#   Subscription Fields: messages, messaging_postbacks, message_reactions
```

**Reverb (WebSocket) — não precisa ngrok local**. Cliente JS conecta direto no container Docker exposto na porta 8080 do host; em dev o cliente é o mesmo host (`http://localhost:8080`).

**Widget JS — só precisa tunnel quando testar embed em site externo real**. Teste local em HTML estático basta servir do mesmo host (`http://paciente-alfa.lvh.me/widget/v1/{key}.js`).

### Lista de pré-requisitos para te lembrar quando o lote de integração começar

1. ✅ Provisionar conta Twilio (Sandbox WhatsApp grátis para testes) → obter `ACCOUNT_SID`/`AUTH_TOKEN`.
2. ✅ Provisionar app Meta for Developers + Instagram Business test account (pode usar Pages Test Tools).
3. ✅ Confirmar bucket S3 (ou criar bucket MinIO em dev).
4. ✅ Confirmar Reverb container rodando + portas expostas no `compose.yaml` (você já tem).
5. ✅ Setup ngrok com domínio fixo (recomendado: gratuito agora com `--domain`).
6. ✅ Gerar `META_WEBHOOK_VERIFY_TOKEN` (256 bits hex, qualquer ferramenta).

Quando chegar nesse ponto, **te aviso explicitamente com checklist enxuto**.
