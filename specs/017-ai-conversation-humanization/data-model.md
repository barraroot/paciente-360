# Phase 1 — Data Model: Humanização da Conversa da IA

Convenções do projeto: toda tabela com dados de tenant tem `tenant_id` (FK cascade) + global scope; migrations aditivas e idempotentes (Constituição IV); timestamps `timestamptz`.

## Tabelas NOVAS

### 1. `ai_work_contexts` — Contexto de trabalho por clínica (US2)

Um registro **por tenant** (singleton). Híbrido: campos estruturados + texto livre. Versionado para auditoria.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK→tenants | **UNIQUE** (um por clínica) |
| `services` | jsonb | lista de serviços/procedimentos `[{nome, descricao?}]` |
| `pricing` | jsonb | `[{item, valor_a_vista?, valor_cartao?, observacao?}]` (texto, não-clínico) |
| `locations` | jsonb | `[{cidade, endereco?, observacao?}]` |
| `deposit_policy` | jsonb | `{exige_sinal: bool, percentual?, meio?, texto?}` (sinal/confirmação — **handoff**, FR-018) |
| `tone` | varchar(120) | tom de voz (ex.: "acolhedor, com emojis") |
| `qualification_questions` | jsonb | lista ordenada de perguntas de qualificação `["...", "..."]` (FR-014) |
| `free_form` | text | diferenciais/abordagem (texto livre) |
| `version` | int default 1 | incrementa a cada upsert (auditoria FR-025) |
| `is_active` | bool default true | |
| `created_at`/`updated_at` | timestamptz | |

- **Render para prompt** (`AiWorkContextService::renderForPrompt`): bloco markdown "# Contexto de Trabalho da Clínica" com seções (serviços, valores, locais, política de sinal, tom, perguntas) + texto livre. Allow-list não-clínica validada no Form Request.
- **Precedência (FR-011)**: usado como fonte de voz/política e de fatos NÃO presentes no DB; tools sobrepõem em dados dinâmicos.

### 2. `ai_conversation_summaries` — Resumo rolante (US1/US3)

Um registro **por conversa**. Resume os turnos **antes** da janela verbatim.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK→tenants | |
| `conversation_id` | bigint FK→messaging_conversations | **UNIQUE** |
| `summary_text` | text | resumo compacto pseudonimizado |
| `key_facts` | jsonb | `{complaint?, qualification?:[], location?, quoted_price?, intent?}` (FR-004/002a) |
| `funnel_stage` | varchar(20) | greeting\|qualifying\|value\|pricing\|location\|slot\|reservation\|escalated (US4) |
| `covered_up_to_message_id` | bigint | última mensagem já resumida (incrementalidade FR-022) |
| `version` | int default 1 | |
| `created_at`/`updated_at` | timestamptz | |

- **Sem PII bruta**: gerado a partir de mensagens já pseudonimizadas; nome via placeholder.
- **Índice**: `(tenant_id, conversation_id)` unique.
- **Retenção**: alinhada à retenção de execução de IA (≥6m, Princípio III).

### 3. `ai_tool_invocations` — Auditoria de ferramentas (US5/FR-031)

Uma linha por chamada de tool.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK→tenants | |
| `conversation_id` | bigint FK→messaging_conversations | |
| `correlation_id` | uuid | mesma da execução de IA (correlaciona com `AiExecutionLog`) |
| `tool_name` | varchar(64) | ex.: `get-availability` |
| `input_summary` | jsonb | inputs **minimizados/pseudonimizados** |
| `outcome` | varchar(16) | success\|empty\|error\|denied |
| `result_summary` | jsonb (nullable) | resumo do resultado (sem PII de terceiros) |
| `latency_ms` | int | |
| `created_at` | timestamptz | |

- **Índices**: `(tenant_id, conversation_id, created_at)`, `(correlation_id)`.

## Tabela MODIFICADA

### `ai_execution_logs` (Fase 15) — colunas aditivas

| Coluna nova | Tipo | Notas |
|---|---|---|
| `work_context_version` | int nullable | versão do work context que informou a resposta (FR-025) |
| `summary_version` | int nullable | versão do resumo usado |
| `tools_used` | jsonb nullable | lista de `tool_name` invocadas na resposta |
| `tool_round_trips` | smallint nullable | nº de round-trips (≤ cap, SC-008) |

`input_tokens`/`output_tokens`/`latency_ms` já existem (passam a refletir histórico+resumo+work context — FR-024).

## Entidades REUSADAS (sem schema novo)

| Entidade | Tabela | Uso nesta feature |
|---|---|---|
| **Lead** | `pacientes` (`status='lead'`) | `CreateOrFindLeadTool`: lookup por `telefone_primario_normalizado`+`tenant_id`; cria com `origem` do canal, `origem_origem='canal'`, posiciona em `funil_coluna_atual_id`. Consent: `share_with_integrations_consent`. |
| **Slot Hold (tentative)** | `slot_reservations` (`holder_type='ia'`) | `HoldSlotTool`: cria reserva com TTL `expires_at` + `idempotency_key`; gate de corrida `sr_active_unique`. Commit/confirmação = handoff. |
| **Conversa / Mensagens** | `messaging_conversations` / `messaging_messages` | Fonte do histórico; `patient_id`, `external_thread_id` (telefone/thread), `ai_paused_until`, `last_inbound_message_at`. |
| **Profissionais** | `professionals` | `ListProfessionalsTool` / disponibilidade. |
| **Serviços + preços** | `appointment_types` (`nome`, `descricao`, `valor_particular`, `valor_convenio_default`, `duration_minutes`, `is_active`) | `GetClinicInfoTool` (topic=services/pricing) — **fonte DB** dos serviços e valores; precede o work context (FR-011). |
| **Horário/endereço** | (sem tabela dedicada confirmada) | `GetClinicInfoTool` (topic=hours/address) cai no **work context** quando não há fonte no DB; horário pode derivar dos horários de trabalho dos profissionais (Fase 5) se aplicável. |
| **Agenda/disponibilidade** | catálogo Fase 5 (`appointment_types`, slots) | `GetAvailabilityTool` — slots reais. |
| **Persona / KB / Guardrail** | `ai_personas`, `ai_knowledge_*`, `ai_guardrails` | Inalterados; work context complementa com precedência FR-011. |

## Diagrama de relacionamento (texto)

```
tenants 1───* ai_work_contexts        (UNIQUE tenant_id)
tenants 1───* ai_conversation_summaries 1───1 messaging_conversations
tenants 1───* ai_tool_invocations *───1 messaging_conversations
messaging_conversations *───1 pacientes (patient_id; lead quando status='lead')
slot_reservations *───1 professionals   (holder_type='ia' = hold da IA)
ai_execution_logs *───1 messaging_conversations  (+ colunas aditivas)
```

## Regras de validação principais

- `ai_work_contexts`: `qualification_questions` ≤ N itens (config); valores em `pricing`/`deposit_policy`/`locations` são texto livre **não-clínico** (allow-list no Form Request — sem campos clínicos). `tenant_id` único (upsert).
- `ai_conversation_summaries`: `funnel_stage` ∈ enum; `covered_up_to_message_id` monotonicamente crescente.
- `ai_tool_invocations`: `outcome` ∈ enum; `input_summary`/`result_summary` **nunca** contêm PII de terceiros nem dado clínico além do permitido.
