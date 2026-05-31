---
description: "Lista de tarefas para Fase 18 — Conversa Reativa, Multimodal e Auto-Curadoria do Kanban via IA"
---

# Tasks: Conversa Reativa, Multimodal e Auto-Curadoria do Kanban via IA

**Input**: Design documents from `/specs/018-ai-multimodal-mcp/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md (todos presentes)

**Tests**: incluídos — a Constituição IV (Spec-Driven Test-First) é NON-NEGOTIABLE; toda mudança deve ter teste programático.

**Organization**: tasks particionadas por user story (US1–US7) com fases de Setup e Foundational antes. **MVP mínimo viável = Setup + Foundational + US7 + US1 + US2 + US3** (US7 entra como P1 por ser infra-crítica sob Q2=B). US4, US5, US6 são entregas incrementais (P2).

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependências)
- **[Story]**: user story (US1..US7) — obrigatório nas phases de US
- Inclui caminho absoluto/relativo do arquivo na descrição

## Path Conventions

Web app (Laravel 13 + Vue 3) — base no plano:
- **Backend**: `app/Domain/Ai/{Coalescing,Mcp,Voice,Tools,...}/`, `app/Domain/Messaging/{Audio,RateLimiting,...}/`, `app/Domain/Crm/Kanban/`, `app/Http/{Controllers,Requests,Resources}/`, `database/migrations/`, `config/`, `routes/`
- **Frontend**: `resources/js/{pages,components,stores}/`
- **Tests**: `tests/{Feature,Unit,e2e}/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: instalação de dependências, env e estruturas-base. Não bloqueia desenvolvimento de domínio, só viabiliza.

- [X] T001 Instalar `laravel/mcp:^0.x` via `vendor/bin/sail composer require laravel/mcp:^0.x` e confirmar versão em `composer.json`
- [X] T002 Publicar config do MCP: `vendor/bin/sail artisan vendor:publish --provider="Laravel\\Mcp\\McpServiceProvider"` (arquivo `config/mcp.php`)
- [X] T003 [P] Adicionar entradas em `.env.example` para todas as variáveis novas do quickstart.md §3 (AI_COALESCE_*, AI_TOOLS_VIA_MCP, MCP_*, MESSAGING_STT_*, MESSAGING_TTS_*, MESSAGING_RATE_*, MESSAGING_COOLDOWN_*)
- [X] T004 [P] Adicionar serviço `mcp-server` ao `compose.yaml` no profile `mcp` (build do mesmo Dockerfile do app, command para servir routes/mcp.php em :8090, mesma network/db/redis)
- [X] T005 [P] Criar arquivo `config/ai.php` block `coalesce` + `mcp` (passive_debounce_s, max_turn_s, max_reprocesses, circuit_breaker thresholds) ler de env com defaults
- [X] T006 [P] Criar arquivo `config/messaging.php` blocks `audio.stt`, `audio.tts`, `audio.outbound.triggers`, `rate.per_conversation`, `rate.per_identifier`, `rate.window_minutes`, `cooldown.minutes`
- [X] T007 [P] Criar arquivo `config/voice-catalog.php` com `system_default_voice_id` lookup
- [X] T008 [P] Adicionar fila `transcription` em `config/horizon.php` (supervisor próprio, minProcesses=1, maxProcesses=4)

**Checkpoint**: dependências instaladas, config base presente. Pode começar Phase 2.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: schema do banco, models, seeds, permissions, rate limiter. **Bloqueia todas as user stories**.

**⚠️ CRITICAL**: nenhuma user story pode começar até este phase fechar (exceto tasks marcadas [P] que de fato sejam ortogonais — modelar a US à vontade).

### Migrations (executar em ordem cronológica; nomes timestampados `2026_06_*`)

- [X] T009 Criar migration `..._add_transcricao_to_consent_finalidade_enum.php` com `DB::statement("ALTER TYPE consent_finalidade ADD VALUE IF NOT EXISTS 'transcricao'")`, `$withinTransaction = false`, sem `down()` (docblock explica)
- [X] T010 [P] Criar migration `..._create_voice_catalog_table.php` por data-model.md §3 (sem tenant_id — global; UNIQUE `(provider, provider_voice_id)`; UNIQUE parcial 1 system_default por language)
- [X] T011 [P] Criar migration `..._create_persona_test_sessions_table.php` por data-model.md §4 (uuid PK, FK ai_personas + users + personal_access_tokens)
- [X] T012 [P] Criar migration `..._create_kanban_pipeline_mappings_table.php` por data-model.md §5 (UNIQUE `(tenant_id, event_kind)`)
- [X] T013 [P] Criar migration `..._create_kanban_curation_events_table.php` por data-model.md §6 (índices `(tenant_id, paciente_id, created_at)` e `(tool_invocation_id) WHERE NOT NULL`)
- [X] T014 [P] Criar migration `..._create_audio_transcriptions_table.php` por data-model.md §1 (UNIQUE `(tenant_id, message_id)`)
- [X] T015 [P] Criar migration `..._create_audio_syntheses_table.php` por data-model.md §2 (UNIQUE `(tenant_id, message_id)`)
- [X] T016 [P] Criar migration `..._create_mcp_circuit_breaker_snapshots_table.php` por data-model.md §7 (sem tenant_id — global)
- [X] T017 [P] Criar migration `..._add_voice_id_to_ai_personas.php` (FK nullable → voice_catalog ON DELETE SET NULL)
- [X] T018 [P] Criar migration `..._add_transcription_columns_to_messaging_messages.php` (transcription_id, is_audio_origin, sandbox, sandbox_session_id + índices)
- [X] T019 [P] Criar migration `..._add_cooldown_to_messaging_conversations.php` (cooldown_until, cooldown_reason + índice parcial)
- [X] T020 Criar migration `..._add_is_initial_to_funil_colunas.php` (BOOL default false + UNIQUE parcial `(tenant_id) WHERE is_initial=true`); backfill: 1 coluna por tenant marcada por slug existente ('new' ou primeira por posição)
- [X] T021 Decidir e criar migration de `default_voice_id` no tenant — **executar ANTES desta task**: `vendor/bin/sail artisan tinker --execute 'echo Schema::hasTable("tenant_settings") ? "tenant_settings" : (Schema::hasColumn("tenants","default_voice_id") ? "tenants" : "CREATE_NEW");'`. Decisão: se retornar `tenant_settings` → `ALTER TABLE tenant_settings ADD COLUMN default_voice_id BIGINT NULL REFERENCES voice_catalog(id) ON DELETE SET NULL`; se retornar `tenants` → ADD COLUMN diretamente em `tenants`; se `CREATE_NEW` → criar `tenant_voice_settings (id, tenant_id UNIQUE, default_voice_id, tts_enabled DEFAULT true, created_at, updated_at)` minimalista. Atualizar data-model.md §11 com o caminho escolhido como nota de migration.
- [X] T022 Executar `vendor/bin/sail artisan migrate:fresh --env=testing` + `vendor/bin/sail artisan test --compact tests/Feature/Database/MigrationsRunCleanTest.php` (ou criar smoke test inline) — garante migrations limpas

### Enum & Models

- [X] T023 [P] Adicionar `Transcricao` ao enum PHP `App\Domain\Consent\Enums\ConsentFinalidade` (Fase 8) + atualizar testes existentes que enumeram valores
- [X] T024 [P] Criar Eloquent model `App\Domain\Ai\Voice\Models\VoiceCatalogEntry` em `app/Domain/Ai/Voice/Models/VoiceCatalogEntry.php` (sem global scope — catálogo global)
- [X] T025 [P] Criar Eloquent model `App\Domain\Ai\Persona\Models\PersonaTestSession` em `app/Domain/Ai/Persona/Models/PersonaTestSession.php` com global scope tenant
- [X] T026 [P] Criar Eloquent model `App\Domain\Crm\Kanban\Models\KanbanPipelineMapping` em `app/Domain/Crm/Kanban/Models/KanbanPipelineMapping.php` com global scope tenant
- [X] T027 [P] Criar Eloquent model `App\Domain\Crm\Kanban\Models\KanbanCurationEvent` em `app/Domain/Crm/Kanban/Models/KanbanCurationEvent.php` com global scope tenant
- [X] T028 [P] Criar Eloquent model `App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription` em `app/Domain/Messaging/Audio/Inbound/Models/AudioTranscription.php`
- [X] T029 [P] Criar Eloquent model `App\Domain\Messaging\Audio\Outbound\Models\AudioSynthesis` em `app/Domain/Messaging/Audio/Outbound/Models/AudioSynthesis.php`
- [X] T030 [P] Criar Eloquent model `App\Domain\Ai\Mcp\Models\McpCircuitBreakerSnapshot` em `app/Domain/Ai/Mcp/Models/McpCircuitBreakerSnapshot.php` (sem tenant scope)
- [X] T031 [P] Estender `App\Domain\Ai\Persona\Models\AiPersona` (Fase 15) com `belongsTo(VoiceCatalogEntry, 'voice_id')` + fillable
- [X] T032 [P] Estender `App\Domain\Messaging\Message\Models\Message` (Fase 3) com `belongsTo(AudioTranscription, 'transcription_id')` + `belongsTo(PersonaTestSession, 'sandbox_session_id')` + casts (is_audio_origin, sandbox)
- [X] T033 [P] Estender `App\Domain\Messaging\Conversation\Models\Conversation` (Fase 3) com casts `cooldown_until` (datetime) e helper `isOnCooldown()`

### Seeders

- [X] T034 [P] Criar `database/seeders/VoiceCatalogSeeder.php` — 4 vozes ElevenLabs PT-BR (2F: acolhedora+profissional; 2M: profissional+calmo); 1 marcada `is_system_default=true`
- [X] T035 [P] Criar `database/seeders/DefaultKanbanPipelineMappingSeeder.php` — itera tenants existentes, idempotente; cria/upserta os 7 mappings padrão (lead_created → 'new' etc) por research.md §R7; cria `funil_colunas` faltantes com `is_system=true`

### Permissions, RateLimiter, Eventos

- [X] T036 [P] Adicionar `ai.persona.test` ao `PermissionSeeder` (guard `web`); atribuir ao role "Admin Clínica" por default
- [X] T037 [P] Registrar 2 RateLimiters em `app/Providers/RouteServiceProvider.php`: `messaging:inbound:per-conversation` (default 30/10min by conversation_id) e `messaging:inbound:per-identifier` (default 100/10min by tenant_id:identifier) — ler de `config('messaging.rate.*')`
- [X] T038 [P] Criar event class `App\Domain\Crm\Kanban\Events\KanbanCardCurated` (Auditable, payload: paciente_id, event_kind, source, applied, tenant_id) para audit

### Métricas Prometheus base

- [X] T039 Registrar novas métricas no exporter Prometheus existente: `ai_coalesce_messages_per_turn` (histogram), `ai_coalesce_reprocess_count` (histogram), `ai_coalesce_flush_reason_total` (counter), `ai_mcp_request_duration_seconds` (histogram), `ai_mcp_circuit_state` (gauge 0/1/2), `ai_mcp_circuit_transitions_total` (counter), `ai_stt_duration_seconds` (histogram), `ai_tts_duration_seconds` (histogram), `ai_tts_fallback_to_text_total` (counter), `ai_rate_limit_cooldown_active_total` (counter), `ai_kanban_curation_events_total` (counter)

**Checkpoint**: schema/models/seeds/permissions prontos. Pode começar US7 + US1 + US2 + US4 em paralelo (independentes de US7); US3, US5, US6 esperam US7.

---

## Phase 3: User Story 7 — Servidor MCP como caminho único de tools (Priority: P1) ⚡ INFRA-CRÍTICA

**Goal**: subir o servidor MCP local (laravel/mcp v0) com as 6 capabilities equivalentes às tools da Fase 17, autenticação Sanctum PAT + tenant-scoped, circuit breaker auto-revert para tools nativas, e modelo de sandbox. **US3 e US6 dependem desta phase**.

**Independent Test**: cliente MCP autenticado lista capabilities + invoca `get-clinic-info` retornando apenas dados do tenant da credencial; tentativa cross-tenant é negada; circuit breaker abre após N falhas e auto-reverte. **Suíte Fase 15 + 17 verde com `AI_TOOLS_VIA_MCP=true` (gate FR-053)**.

**Why first among P1s**: decisão do owner (user input) — capabilities são pré-requisito de US3 (`update-lead-profile`) e US6 (sandbox token). Sob Q2=B (substituição), o MCP é infra-crítica; sem ele, IA produção fica sem tools quando o flag promove.

### Estrutura do servidor + auth

- [X] T040 [US7] Criar `routes/mcp.php` (registrar nas rotas do `MCPServerProvider`); registrar 6 capabilities iniciais
- [X] T041 [US7] Implementar `App\Domain\Ai\Mcp\Server\Auth\McpTokenGuard` em `app/Domain/Ai/Mcp/Server/Auth/McpTokenGuard.php` — valida Sanctum PAT, ability `mcp.invoke`, popula contexto de tenant (a partir de coluna nova `personal_access_tokens.tenant_id` ou tabela auxiliar — definir migration aqui), propaga `sandbox` metadata
- [X] T042 [US7] Criar migration aux `..._add_tenant_id_to_personal_access_tokens.php` (nullable + index) ou pivot table `mcp_token_metadata (token_id, tenant_id, sandbox)` — decisão em PR; cuidado para não quebrar Fase 4 (tokens existentes da SPA não usam tenant_id direto, herdam do user)
- [X] T043 [US7] Implementar `App\Domain\Ai\Mcp\Server\McpServerProvider` em `app/Domain/Ai/Mcp/Server/McpServerProvider.php` registrando as 7 capabilities (6 existentes + `update-lead-profile` virá em US3)

### Bridge das 6 capabilities (delegam aos services da Fase 17)

- [X] T044 [P] [US7] Implementar `App\Domain\Ai\Mcp\Capabilities\GetClinicInfoCapability` em `app/Domain/Ai/Mcp/Capabilities/GetClinicInfoCapability.php` — input vazio, delega ao service da tool existente Fase 17, formato output por `contracts/mcp-capabilities.contract.md` §Capability 1
- [X] T045 [P] [US7] Implementar `App\Domain\Ai\Mcp\Capabilities\ListProfessionalsCapability` em `app/Domain/Ai/Mcp/Capabilities/ListProfessionalsCapability.php`
- [X] T046 [P] [US7] Implementar `App\Domain\Ai\Mcp\Capabilities\GetAvailabilityCapability` em `app/Domain/Ai/Mcp/Capabilities/GetAvailabilityCapability.php`
- [X] T047 [P] [US7] Implementar `App\Domain\Ai\Mcp\Capabilities\GetCurrentPatientCapability` em `app/Domain/Ai/Mcp/Capabilities/GetCurrentPatientCapability.php` — **nunca retorna nome** (FR-029); inclui `consents` map
- [X] T048 [P] [US7] Implementar `App\Domain\Ai\Mcp\Capabilities\CreateOrFindLeadCapability` em `app/Domain/Ai/Mcp/Capabilities/CreateOrFindLeadCapability.php` — caminho real + caminho sandbox (output sintético)
- [X] T049 [P] [US7] Implementar `App\Domain\Ai\Mcp\Capabilities\HoldSlotCapability` em `app/Domain/Ai/Mcp/Capabilities/HoldSlotCapability.php` — caminho real + sandbox

### Logging & Bridge (cliente)

- [X] T050 [US7] Implementar `App\Domain\Ai\Mcp\Client\McpCallLogger` em `app/Domain/Ai/Mcp/Client/McpCallLogger.php` — persiste em `ai_tool_invocations` (Fase 17) com `source='mcp'`, mantém schema equivalente
- [X] T051 [US7] Implementar `App\Domain\Ai\Mcp\Client\McpToolBridge` em `app/Domain/Ai/Mcp/Client/McpToolBridge.php` — adapta `Tool` (laravel/ai contract) → chamada HTTP ao servidor MCP local; usa `Http::baseUrl(config('ai.mcp.url'))->withToken($pat)`; mede latência; emite `ai_mcp_request_duration_seconds`
- [X] T052 [US7] Modificar `App\Domain\Ai\Tools\Support\ToolRunner` (ou equivalente que o `PersonaAgent` consome para invocar tools) para: se `AI_TOOLS_VIA_MCP=true` AND circuit breaker `closed`/`half_open` → roteia via `McpToolBridge`; senão → executa tool nativa (FR-052 fallback runtime). Decisão por chamada (não por boot).

### Circuit breaker

- [X] T053 [US7] Implementar `App\Domain\Ai\Mcp\CircuitBreaker\McpCircuitBreaker` em `app/Domain/Ai/Mcp/CircuitBreaker/McpCircuitBreaker.php` — Redis-backed (chaves `mcp:cb:*` de data-model §Estruturas Redis), métodos `recordSuccess()`, `recordFailure()`, `state()`, `attemptCanary()`, `transitionTo()` (com backoff de cooldown)
- [X] T054 [US7] Criar events `App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitOpened` e `McpCircuitClosed` em `app/Domain/Ai/Mcp/CircuitBreaker/Events/`; ambos `Auditable`; listener `PersistMcpCircuitSnapshotListener` grava em `mcp_circuit_breaker_snapshots`
- [X] T055 [US7] Integrar `McpCircuitBreaker` em `McpToolBridge`: capturar exceções (Connection refused, 5xx, timeout) → `recordFailure()`; estados `open`/`half_open` mudam roteamento no `ToolRunner` (T052)

### Sandbox model

- [X] T056 [US7] Implementar `App\Domain\Ai\Mcp\Sandbox\SandboxContext` em `app/Domain/Ai/Mcp/Sandbox/SandboxContext.php` — singleton para a request MCP atual, exposta via container scope
- [X] T057 [US7] Implementar `App\Domain\Ai\Mcp\Sandbox\SandboxNeutralizer` em `app/Domain/Ai/Mcp/Sandbox/SandboxNeutralizer.php` — método `applyTo(string $capability, array $input): ?array` que, se sandbox=true, retorna output sintético para capabilities de escrita (CreateOrFindLead, HoldSlot) sem chamar service real; capabilities de leitura passam direto

### Tests do US7

- [X] T058 [P] [US7] Criar `tests/Feature/Ai/Mcp/McpAuthTest.php` — sem token → 401; token sem ability → 403; token sem tenant → 403
- [X] T059 [P] [US7] Criar `tests/Feature/Ai/Mcp/McpCrossTenantTest.php` — **adversarial (SC-007)**: token do tenant A, invoca `get-current-patient` para paciente do tenant B → 403 ou not_found; tenta passar tenant_id no input → ignorado (schema rejeita)
- [X] T060 [P] [US7] Criar `tests/Feature/Ai/Mcp/McpCapabilitiesListingTest.php` — autenticado, retorna as 7 capabilities (após US3 incluir update-lead-profile) com descrições PT-BR
- [X] T061 [P] [US7] Criar `tests/Feature/Ai/Mcp/CircuitBreakerTest.php` — 3 falhas consecutivas → `open`; após cooldown → `half_open`; canário sucesso → `closed`; canário falha → `open` com cooldown dobrado (cap 600s); transições persistidas em snapshot
- [X] T062 [P] [US7] Criar `tests/Feature/Ai/Mcp/McpToolBridgeRoutingTest.php` — flag ON + CB closed → bridge usada; flag ON + CB open → tool nativa; flag OFF → sempre nativa
- [X] T063 [P] [US7] Criar `tests/Feature/Ai/Mcp/SandboxNeutralizationTest.php` — token sandbox + CreateOrFindLead → retorna `{patient_id: "sandbox-uuid", sandbox: true}` e **0 linhas inseridas em `pacientes`**; idem HoldSlot e `slot_reservations`
- [X] T063a [P] [US7] **FR-023 guardrail estrutural**: criar `tests/Feature/Ai/Mcp/IaCannotAutoConfirmTest.php` que valida 3 invariantes: (1) varredura via reflection do diretório `app/Domain/Ai/Mcp/Capabilities/` confirma que NENHUMA capability tem método que escreva em `funil_colunas` com slug em `['agendado','confirmado']` (só listeners de eventos de domínio fazem); (2) o único caminho que muta `Paciente::funil_coluna_atual_id` para `slug='agendado'` é `PromoteToScheduledOnHoldPlaced` (T101); (3) o único caminho para `slug='confirmado'` é `PromoteToConfirmedOnReservationPaid` (T102). Falha = alguém adicionou capability/listener que burla FR-023.

### Gate FR-053 — Paridade comportamental (DEDICADA — antes de qualquer promoção de flag)

- [X] T064 [US7] **GATE FR-053**: criar `tests/Feature/Ai/Mcp/ParityWithNativeToolsTest.php` que executa programaticamente a **suíte completa de IA das Fases 15 + 17** sob `AI_TOOLS_VIA_MCP=true` (env override) e exige 100% de aprovação. Falha desta task = **não promover flag em produção**. Esta task deve ficar com tag `@parity-gate` para roda no CI noturno dedicado. Documentar no quickstart §6 que esta task é o gate de cut-over.

### Métricas + observabilidade do MCP

- [X] T065 [US7] Garantir que `McpToolBridge` emite `ai_mcp_request_duration_seconds{capability, outcome, source(production|sandbox)}` e `ai_mcp_request_total{capability, outcome}`; `McpCircuitBreaker` emite gauge `ai_mcp_circuit_state` (0/1/2) e counter `ai_mcp_circuit_transitions_total{to, source}`

**Checkpoint US7**: servidor MCP roda, capabilities respondem, circuit breaker + sandbox testados, gate de paridade armado. US3 e US6 destravados.

---

## Phase 4: User Story 1 — Coalescência híbrida (Priority: P1) 🎯 MVP

**Goal**: a IA deixa de responder 3x para 3 mensagens consecutivas — coalesce o burst, espera o paciente parar (debounce ~4s), e se nova msg chega enquanto pensa, descarta o rascunho e refaz.

**Independent Test**: enviar 3 mensagens com 2-3s de intervalo → 1 resposta da IA contendo contexto das 3 mensagens; auditoria registra coalescência. Burst infinito atinge teto e dispatcha.

**Pode ser desenvolvida em paralelo com US7 e US2** — não depende de MCP nem de kanban.

### Serviços de coalescência

- [X] T066 [P] [US1] Implementar `App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator` em `app/Domain/Ai/Coalescing/Services/ConversationTurnCoordinator.php` — métodos `joinOrStartTurn(Message $msg): TurnVersion`, `currentVersion(ConvId): int`, `flushIfDue(ConvId): ?Turn`; manipula chaves Redis por data-model §Estruturas Redis com TTL 5min
- [X] T067 [P] [US1] Implementar `App\Domain\Ai\Coalescing\Services\PassiveDebounceScheduler` em `app/Domain/Ai/Coalescing/Services/PassiveDebounceScheduler.php` — agenda `FlushCoalescedTurnJob` com delay `passive_debounce_s`; reset via `SET ai:turn:{conv}:debounce :_ EX <s>`
- [X] T068 [US1] Implementar `App\Domain\Ai\Coalescing\Services\TurnVersionGuard` em `app/Domain/Ai/Coalescing/Services/TurnVersionGuard.php` — método `assertCurrent(ConvId, int $version): bool` (lê Redis e compara)
- [X] T069 [US1] Criar `App\Jobs\Ai\FlushCoalescedTurnJob` em `app/Jobs/Ai/FlushCoalescedTurnJob.php` (fila `ai`) — checa: se versão atual == versão do job → constroi `Turn` com `msgs` do Redis → despacha `ProcessAiResponseJob(conversation_id, turn_version, message_ids)`; senão → no-op (foi superseded)

### Integração com pipeline existente

- [X] T070 [US1] Modificar `App\Listeners\Messaging\ProcessInboundMessageListener` (ou equivalente que hoje enfileira ProcessAiResponseJob diretamente) para chamar `ConversationTurnCoordinator::joinOrStartTurn()` em vez de despachar direto; respeitar estado pausado/escalado (FR-006 — checar `AiConversationAssignment::status`)
- [X] T071 [US1] Modificar `App\Jobs\Ai\ProcessAiResponseJob` (Fase 15/17) para: (a) receber `turn_version`; (b) buscar todas as msgs da `turn` (não só latest); (c) ANTES do dispatch outbound, chamar `TurnVersionGuard::assertCurrent()` — se falhou, `INCR ai:turn:{conv}:reprocess`, se < `max_reprocesses` re-agendar `FlushCoalescedTurnJob` e retornar; senão dispatchar com o que tem (FR-004); (d) após dispatch outbound, limpar estado do turno

### Eventos auditáveis + ordem

- [X] T072 [P] [US1] Criar events `App\Domain\Ai\Coalescing\Events\TurnCoalesced` e `TurnDispatched` em `app/Domain/Ai/Coalescing/Events/` — `Auditable`; payload `{conversation_id, tenant_id, turn_version, message_count, reprocess_count, flush_reason: 'passive_debounce_elapsed'|'max_turn_seconds'|'max_reprocesses_reached'}`
- [X] T073 [US1] Garantir ordem cronológica de mensagens no `Turn` (FR-007): o Redis LIST `ai:turn:{conv}:msgs` é push-tailwise; o `AiContextBuilderService` (Fase 17) deve consumir nessa ordem

### Tests US1

- [X] T074 [P] [US1] Criar `tests/Feature/Ai/Coalescing/TurnCoalescenceTest.php` — 3 inbound msgs com 2s entre cada → assertEnqueued(ProcessAiResponseJob, count=1) E assertDispatched(outbound, count=1); audit `TurnDispatched` com `message_count=3`
- [X] T075 [P] [US1] Criar `tests/Feature/Ai/Coalescing/CancelAndReprocessTest.php` — msg chega DURANTE o processamento (simula via `INCR` manual antes do dispatch guard) → ProcessAi rejeita dispatch, re-enfileira FlushJob, novo ProcessAi roda com msgs atualizadas
- [X] T076 [P] [US1] Criar `tests/Feature/Ai/Coalescing/CoalesceTimeoutTest.php` — teto `max_turn_s=20s` atingido → flush forçado; `max_reprocesses=3` excedido → dispatcha com o que tem (FR-004)
- [X] T077 [P] [US1] Criar `tests/Feature/Ai/Coalescing/CoalescePausedConversationTest.php` — conversa com `AiConversationAssignment::status='paused_to_human'` → joinOrStartTurn no-op, nenhuma resposta automática (FR-006)
- [X] T078 [P] [US1] Criar `tests/Unit/Ai/Coalescing/TurnVersionGuardTest.php` — unit do guard contra Redis fake
- [X] T079 [P] [US1] Criar `tests/Unit/Ai/Coalescing/PassiveDebounceTest.php` — unit do scheduler (reset, delay)
- [X] T080 [P] [US1] Criar `tests/Feature/Ai/Coalescing/CoalesceOrderPreservedTest.php` — 5 msgs com timestamps embaralhados na fila → `Turn` mantém ordem cronológica de chegada (FR-007)

**Checkpoint US1**: coalescência operando; nenhuma resposta duplicada; metrics emitidas (T039 já registrou).

---

## Phase 5: User Story 2 — Lead onboarding automático (Priority: P1) 🎯 MVP

**Goal**: toda mensagem inbound de canal suportado garante existência do contato no tenant; contato novo vira lead na coluna inicial do funil em <30s; contato existente como lead recebe a conversa anexada ao card; **paciente regular (não-lead) NÃO entra no kanban** (Q-clarify-3=B).

**Independent Test**: mensagem WhatsApp de número novo → card 'new' em <30s; mesmo número de novo → 1 card (idempotência); número de paciente regular → 0 card (anexa ao prontuário).

**Pode ser desenvolvida em paralelo com US7 e US1**.

### Serviços de onboarding

- [X] T081 [US2] Implementar `App\Domain\Crm\Kanban\Services\LeadOnboardingService` em `app/Domain/Crm/Kanban/Services/LeadOnboardingService.php` com método `ensureFor(ChannelType $type, string $identifier, int $tenantId): OnboardingOutcome` — lookup por `telefone_primario_normalizado` (WA) ou `instagram_handle` (IG) ou opaque (widget); cria `Paciente status='lead'` + insere `funil_coluna_atual_id = coluna is_initial=true`; idempotente via UNIQUE `(tenant_id, identificador)` (DB-level)
- [X] T082 [US2] Implementar `App\Domain\Crm\Kanban\Services\KanbanPipelineMappingService` em `app/Domain/Crm/Kanban/Services/KanbanPipelineMappingService.php` — `forTenant(int $id): Collection<KanbanPipelineMapping>`, `colunaFor(int $tenantId, string $eventKind): FunilColuna`, com fallback ao default seedado
- [X] T083 [US2] Implementar listener `App\Listeners\Crm\Kanban\EnqueueLeadOnInboundMessageListener` em `app/Listeners/Crm/Kanban/EnqueueLeadOnInboundMessageListener.php` que escuta `InboundMessageReceived` (Fase 3) e chama `LeadOnboardingService::ensureFor()` — **antes** do `ConversationTurnCoordinator` (T070); auto-discovered Laravel 11+
- [X] T084 [US2] Implementar reabertura: se contato existe como **lead** em coluna `is_terminal=true` (ex: "perdido") → mover de volta para `is_initial=true` com `KanbanCurationEvent(event_kind='lead_reactivated', reason='paciente respondeu após X dias')` (FR-013)
- [X] T085 [US2] Implementar **detecção FR-011a (Q-clarify-3=B)**: se contato existente tem `status != 'lead'` (paciente regular) → **NÃO** cria card no kanban; conversa anexa ao prontuário existente; auditar como `KanbanCurationEvent(event_kind='inbound_attached_to_existing_patient', source='auto_listener')`
- [X] T086 [US2] Implementar detecção de duplicidade cross-canal (FR-014): job/listener detecta mesmo `nome_completo` + `email` ou heurística semelhante entre WA e IG → emite `PossibleDuplicateContactDetected` (sem fundir); operador resolve manualmente

### Falhas operacionais

- [X] T087 [US2] Tratar falhas de auto-criação (tenant suspended, permission, dados inválidos): auditar `KanbanCurationEvent(event_kind='onboarding_failed', applied=false, suppression_reason=...)` + alertar operador no inbox (insere mensagem de sistema na conversa); **NÃO** deixar o paciente sem resposta — a conversa segue (FR-015)

### Tests US2

- [X] T088 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingWhatsAppTest.php` — webhook fake WA com número novo → 1 linha em `pacientes` (status='lead'), card na coluna `is_initial=true`, evento `KanbanCardCurated(event_kind='lead_created')`
- [X] T089 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingInstagramTest.php` — analogous, handle IG
- [X] T090 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingWidgetTest.php` — identificador opaco
- [X] T091 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingIdempotencyTest.php` — 5 inbounds do mesmo número em paralelo (simular via `Concurrent::run`) → 1 card só, 0 duplicates
- [X] T092 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingExistingPatientTest.php` — paciente com `status='ativo'` (Fase 2) envia msg → 0 cards no kanban; conversa anexa; evento `inbound_attached_to_existing_patient`
- [X] T093 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingTerminalReactivationTest.php` — paciente lead em "perdido" volta → card reabre para "new" com event `lead_reactivated`
- [X] T094 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingTenantSuspendedTest.php` — tenant suspenso → onboarding pula, evento `onboarding_failed(suppression_reason=tenant_suspended)`, conversa segue (sem perder a msg)
- [X] T095 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingCrossTenantIsolationTest.php` — mesmo número como lead em 2 tenants → 2 cards independentes (não funde)
- [X] T096 [P] [US2] Criar `tests/Feature/Crm/Kanban/LeadOnboardingLatencyTest.php` — mede ms entre `InboundMessageReceived` e `Paciente::created` → assert <30s (SC-002)

**Checkpoint US2**: leads entram sozinhos no kanban, paciente regular respeitado, idempotência forte.

---

## Phase 6: User Story 3 — Auto-curadoria do Kanban via conversa (Priority: P1) 🎯 MVP

**Goal**: a IA popula nome/observações estruturadas do card via capability MCP; status do card transiciona automaticamente conforme eventos do funil (hold colocado → "agendado", PIX confirmado → "confirmado", inatividade → "perdido"); operador wins sob conflito (FR-020).

**Independent Test**: rodar conversa qualificação→preço→agendamento → card tem nome após paciente dizê-lo, observações populadas, transita "new"→"qualificando"→"negociando"→"agendado".

**Depende de**: US7 (capability `update-lead-profile`) + US2 (lead existe). Pode iniciar logo após US7+US2 fechadas.

### Capability `update-lead-profile` (a 7ª no MCP)

- [X] T097 [US3] Implementar `App\Domain\Ai\Mcp\Capabilities\UpdateLeadProfileCapability` em `app/Domain/Ai/Mcp/Capabilities/UpdateLeadProfileCapability.php` por contract `mcp-capabilities.contract.md` §Capability 7; allow-list de campos (`name|complaint|preferred_city|urgency|procedure|price_range`); validação de `name` por regex; rejeita PII clínica via `PiiScrubber::detectClinical($value)` → `error_code: clinical_pii_blocked`; em sandbox, no-op com `{sandbox: true, applied: false}`; registra `KanbanCurationEvent(event_kind='profile_updated', field_changed, value_before, value_after)`; idempotência simples (atualizar campo já com o mesmo valor não cria evento)
- [X] T098 [US3] Registrar `UpdateLeadProfileCapability` no `McpServerProvider` (T043) — fechando as 7 capabilities

### Serviços de curadoria + transição

- [X] T099 [US3] Implementar `App\Domain\Crm\Kanban\Services\KanbanCurationService` em `app/Domain/Crm/Kanban/Services/KanbanCurationService.php` — método `applyProfileUpdate(Paciente $p, string $field, ?string $valueBefore, string $valueAfter, ?int $turnVersion, ?int $toolInvocationId): KanbanCurationEvent`; mantém histórico do valor anterior consultável (FR-021)
- [X] T100 [US3] Implementar `App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService` em `app/Domain/Crm/Kanban/Services/KanbanAutoTransitionService.php` — método `apply(Paciente $p, string $eventKind, array $context=[]): KanbanCurationEvent`; consulta `KanbanPipelineMappingService` para coluna-alvo; aplica **FR-020 — não regredir** se status atual já é mais avançado OU se houve override manual prévio (verifica `Paciente.funil_coluna_changed_by_user_at` ou flag equivalente); auditá supressão com `suppression_reason='manual_override'`

### Listeners de eventos de domínio

- [X] T101 [P] [US3] Implementar `App\Listeners\Crm\Kanban\PromoteToScheduledOnHoldPlaced` em `app/Listeners/Crm/Kanban/PromoteToScheduledOnHoldPlaced.php` escutando `SlotReservation::created` (Fase 5) filtrando `holder_type='ia'` → `KanbanAutoTransitionService::apply($paciente, 'slot_held')`
- [X] T102 [P] [US3] Implementar `App\Listeners\Crm\Kanban\PromoteToConfirmedOnReservationPaid` escutando `AppointmentConfirmed` (Fase 5) → `KanbanAutoTransitionService::apply($paciente, 'reservation_confirmed')`
- [X] T103 [P] [US3] Implementar `App\Listeners\Crm\Kanban\PromoteToHumanOnEscalation` escutando `AiAssignmentEscalatedToHuman` (Fase 15) → `KanbanAutoTransitionService::apply($paciente, 'ai_paused_to_human', ['reason' => $event->reason])`
- [X] T104 [US3] Implementar `App\Listeners\Crm\Kanban\DowngradeToLostOnInactivityListener` (acionado por cron) — para cada paciente lead inativo há > N dias (config por tenant default 30) sem mensagem inbound → `KanbanAutoTransitionService::apply($paciente, 'inactivity', ['days_inactive' => N])`; registrar comando `app/Console/Commands/Kanban/DowngradeInactiveLeadsCommand.php` agendado em `app/Console/Kernel.php` daily 03:00 tenant-aware
- [X] T105 [US3] Definir eventos novos `App\Domain\Ai\Events\AiQualificationStarted` e `AiValueAccepted` em `app/Domain/Ai/Events/`; emitir no `AiMessageProcessor` (Fase 17) quando detectar **funnel_stage** (já existe no resumo da Fase 17): primeira vez que `funnel_stage='qualifying'` → emit Qualificacao; primeira `value_accepted=true` → emit ValueAccepted; criar listeners correspondentes `PromoteToQualifyingOnAiQualificationStarted` e `PromoteToNegotiatingOnAiValueAccepted`

### Endpoints REST (config por tenant + audit + promote manual)

- [X] T106 [US3] Implementar `App\Http\Controllers\Api\V1\Kanban\KanbanPipelineMappingController` em `app/Http/Controllers/Api/V1/Kanban/KanbanPipelineMappingController.php` — GET (`index`), PUT por `event_kind`, POST `restore-defaults` por contract; middleware `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`, permission `funil.manage`
- [X] T107 [US3] Implementar `App\Http\Controllers\Api\V1\Kanban\KanbanCurationEventController` em `app/Http/Controllers/Api/V1/Kanban/KanbanCurationEventController.php` — GET listing paginado por contract (filtros paciente_id, event_kind, source, from, to)
- [X] T108 [US3] Implementar `App\Http\Controllers\Api\V1\Pacientes\PromoteToKanbanController` em `app/Http/Controllers/Api/V1/Pacientes/PromoteToKanbanController.php` — POST `/pacientes/{paciente}/promote-to-kanban` (FR-011a UX); 409 se já em coluna não-terminal
- [X] T109 [P] [US3] Criar Form Requests + Resources correspondentes em `app/Http/Requests/Kanban/` e `app/Http/Resources/Kanban/`
- [X] T110 [US3] Registrar rotas em `routes/api.php` (grupo `kanban`)

### Adaptação do PersonaAgent (instrução)

- [X] T111 [US3] Adicionar instrução no system prompt da `PersonaAgent` (Fase 17 — provavelmente em `AiGuardrailEnforcer::composeInstructions`) descrevendo a capability `update-lead-profile` e quando chamá-la (paciente disse nome → chame; paciente disse cidade → chame; etc.) — sem mudar nada no fluxo de decisão; o modelo decide via tool description natural; **gate de regressão**: testes Fase 17 que checam prompt content podem precisar de ajuste

### Frontend — config por tenant

- [X] T112 [P] [US3] Criar Pinia store `resources/js/stores/kanban/pipelineMapping.js` com `fetch()`, `update(eventKind, funilColunaId, isActive)`, `restoreDefaults()`
- [X] T113 [US3] Criar `resources/js/pages/Kanban/KanbanPipelineConfigPage.vue` — lista os 7 mappings com dropdown de `funil_colunas` do tenant; switch de active; botão restore; rota `/panel/kanban/pipeline-config`
- [X] T114 [P] [US3] Atualizar `resources/js/config/navigation.js` para incluir o novo menu (permission `funil.manage`)

### Tests US3

- [X] T115 [P] [US3] Criar `tests/Feature/Crm/Kanban/AutoTransitionHoldPlacedTest.php` — `SlotReservation::created` (holder=ia) → card vai para coluna mapping `slot_held` (default "agendado")
- [X] T116 [P] [US3] Criar `tests/Feature/Crm/Kanban/AutoTransitionConfirmedTest.php` — `AppointmentConfirmed` → "confirmado"
- [X] T117 [P] [US3] Criar `tests/Feature/Crm/Kanban/AutoTransitionEscalationTest.php` — escalação → "humano"
- [X] T118 [P] [US3] Criar `tests/Feature/Crm/Kanban/AutoTransitionInactivityTest.php` — comando cron, paciente sem inbound > 30d → "perdido"
- [X] T119 [P] [US3] Criar `tests/Feature/Crm/Kanban/ManualOverrideNoRegressTest.php` — operador moveu para "humano"; depois evento `slot_held` → FR-020 supressão; `KanbanCurationEvent.applied=false, suppression_reason='manual_override'`
- [X] T120 [P] [US3] Criar `tests/Feature/Crm/Kanban/KanbanCurationEventAuditTest.php` — todo evento aplicado gera 1 linha auditável com source correto (ia_tool vs auto_listener vs manual_override_blocked)
- [X] T121 [P] [US3] Criar `tests/Feature/Ai/Mcp/UpdateLeadProfileCapabilityTest.php` — name update OK; complaint update OK; tentativa com CID/diagnóstico → `clinical_pii_blocked`; sandbox token → no-op
- [X] T122 [P] [US3] Criar `tests/Feature/Crm/Kanban/ProfileHistoryPreservedTest.php` — paciente diz cidade A, depois B → ambas as linhas em `kanban_curation_events` com value_before/value_after (FR-021)
- [X] T123 [P] [US3] Criar `tests/Feature/Crm/Kanban/PromoteToKanbanFromExistingPatientTest.php` — paciente regular → POST promote → card criado na coluna escolhida, evento `manual_promoted_to_kanban`
- [X] T124 [P] [US3] Criar `tests/Feature/Crm/Kanban/PipelineMappingTenantCustomizationTest.php` — tenant muda mapping `slot_held → coluna X`; novo hold → card vai para X (não default)
- [X] T125 [P] [US3] Criar `tests/Feature/Crm/Kanban/PipelineMappingCrossTenantIsolationTest.php` — mapping de tenant A não afeta tenant B
- [X] T125a [US3] **FR-011b — distinção lead vs paciente regular no contexto da IA**: estender o `WorkContext` (Fase 17) injetando, a cada turno, flag `is_lead` derivada do paciente da conversa (já disponível via `GetCurrentPatientCapability` — FR-047). Modificar `AiGuardrailEnforcer::composeInstructions` (Fase 17) para adicionar bloco condicional no system prompt: "Este interlocutor é um **paciente já cadastrado** — NÃO refaça qualificação como se fosse desconhecido; respeite o relacionamento existente; ofereça ações apropriadas (revisão/retorno) ao invés de funil de venda." quando `is_lead=false`. Manter prompt atual quando `is_lead=true`.
- [X] T125b [P] [US3] Criar `tests/Feature/Ai/PersonaContextRespectsLeadVsExistingPatientTest.php` — paciente regular envia "queria saber sobre dor" → assert system prompt contém bloco "paciente já cadastrado" + a IA NÃO chama `create-or-find-lead` (já existe); lead envia mesma msg → assert sem bloco + comportamento de qualificação normal.
- [X] T125c [US3] **FR-023 (guardrail aditivo)** — adicionar restrição na descrição da `UpdateLeadProfileCapability` (T097) deixando explícito ao modelo: "esta tool NÃO move card para 'agendado' nem 'confirmado' — esses estados refletem eventos do agendamento/pagamento e são aplicados automaticamente pelos listeners apropriados".

**Checkpoint US3**: nome/observações populam sozinhos; cards transitam pelo funil automaticamente; operador wins sob conflito; auditoria completa; IA distingue lead vs paciente regular.

🎯 **MVP READY**: Setup + Foundational + US7 + US1 + US2 + US3 fechados = MVP entregável (humanização + funil automático).

---

## Phase 7: User Story 4 — Transcrição de áudio inbound (STT) (Priority: P2)

**Goal**: áudio inbound de WhatsApp/Instagram Direct é transcrito em PT-BR; transcrição vira conteúdo da mensagem; IA processa normalmente; áudio original disponível ao operador; widget de site fica fora.

**Independent Test**: webhook WA com áudio PT-BR → transcrição em <10s p95; mensagem texto na conversa marcada `is_audio_origin=true`; IA responde coerentemente; falha → mensagem visível "áudio não entendido".

**Não depende de outras stories** — pode iniciar logo após Foundational.

### Provider STT + serviço de orquestração

- [X] T126 [P] [US4] Criar interface `App\Domain\Messaging\Audio\Inbound\Services\AudioTranscriptionProvider` em `app/Domain/Messaging/Audio/Inbound/Services/AudioTranscriptionProvider.php` — método `transcribe(MessageMedia $media, ?string $language = null): TranscriptionResult` (DTO com `text`, `language_detected`, `truncated`, `error_code`, `duration_seconds`, `latency_ms`)
- [X] T127 [P] [US4] Implementar `App\Domain\Messaging\Audio\Inbound\Services\OpenAIWhisperProvider` em `app/Domain/Messaging/Audio/Inbound/Services/OpenAIWhisperProvider.php` usando `Http::attach()->post('https://api.openai.com/v1/audio/transcriptions')`; trata erros (silence/lang/timeout/corruption) mapeando para `error_code`; timeout via `config('messaging.audio.stt.timeout_s')`
- [X] T128 [US4] Implementar `App\Domain\Messaging\Audio\Inbound\Services\AudioTranscriptionService` em `app/Domain/Messaging/Audio/Inbound/Services/AudioTranscriptionService.php` — método `transcribeInbound(MessageMedia $media): AudioTranscription`; persiste linha em `audio_transcriptions`; atualiza `messaging_messages.content` com texto transcrito + `is_audio_origin=true` + `transcription_id` (somente se sucesso); passa pelo `PiiScrubber` antes de gravar (FR-055b)

### Job + integração no pipeline

- [X] T129 [US4] Criar `App\Jobs\Messaging\TranscribeInboundAudioJob` em `app/Jobs/Messaging/TranscribeInboundAudioJob.php` (fila `transcription`, prioridade alta — paciente espera) que invoca o service
- [X] T130 [US4] Modificar `ProcessInboundMessageJob` (Fase 3) — se mídia é áudio E canal != widget → enfileira `TranscribeInboundAudioJob` ANTES do listener de coalescência; a coalescência (US1) só roda após o job concluir (encadear via job chain ou marcar mensagem `pending_transcription=true`)
- [X] T131 [US4] Implementar fallback em falha (FR-027): se `TranscriptionResult.error_code != null` → insere `Message` system na conversa "áudio não entendido — peça ao paciente para repetir"; **não** roda IA para essa mensagem; operador é alertado no inbox

### Adapters de canal — download de áudio

- [X] T132 [P] [US4] Modificar `App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter` (Fase 14) — método `downloadMediaTo(MessageMedia $media, string $storageDisk): string` que persiste o áudio em storage local antes da TTL do provider expirar (Twilio media URL ~24h)
- [X] T133 [P] [US4] Modificar `App\Domain\Messaging\Channel\Adapters\EvolutionApiAdapter` (Fase 14) — idem para Evolution (base64 inline OU URL temporária)
- [X] T134 [P] [US4] Modificar `App\Domain\Messaging\Channel\Adapters\InstagramGraphAdapter` — idem para IG Direct

### Tests US4

- [X] T135 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionWhatsAppTest.php` — `Http::fake()` Whisper retornando texto; webhook fake WA com áudio → assert `audio_transcriptions` tem linha; `messaging_messages.content` é o texto + `is_audio_origin=true`
- [X] T136 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionInstagramTest.php` — idem IG
- [X] T137 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionWidgetSkipTest.php` — áudio de widget NÃO dispara STT (comportamento atual mantido — FR-026)
- [X] T138 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionFailureTest.php` — provider retorna `error_code='silence'` → mensagem system "áudio não entendido"; IA NÃO é invocada para esse turno
- [X] T139 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionTruncationTest.php` — áudio > limite → `truncated=true` com mensagem visível
- [X] T140 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionPiiScrubberTest.php` — transcrição com CPF "123.456.789-00" → texto vai para o modelo pseudonimizado (FR-055b)
- [X] T141 [P] [US4] Criar `tests/Feature/Messaging/Audio/InboundTranscriptionLatencyTest.php` — mede ms de STT; assert p95 < 10s (SC-004)

**Checkpoint US4**: áudio inbound vira texto utilizável pela IA; falhas degradam gracioso.

---

## Phase 8: User Story 5 — Resposta em áudio (TTS) sob gatilho explícito (Priority: P2)

**Goal**: quando o paciente sinaliza preferência por áudio (Q3=A — frases PT-BR), a próxima resposta da IA vai como áudio TTS pelo canal; widget de site fica fora; fallback automático para texto se TTS falhar; voz vem da Persona (Q-clarify-4=B).

**Independent Test**: paciente diz "não sei ler" → próxima resposta chega como áudio WA/IG; falha do TTS → texto enviado normalmente; tenant com TTS desativado → sempre texto.

**Depende de**: Setup (catálogo de vozes seedado, T034). Pode iniciar após US4 ou em paralelo.

### Provider TTS + serviços

- [X] T142 [P] [US5] Criar interface `App\Domain\Messaging\Audio\Outbound\Services\AudioSynthesisProvider` em `app/Domain/Messaging/Audio/Outbound/Services/AudioSynthesisProvider.php` — método `synthesize(string $text, string $providerVoiceId, string $format = 'mp3'): SynthesisResult` (DTO com `audio_bytes`, `duration_s`, `latency_ms`, `error_code`)
- [X] T143 [P] [US5] Implementar `App\Domain\Messaging\Audio\Outbound\Services\ElevenLabsProvider` em `app/Domain/Messaging/Audio/Outbound/Services/ElevenLabsProvider.php` usando `Http::withHeader('xi-api-key', ...)`; modelo `eleven_turbo_v2_5` por default
- [X] T144 [US5] Implementar `App\Domain\Messaging\Audio\Outbound\Services\TtsTextNormalizer` em `app/Domain/Messaging/Audio/Outbound/Services/TtsTextNormalizer.php` — normaliza R$/horários/datas/abreviações/telefones para fala (FR-035); cobre PT-BR
- [X] T145 [US5] Implementar `App\Domain\Messaging\Audio\Inbound\Services\AudioPreferenceDetector` em `app/Domain/Messaging/Audio/Inbound/Services/AudioPreferenceDetector.php` — lê `config('messaging.audio.outbound.triggers')`, normaliza texto, match por substring tolerante a acento/caps; método `prefersAudioForTurn(Turn $turn): bool`
- [X] T146 [US5] Implementar `App\Domain\Ai\Voice\Services\PersonaVoiceResolverService` em `app/Domain/Ai/Voice/Services/PersonaVoiceResolverService.php` — método `resolveFor(AiPersona $p): VoiceCatalogEntry`; cadeia Persona.voice_id → tenant default → system default
- [X] T147 [US5] Implementar `App\Domain\Messaging\Audio\Outbound\Services\AudioSynthesisService` em `app/Domain/Messaging/Audio/Outbound/Services/AudioSynthesisService.php` — método `synthesize(string $text, AiPersona $persona, string $channel): AudioSynthesis`; orquestra normalize + provider + persiste linha + cria `messaging_message_media` para o áudio gerado

### Integração no dispatch outbound

- [X] T148 [US5] Modificar o dispatch outbound (provavelmente em `OutboundNotificationDispatcher` Fase 13 OU diretamente no `ProcessAiResponseJob` após gerar texto da IA): se `AudioPreferenceDetector::prefersAudioForTurn()` AND canal != widget AND `config('messaging.tts.enabled')` AND tenant não desativou → invocar `AudioSynthesisService`; anexar áudio à `Message` outbound; texto persiste na timeline em paralelo
- [X] T149 [US5] Implementar fallback (FR-034): se `synthesize()` retorna `error_code` → `audio_syntheses.fallback_to_text=true`, **mensagem texto é enviada normalmente sem perder o conteúdo**; emite `ai_tts_fallback_to_text_total`
- [X] T150 [US5] Implementar segmentação/resumo (FR-036): se texto > `config('messaging.audio.tts.max_text_length')` → resumir via prompt simples (1 call extra ao mesmo provider de IA) OU segmentar em 2-3 áudios sequenciais
- [X] T151 [US5] Implementar flag tenant `tts_enabled` (FR-037): adicionar coluna no mesmo tenant settings/tabela de voz default; controller respeita

### Adapters de canal — upload de áudio

- [X] T152 [P] [US5] Modificar `WhatsAppCloudAdapter::sendOutbound()` para suportar mídia áudio (URL pública assinada com TTL curto via `Storage::temporaryUrl()`)
- [X] T153 [P] [US5] Modificar `EvolutionApiAdapter::sendOutbound()` idem (`/message/sendMedia` ou `/message/sendAudio`)
- [X] T154 [P] [US5] Modificar `InstagramGraphAdapter::sendOutbound()` idem (Graph API audio attachment)

### Endpoints de voz

- [X] T155 [US5] Implementar `App\Http\Controllers\Api\V1\Ai\Voices\VoiceCatalogController` em `app/Http/Controllers/Api/V1/Ai/Voices/VoiceCatalogController.php` — GET listing por `language`, **não expõe** `provider_voice_id` nem `provider` (FR-037c)
- [X] T156 [US5] Implementar GET/PUT `/api/v1/tenant/settings/voice` em controller dedicado (default_voice_id)
- [X] T157 [US5] Estender `App\Http\Resources\Ai\PersonaResource` (Fase 15) para incluir `voice_id` e `voice: VoiceCatalogResource::make()`
- [X] T158 [US5] Estender `App\Http\Requests\Ai\UpdatePersonaRequest` (Fase 15) para validar `voice_id` (existe em `voice_catalog`, ativa, language compatível)
- [X] T159 [US5] Registrar rotas em `routes/api.php`

### Super-Admin (Filament) — catálogo

- [ ] T160 [US5] Criar `App\Filament\Resources\VoiceCatalogResource` por contract `voice-catalog.api.md` §Super-Admin — listagem + form + actions ativar/desativar + marcar como system default (1 por language); upload de `preview_audio` para storage public

### Frontend SPA — UI da voz

- [ ] T161 [P] [US5] Criar Pinia store `resources/js/stores/ia/voiceCatalog.js` com `fetchVoices(language)`, `fetchDefault()`, `setDefault(voiceId)`
- [ ] T162 [US5] Adicionar select de voz no `PersonaFormModal.vue` (Fase 15) com preview audio inline e tag de gênero/tom (Q-clarify-4=B coerência)
- [ ] T163 [P] [US5] Criar componente `resources/js/components/Ia/VoiceDefaultSettings.vue` (tela de settings do tenant)

### Tests US5

- [X] T164 [P] [US5] Criar `tests/Unit/Messaging/Audio/AudioPreferenceDetectorTest.php` — cada gatilho da lista PT-BR mata; falsos negativos (paciente "não sei se quero ler") não disparam
- [X] T165 [P] [US5] Criar `tests/Unit/Messaging/Audio/TtsTextNormalizerTest.php` — "R$ 300,00" → "trezentos reais"; "14h30" → "duas e meia da tarde"; "Dr." → "doutor"; etc.
- [X] T166 [P] [US5] Criar `tests/Feature/Messaging/Audio/OutboundSynthesisTriggeredTest.php` — paciente disse "não sei ler" → próxima resposta tem mídia áudio + texto persistido
- [X] T167 [P] [US5] Criar `tests/Feature/Messaging/Audio/OutboundSynthesisFallbackTest.php` — Whisper… ops, ElevenLabs falha → `fallback_to_text=true`, texto enviado, audit
- [X] T168 [P] [US5] Criar `tests/Feature/Messaging/Audio/OutboundSynthesisWidgetSkipTest.php` — gatilho em widget → NÃO gera áudio (FR-032)
- [X] T169 [P] [US5] Criar `tests/Feature/Ai/Voice/PersonaVoiceResolverTest.php` — Persona com voice_id; sem voice_id → tenant default; sem nenhum → system default
- [X] T170 [P] [US5] Criar `tests/Feature/Messaging/Audio/TenantTtsDisabledTest.php` — tenant `tts_enabled=false` → todos os turnos respondem em texto, audit "disabled by config" (não falha)
- [X] T171 [P] [US5] Criar `tests/Feature/Messaging/Audio/OutboundSynthesisAudioReversalTest.php` — paciente pediu áudio no turno 3; turno 4 ele digita texto sem pedir → IA volta a texto (FR-033)
- [X] T172 [P] [US5] Criar `tests/Feature/Messaging/Audio/OutboundSynthesisSegmentationTest.php` — texto > limite → segmentação/resumo aplicado
- [X] T173 [P] [US5] Criar `tests/Feature/Ai/Voice/VoiceCatalogApiTest.php` — GET listing não retorna `provider_voice_id`; permission gate
- [X] T174 [P] [US5] Criar `tests/Feature/Ai/Voice/PersonaVoiceUpdateTest.php` — PUT voice_id válido OK; inativa → 422; outra language → 422

**Checkpoint US5**: TTS opera sob gatilho; cada Persona tem voz; fallback garantido.

---

## Phase 9: User Story 6 — Chat de teste de Persona (sandbox) (Priority: P2)

**Goal**: botão "Testar" na tela de Personas abre chat sandbox; admin conversa como paciente; IA roda ciclo completo com `sandbox=true` propagado pelo token MCP; zero efeitos colaterais reais; sessão isolada por (admin, persona).

**Independent Test**: admin abre Testar em Persona X → modal abre → manda 5 mensagens → IA responde no modal via Reverb; `pacientes` real **não** ganhou linha; `slot_reservations` real **não** ganhou linha; ao fechar, token MCP revogado.

**Depende de**: US7 (sandbox token model + SandboxNeutralizer).

### Serviço + controller

- [X] T175 [US6] Implementar `App\Domain\Ai\Persona\Services\PersonaTestSessionService` em `app/Domain/Ai/Persona/Services/PersonaTestSessionService.php` — método `open(User $admin, AiPersona $persona, ?array $draft = null): PersonaTestSession`: persiste sessão com `persona_snapshot` (versão publicada OU draft per FR-039); emite Sanctum PAT com ability `mcp.invoke` + metadata `sandbox=true` + tenant; auto-fecha sessão `open` anterior do mesmo (admin, persona) com motivo `superseded`
- [X] T176 [US6] Método `PersonaTestSessionService::close(PersonaTestSession $s)`: marca `status='closed'`, `closed_at=now`, **revoga** o `PersonalAccessToken` correspondente (efeito imediato FR-051)
- [X] T177 [US6] Método `PersonaTestSessionService::archive(PersonaTestSession $s)`: `status='archived'` (configurável FR-043)
- [X] T178 [US6] Implementar `App\Http\Controllers\Api\V1\Ai\Personas\PersonaTestSessionController` em `app/Http/Controllers/Api/V1/Ai/Personas/PersonaTestSessionController.php` — endpoints `store`, `close`, `index`, `archive`, `sendMessage` por contract `persona-test-chat.api.md`; middleware padrão + permission `ai.persona.test`
- [X] T179 [P] [US6] Form Requests: `OpenPersonaTestSessionRequest` (valida `use_draft` + `persona_draft` shape), `SendPersonaTestMessageRequest` (text OR audio_base64)
- [X] T180 [P] [US6] Resources: `PersonaTestSessionResource`
- [X] T181 [US6] Registrar rotas em `routes/api.php`

### Pipeline sandbox

- [X] T182 [US6] Modificar `ProcessAiResponseJob` (Fase 17 modificado por US1): se a conversa é uma `persona_test_session` (detectável via flag/relacionamento na `Message`) → usar `SandboxOutboundDispatcher` em vez de `OutboundNotificationDispatcher`
- [X] T183 [US6] Implementar `App\Domain\Ai\Persona\Services\SandboxOutboundDispatcher` em `app/Domain/Ai/Persona/Services/SandboxOutboundDispatcher.php` — persiste a resposta da IA como `Message` com `sandbox=true, sandbox_session_id=...`; broadcast `PersonaTestMessageBroadcasted` event no canal privado `private-persona-test.{session_id}`
- [X] T184 [US6] Criar event `App\Events\Ai\Persona\PersonaTestMessageBroadcasted` em `app/Events/Ai/Persona/PersonaTestMessageBroadcasted.php` (ShouldBroadcast) + `PersonaTestThinking` + `PersonaTestSessionClosed`
- [X] T185 [US6] Definir autorização de canal Echo `private-persona-test.{session_id}` em `routes/channels.php` — checagem: `auth()->id() === $session->admin_user_id` AND session `status='open'`

### Isolamento de métricas (gate de regressão)

- [X] T186 [US6] **Auditar e atualizar** todos os agregadores existentes que leem `messaging_messages` ou `ai_execution_logs` para adicionar `WHERE sandbox=false`/`WHERE sandbox_session_id IS NULL`: `DashboardHomeService` (Fase 10), `ExecutiveDashboardCollectors` (Fase 11), `AiUsageMetricsCollector` (Fase 15), `MessagingMetricsCollector` (Fase 13). Adicionar test específico para cada um.

### Frontend SPA

- [ ] T187 [US6] Modificar `resources/js/pages/Ia/PersonasIndexPage.vue` (Fase 15) — adicionar botão "Testar" no card de cada Persona (gated por permission `ai.persona.test` via store de auth)
- [ ] T188 [US6] Modificar `resources/js/components/Ia/PersonaFormModal.vue` (Fase 15) — adicionar botão "Testar persona em edição" que envia `use_draft=true, persona_draft=formData` (FR-039)
- [ ] T189 [US6] Criar `resources/js/components/Ia/PersonaTestChatModal.vue` — sandbox chat UI (R14): half-screen desktop / fullscreen mobile; stream Reverb; indicador "IA pensando"; input texto + opção "enviar como áudio" (upload); botão "limpar conversa"; footer "abrir histórico de sessões"
- [ ] T190 [P] [US6] Criar Pinia store `resources/js/stores/ia/personaTest.js` com `openSession`, `sendMessage`, `closeSession`, `subscribeToEcho`, `listSessions`, `archiveSession`
- [ ] T191 [P] [US6] Componente `PersonaTestSessionHistoryDrawer.vue` — lista sessões arquivadas do admin

### Tests US6

- [X] T192 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestChatOpenSendReceiveTest.php` — open sandbox + send msg + assert resposta da IA broadcasted via Reverb fake; sessão `open` em DB
- [X] T193 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestSandboxNeutralizedTest.php` — IA chama `create-or-find-lead` no sandbox → `pacientes` **não** ganha linha; idem `hold-slot`/`slot_reservations` (FR-040/041)
- [X] T194 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestPermissionDeniedTest.php` — user sem `ai.persona.test` → POST open retorna 403
- [X] T195 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestIsolationBetweenAdminsTest.php` — admin A abre sessão; admin B chama GET listing → não vê sessão de A (FR-043)
- [X] T196 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestMetricsExclusionTest.php` — mensagens sandbox NÃO aparecem em DashboardHomeService, ExecutiveDashboard, AiUsage etc. (FR-042) — gate de regressão sobre T186
- [X] T197 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestDraftPersonaTest.php` — open com `use_draft=true, persona_draft={...}` → IA usa o draft no snapshot; persona publicada continua intocada (FR-039)
- [X] T198 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestSessionTokenRevokedOnCloseTest.php` — close → `PersonalAccessToken` deletado; próxima chamada com esse token falha 401
- [X] T199 [P] [US6] Criar `tests/Feature/Ai/Personas/PersonaTestSupersedeOpenSessionTest.php` — admin abre sessão para Persona X; abre outra para Persona X → primeira vira `closed` com motivo `superseded`

**Checkpoint US6**: admin tem ferramenta de validação isolada; zero risco de leak para produção; cobertura por testes de neutralização.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: rate limit + cooldown, consent transcricao, observability final, runbook de cut-over, E2E, atualização do CLAUDE.md.

### Rate limit anti-abuso (Q-clarify-5=C)

- [ ] T200 [P] Implementar `App\Domain\Messaging\RateLimiting\InboundConversationLimiter` em `app/Domain/Messaging/RateLimiting/InboundConversationLimiter.php` — método `checkOrThrow(int $conversationId, int $tenantId, string $identifier): void`; usa os 2 `RateLimiter::for(...)` registrados (T037); excedido → throw `RateLimitExceededException` capturada pelo listener
- [ ] T201 [P] Implementar `App\Domain\Messaging\RateLimiting\CooldownService` em `app/Domain/Messaging/RateLimiting/CooldownService.php` — `startFor(Conversation $c, string $reason)` (popula `cooldown_until/cooldown_reason`, eleva `priority='alta'`, emite `ConversationCooldownStarted`), `endBy(Conversation $c, User $u)` (limpa + audit)
- [ ] T202 [P] Implementar `App\Domain\Messaging\RateLimiting\IsConversationOnCooldownChecker` — método `check(Conversation $c): bool`; chamado por `ProcessAiResponseJob`, `KanbanCurationService`, `AudioSynthesisService`, `McpToolBridge` antes de operar (FR-008c)
- [ ] T203 Integrar `InboundConversationLimiter::checkOrThrow()` no `EnqueueLeadOnInboundMessageListener` (T083) — ANTES da coalescência; excedido → `CooldownService::startFor()`; mensagem segue persistindo (não é descartada)
- [ ] T204 [P] Heurística simples "abuso vs crise" (FR-008d) — classificador em `App\Domain\Messaging\RateLimiting\BurstClassifier` (Levenshtein + freq sustentada); usado só para label do alerta do operador
- [ ] T205 [P] UI: badge "Em cooldown — N min" no inbox card; action "encerrar cooldown" (permission `messaging.cooldown.manage` nova)
- [ ] T206 [P] Criar events `ConversationCooldownStarted` e `ConversationCooldownEnded` em `app/Events/Messaging/RateLimiting/` (ambos Auditable)
- [ ] T207 [P] Tests: `tests/Feature/Messaging/RateLimiting/InboundCooldownPerConversationTest.php`, `InboundCooldownPerIdentifierTest.php`, `CooldownLiftedByOperatorTest.php`, `CooldownExpiresAutomaticallyTest.php`, `CooldownPreventsIaSideEffectsTest.php` (cobre os 4 checkers)

### Consent transcricao (Q-clarify-2=B)

- [ ] T208 [P] Estender CRUD de consentimentos do paciente (Fase 8) para incluir a finalidade `transcricao` por contract `consent-transcricao.api.md`
- [ ] T209 [P] Criar `resources/js/components/Pacientes/ConsentTranscricaoToggle.vue` — toggle com texto explicativo e modal "saiba mais"; integração com store de consentimentos
- [ ] T210 [P] Criar `App\Jobs\Compliance\PurgeExpiredAudioRawJob` em `app/Jobs/Compliance/PurgeExpiredAudioRawJob.php` (fila `compliance`) — cron diário; para cada `AudioTranscription` cujo `media.created_at < now() - default_retention_days` AND paciente **não** tem consent `Transcricao` ativo → deleta arquivo + null em `storage_path`, marca `purged_at` em `messaging_message_media`
- [ ] T211 Adicionar agendamento do job em `app/Console/Kernel.php` (daily 04:00) + comando manual `compliance:purge-extended-audio`
- [ ] T212 [P] Implementar purge retroativo on revoke (FR-055c): listener `OnConsentTranscricaoRevoked` → enfileira `PurgePatientExtendedAudioJob` para o paciente específico imediatamente
- [ ] T213 [P] Tests: `tests/Feature/Compliance/PurgeExpiredAudioRawTest.php` (sem consent → purga), `PurgeRespectsConsentTranscricaoTest.php` (com consent → mantém), `ConsentTranscricaoRevokeTriggersPurgeTest.php`

### Cut-over runbook + observabilidade

- [ ] T214 [P] Criar painel Grafana `paciente360-fase18-overview` exibindo: ai_coalesce_*, ai_mcp_request_duration p95 por capability, ai_mcp_circuit_state gauge, ai_stt_duration p95, ai_tts_duration p95, ai_tts_fallback_to_text_total, ai_rate_limit_cooldown_active_total
- [ ] T215 [P] Criar alerta Prometheus: `ai_mcp_circuit_state > 0 for 5m` → page operador; `ai_mcp_request_duration_seconds{quantile=0.95} > 1.0` → warn
- [ ] T216 Documentar **runbook de cut-over** em `docs/runbooks/fase18-mcp-cutover.md` (criar dir se não existir) seguindo quickstart.md §6 — sequência: deploy flag OFF → smoke MCP via sandbox → janela contínua circuit closed → CI noturno com `AI_TOOLS_VIA_MCP=true` → flag ON em prod baixa carga → 48h monitoramento → critério rollback
- [ ] T217 Atualizar OpenAPI/Scribe (princípio IV) para novos endpoints: `/api/v1/ai/personas/{id}/test/sessions/*`, `/api/v1/ai/voices`, `/api/v1/kanban/pipeline-mappings/*`, `/api/v1/kanban/curation-events`, `/api/v1/pacientes/{id}/promote-to-kanban`, `/api/v1/pacientes/{id}/consents/transcricao/*`

### E2E (DEFERRED conforme padrão das fases anteriores)

- [ ] T218 [P] Criar `tests/e2e/ai-multimodal-conversation.spec.ts` — paciente novo envia áudio → IA transcreve, responde texto, card surge no kanban; paciente diz "não sei ler" → próxima resposta como áudio; hold → card → "agendado" (DEFERRED se custo alto)
- [ ] T219 [P] Criar `tests/e2e/persona-test-chat.spec.ts` — admin abre Persona, clica Testar, conversa 5 turnos, fecha → zero side effects (DEFERRED ditto)

### Suíte completa, formatação, CLAUDE.md

- [ ] T220 Rodar `vendor/bin/sail bin pint --dirty --format agent` (formatação)
- [ ] T221 Rodar `vendor/bin/sail artisan test --compact` — toda a suíte deve passar; expectativa ~1900+ tests (Fase 17 deixou ~1730+, esta fase soma ~140 tests novos)
- [ ] T222 Rodar `AI_TOOLS_VIA_MCP=true vendor/bin/sail artisan test --compact --testsuite=Feature --group=parity-gate` — gate FR-053 (T064) deve passar
- [ ] T222a **Benchmark SLA Fase 18**: criar `tests/Feature/Ai/Sla/Phase18BenchmarkSlaTest.php @group=sla-benchmark` cobrindo os SCs mensuráveis em ambiente determinístico (Http::fake() + Bus::fake() onde aplicável): **SC-001** (50 conversas com burst 3 msgs em 6s → asserção `respostas/turnos ≥ 0.99`); **SC-008** (20 conversas onde paciente diz nome no turno 1 ou 2 → asserção `Paciente.nome != null` em ≤2 turnos em ≥95%); **SC-010** (medir latência fim-a-fim simulada — coalescência + MCP fake + TTS fake — assert p95 ≤12s; coalescência sozinha assert ≤+4s sobre baseline). Adicionar cenário de **burst infinito** (1000 msg em 60s) — assert: sistema não trava, cooldown ativado (T201), IA para, operador alertado. Excluído do CI padrão (tag `@group=sla-benchmark`), executado no CI noturno e antes de cut-over. **SC-003/005** dependem de produção real: documentar em T214 como alerta Grafana sobre métricas live.
- [ ] T223 Atualizar `CLAUDE.md` bloco SPECKIT START/END com sumário DELIVERED da Fase 18 + adicionar seção "Fase 18 — Key Patterns" com pontos críticos (coalescência Redis, MCP substituição+CB+sandbox, kanban auto-curadoria FR-020, STT/TTS providers, rate limit 2 camadas, consent transcricao)
- [ ] T224 Atualizar `.specify/feature.json` com `status=delivered`, `delivered_at=YYYY-MM-DD`, `constitution_check="7/7 PASS (1 desvio target latência p95 ≤12s)"`

---

## Dependencies & Execution Order

### Phase Dependencies

```
Setup (Phase 1)
   └─► Foundational (Phase 2) — bloqueia todas as user stories
         ├─► US7 (Phase 3) — MCP infra; bloqueia US3 e US6
         ├─► US1 (Phase 4) — coalescência; independente
         ├─► US2 (Phase 5) — lead onboarding; independente
         ├─► US4 (Phase 7) — STT; independente
         └─► US5 (Phase 8) — TTS; depende só do voice catalog seedado (T034)
                                  
         US3 (Phase 6) ◄── precisa de US7 (capability update-lead-profile) + US2 (lead existe)
         US6 (Phase 9) ◄── precisa de US7 (sandbox token model)
                                  
   └─► Polish (Phase 10) — depois de todas as US desejadas
```

### Gate FR-053 (paridade)

**T064 (ParityWithNativeToolsTest)** é o gate dedicado. **Antes** de qualquer promoção do flag `AI_TOOLS_VIA_MCP=true` em produção (operação humana, não task), esta task deve estar verde. Está dentro da US7 phase porque verifica a infra MCP; é executada como `--group=parity-gate` no CI noturno (T222).

### MVP mínimo viável

**Setup + Foundational + US7 + US1 + US2 + US3** = 125 tasks (T001-T125) entregam:
- Coalescência híbrida (humanização real)
- Lead onboarding automático no funil
- Auto-curadoria do kanban via conversa
- Servidor MCP operacional (flag OFF default — paridade ainda exige tests verdes T064)

US4/US5/US6 são incrementos pós-MVP (audio + sandbox).

### Within Each User Story

- Tests podem ser escritos junto com a implementação (Constituição IV — test-first); cada arquivo de test [P] é independente
- Models antes de services antes de controllers antes de UI
- Listeners depois dos services que eles chamam

### Parallel Opportunities

- **Foundational T010-T019** — 10 migrations em paralelo (arquivos diferentes)
- **Foundational T024-T033** — 10 models em paralelo
- **US7 T044-T049** — 6 capabilities em paralelo (depois de T040-T043 base)
- **US1 T066-T067** — coordinator + scheduler em paralelo
- **US2 T088-T096** — 9 tests em paralelo
- **US3 T101-T103** — 3 listeners em paralelo; T112-T114 frontend em paralelo
- **US3 T115-T125** — 11 tests em paralelo
- **US4 T132-T134** — 3 adapters em paralelo; T135-T141 tests em paralelo
- **US5 T152-T154** — 3 adapters em paralelo; T161-T163 frontend; T164-T174 tests
- **US6 T192-T199** — 8 tests em paralelo
- **Polish T200-T215** — rate limit + consent + observability em paralelo (vários sub-domínios)

---

## Parallel Example: User Story 7 (Capabilities)

```bash
# Após T040-T043 (estrutura + auth + provider): lançar as 6 capabilities em paralelo:
Task: "Implementar GetClinicInfoCapability em app/Domain/Ai/Mcp/Capabilities/GetClinicInfoCapability.php"
Task: "Implementar ListProfessionalsCapability em app/Domain/Ai/Mcp/Capabilities/ListProfessionalsCapability.php"
Task: "Implementar GetAvailabilityCapability em app/Domain/Ai/Mcp/Capabilities/GetAvailabilityCapability.php"
Task: "Implementar GetCurrentPatientCapability em app/Domain/Ai/Mcp/Capabilities/GetCurrentPatientCapability.php"
Task: "Implementar CreateOrFindLeadCapability em app/Domain/Ai/Mcp/Capabilities/CreateOrFindLeadCapability.php"
Task: "Implementar HoldSlotCapability em app/Domain/Ai/Mcp/Capabilities/HoldSlotCapability.php"

# Após T044-T049: lançar 6 tests do MCP em paralelo:
Task: "Criar tests/Feature/Ai/Mcp/McpAuthTest.php"
Task: "Criar tests/Feature/Ai/Mcp/McpCrossTenantTest.php"
Task: "Criar tests/Feature/Ai/Mcp/McpCapabilitiesListingTest.php"
Task: "Criar tests/Feature/Ai/Mcp/CircuitBreakerTest.php"
Task: "Criar tests/Feature/Ai/Mcp/McpToolBridgeRoutingTest.php"
Task: "Criar tests/Feature/Ai/Mcp/SandboxNeutralizationTest.php"
```

## Parallel Example: User Story 3 (Listeners)

```bash
# Após T099-T100 (services): lançar listeners em paralelo:
Task: "Implementar PromoteToScheduledOnHoldPlaced escutando SlotReservation::created"
Task: "Implementar PromoteToConfirmedOnReservationPaid escutando AppointmentConfirmed"
Task: "Implementar PromoteToHumanOnEscalation escutando AiAssignmentEscalatedToHuman"
```

---

## Implementation Strategy

### MVP First (Setup + Foundational + US7 + US1 + US2 + US3)

1. Phase 1 (Setup): T001-T008 (~1 dia)
2. Phase 2 (Foundational): T009-T039 (~3 dias — 31 tasks, muito paralelizável)
3. Phase 3 (US7 MCP): T040-T065 (~5 dias — capabilities + circuit breaker + sandbox + gate paridade)
4. **Phase 4 (US1 Coalescência) e Phase 5 (US2 Lead onboarding) em PARALELO com US7**: T066-T096 (~3-4 dias)
5. Phase 6 (US3 Auto-curadoria): T097-T125 (~4 dias — depende de US7+US2 fechados)
6. **STOP and VALIDATE**: roda quickstart.md §10 DoD; smoke real em staging
7. Deploy MVP com `AI_TOOLS_VIA_MCP=false`

⚠️ **Decisão consciente sobre rate limit no MVP**: FR-008a/b são implementados na Phase 10 Polish (T200-T207), **não no MVP**. O RateLimiter Laravel está registrado em T037 (Foundational) mas o **enforcement** + **cooldown auditável** só sobem no Polish. Implicação: entre o ship do MVP (após T125) e o ship do Polish (após T207), a coalescência da US1 **não tem proteção anti-bot/anti-burst-infinito além dos tetos por turno** (FR-003: 30s teto + 3 reprocessos max). É aceitável para staging/early-access controlado, **inaceitável para produção aberta**. **Gate de produção**: T200-T207 (rate limit + cooldown) **devem fechar antes** de promover MVP a tenants pagantes em produção. T222a SLA bench inclui cenário de burst infinito (1000 msg em 60s) para validar.

### Incremental Delivery (post-MVP)

1. US4 (STT): T126-T141 (~3 dias) — independente
2. US5 (TTS): T142-T174 (~4 dias) — depende só de voice catalog
3. US6 (Persona test chat): T175-T199 (~4 dias) — depende de US7
4. Polish: T200-T224 (~5 dias) — rate limit, consent, observability, cut-over runbook

### Parallel Team Strategy

Com 3 devs após Foundational:
- **Dev A**: US7 (infra MCP) — caminho crítico para US3/US6
- **Dev B**: US1 (coalescência) + US2 (lead onboarding) em sequência
- **Dev C**: US4 (STT) + US5 (TTS) em sequência

US3, US6 só começam depois que US7 fechar. Polish é tarefa final, multi-pessoa.

### Cut-over MCP (operacional, pós-merge)

1. Merge com `AI_TOOLS_VIA_MCP=false`. MCP server sobe mas só recebe tráfego do chat de teste (US6).
2. Admins exercitam Persona Test → MCP em carga real e isolada por semanas.
3. CI noturno roda `T222` (group=parity-gate) — deve passar consistentemente.
4. Métricas em janela contínua: circuit closed, latência p95 < 500ms, zero erros.
5. Promote `AI_TOOLS_VIA_MCP=true` em horário de baixa carga.
6. Monitorar 24-48h. Se circuit abrir → fallback runtime já ativo (FR-053b); investigar.
7. Se necessário rollback: `AI_TOOLS_VIA_MCP=false` (efeito em <1min via cache config:clear).

---

## Notes

- [P] tasks = arquivos diferentes, sem dependências
- [Story] label mapeia task ao user story para rastreabilidade
- Cada US deve ser independentemente completável e testável (exceto US3/US6 → US7)
- Tests programáticos são obrigatórios (Constituição IV — NON-NEGOTIABLE)
- Pint depois de cada bloco de tasks PHP (`vendor/bin/sail bin pint --dirty --format agent`)
- Migrations sempre aditivas e idempotentes; testar `migrate:fresh` (T022)
- **NUNCA** mover flag `AI_TOOLS_VIA_MCP=true` em produção sem T064 verde + 24-48h de smoke via sandbox (US6) com circuit closed
- Stop em qualquer checkpoint para validar a US independentemente
