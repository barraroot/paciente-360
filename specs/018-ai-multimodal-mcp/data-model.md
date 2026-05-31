# Phase 1 — Data Model: Conversa Reativa, Multimodal e MCP

Esquema concreto: **7 tabelas novas**, **5 alterações** em existentes (`ai_personas.voice_id`; `messaging_messages` 4 colunas; `messaging_conversations` 2 colunas de cooldown; `funil_colunas.is_initial` UNIQUE parcial; `tenant_settings.default_voice_id` OU tabela auxiliar), **1 alteração de enum PostgreSQL** (`consent_finalidade` ADD VALUE `transcricao`), **estruturas Redis** (não-persistentes) detalhadas. **Total 13 migrations + 1 execução de migrate:fresh** (T009-T022 em tasks.md). Tudo aditivo e idempotente.

---

## Sumário das tabelas novas

| Tabela | Propósito | US |
|---|---|---|
| `audio_transcriptions` | Texto + metadados de cada áudio inbound transcrito (STT) | US4 |
| `audio_syntheses` | Texto + metadados de cada áudio outbound gerado (TTS) | US5 |
| `voice_catalog` | Catálogo global de vozes curado pelo super-admin | US5 |
| `persona_test_sessions` | Sessões isoladas do chat de teste de Persona | US6 |
| `kanban_pipeline_mappings` | Mapping evento→coluna do funil por tenant | US3 |
| `kanban_curation_events` | Auditoria de toda mutação automática do card | US3 |
| `mcp_circuit_breaker_snapshots` | Snapshot histórico de transições do circuit breaker (analytics) | US7 |

## Sumário das alterações em tabelas existentes

| Tabela | Coluna(s) | Propósito |
|---|---|---|
| `ai_personas` | `voice_id` (FK nullable → `voice_catalog`) | Voz como atributo da Persona (FR-037a) |
| `messaging_messages` | `transcription_id`, `is_audio_origin`, `sandbox` | STT + flag de sandbox |
| `messaging_conversations` | `cooldown_until`, `cooldown_reason` | Cooldown de rate limit (FR-008b) |
| `tenant_settings` (ou equivalente) | `default_voice_id` | Default tenant de voz (R9 fallback) |

## Alteração de enum PostgreSQL

| Enum | Valor adicionado |
|---|---|
| `consent_finalidade` (Fase 8) | `transcricao` |

---

## 1. `audio_transcriptions` (NOVA)

Persiste a transcrição (STT) de cada áudio inbound. Uma linha por áudio processado.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK → `tenants` | Global scope |
| `message_id` | bigint FK → `messaging_messages` | Áudio do qual veio |
| `media_id` | bigint FK → `messaging_message_media` | Arquivo bruto referenciado |
| `provider` | string(40) | `openai_whisper`, `google_stt`, `azure_speech` |
| `language_detected` | string(10) | `pt-BR`, `pt`, `en`, `es`... |
| `transcribed_text` | text | Texto antes do `PiiScrubber`; nullable se falhou |
| `truncated` | bool default false | Áudio acima do limite (FR-028) |
| `error_code` | string(40) nullable | `silence`, `language_unsupported`, `timeout`, `provider_error`, `audio_corrupted` |
| `error_message` | text nullable | Detalhes para audit |
| `duration_seconds` | smallint nullable | |
| `latency_ms` | int nullable | Tempo do STT |
| `created_at` / `updated_at` | timestamptz | |

**Índices**:
- `(tenant_id, message_id)` UNIQUE — uma transcrição por mensagem.
- `(tenant_id, created_at)` para queries por janela temporal (métricas).
- `(provider, error_code) WHERE error_code IS NOT NULL` — análise de falhas por provedor.

**Retenção**: segue `messaging_message_media` (Fase 13). NÃO depende do consent `Transcricao` — é texto, sem voz biométrica.

---

## 2. `audio_syntheses` (NOVA)

Persiste cada áudio outbound gerado (TTS). Uma linha por mensagem outbound que teve TTS.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK | |
| `message_id` | bigint FK → `messaging_messages` | Mensagem outbound que carrega o áudio |
| `media_id` | bigint FK → `messaging_message_media` nullable | Arquivo gerado (URL/path) |
| `voice_id` | bigint FK → `voice_catalog` | Voz usada |
| `provider` | string(40) | `elevenlabs`, `openai_tts`, `azure_tts` |
| `source_text` | text | Texto original (PT-BR) submetido ao TTS |
| `normalized_text` | text | Texto pós `TtsTextNormalizer` (FR-035) |
| `segmented` | bool default false | Resposta foi resumida/segmentada por exceder limite (FR-036) |
| `duration_seconds` | smallint nullable | |
| `latency_ms` | int nullable | |
| `error_code` | string(40) nullable | `provider_error`, `too_long`, `voice_unavailable` |
| `error_message` | text nullable | |
| `fallback_to_text` | bool default false | Se TTS falhou e caiu em texto (FR-034) |
| `created_at` / `updated_at` | timestamptz | |

**Índices**:
- `(tenant_id, message_id)` UNIQUE.
- `(tenant_id, created_at)`.
- `(provider, error_code) WHERE error_code IS NOT NULL`.

**Retenção**: igual a `messaging_message_media` outbound (Fase 13).

---

## 3. `voice_catalog` (NOVA)

Catálogo global (NÃO tenant-scoped — gerenciado pelo super-admin).

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `provider` | string(40) | `elevenlabs`, `openai_tts` etc. |
| `provider_voice_id` | string(80) | ID interno do provedor (não exposto à UI do admin de clínica) |
| `display_name` | string(80) | "Camila Acolhedora", "Carlos Profissional" |
| `gender` | string(10) | `f`, `m`, `neutral` |
| `tone` | string(40) | `acolhedor`, `profissional`, `energico`, `calmo` |
| `language` | string(10) | `pt-BR` no MVP |
| `preview_audio_path` | string(255) nullable | Caminho no storage para preview de 5-10s |
| `is_active` | bool default true | Super-admin desativa quando quiser |
| `is_system_default` | bool default false | Voz default quando Persona/tenant não definem |
| `created_at` / `updated_at` | timestamptz | |

**Índices**:
- `(provider, provider_voice_id)` UNIQUE.
- `(language, is_active)` para listagem da UI.
- Exatamente 1 voz com `is_system_default=true` por `language` — garantido por unique parcial e seeder.

**Seed inicial**: 4 vozes ElevenLabs PT-BR (2F: acolhedora + profissional; 2M: profissional + calmo); 1 marcada como `is_system_default`.

---

## 4. `persona_test_sessions` (NOVA)

Sessões isoladas do chat de teste de Persona (US6).

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | bigint FK | |
| `persona_id` | bigint FK → `ai_personas` | |
| `persona_snapshot` | jsonb | Snapshot da Persona/work-context no momento da abertura — testa-se exatamente o que se vê |
| `admin_user_id` | bigint FK → `users` | Quem testa |
| `mcp_token_id` | bigint FK → `personal_access_tokens` nullable | Token Sanctum scoped sandbox (revogado no close) |
| `status` | string(20) | `open`, `closed`, `archived` |
| `closed_at` | timestamptz nullable | |
| `archived_at` | timestamptz nullable | Permite revisão posterior se config global liga |
| `created_at` / `updated_at` | timestamptz | |

**Índices**:
- `(tenant_id, admin_user_id, status)` — listagem por admin.
- `(persona_id, created_at)` — todas as sessões de uma persona.

**Mensagens da sessão**: usam `messaging_messages` com `sandbox=true` E uma nova coluna `sandbox_session_id` (FK) — evita schema duplicado. Não-poluição de métricas é via WHERE no agregador (`WHERE sandbox = false`).

**Retenção**: 30 dias após `closed_at` por default; arquivadas (`archived_at`) ficam indefinidamente até o admin deletar.

---

## 5. `kanban_pipeline_mappings` (NOVA)

Mapping evento→coluna do funil, por tenant.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK | |
| `event_kind` | string(40) | `lead_created`, `qualification_started`, `value_accepted`, `slot_held`, `reservation_confirmed`, `ai_paused_to_human`, `inactivity` |
| `funil_coluna_id` | bigint FK → `funil_colunas` | Coluna alvo da transição |
| `is_active` | bool default true | Tenant pode desligar transição individual |
| `created_at` / `updated_at` | timestamptz | |

**Índices**:
- `(tenant_id, event_kind)` UNIQUE — um mapping por evento por tenant.

**Seed por tenant** (executado no `LeadOnboardingService::ensurePipelineMappingForTenant`, idempotente):

```
lead_created          → funil_colunas WHERE slug='new'         (criada se não existir)
qualification_started → funil_colunas WHERE slug='qualificando'
value_accepted        → funil_colunas WHERE slug='negociando'
slot_held             → funil_colunas WHERE slug='agendado'
reservation_confirmed → funil_colunas WHERE slug='confirmado'
ai_paused_to_human    → funil_colunas WHERE slug='humano'
inactivity            → funil_colunas WHERE slug='perdido'
```

Se algum slug não existir nas `funil_colunas` do tenant, o seeder cria com `is_system=true`, `motivo_obrigatorio` apropriado, e `posicao` derivada da ordem do funil.

**Observação**: a coluna `funil_colunas.is_initial` (BOOL) é adicionada via migration nova para identificar a coluna "new" sem depender de slug exato (admins podem renomear slug `new` para `novos-leads`). Por convenção, exatamente 1 coluna por tenant tem `is_initial=true` (UNIQUE parcial).

---

## 6. `kanban_curation_events` (NOVA)

Audit log de toda mutação automática feita no card pela IA ou por listener.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK | |
| `paciente_id` | bigint FK → `pacientes` | |
| `event_kind` | string(40) | mesmo enum de mappings + `profile_updated`, `note_added` |
| `source` | string(20) | `ia_tool`, `auto_listener`, `manual_override_blocked` |
| `from_coluna_id` | bigint FK nullable | Status anterior (null se profile update sem move) |
| `to_coluna_id` | bigint FK nullable | Status novo |
| `applied` | bool | False quando transição foi suprimida (FR-020) |
| `suppression_reason` | string(60) nullable | `manual_override`, `terminal_no_regress`, `cooldown_active` |
| `field_changed` | string(40) nullable | Para `profile_updated`: `name`, `complaint`, `preferred_city`, `urgency`, `procedure`, `price_range` |
| `value_before` | text nullable | Valor anterior (audit history — FR-021) |
| `value_after` | text nullable | Valor novo |
| `turn_version` | int nullable | Versão do turno na coalescência (FR-005) |
| `tool_invocation_id` | bigint FK → `ai_tool_invocations` nullable | Quando origem é tool MCP |
| `actor_type` | string(20) | `ia`, `system`, `user` |
| `actor_id` | bigint nullable | user_id quando manual |
| `reason` | text nullable | Justificativa textual (opcional) |
| `created_at` | timestamptz | |

**Índices**:
- `(tenant_id, paciente_id, created_at)` — timeline do card.
- `(tenant_id, event_kind, created_at)` — analytics.
- `(tool_invocation_id) WHERE tool_invocation_id IS NOT NULL` — joins com auditoria de IA.

**Retenção**: ≥6m (alinhado com `ai_execution_logs` da Fase 15).

---

## 7. `mcp_circuit_breaker_snapshots` (NOVA)

Snapshot histórico de transições do circuit breaker (analytics + auditoria). O estado **vivo** é Redis (R5); esta tabela registra cada abertura/fechamento.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `transition_to` | string(20) | `open`, `half_open`, `closed` |
| `failures_observed` | int | Contador no momento da transição |
| `cooldown_seconds` | int | Valor de cooldown aplicado |
| `last_error_code` | string(60) nullable | |
| `last_error_message` | text nullable | |
| `source` | string(20) | `automatic` (circuit breaker) ou `manual_flag` (admin mudou `AI_TOOLS_VIA_MCP`) |
| `actor_user_id` | bigint nullable | Quando source=manual_flag |
| `created_at` | timestamptz | |

**Índices**:
- `(created_at DESC)` — leitura mais recente primeiro.
- `(transition_to, created_at)` — análise por estado.

**Sem `tenant_id`** — é estado global do MCP (1 servidor por instalação).

**Retenção**: 90 dias.

---

## 8. Alterações em `ai_personas` (Fase 15)

```sql
ALTER TABLE ai_personas
    ADD COLUMN voice_id BIGINT NULL REFERENCES voice_catalog(id) ON DELETE SET NULL;
CREATE INDEX ai_personas_voice_id_idx ON ai_personas (voice_id) WHERE voice_id IS NOT NULL;
```

- Nullable → fallback no `PersonaVoiceResolverService` (R9).
- `ON DELETE SET NULL` para super-admin poder desativar/remover voz sem quebrar Personas.

## 9. Alterações em `messaging_messages` (Fase 3)

```sql
ALTER TABLE messaging_messages
    ADD COLUMN transcription_id BIGINT NULL REFERENCES audio_transcriptions(id) ON DELETE SET NULL,
    ADD COLUMN is_audio_origin BOOLEAN NOT NULL DEFAULT false,
    ADD COLUMN sandbox BOOLEAN NOT NULL DEFAULT false,
    ADD COLUMN sandbox_session_id UUID NULL REFERENCES persona_test_sessions(id) ON DELETE CASCADE;
CREATE INDEX messaging_messages_tenant_sandbox_idx ON messaging_messages (tenant_id, sandbox);
CREATE INDEX messaging_messages_sandbox_session_idx ON messaging_messages (sandbox_session_id) WHERE sandbox_session_id IS NOT NULL;
```

- `transcription_id` aponta para o STT correspondente (NULL para mensagens texto-nato).
- `is_audio_origin` marca que o **conteúdo** foi originado de áudio (útil para UI mostrar ícone "áudio transcrito").
- `sandbox` + `sandbox_session_id` permitem filtrar mensagens de teste das métricas de produção (FR-042).
- Métricas agregadoras (DashboardService, ExecutiveDashboard) ganham `WHERE sandbox = false` no scope global default (revisar pontos existentes — gate de regressão).

## 10. Alterações em `messaging_conversations` (Fase 3)

```sql
ALTER TABLE messaging_conversations
    ADD COLUMN cooldown_until TIMESTAMPTZ NULL,
    ADD COLUMN cooldown_reason VARCHAR(80) NULL;
CREATE INDEX messaging_conversations_cooldown_idx ON messaging_conversations (cooldown_until) WHERE cooldown_until IS NOT NULL;
```

- `cooldown_until`: timestamp futuro indica que a conversa está em cooldown (R10/FR-008b/c). NULL = sem cooldown.
- `cooldown_reason`: `rate-limit:per-conversation`, `rate-limit:per-identifier`, `manual:operator`.

## 11. Alteração em tenant settings — `default_voice_id`

Tabela alvo: confirmar nome em `database/migrations` (provavelmente `tenant_settings` ou colunas em `tenants`). Migration adiciona:

```sql
ALTER TABLE <tenant_settings_table>
    ADD COLUMN default_voice_id BIGINT NULL REFERENCES voice_catalog(id) ON DELETE SET NULL;
```

Se não houver tabela de settings dedicada, criar uma nova `tenant_voice_settings` minimalista — decisão tomada em T021 antes de gerar a migration (rodar `mcp__laravel-boost__database-schema` para confirmar).

## 12. Alteração em `funil_colunas` (Fase 2) — `is_initial`

```sql
ALTER TABLE funil_colunas ADD COLUMN is_initial BOOLEAN NOT NULL DEFAULT false;
CREATE UNIQUE INDEX funil_colunas_tenant_initial_unique ON funil_colunas (tenant_id) WHERE is_initial = true;
```

Backfill por tenant: marcar como `is_initial=true` a coluna com `slug='new'`; se não existir, a primeira por `posicao` ASC; se tenant não tem coluna nenhuma, o seeder de mappings (T035) cria uma com `slug='new'`, `is_system=true`, `is_initial=true`.

Necessário para FR-011/FR-013 não dependerem de slug literal `'new'` (admin pode renomear).

## 13. Alteração no enum `consent_finalidade`

Migration própria, executada fora de transação por restrição do PostgreSQL:

```php
public function up(): void
{
    DB::statement("ALTER TYPE consent_finalidade ADD VALUE IF NOT EXISTS 'transcricao'");
}
```

`ConsentFinalidade::Transcricao` adicionado ao enum PHP. Migration **NÃO reversível** (PostgreSQL não suporta `DROP VALUE` em enum); documentado no docblock.

---

## Estruturas Redis (não-persistentes)

### Coalescência (R3)

```
ai:turn:{conv}:v                INCR-only INTEGER     TTL 5min
ai:turn:{conv}:debounce         STRING ":_"           TTL 4s (config: passive_debounce_s)
ai:turn:{conv}:started_at       INTEGER (unix ts)     TTL 5min
ai:turn:{conv}:msgs             LIST<message_id>      TTL 5min
ai:turn:{conv}:reprocess        INTEGER               TTL 5min
ai:turn:{conv}:dispatching      STRING "{job_id}"     TTL 5s (lock final)
```

### Circuit breaker (R5)

```
mcp:cb:state                    STRING ("closed"|"open"|"half_open")
mcp:cb:failures                 INTEGER (com TTL 30s para janela rolante)
mcp:cb:opened_at                INTEGER (unix ts)
mcp:cb:cooldown_seconds         INTEGER (atual com backoff)
mcp:cb:canary_in_flight         STRING ("1") NX EX 30 (lock da canário no half_open)
```

### Rate limit (R10) — reusa namespace existente do Laravel `RateLimiter`

```
laravel_cache:messaging:inbound:per-conversation:{conv_id}
laravel_cache:messaging:inbound:per-identifier:{tenant_id}:{identifier}
```

### Audio preference por turno (R11)

```
ai:turn:{conv}:audio_preference STRING "true"|"false" TTL 5min
```

---

## Relações chave

```text
ai_personas        ── voice_id ──► voice_catalog
messaging_messages ── transcription_id ──► audio_transcriptions
messaging_messages ── sandbox_session_id ──► persona_test_sessions
audio_transcriptions ── message_id ──► messaging_messages
audio_syntheses    ── message_id ──► messaging_messages
audio_syntheses    ── voice_id ──► voice_catalog
kanban_pipeline_mappings ── funil_coluna_id ──► funil_colunas
kanban_curation_events ── paciente_id ──► pacientes
kanban_curation_events ── tool_invocation_id ──► ai_tool_invocations (Fase 17)
persona_test_sessions ── mcp_token_id ──► personal_access_tokens
persona_test_sessions ── persona_id ──► ai_personas
```

---

## State transitions importantes

### Card de paciente no kanban (auto-curadoria)

```
            ┌──────────┐
            │   new    │ ← FR-009/011 (auto-criação)
            └────┬─────┘
                 │ qualification_started
                 ▼
        ┌────────────────┐
        │ qualificando   │
        └────┬───────────┘
             │ value_accepted
             ▼
        ┌────────────────┐
        │  negociando    │
        └────┬───────────┘
             │ slot_held (tool: HoldSlotCapability)
             ▼
        ┌────────────────┐
        │   agendado     │
        └────┬───────────┘
             │ reservation_confirmed (evento Fase 5)
             ▼
        ┌────────────────┐
        │  confirmado    │  (TERMINAL — não regride por automação)
        └────────────────┘

Em qualquer estágio: ai_paused_to_human → "humano"
Em qualquer estágio inativo N dias: → "perdido" (TERMINAL)
```

**Regras de inviolabilidade**:
- Transição automática para frente **só ocorre se** estado atual != mesmo OU mais avançado.
- Transição manual do operador (FR-020) **trava** retrocessos automáticos: se operador moveu para "negociando" → "humano", o `slot_held` automático NÃO regride para "agendado"; registra `suppression_reason='manual_override'` em `kanban_curation_events`.

### Circuit breaker do MCP (R5/FR-053b/c/d)

```
                          ┌──────────┐
                          │  closed  │ ◄────────────────┐
                          └────┬─────┘                  │
                               │ N failures             │ canary success
                               │ in window              │
                               ▼                        │
                          ┌──────────┐                  │
                  ┌──────►│   open   │                  │
                  │       └────┬─────┘                  │
                  │            │ cooldown elapsed       │
                  │            ▼                        │
                  │       ┌──────────┐                  │
                  │       │ half_open│ ──canary───┐     │
                  │       └──────────┘            │     │
                  │              ▲                ▼     │
                  │              │             check ◄──┘
                  │              │             success ?
                  │              │                │
                  └──────────────┴─canary fail────┘
                                  (backoff)
```

### Persona test session (US6)

```
created ── status='open' (token Sanctum emitido) ─►  user interaction
              │                                          │
              │                                          ▼
              │                                    user closes
              │                                          │
              ▼                                          ▼
        token revogado                            status='closed'
        ao closed                                  closed_at = now
                                                         │
                                                         ▼
                                                 admin archives?
                                                  status='archived'
                                                  archived_at = now
```

---

## Volume estimado (sanity check)

| Tabela | Cresc. anual (tenant ativo médio) | Notas |
|---|---|---|
| `audio_transcriptions` | ~50k linhas | Assume 200 áudios inbound/dia |
| `audio_syntheses` | ~10k linhas | TTS é sob gatilho — minoria |
| `voice_catalog` | ~10-20 linhas | Global, curado |
| `persona_test_sessions` | ~500 linhas | Admin testa ocasionalmente |
| `kanban_pipeline_mappings` | 7 linhas | Fixo por tenant |
| `kanban_curation_events` | ~150k linhas | Cada turno produz ~1-2 eventos |
| `mcp_circuit_breaker_snapshots` | <100 linhas | Estável |

Sem particionamento necessário. Reter 6-12m. Purge job opcional (deferred).
