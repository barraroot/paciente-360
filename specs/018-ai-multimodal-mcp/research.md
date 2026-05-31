# Phase 0 — Research: Conversa Reativa, Multimodal e MCP

Resolve as incógnitas técnicas do `plan.md`. Cada item: **Decisão · Rationale · Alternativas**.

---

## R1 — Provedor de Speech-to-Text (STT) para áudio inbound

**Decisão**: **OpenAI Whisper API** (`whisper-1`) como provedor default, com `AudioTranscriptionProvider` plugável via config para fallback futuro.

**Rationale**:
- PT-BR maduro e de alta qualidade (clínicas no interior, áudios curtos, ruído de fundo de WhatsApp).
- API simples (`POST /audio/transcriptions` com multipart) — integra via Laravel `Http::attach()` em <30 LOC; sem nova lib pesada (sem SDK obrigatório).
- Custo $0.006/min — para a janela de uso esperada (centenas de áudios/dia/tenant) cabe folgado no custo unitário do plano.
- Mock fácil em teste via `Http::fake()`; já é o padrão usado nas integrações OpenAI da Fase 15 (embeddings).
- Idiomas: PT default, mas detecta espanhol/inglês automaticamente — útil para o edge case "áudio em outro idioma" (spec edge case).
- Limite de tamanho: 25MB/áudio; WhatsApp PTT raramente passa de 10MB → confortável.
- **Sem armazenamento no provedor** (zero-retention pode ser pedido por DPA) — alinha com LGPD.

**Alternativas avaliadas**:
- **Google Cloud Speech-to-Text** — qualidade similar, mas exige GCP setup (service account, billing), mais cerimônia operacional, custo similar.
- **Azure Speech** — análogo ao Google, sem ganho diferenciado.
- **Whisper local (`whisper.cpp` ou modelo HuggingFace)** — elimina custo unitário e latência de rede, mas exige GPU dedicada ou aceitar latência de CPU (10-30s/áudio = inaceitável). Reservado para fase futura se volume justificar.
- **AssemblyAI / Deepgram** — boas em inglês, PT-BR menos polido que Whisper.

**Gate**: a interface `AudioTranscriptionProvider` (transcribe(media, language) → TranscriptionResult) é o ponto único; swap futuro = troca da binding no service container.

---

## R2 — Provedor de Text-to-Speech (TTS) para áudio outbound

**Decisão**: **ElevenLabs** como provedor default para vozes PT-BR de qualidade humana; `AudioSynthesisProvider` plugável.

**Rationale**:
- Vozes PT-BR genuinamente naturais (clínica feminina acolhedora — Q-clarify-4=B exige catálogo curado coerente com a Persona; ElevenLabs tem o melhor catálogo PT-BR comercial).
- Streaming/chunked output reduz latência percebida (paciente começa a ouvir antes do áudio terminar de ser gerado).
- API simples, mock-friendly.
- Modelos `eleven_multilingual_v2` e `eleven_turbo_v2_5` (latência baixa) cobrem o espectro qualidade/velocidade — clínica escolhe na config global.
- Suporte a SSML básico para normalização de números/datas (FR-035).
- **Cap de tamanho da saída** controlado (texto entra, áudio sai limitado pelo modelo escolhido) → previne FR-036.

**Alternativas avaliadas**:
- **OpenAI TTS (`tts-1`/`tts-1-hd`)** — vozes mais robóticas em PT-BR (sotaque carregado), catálogo limitado de gêneros/tons (Q-clarify-4=B fica difícil de respeitar coerentemente).
- **Azure Neural TTS** — qualidade boa, mas catálogo PT-BR limitado a poucas vozes e setup complexo.
- **Google Cloud TTS** — similar ao Azure.
- **TTS local** (Bark, XTTS) — qualidade variável, GPU-bound, fora de escopo para o MVP.

**Gate**: interface `AudioSynthesisProvider` (synthesize(text, voice_id, format) → SynthesisResult). Provedor binding em `config/messaging.php`.

**Risco**: ElevenLabs é provedor único PT-BR de qualidade — fica como SPOF do TTS. Mitigação: o fallback FR-034 (falha TTS → texto) cobre indisponibilidade; sem necessidade de provider redundante no MVP.

---

## R3 — Coalescência híbrida: estruturas Redis + atomicidade

**Decisão**: **Redis-backed turn versioning** com `INCR` atômico + `SET NX EX` para debounce passivo + verificação de versão antes do dispatch.

### Estado por conversa

```
ai:turn:{conversation_id}:v          INTEGER (incrementado a cada nova msg ou início de turno)
ai:turn:{conversation_id}:debounce   STRING (chave-fantasma com EX 3-4s, indica "debounce ativo")
ai:turn:{conversation_id}:started_at INTEGER (timestamp do início do turno, para teto absoluto 30s)
ai:turn:{conversation_id}:msgs       LIST   (mensagens coalescidas — IDs do messaging_messages)
ai:turn:{conversation_id}:reprocess  INTEGER (contador, max 3 — FR-004)
```

### Fluxo (FR-001..008)

1. **Inbound chega** → listener (`StartCoalescingTurnListener`):
   - `INCR ai:turn:{id}:v` (ganha versão N).
   - Se primeira mensagem do turno (`msgs` vazio): seta `started_at`, push ID, agenda job `FlushCoalescedTurnJob` com delay = `passive_debounce_s` (default 4s).
   - Se já há turno em curso: push ID, **reseta o debounce** (`SET ai:turn:{id}:debounce :_ EX 4s`), e se houver job IA em curso, ele é cancelado na próxima checagem de versão.
2. **`FlushCoalescedTurnJob`** roda:
   - Lê versão atual N; cria contexto com `msgs`; despacha `ProcessAiResponseJob(turn_version=N)`.
3. **`ProcessAiResponseJob`**:
   - Faz tudo da Fase 17 (RAG, work context, histórico, MCP tools).
   - Antes do dispatch outbound: `GET ai:turn:{id}:v` — se != N (chegou nova mensagem), **descarta o rascunho**, `INCR ai:turn:{id}:reprocess`, se < 3 re-agenda flush; senão, dispatcha o que tem (FR-004).
4. **Dispatch outbound efetivo**:
   - Limpa `msgs`, `started_at`, `reprocess`, `debounce`.
   - Versão **NÃO** é zerada (segue incrementando para serializar turnos seguintes).

### Garantia de ordem e zero-perda (FR-007)

- O `msgs` é LIST (push tailwise) — ordem cronológica garantida.
- Mensagens recebidas DURANTE o reprocessamento entram no LIST e disparam INCR — serão consideradas no próximo reflow.
- Garantia de "1 turno = 1 resposta" (SC-011): a versão-N só dispara dispatch se ainda for atual; mensagens novas viraram versão N+1.

### Concorrência (FR-008)

- `INCR` é atômico.
- A janela exata de race (entre o `GET v` no dispatch e o `send` real) é coberta por um lock final `SET ai:turn:{id}:dispatching {jobid} NX EX 5`. Quem ganha o lock dispatcha; o lock é liberado pós-send.

**Alternativas avaliadas**:
- **Sem cancel-and-reprocess, só debounce passivo** (Q1=B) — perde a UX de "absorver msg que chegou enquanto pensa".
- **Sem debounce, só cancel-and-reprocess** (Q1=A) — gasta compute em todo burst curto.
- **Estado em DB (PostgreSQL row lock)** — overhead alto, atomicidade depende de transação, dificulta debug.
- **In-memory por worker** (estado no processo Horizon) — perde se o worker morre/reinicia; multi-worker quebra.

---

## R4 — laravel-mcp v0: servidor, transporte, autenticação

**Decisão**: usar **laravel/mcp v0** como pacote oficial (já listado nas dependências do projeto), expondo um **servidor MCP local sobre HTTP/Streamable** (não stdio), autenticado por **Sanctum PAT com ability `mcp.invoke`** + claim de tenant herdado do token.

### Setup

- `composer require laravel/mcp:^0.x` (já presente — `laravel/mcp - v0` em CLAUDE.md).
- `php artisan make:mcp-server` cria a base; rotas registradas em `routes/mcp.php` (convenção do pacote v0).
- Serviço `mcp-server` no `compose.yaml` no profile `mcp` (não sobe por default em dev simples; cobertura por testes feature). Em produção sobe como serviço próprio, escutando em rede interna do cluster.

### Autenticação

- **Credencial MCP = Sanctum PAT** com ability `mcp.invoke` (modelo já existente da Fase 4).
- Para a IA de produção: token é emitido por job *no startup do processamento* com TTL curto (5min), abilities `mcp.invoke`, scoped no tenant da conversa. O token só vive durante a request da IA → minimiza superfície.
- Para chat de teste (US6): token emitido para a sessão `persona_test_sessions` com abilities `mcp.invoke` + meta `sandbox=true`; mesmo TTL curto.
- Para integrações externas (futuro — Claude Desktop): admin emite token long-lived com mesma ability via UI dedicada.

### Tenant scoping (FR-046)

- Token carrega `tenant_id` no campo `name` ou em uma claim custom (Sanctum suporta `tokenable` mas o tenant precisa ser explícito).
- `McpTokenGuard` resolve `tenant_id` do token na entrada da request, popula contexto global de tenant (mesmo middleware-equivalente da API REST), e o Service de cada capability filtra `tenant_id` na query.
- **Teste adversarial obrigatório** (SC-007): tentar passar `tenant_id` no input da capability é simplesmente ignorado pelo schema (não é input declarado).

### Auditoria (FR-049)

- `McpCallLogger` middleware-equivalente persiste `(capability, tenant_id, conversation_id?, input_sanitizado, outcome, latency_ms, token_id, sandbox)` numa tabela `ai_tool_invocations` reusada da Fase 17 (já existe).
- Source de tool fica em `source` enum: `native|mcp` para distinguir caminho.

**Alternativas avaliadas**:
- **stdio transport** — útil para clientes Claude Desktop, mas inadequado para IA de produção que precisa de chamadas paralelas/repetidas com baixa latência local; HTTP local com keep-alive vence.
- **gRPC** — overkill, exige proto + lib + setup HTTP/2 puro; sem ganho real local.
- **REST custom no Laravel sem MCP** — perde a interoperabilidade externa (Claude Desktop não consome REST custom, consome MCP). E é exatamente o pedido literal do usuário ("servidor mcp com laravel-mcp").

---

## R5 — Circuit breaker para o MCP (FR-053b/c/d)

**Decisão**: **implementação custom enxuta** sobre Redis (estado + contador), sem pacote externo, integrada ao `McpToolBridge`.

**Rationale**:
- Pacotes maduros (`leszczynski/laravel-circuit-breaker`, `gabrielanhaia/laravel-circuit-breaker`) trazem dependência+abstração que para nosso caso é overkill — temos UM endpoint a proteger (o MCP local), com regras simples: N falhas → abre; cooldown M segundos → meio-aberto (tenta canário); sucesso → fecha.
- Custom mantém o controle do "abrir circuito = roteador volta a usar `laravel/ai` nativa" (FR-052), que é específico do nosso modelo.
- Implementação cabe em ~80 LOC + 1 tabela snapshot (analytics) + chaves Redis.

### Estado (Redis)

```
mcp:cb:state                STRING ("closed"|"open"|"half_open")
mcp:cb:failures             INTEGER (window-aware via sorted set; ou simples contador com TTL 30s)
mcp:cb:opened_at            INTEGER (timestamp)
mcp:cb:cooldown_seconds     INTEGER (atual, com backoff)
```

### Lógica

- **Closed** (default): toda chamada vai ao MCP. Erros (timeout, 5xx, connection refused) `INCR failures`. Se `failures >= threshold` (default 3 em ≤30s) → estado `open`, `opened_at = now`, alerta operador (Sentry + evento auditável `McpCircuitOpened`).
- **Open**: chamadas pulam o MCP e usam `laravel/ai` nativa imediatamente (FR-053b). Após `cooldown_seconds`, transita para `half_open`.
- **Half-open**: a próxima chamada é uma **canário** (1 request real ao MCP). Sucesso → `closed`, `failures = 0`, evento `McpCircuitClosed`. Falha → volta a `open`, dobra `cooldown_seconds` (cap 600s).
- Toda transição auditada (FR-053d) e exposta como gauge Prometheus `ai_mcp_circuit_state` (0=closed, 1=half_open, 2=open).

### Distinção vs flag manual (FR-053d)

- Flag `AI_TOOLS_VIA_MCP=false` (rollback operacional) é decisão humana, registrada via `App\Audit\Auditable` com ator = admin.
- Circuit breaker é decisão automática, ator = `system` com causa específica.
- Ambos resultam no mesmo runtime (`laravel/ai` nativa em uso), mas com origens distintas no log/Prometheus.

**Alternativas avaliadas**:
- **Pacote `gabrielanhaia/laravel-circuit-breaker`** — abstrações ricas (handlers, eventos), boa qualidade, mas para 1 endpoint, é overhead. Adoptar se ganhar multi-endpoint depois.
- **Sem circuit breaker, só retry exponencial** — não cobre o caso de degradação prolongada do MCP; IA fica lenta por minutos esperando timeouts.

---

## R6 — Sandbox de Persona (US6): isolamento

**Decisão**: Sandbox flag **propagada pela credencial MCP** (`sandbox=true` no token metadata) + `SandboxNeutralizer` que intercepta capabilities de escrita.

### Como funciona

1. Admin clica "Testar" → `PersonaTestSessionController::store()` cria uma `persona_test_session` (UUID, admin_id, persona_id, persona_version_at_open) e emite um token Sanctum com ability `mcp.invoke` + `metadata.sandbox=true`.
2. Frontend abre `PersonaTestChatModal.vue` com sessão; mensagens digitadas viram `Message` marcadas `sandbox=true` na `messaging_messages.metadata` (ou tabela separada — decidido no data-model).
3. `ProcessAiResponseJob` roda o ciclo completo (coalescência, work context, RAG, MCP tools) **mas o despacho outbound NÃO sai pelo `OutboundNotificationDispatcher`** — vai pra um `SandboxOutboundDispatcher` que só persiste a resposta na sessão e emite por WebSocket pro modal.
4. Capabilities de escrita do MCP, ao receberem token com `sandbox=true`:
   - `CreateOrFindLeadCapability` → retorna lead sintético (sem persistir em `pacientes`).
   - `HoldSlotCapability` → retorna hold sintético (sem persistir em `slot_reservations`).
   - Capabilities de leitura funcionam normalmente sobre dados reais (FR-041).
5. Eventos auditáveis no sandbox carregam `sandbox=true` e são filtrados das métricas de produção (conversion rate, AHT etc. — FR-042).
6. Ao fechar o modal, sessão fica como `closed_at` populado; admin pode reabrir histórico se config global permitir (FR-043).

### Permissão (FR-044)

- `ai.persona.test` nova permission Spatie, atribuída ao role "Admin Clínica" por default; gate visível na UI.

**Alternativas avaliadas**:
- **Sandbox em DB separado** — overhead operacional (migrations duplas, schema sync), sem ganho real.
- **Modo "dry-run" via flag passada por header HTTP** — fácil de bypassar; menos seguro.
- **Sandbox via fake() do Laravel** — só funciona em testes, não em runtime real do painel.

---

## R7 — Auto-curadoria do Kanban: tools + mapping

**Decisão**: 2 novas capabilities MCP de **escrita reversível** que delegam ao novo `KanbanCurationService`, + 5 listeners de eventos de domínio que disparam o `KanbanAutoTransitionService` lendo o `kanban_pipeline_mappings` do tenant.

### Capabilities novas (estendem as 6 da Fase 17)

- `UpdateLeadProfileCapability` — entrada: `{patient_id (do contexto), field (enum: name|complaint|preferred_city|urgency|procedure|price_range), value (string sanitizado)}` → atualiza campo do `Paciente` + insere `KanbanCurationEvent` (audit FR-022). Limite a allow-list de campos NÃO clínicos (FR-016/057).
- (As 6 originais permanecem: get-clinic-info, list-professionals, get-availability, get-current-patient, create-or-find-lead, hold-slot — só ficam expostas via MCP em vez de `laravel/ai`.)

### Mapping evento→status (FR-019)

- Tabela `kanban_pipeline_mappings` (per-tenant): `event_kind` (enum: lead_created, qualification_started, value_accepted, slot_held, reservation_confirmed, ai_paused_to_human, inactivity) → `funil_coluna_id`. Tenants novos recebem um default seedado (mapping do plan):
  - `lead_created → "new"`, `qualification_started → "qualificando"`, `value_accepted → "negociando"`, `slot_held → "agendado"`, `reservation_confirmed → "confirmado"`, `ai_paused_to_human → "humano"`, `inactivity → "perdido"`.
- Cada listener escuta um evento de domínio existente e chama `KanbanAutoTransitionService::apply($paciente, $eventKind)` que (a) busca o mapping, (b) checa FR-020 (não regredir status sob movimentação manual prévia), (c) faz `Paciente::update(['funil_coluna_atual_id' => ...])`, (d) emite `KanbanCurationEvent`.

### Eventos escutados

| Evento de domínio | Fase | Listener |
|---|---|---|
| `InboundMessageReceived` | Fase 3 | `EnqueueLeadOnInboundMessageListener` — só dispara se contato é novo OU lead existente (FR-011/011a) |
| `SlotReservation::created` | Fase 5 | `PromoteToScheduledOnHoldPlaced` |
| `AppointmentConfirmed` (existe) | Fase 5 | `PromoteToConfirmedOnReservationPaid` |
| `AiAssignmentEscalatedToHuman` | Fase 15 | `PromoteToHumanOnEscalation` |
| Inatividade N dias | scheduler | `DowngradeToLostOnInactivityListener` (cron diário) |

### Persistência

- `kanban_curation_events`: `(id, tenant_id, paciente_id, event_kind, from_coluna_id, to_coluna_id, applied (bool), reason (text), source (enum: 'ia_tool'|'auto_listener'|'manual_override'), turn_id?, created_at)`. Audita TUDO, inclusive supressões (FR-020).

---

## R8 — `ConsentFinalidade::Transcricao` (Q-clarify-2=B)

**Decisão**: adicionar valor `Transcricao` ao enum `App\Domain\Consent\Enums\ConsentFinalidade` (Fase 8) via migration aditiva ao tipo PostgreSQL, e estender o fluxo de consentimentos do paciente para incluí-la opcionalmente.

### Migration

```sql
ALTER TYPE consent_finalidade ADD VALUE IF NOT EXISTS 'transcricao';
```

(Migration própria, idempotente, executada fora de transação por restrição do PostgreSQL com `ADD VALUE`.)

### Aplicação

- `AudioTranscriptionService::storeAudio()`:
  - Sempre persiste o WAV/OGG inbound em storage com TTL = retention default das mídias (Fase 13).
  - Cria `AudioTranscription` com `(media_id, transcribed_text, language, truncated, error?)`.
  - Job `PurgeExpiredAudioRawJob` (cron) deleta o WAV após o TTL **a menos que** o paciente tenha consent ativo `Transcricao`.
- UI: novo toggle `ConsentTranscricaoToggle.vue` no fluxo de consentimentos do paciente (visualizar/aceitar/revogar). Revogação dispara purge retroativo dos áudios que ainda existem (FR-055c).

**Alternativas avaliadas**:
- **Não distinguir finalidades, retenção sempre default** — perde a opção de qualidade/treinamento; menor flexibilidade.
- **Sempre purgar imediatamente após transcrição** — minimização máxima, mas perde caminho de auditoria forense (operador relata "a IA respondeu errado para esse áudio" — sem áudio para revisar).

---

## R9 — Catálogo de vozes + atributo `voice_id` na Persona (Q-clarify-4=B)

**Decisão**: tabela `voice_catalog` curada pelo super-admin (não-tenant-scoped — é catálogo global da plataforma) + coluna `voice_id` (nullable) em `ai_personas`. Tenant tem `default_voice_id` em `tenant_settings` (ou tabela existente equivalente — verificar no data-model).

### `voice_catalog`

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `provider_voice_id` | string | ID interno do provedor (ElevenLabs) — não exposto à UI do admin de clínica |
| `name` | string | "Camila Acolhedora", "Carlos Profissional" — visível ao admin |
| `gender` | enum | `f`, `m`, `neutral` |
| `tone` | enum | `acolhedor`, `profissional`, `energico`, `calmo` |
| `language` | string | `pt-BR` (catálogo MVP só PT-BR) |
| `is_active` | bool | super-admin pode desativar uma voz |
| `preview_audio_url` | string nullable | preview pré-gerado para a UI |

### Resolução em runtime

`PersonaVoiceResolverService::resolveFor(Persona $p): VoiceCatalogEntry`:
1. `$p->voice_id` → se presente, retorna.
2. `tenant_settings.default_voice_id` → se presente, retorna.
3. Voz "system default" do super-admin (configurada em `config/voice-catalog.php`) → retorna.

**Alternativas avaliadas**:
- **`voice_id` direto na Persona como string livre** — admin teria que conhecer IDs do provedor; quebra Q-clarify-4=B (catálogo curado).
- **Voz hardcoded por tenant** — perde o lock no atributo da Persona; quando admin troca de Persona, voz não muda.

---

## R10 — Rate limit anti-abuso (Q-clarify-5=C): reuso do RateLimiter Laravel

**Decisão**: registrar **2 novos `RateLimiter::for(...)`** no `RouteServiceProvider` (padrão Laravel; o mesmo mecanismo usado pelo `ApiPublicRateLimiter` da Fase 8) e aplicá-los como gate ANTES da coalescência (no listener `EnqueueLeadOnInboundMessageListener` ou em um middleware-equivalent dedicado).

### Limiters

```php
RateLimiter::for('messaging:inbound:per-conversation', function (Request $req) {
    return Limit::perMinutes(10, config('messaging.rate.per_conversation', 30))
                ->by($req->conversation_id);
});

RateLimiter::for('messaging:inbound:per-identifier', function (Request $req) {
    return Limit::perMinutes(10, config('messaging.rate.per_identifier', 100))
                ->by($req->tenant_id.':'.$req->channel_identifier);
});
```

### Cooldown auditável (FR-008b/c)

- Excedido → `CooldownService::startFor($conversation, $reasonHeuristic)`:
  - Persiste `cooldown` em `messaging_conversations.metadata` (ou tabela `messaging_conversation_cooldowns`).
  - Emite evento `ConversationCooldownStarted` (auditável, ator=system, reason="rate-limit:per-conversation").
  - Marca prioridade do card do operador como "alta".
  - Durante o cooldown: `IsConversationOnCooldownChecker::check()` é chamado em todos os pontos de:
    - `ProcessAiResponseJob` → no-op
    - `KanbanCurationService` → no-op
    - `AudioSynthesisService` → no-op
    - `McpToolBridge` → no-op (não há tool a invocar sem IA)
- Duração default 15min; expira sozinho ou via ação do operador `CooldownService::endBy($user)` (auditado).

### Distinção heurística (FR-008d)

Simples regex/contagem sobre as últimas N mensagens:
- "abuso provável" = mensagens idênticas (Levenshtein < threshold) OU >X msg/s sustentado.
- "paciente em crise" = mensagens distintas em alta frequência com pontuação emocional ("!!!", "????", palavras-chave dor/urgência).
- Apenas para classificação do alerta na fila do operador; NÃO altera comportamento automático.

**Alternativas avaliadas**:
- **Sem rate limit (Q5=A)** — fácil de explodir custo (cada msg é um turno + tools + STT/TTS).
- **Rate limit só por conversa (Q5=B)** — pega o caso comum, mas atacante distribui em N conversas no mesmo tenant.
- **Classificador NLP para distinguir bot/crise/normal (Q5=D)** — sofisticação para fase futura.

---

## R11 — Detecção de gatilho de áudio outbound (Q3=A)

**Decisão**: `AudioPreferenceDetector` baseado em **lista de frases PT-BR** + variações + tolerância a typos (Levenshtein leve).

### Gatilhos (configuráveis em `config/messaging.php`)

```php
'audio.outbound.triggers' => [
    'não sei ler', 'nao sei ler', 'não consigo ler', 'nao consigo ler',
    'manda áudio', 'manda audio', 'me responda em áudio', 'me responda em audio',
    'me responda por áudio', 'me responda por audio',
    'responde por áudio', 'responde por audio', 'manda por áudio', 'manda por audio',
    'tô dirigindo', 'tô andando', 'tô ocupado', 'estou dirigindo',
    'só posso ouvir', 'so posso ouvir',
],
```

### Lógica

- Em cada turno: examina (a) o texto da mensagem ATUAL do paciente (se for texto) ou (b) a transcrição do áudio recebido.
- Match por substring após normalização (lowercase, sem acento, sem pontuação).
- Match → `SetAudioPreferenceForTurn::set(turn, true)` no contexto do turno; `AudioSynthesisService` só roda se essa preferência é true.
- **Por turno** (FR-033): a flag é por turno (vai junto no turn-state do Redis); próximo turno reseta a menos que novo gatilho apareça.

**Alternativas avaliadas**:
- **Classificador embutido na IA (function call/intent)** — adiciona round-trip ao MCP só para isso; matcher de string é instantâneo e auditável.
- **Sempre áudio quando paciente mandou áudio (Q3=C)** — rejeitado pela clarify.

---

## R12 — Captura/envio de áudio nos adapters de canal

**Decisão**: estender `WhatsAppCloudAdapter`, `EvolutionApiAdapter` e `InstagramGraphAdapter` para baixar mídia inbound (já feito parcialmente — verificar) e enviar mídia outbound (já parcialmente — campo `media_url` existente). Adicionar passo de download para storage local (`messaging_message_media.storage_path`) **antes** de submeter ao STT.

### Detalhes

- **WhatsApp Cloud (Twilio)** — inbound `MediaUrl0` já é baixado pela Fase 13; basta acionar STT quando `media_type=audio/*`.
- **Evolution API** — mídia chega base64 OU URL temporária; baixar para storage local antes do TTL do servidor expirar.
- **Instagram Graph** — inbound mídia via URL temporária; mesmo padrão.
- **Outbound áudio** — todos os adapters suportam `media_url` em outbound; passar a URL pública do áudio gerado em storage (assinada, TTL curto, fora do escopo de retenção prolongada que precisa de consent Transcricao do paciente — TTS outbound é da clínica, não do paciente).

**Sem novo adapter**; apenas paths novos nas implementações existentes.

---

## R13 — Idempotência e ordem nas filas durante coalescência

**Decisão**: usar **chave de idempotência por turn-version** no `ProcessAiResponseJob` e job cancelable via versão.

- `ProcessAiResponseJob` recebe `(conversation_id, turn_version)`; calcula `job_key = sha1("{conversation_id}:{turn_version}")`.
- Antes de processar: `Cache::lock($job_key, 30)->get()` — se já processado, no-op (idempotência forte ao retry da fila).
- Ao final do processamento, antes do dispatch outbound: `if (RedisVersion::get(conversation_id) != turn_version) return;` (cancel-and-reprocess — FR-002).
- Mensagens novas que chegam DURANTE o processamento incrementam a versão; o job atual será descartado no check final; um novo job é enfileirado pelo listener com a nova versão.

---

## R14 — UI/UX do botão "Testar" na tela de Personas (US6)

**Decisão**: botão `Testar` no card de cada Persona + botão "Testar persona" dentro do modal de edição (este último envia a versão NÃO-PUBLICADA do form local — FR-039). Modal de chat ocupa metade direita da tela em desktop, fullscreen em mobile, com:
- Header: nome da Persona, versão sendo testada (publicada ou em edição), botão fechar.
- Stream WebSocket-like (reuso de Echo/Reverb): mensagens chegam por canal privado `private-persona-test.{session_id}`.
- Input simples; opção "enviar como áudio" para testar gatilho (gera áudio de teste localmente ou anexa upload).
- Indicador "IA está pensando…" durante coalescência.
- Botão "limpar conversa" → fecha session, abre nova.
- Footer com link "abrir histórico de sessões" (admin pode reabrir sessões arquivadas).

Decisão visual delegada ao `ux-director` quando começar a Phase 2.

---

## R15 — Outras decisões menores

- **Fila Horizon** — nova fila `transcription` com prioridade alta (paciente está esperando). `ProcessInboundMessageJob` → enfileira `TranscribeInboundAudioJob` ANTES de delegar ao listener de coalescência. STT em paralelo com debounce passivo.
- **Estado de turno no Redis** — namespace `ai:turn:*`, todas as chaves com TTL hard de 5min para evitar leak se job morre.
- **Compose Docker** — `mcp-server` opcional sob profile `mcp` para dev local. Em produção é serviço sempre-on.
- **Migrations idempotentes** — todas as 10 migrations novas têm `IF NOT EXISTS` ou checagem de coluna existente; pode rodar em prod já migrada sem efeito.
- **Backwards compat** — `AI_TOOLS_VIA_MCP=false` (default no merge) preserva tools nativas como caminho ativo; flag só vira `true` num PR/ambiente dedicado após paridade verificada (FR-053).
