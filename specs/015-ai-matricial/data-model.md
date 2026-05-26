# Phase 1 — Data Model: IA Matricial

Convenções: todas as tabelas usam `id` (bigint), `created_at`/`updated_at`. Todas — **exceto `ai_models`** — têm `tenant_id` (FK `tenants`, indexado) e aplicam `BelongsToTenant` + `TenantScope` (Princípio II). Soft delete onde o padrão do projeto usa (personas/bases/guardrails seguem o padrão de soft delete já adotado). `created_by`/`updated_by` referenciam `users`. Identificadores de código em inglês; strings de UI em pt-BR (i18n).

---

## 1. `ai_models` — catálogo global da plataforma (SEM tenant)

| Coluna | Tipo | Notas |
|--------|------|-------|
| name | varchar | nome de exibição |
| provider | varchar | ex.: `anthropic`, `openai` |
| internal_identifier | varchar | id do provedor (ex.: `claude-...`); **UNIQUE** |
| description | text null | |
| config_schema | jsonb | parâmetros permitidos e limites (ex.: `{temperature:{min,max}, max_tokens:{max}}`) |
| supports_embedding | boolean default false | se o modelo serve para embeddings RAG |
| embedding_dimension | int null | dimensão quando embedding |
| is_active | boolean default true | inativo não selecionável em **novas** personas |

**Gestão**: Filament super-admin (`AiModelResource`). Tenant só lê ativos via API.

---

## 2. `ai_personas`

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| ai_model_id | FK `ai_models` | modelo selecionado (ativo no momento da escolha; histórico preservado) |
| name | varchar | |
| description | text null | descrição interna |
| markdown_content | text | conteúdo principal (sanitizado no save) |
| tone | varchar null | tom de voz |
| objective | text null | objetivo do atendimento |
| limitations | text null | limitações |
| initial_message | text null | mensagem inicial opcional |
| fallback_message | text null | mensagem de fallback opcional |
| handoff_rules | text null | regras de encaminhamento humano (Markdown) |
| model_settings | jsonb null | parâmetros compatíveis com `config_schema` do modelo (validados) |
| is_active | boolean default true | inativa não recebe novos atendimentos |
| created_by / updated_by | FK users null | |
| deleted_at | timestamp null | soft delete |

**Validações**: `model_settings` validado contra `ai_models.config_schema`; modelo selecionado deve estar **ativo** na criação (FR-002/FR-003). Markdown sanitizado (FR-041).
**Relações**: belongsTo AiModel; hasMany AiPersonaChannel; belongsToMany KnowledgeBase/Guardrail (pivots); hasMany AiConversationAssignment.

---

## 3. `ai_knowledge_bases`

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| name | varchar | |
| description | text null | |
| markdown_content | text | sanitizado no save; fonte do chunking RAG |
| tags | jsonb null | tags opcionais |
| metadata | jsonb null | metadados opcionais |
| is_active | boolean default true | inativa não é recuperada em novas respostas (FR-021/021b) |
| indexed_at | timestamp null | última (re)indexação concluída |
| created_by / updated_by | FK users null | |
| deleted_at | timestamp null | |

---

## 4. `ai_knowledge_chunks` — índice semântico (RAG / pgvector)

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | filtro de isolamento na similaridade |
| ai_knowledge_base_id | FK `ai_knowledge_bases` (cascade) | |
| chunk_index | int | ordem do fragmento |
| content | text | trecho original (texto puro do chunk) |
| token_count | int null | |
| embedding | `vector(N)` | N = `embedding_dimension` do modelo de embedding (config) |

**Índice**: HNSW (cosine) em `embedding`; índice btree em `(tenant_id, ai_knowledge_base_id)`.
**Ciclo de vida**: gerado/substituído por `EmbedKnowledgeBaseJob` ao salvar conteúdo da base (R10). Recuperação filtra por `tenant_id`, bases **ativas** associadas à persona, top-K acima de limiar.

---

## 5. `ai_guardrails`

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| name | varchar | |
| description | text null | |
| category | varchar null | ex.: `seguranca`,`lgpd`,`atendimento_medico`,`encaminhamento`,`tom_de_voz`,`restricoes_comerciais`,`emergencia`,`privacidade` |
| markdown_content | text | sanitizado no save |
| is_active | boolean default true | inativo não aplicado em novas respostas (FR-025) |
| created_by / updated_by | FK users null | |
| deleted_at | timestamp null | |

> **Nota**: guardrails médicos mínimos obrigatórios **NÃO** vivem nesta tabela — são código/config sempre aplicado (R6, FR-026/027). A clínica só adiciona restrições.

---

## 6. `ai_persona_channels` — matriz Persona × Canal

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| ai_persona_id | FK `ai_personas` (cascade) | |
| channel_type | varchar | **mesmos identificadores existentes**: `whatsapp`,`instagram`,`web` (web = Widget de site) |
| is_active | boolean default true | célula ativa/inativa da matriz |

**Constraints**: UNIQUE `(tenant_id, ai_persona_id, channel_type)`. `channel_type` validado contra os tipos existentes em `messaging_channels`.
**Uso**: round-robin considera apenas linhas `is_active=true` de personas `is_active=true`.

---

## 7. `ai_persona_knowledge_base` — pivot Persona × Base

| Coluna | Tipo |
|--------|------|
| tenant_id | FK |
| ai_persona_id | FK (cascade) |
| ai_knowledge_base_id | FK (cascade) |

**Constraints**: UNIQUE `(ai_persona_id, ai_knowledge_base_id)`. **Co-tenancy validada** no Service (persona e base do mesmo tenant) — FR-019/020.

---

## 8. `ai_persona_guardrail` — pivot Persona × Guardrail

| Coluna | Tipo |
|--------|------|
| tenant_id | FK |
| ai_persona_id | FK (cascade) |
| ai_guardrail_id | FK (cascade) |

**Constraints**: UNIQUE `(ai_persona_id, ai_guardrail_id)`. Co-tenancy validada (FR-023/024).

---

## 9. `ai_channel_distribution_states` — estado do round-robin

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| channel_type | varchar | `whatsapp`/`instagram`/`web` |
| last_ai_persona_id | FK `ai_personas` null | última persona atribuída |
| last_position | int default -1 | posição no ciclo |
| updated_at | timestamp | |

**Constraints**: UNIQUE `(tenant_id, channel_type)`. Acesso sempre via `lockForUpdate()` em transação (R3).

---

## 10. `ai_conversation_assignments` — atribuição de persona à conversa existente

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| conversation_id | FK `messaging_conversations` (cascade) | **referência à conversa existente** |
| channel_type | varchar | herdado da conversa/canal |
| ai_persona_id | FK `ai_personas` | persona atribuída |
| status | varchar | `assigned`,`in_progress`,`paused`,`closed`,`error` |
| assigned_at | timestamp | |
| unassigned_at | timestamp null | |
| paused_by | FK users null | quem pausou (atendente) |
| paused_at | timestamp null | |
| metadata | jsonb null | contexto auxiliar (sem PII clínica) |

**Constraints**: UNIQUE parcial `(conversation_id) WHERE status <> 'closed'` — **uma atribuição ativa por conversa** (continuidade FR-015). 
**Estados da IA na conversa (FR-031)** derivados: sem assignment → *IA não habilitada* / *aguardando atribuição*; `assigned`/`in_progress` → *em atendimento por IA*; `paused` (+ `ai_paused_until` na conversa) → *pausada p/ humano*; `closed` → *encerrada*; `error` → *erro na IA*.
**Pausa**: reusa `messaging_conversations.ai_paused_until` + `ai_pause_set_by` existentes (R5); listener `PauseAiOnHumanTakeover`. Pausa **indefinida** (sem auto-resume temporizado) — só reativação manual por `inbox.respond` (clarificação 2026-05-26).
**Reatribuição (FR-016a)**: ao desativar/remover persona do canal, as atribuições `assigned`/`in_progress` dela são reapontadas via `AiPersonaSelectorService` para outra persona ativa do canal (nova linha/atualização registra a persona); sem outra ativa → `status=paused` + handoff humano.

---

## 11. `ai_execution_logs` — log auditável por decisão (Princípio III/V)

| Coluna | Tipo | Notas |
|--------|------|-------|
| tenant_id | FK | |
| conversation_id | FK null | |
| message_id | FK `messaging_messages` null | mensagem de origem |
| response_message_id | FK `messaging_messages` null | mensagem enviada pela IA (se enviada) |
| ai_persona_id | FK null | |
| ai_model_id | FK null | |
| channel_type | varchar null | |
| correlation_id | uuid | rastreio ponta a ponta (Princípio V) |
| prompt_summary | text null | **prompt enviado** (pseudonimizado) |
| context_summary | jsonb null | **contexto pseudonimizado**: bases/guardrails usados, chunks recuperados (ids), sem PII clínica |
| classified_intent | varchar null | intenção classificada (Princípio III) |
| confidence_score | decimal null | score de confiança (Princípio III) |
| response_summary | text null | resposta gerada |
| action | varchar null | `sent`,`escalated_human`,`redirected_scheduling`,`flagged_review`,`suppressed` |
| status | varchar | `success`,`escalated`,`failed` |
| error_message | text null | sem PII |
| latency_ms | int null | alvo p95 ≤ 5000 |
| input_tokens / output_tokens | int null | |
| estimated_cost | decimal null | só log/auditoria (cobrança fora de escopo) |

**Retenção**: ≥ 6 meses (Princípio III). Marcador `ContainsNoClinicalData` aplicado aos eventos derivados; conteúdo já pseudonimizado (Princípio I).
**Isolamento**: escopado por tenant; `ai.log.view` exigido (FR-035, SC-005).

---

## Reuso de entidades existentes (NÃO recriadas)

| Entidade existente | Uso pela IA Matricial |
|--------------------|------------------------|
| `Tenant` (slug/subdomínio) | escopo de todas as `ai_*` (exceto `ai_models`) |
| `messaging_channels` (`type`: whatsapp/instagram/web) | `channel_type` referencia esses identificadores |
| `messaging_conversations` (`ai_paused_until`,`ai_pause_set_by`,`priority`,`status`) | atribuição/pausa/escala (prioridade alta em urgência) |
| `messaging_messages` (`sender_type=ai`,`direction=out`) | resposta da IA enviada via `MessageDispatchService` |
| Eventos `MensagemRecebida`,`ConversaCriada`,`ConversaAssumidaPorHumano`,`ConversaRetomadaPelaIA` | gatilhos/listeners |
| `PiiScrubber`/`PiiDetector`/`AnonymizationMap` | pseudonimização do contexto antes do LLM |
| `MessageDispatchService::send` | único ponto de envio (mantém gate Meta da Fase 13/14) |
| Abilities Spatie (`inbox.respond`) | pausar/reativar IA na conversa |
