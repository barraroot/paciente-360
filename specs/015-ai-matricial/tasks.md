---
description: "Task list — IA Matricial (feature 015)"
---

# Tasks: IA Matricial

**Input**: Design documents from `/specs/015-ai-matricial/`
**Prerequisites**: plan.md, spec.md, research.md (R1–R12), data-model.md (11 tabelas), contracts/api.md (G1–G12), quickstart.md (Lotes A–I)

**Tests**: INCLUÍDOS — exigidos pelo Princípio IV (test-first, cobertura ≥70%), pelo Princípio III (testes de bypass de guardrail) e pelo pedido do usuário (testes multi-tenant / segurança / fluxo de IA).

**Organização**: por user story (US1–US8 do spec.md), em ordem de prioridade (P1→P3), respeitando a ordem incremental pedida (DB→CRUD→matrizes→round-robin→atribuição→jobs→canais→logs/fallback→Vue→editor→auxiliar→conversa→testes).

**Escopo NEGATIVO (não fazer)**: recriar clínicas, auth, WhatsApp, Widget, Instagram, atendimento humano ou módulo de conversas; refatorar o sistema. A camada é **plugável** sobre o existente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US8; fases Setup/Foundational/Polish não têm label
- Comandos sempre via `vendor/bin/sail`

---

## Phase 1: Setup (Infraestrutura compartilhada)

**Purpose**: dependências aprovadas (laravel/ai + pgvector) e configuração base.

- [x] T001 Instalar Laravel AI SDK: `vendor/bin/sail composer require laravel/ai` (fixar v0.x no `composer.json`) e publicar config (`config/ai.php`)
- [x] T002 [P] Migration de extensão `CREATE EXTENSION IF NOT EXISTS vector` em `database/migrations/`; (opcional) `vendor/bin/sail composer require pgvector/pgvector`
- [x] T003 [P] Criar `config/ai.php` (sobre a config publicada): provider (Anthropic default) + credenciais via env globais, `embedding_dimension`, `confidence_threshold`, `debounce_seconds`, `retry.attempts`/`backoff`, `rag.top_k`/`min_similarity`, referência aos guardrails médicos mínimos
- [x] T004 [P] Criar `lang/pt_BR/ai.php` (rótulos/erros das telas e validações da IA Matricial)
- [x] T005 Adicionar supervisor/fila `ai` em `config/horizon.php` (worker dedicado para `ProcessAiResponseJob`)

---

## Phase 2: Foundational (DB + Models + Abilities + Serviços compartilhados)

**⚠️ BLOQUEIA todas as user stories.** Cobre o item "1. Banco de dados e models" do pedido.

### Migrations (tabelas `ai_*` — data-model.md)

- [x] T006 [P] Migration `ai_models` (catálogo global, SEM tenant; UNIQUE `internal_identifier`; `config_schema` jsonb; `supports_embedding`/`embedding_dimension`) em `database/migrations/`
- [x] T007 [P] Migration `ai_personas` (tenant, FK `ai_model_id`, markdown/tone/objective/limitations/initial/fallback/handoff, `model_settings` jsonb, `is_active`, created_by/updated_by, soft delete)
- [x] T008 [P] Migration `ai_knowledge_bases` (tenant, markdown, tags/metadata jsonb, `is_active`, `indexed_at`, soft delete)
- [x] T009 [P] Migration `ai_guardrails` (tenant, `category`, markdown, `is_active`, soft delete)
- [x] T010 [P] Migration `ai_persona_channels` (tenant, persona, `channel_type` whatsapp/instagram/web, `is_active`, UNIQUE `(tenant_id,ai_persona_id,channel_type)`)
- [x] T011 [P] Migration `ai_persona_knowledge_base` (pivot, tenant, UNIQUE `(ai_persona_id,ai_knowledge_base_id)`)
- [x] T012 [P] Migration `ai_persona_guardrail` (pivot, tenant, UNIQUE `(ai_persona_id,ai_guardrail_id)`)
- [x] T013 [P] Migration `ai_channel_distribution_states` (tenant, `channel_type`, `last_ai_persona_id`, `last_position`, UNIQUE `(tenant_id,channel_type)`)
- [x] T014 [P] Migration `ai_conversation_assignments` (tenant, FK `conversation_id`, `channel_type`, `ai_persona_id`, `status`, assigned/unassigned/paused fields, `metadata`, UNIQUE parcial `(conversation_id) WHERE status<>'closed'`)
- [x] T015 [P] Migration `ai_knowledge_chunks` (tenant, FK base cascade, `chunk_index`, `content`, `token_count`, `embedding vector(N)`, índice HNSW cosine + btree `(tenant_id,ai_knowledge_base_id)`) — depende de T002
- [x] T016 [P] Migration `ai_execution_logs` (tenant, conversation/message/response_message/persona/model, `correlation_id`, prompt/context/intent/confidence/response/action/status/error/latency/tokens/cost; retenção ≥6m)

### Models + Factories

- [x] T017 [P] Model `app/Domain/Ai/Model/Models/AiModel.php` (global, SEM `BelongsToTenant`) + factory
- [x] T018 [P] Model `app/Domain/Ai/Persona/Models/AiPersona.php` (`BelongsToTenant`, soft delete, relações model/channels/bases/guardrails/assignments) + factory + states (active/inactive)
- [x] T019 [P] Model `app/Domain/Ai/KnowledgeBase/Models/AiKnowledgeBase.php` + `AiKnowledgeChunk.php` (cast vector ou raw) + factories
- [x] T020 [P] Model `app/Domain/Ai/Guardrail/Models/AiGuardrail.php` + factory
- [x] T021 [P] Models pivot `app/Domain/Ai/Matrix/Models/{AiPersonaChannel,AiPersonaKnowledgeBase,AiPersonaGuardrail}.php`
- [x] T022 [P] Model `app/Domain/Ai/Distribution/Models/AiChannelDistributionState.php`
- [x] T023 [P] Model `app/Domain/Ai/Assignment/Models/AiConversationAssignment.php` + factory + states
- [x] T024 [P] Model `app/Domain/Ai/Execution/Models/AiExecutionLog.php` + factory

### Compartilhado (blocking)

- [x] T025 Registrar abilities IA (`ai.persona.view/manage`, `ai.knowledge.view/manage`, `ai.guardrail.view/manage`, `ai.matrix.manage`, `ai.log.view`) em seeder de permissões + definir gates ability-based em `AppServiceProvider` usando `$user->hasPermissionTo(...)` (NUNCA `can()` — evitar recursão, lição Fase 12)
- [x] T026 [P] `app/Domain/Ai/Services/MarkdownSanitizerService.php` (remove HTML/script/eventos/`javascript:`; mantém Markdown puro) + unit test em `tests/Unit/Ai/MarkdownSanitizerServiceTest.php`
- [x] T027 [P] `AiModelResource` Filament (super-admin) em `app/Filament/Resources/` (CRUD catálogo global) + policy super-admin + seeder de ≥1 modelo ativo (texto + embedding)
- [ ] T028 Grupo de rotas `/api/v1/ai` em `routes/api.php` com middleware `['auth:sanctum','tenant.slug','tenant.not-suspended']` + entradas em `resources/js/config/navigation.js` (gated por abilities IA)

**Checkpoint**: schema + models + abilities + sanitizer prontos — user stories podem começar.

---

## Phase 3: User Story 1 — Configurar persona com modelo (Priority: P1) 🎯 MVP

**Goal**: a clínica cria/edita personas selecionando um modelo ativo; isolado por clínica.
**Independent Test**: criar persona via API com modelo ativo + markdown válido; cross-tenant 404; modelo inativo não selecionável; settings validados.

### Tests (US1)

- [ ] T029 [P] [US1] Feature test CRUD de personas + cross-tenant 404 (G1) em `tests/Feature/Ai/PersonaCrudTest.php`
- [ ] T030 [P] [US1] Unit test validação de `model_settings` vs `config_schema` e bloqueio de modelo inativo (FR-002/003/008) em `tests/Unit/Ai/PersonaModelSettingsTest.php`

### Implementação (US1)

- [ ] T031 [P] [US1] `app/Http/Requests/Ai/StorePersonaRequest.php` e `UpdatePersonaRequest.php` (valida campos, sanitiza markdown via T026, exige modelo ativo na criação, valida settings vs schema)
- [ ] T032 [P] [US1] `app/Policies/Ai/AiPersonaPolicy.php` (abilities `ai.persona.*`, escopo tenant)
- [ ] T033 [P] [US1] `app/Http/Resources/Ai/AiPersonaResource.php` (sem PII; inclui contagem/associações via whenLoaded) + `AiModelResource` (API, somente ativos + os referenciados)
- [ ] T034 [US1] `app/Domain/Ai/Persona/Services/AiPersonaService.php` (create/update/activate/deactivate; ativar/desativar via endpoints dedicados, `is_active` prohibited no update)
- [ ] T035 [US1] `app/Http/Controllers/Api/V1/Ai/AiPersonaController.php` (index/store/show/update/destroy/activate/deactivate) + `AiModelController@index`
- [ ] T036 [US1] Registrar rotas de personas e `GET /ai/models` em `routes/api.php`
- [ ] T037 [P] [US1] Frontend: `resources/js/pages/Ia/PersonasIndex.vue` + `PersonaForm.vue` (selação de modelo; markdown via textarea simples — editor rico vem na US8) + store `resources/js/stores/ia.js` (personas)

**Checkpoint**: personas CRUD funcional e isolado por clínica.

---

## Phase 4: User Story 2 — Matriz Persona×Canal + round-robin + atribuição (Priority: P1)

**Goal**: configurar quais personas atendem quais canais e distribuir novas conversas de forma equilibrada.
**Independent Test**: 2 personas ativas no WhatsApp + 4 novas conversas → 2/2; canal sem persona → IA não atende; conversa atribuída mantém persona.

### Tests (US2)

- [ ] T038 [P] [US2] Unit round-robin: distribuição ≤1 de diferença, concorrência com lock sem duplicar posição, isolamento tenant+canal (G3, SC-002) em `tests/Unit/Ai/PersonaSelectorTest.php`
- [ ] T039 [P] [US2] Feature matriz Persona×Canal (PUT/GET) + co-tenancy + `GET channels/{type}/config` e canal sem persona ativa → IA desabilitada (G5, FR-011a) em `tests/Feature/Ai/PersonaChannelMatrixTest.php`
- [ ] T040 [P] [US2] Feature continuidade: conversa atribuída mantém a mesma persona (G4, SC-003) em `tests/Feature/Ai/AssignmentContinuityTest.php`

### Implementação (US2)

- [ ] T041 [P] [US2] `app/Http/Requests/Ai/StorePersonaChannelsRequest.php` (lista de células; valida `channel_type` ∈ whatsapp/instagram/web) + `AiPersonaChannelResource`
- [ ] T042 [P] [US2] `app/Domain/Ai/Matrix/Services/AiMatrixService.php` (resolve matriz ativa; `isChannelAiEnabled(tenant,channel)` derivado de ≥1 persona ativa — FR-011a; config por canal)
- [ ] T043 [US2] `app/Domain/Ai/Distribution/Services/AiPersonaSelectorService.php` (round-robin com transação + `lockForUpdate` em `ai_channel_distribution_states`; lista determinística; wrap-around)
- [ ] T044 [US2] `app/Domain/Ai/Assignment/Services/AiConversationAssignmentService.php` (assign/find ativo/close; UNIQUE parcial por conversa)
- [ ] T045 [US2] `app/Http/Controllers/Api/V1/Ai/AiPersonaChannelController.php` (GET/PUT matriz; GET config por canal) + policy `ai.matrix.manage` + rotas
- [ ] T046 [P] [US2] Frontend: `resources/js/pages/Ia/MatrizCanais.vue` + `resources/js/components/Ia/PersonaChannelMatrix.vue` (grid de switches WhatsApp/Widget/Instagram)

**Checkpoint**: matriz configurável + atribuição round-robin equilibrada e persistente.

---

## Phase 5: User Story 3 — IA responde end-to-end na conversa (Priority: P1)

**Goal**: inbound em canal habilitado → contexto (persona+guardrails mínimos) → resposta segura → envio pelo canal existente → log; assíncrono, sem bloquear webhook. Cobre itens "6. jobs/filas", "7. integração canais", "8. fallback/logs (base)".
**Independent Test**: simular `MensagemRecebida` em conversa com persona atribuída → resposta `sender_type=ai` enviada via `MessageDispatchService`; guardrails mínimos aplicados sem guardrails da clínica; intenção clínica redirecionada; PII pseudonimizada; falha → retry→escala sem mensagem ao paciente.

### Tests (US3)

- [ ] T047 [P] [US3] Integração inbound→job→envio (G6): `MensagemRecebida` dispara `ProcessAiResponseJob` e resposta sai pelo `MessageDispatchService` (fake) em `tests/Feature/Ai/AiResponseFlowTest.php`
- [ ] T048 [P] [US3] Bypass de guardrail (Princípio III): prompt injection, role-play, tradução, tentativa de PII → redireciona/escala; usa fakes do `laravel/ai` em `tests/Feature/Ai/GuardrailBypassTest.php`
- [ ] T049 [P] [US3] Pseudonimização do contexto antes do LLM (G9, SC-011) em `tests/Feature/Ai/AiPseudonymizationTest.php`
- [ ] T050 [P] [US3] Falha do provedor: retry/backoff, sem mensagem ao paciente, escala ao esgotar (G10c, FR-030c) em `tests/Feature/Ai/AiProviderFailureTest.php`

### Implementação (US3)

- [ ] T051 [P] [US3] `app/Domain/Ai/Agents/PersonaAgent.php` (`implements Agent, HasStructuredOutput` + `Promptable`; provider/model dinâmicos de `ai_models`; `schema` → `{resposta, intencao, confidence, needs_human}`; temperature/max_tokens de `model_settings`)
- [ ] T052 [P] [US3] `app/Domain/Ai/Services/AiGuardrailEnforcer.php` (guardrails médicos mínimos em código/config — FR-026/027; pós-processamento determinístico: intenção clínica→redirect/flag, `confidence<threshold`/`needs_human`→escala, urgência→`priority=alta`)
- [ ] T053 [US3] `app/Domain/Ai/Services/AiContextBuilderService.php` (compõe `instructions`: mínimos + guardrails da clínica + persona + (hook RAG, vazio até US4); pseudonimiza via `PiiScrubber`)
- [ ] T054 [US3] `app/Domain/Ai/Services/AiMessageProcessor.php` (orquestra: build → PersonaAgent->prompt → Enforcer → enviar via `MessageDispatchService` ou escalar → gravar log)
- [ ] T055 [US3] `app/Jobs/Ai/ProcessAiResponseJob.php` (fila `ai`; retries/backoff de `config/ai.php`; relê mensagens não respondidas; aborta se pausada/assumida)
- [ ] T056 [US3] `app/Listeners/Ai/TriggerAiResponseOnInboundMessage.php` (ouve `MensagemRecebida`; checa `AiMatrixService::isChannelAiEnabled`; debounce Redis `ai:debounce:{conversation}`; resolve/atribui persona via US2; despacha job) — auto-discovery
- [ ] T057 [P] [US3] Eventos de domínio `app/Domain/Ai/Assignment/Events/{RespostaIAEnviada,IAEscalouParaHumano,ExecucaoIAFalhou}.php` e `app/Domain/Ai/Persona/Events/PersonaAtribuidaAConversa.php` (`Auditable` + `ContainsNoClinicalData`)
- [ ] T058 [US3] Gravar `ai_execution_logs` no processor (prompt/contexto pseudonimizado/intenção/confiança/resposta/ação/latência/tokens/correlation_id)

**Checkpoint**: a IA atende de ponta a ponta com segurança clínica mínima — MVP funcional.

---

## Phase 6: User Story 4 — Bases de conhecimento + RAG + associação (Priority: P2)

**Goal**: CRUD de bases em Markdown, indexação por embeddings (pgvector) e recuperação semântica no contexto. Cobre "3. associações (base)" + RAG.
**Independent Test**: salvar base dispara reindex; persona com base ativa recupera trechos relevantes; base inativa/desassociada/de outra clínica nunca recuperada.

### Tests (US4)

- [ ] T059 [P] [US4] Feature CRUD de bases + cross-tenant 404 (G1) em `tests/Feature/Ai/KnowledgeBaseCrudTest.php`
- [ ] T060 [P] [US4] Unit chunking determinístico em `tests/Unit/Ai/ChunkingTest.php`
- [ ] T061 [P] [US4] Integração recuperação só de bases ativas + isolamento tenant na similaridade (G8, SC-012) em `tests/Feature/Ai/RagRetrievalTest.php` (usa `Embeddings::fake()`)

### Implementação (US4)

- [ ] T062 [P] [US4] `StoreKnowledgeBaseRequest`/`UpdateKnowledgeBaseRequest` (sanitiza markdown; tags/metadata) + `AiKnowledgeBasePolicy` + `AiKnowledgeBaseResource`
- [ ] T063 [US4] `app/Domain/Ai/KnowledgeBase/Services/AiKnowledgeBaseService.php` (CRUD + activate/deactivate; dispara reindex no save de conteúdo)
- [ ] T064 [US4] `app/Domain/Ai/Services/AiEmbeddingService.php` (`Laravel\Ai\Embeddings::for()->dimensions()`) + chunking por seções/tamanho com overlap
- [ ] T065 [US4] `app/Jobs/Ai/EmbedKnowledgeBaseJob.php` (chunk + embed + substituição transacional dos `ai_knowledge_chunks`; set `indexed_at`)
- [ ] T066 [US4] `AiKnowledgeBaseController` (CRUD + activate/deactivate) + `PUT /ai/personas/{p}/knowledge-bases` (associação, co-tenancy G2) + rotas
- [ ] T067 [US4] Integrar recuperação top-K (tenant + bases ativas associadas + `min_similarity`) ao `AiContextBuilderService` (ativa o hook RAG do T053)
- [ ] T068 [P] [US4] Frontend `resources/js/pages/Ia/BasesIndex.vue` + `BaseForm.vue` + associação de bases na `PersonaForm.vue` + store

**Checkpoint**: respostas enriquecidas por RAG, isoladas por clínica.

---

## Phase 7: User Story 5 — Guardrails + associação + mínimos (Priority: P2)

**Goal**: CRUD de guardrails da clínica e associação a personas; mínimos médicos sempre aplicados. Cobre "3. associações (guardrail)".
**Independent Test**: guardrail ativo aplicado; inativo não; persona sem guardrails ainda aplica mínimos; guardrail de outra clínica não associável.

### Tests (US5)

- [ ] T069 [P] [US5] Feature CRUD de guardrails + cross-tenant 404 (G1) em `tests/Feature/Ai/GuardrailCrudTest.php`
- [ ] T070 [P] [US5] Feature: guardrail inativo não aplicado; mínimos aplicados sem guardrails da clínica (G7/G8, SC-004) em `tests/Feature/Ai/GuardrailApplicationTest.php`

### Implementação (US5)

- [ ] T071 [P] [US5] `StoreGuardrailRequest`/`UpdateGuardrailRequest` (sanitiza markdown; `category`) + `AiGuardrailPolicy` + `AiGuardrailResource`
- [ ] T072 [US5] `app/Domain/Ai/Guardrail/Services/AiGuardrailService.php` (CRUD + activate/deactivate)
- [ ] T073 [US5] `AiGuardrailController` (CRUD + activate/deactivate) + `PUT /ai/personas/{p}/guardrails` (associação, co-tenancy G2) + rotas
- [ ] T074 [US5] Conectar guardrails ativos da clínica associados à persona ao `AiContextBuilderService`/`AiGuardrailEnforcer` (somados aos mínimos)
- [ ] T075 [P] [US5] Frontend `resources/js/pages/Ia/GuardrailsIndex.vue` + `GuardrailForm.vue` + associação na `PersonaForm.vue` + store

**Checkpoint**: guardrails configuráveis sobre o piso obrigatório.

---

## Phase 8: User Story 6 — Controle humano da IA na conversa (Priority: P2)

**Goal**: ver status/persona, pausar (indefinido), assumir (auto-pause), reativar; reatribuição quando persona desativa. Cobre "12. integração na tela de conversa".
**Independent Test**: humano assume → IA pausa; pausada não responde; reativação manual volta; persona desativada → conversas reatribuídas.

### Tests (US6)

- [ ] T076 [P] [US6] Feature pausar/reativar manual e indefinido; pausada não responde (G10, SC-007) em `tests/Feature/Ai/AiPauseResumeTest.php`
- [ ] T077 [P] [US6] Integração auto-pause no `ConversaAssumidaPorHumano` em `tests/Feature/Ai/HumanTakeoverPauseTest.php`
- [ ] T078 [P] [US6] Feature reatribuição ao desativar/remover persona (com e sem outra persona ativa → humano) (G10b, FR-016a) em `tests/Feature/Ai/PersonaReassignmentTest.php`

### Implementação (US6)

- [ ] T079 [P] [US6] `app/Http/Controllers/Api/V1/Ai/AiConversationController.php` (`GET state`, `POST pause`, `POST resume`) + requests + policy (`inbox.respond`) + rotas
- [ ] T080 [US6] `app/Listeners/Ai/PauseAiOnHumanTakeover.php` (ouve `ConversaAssumidaPorHumano`; marca assignment `paused` + `ai_paused_until` indefinido) — auto-discovery
- [ ] T081 [US6] Fluxo de reatribuição no `AiPersonaService::deactivate`/remoção de canal → `AiPersonaSelectorService` reaponta assignments ativos; sem persona ativa → `paused` + handoff humano
- [ ] T082 [P] [US6] Frontend `resources/js/components/Ia/ConversationAiPanel.vue` embutido na tela de conversa existente (indicador IA ativa, persona, canal, status, botões Pausar/Reativar) — sem recriar a tela

**Checkpoint**: supervisão humana completa.

---

## Phase 9: User Story 7 — Logs e auditoria de IA (Priority: P3)

**Goal**: consultar logs de execução escopados por clínica; métricas/observabilidade.
**Independent Test**: logs da clínica listáveis/detalháveis; outra clínica → 404; sem PII clínica.

### Tests (US7)

- [ ] T083 [P] [US7] Feature logs list/detalhe escopado + cross-tenant 404 (G11/G1) em `tests/Feature/Ai/AiExecutionLogTest.php`

### Implementação (US7)

- [ ] T084 [P] [US7] `AiExecutionLogController` (index/show, filtros conversa/persona/status/período) + `AiExecutionLogResource` + policy `ai.log.view` + rotas
- [ ] T085 [US7] Métricas Prometheus (`ai_response_latency_seconds`, `ai_escalation_total`, `ai_messages_total{tenant}`) no endpoint existente + contexto Sentry por tenant/correlation_id
- [ ] T086 [P] [US7] Frontend `resources/js/pages/Ia/LogsIndex.vue` (visualização básica de logs) + store

**Checkpoint**: auditoria e observabilidade.

---

## Phase 10: User Story 8 — Editor Markdown + auxiliar de formatação (Priority: P3)

**Goal**: editor reutilizável com preview sanitizado + auxiliar determinístico (sem IA). Cobre "10. editor" e "11. auxiliar".
**Independent Test**: preview não renderiza script/HTML inseguro; controles inserem Markdown; salvar é ação explícita e o back-end sanitiza.

### Tests (US8)

- [ ] T087 [P] [US8] Feature `POST /ai/markdown/validate` sanitiza e back-end remove script/HTML/eventos (G12, SC-009/010) em `tests/Feature/Ai/MarkdownValidateTest.php`
- [ ] T088 [P] [US8] Teste de componente: preview sanitizado (DOMPurify) e toolbar insere Markdown em `resources/js/components/Ia/__tests__/MarkdownEditor.spec.js`

### Implementação (US8)

- [ ] T089 [P] [US8] `app/Http/Controllers/Api/V1/Ai/AiMarkdownController.php` (`validate` → sanitiza via T026 e retorna avisos) + request + rota (gate por `type`, rate-limited)
- [ ] T090 [P] [US8] `resources/js/components/Ia/MarkdownEditor.vue` (edição + preview sanitizado com DOMPurify + templates + validação de obrigatório + copiar/colar)
- [ ] T091 [P] [US8] `resources/js/components/Ia/MarkdownToolbar.vue` (auxiliar determinístico: título/subtítulo/ênfase/parágrafo/citação/lista/checklist/link/tabela → emite Markdown, sem IA)
- [ ] T092 [US8] Trocar o textarea simples pelo `MarkdownEditor` nas forms de Persona/Base/Guardrail (`PersonaForm.vue`, `BaseForm.vue`, `GuardrailForm.vue`)
- [ ] T093 [P] [US8] Templates Markdown pré-carregados (persona/base/guardrail) conforme spec, em `resources/js/components/Ia/markdownTemplates.js`

**Checkpoint**: experiência de criação de conteúdo completa.

---

## Phase 11: Polish & Cross-Cutting (Testes transversais + Hardening)

**Purpose**: itens "13. testes multi-tenant", "14. testes de segurança", "15. testes de fluxo de IA" consolidados + hardening.

- [ ] T094 [P] Suíte de **isolamento multi-tenant** sobre TODAS as entidades `ai_*` (personas/bases/guardrails/matriz/assignments/logs/chunks): clínica A nunca lê/associa/usa dados da B; conversa de A nunca recebe persona de B (G1 consolidado, Princípio II) em `tests/Feature/Ai/AiTenantIsolationTest.php`
- [ ] T095 [P] Testes de **segurança**: cada endpoint exige a ability correta (403 sem permissão); rate limiting nos endpoints IA + `markdown/validate`; sanitização no back-end mesmo via chamada direta à API (G2/G12, Princípio VII) em `tests/Feature/Ai/AiSecurityTest.php`
- [ ] T096 [P] Teste de **fluxo de IA** completo (integração): inbound → round-robin → contexto (RAG+guardrails) → resposta enviada → log; + reatribuição + escala por urgência/baixa confiança (Princípio III) em `tests/Feature/Ai/AiEndToEndFlowTest.php`
- [ ] T097 E2E Playwright (jornada IA no chat): IA responde → atendente pausa → assume → reativa, em `tests/e2e/ai-matricial.spec.js` (Princípio IV)
- [ ] T098 [P] Auditoria a11y das páginas/modais/editor da IA (axe; 0 violations) e navegação por permissões
- [ ] T099 Rodar `vendor/bin/sail bin pint --dirty --format agent` + `vendor/bin/sail artisan test --compact` (cobertura ≥70%, sem regressão) + validar `quickstart.md` (Lotes A–I) + Constitution Re-Check 7/7

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (P1)**: sem dependências. T001/T002 antes do que usa SDK/pgvector.
- **Foundational (P2)**: depende de Setup; **bloqueia todas as user stories**. T015 depende de T002. Models (T017–T024) dependem das migrations. T025–T028 dependem dos models.
- **US1 (P3)**: depende de Foundational. **MVP base**.
- **US2 (P4)**: depende de Foundational + US1 (personas existem para a matriz).
- **US3 (P5)**: depende de US1 + US2 (persona atribuída). Funciona sem US4/US5 (guardrails mínimos + sem bases).
- **US4 (P6)**: depende de Foundational; integra ao `AiContextBuilderService` da US3 (T067 ativa o hook RAG).
- **US5 (P7)**: depende de Foundational; integra ao Enforcer da US3 (T074).
- **US6 (P8)**: depende de US2/US3 (assignment existe).
- **US7 (P9)**: depende de US3 (logs gerados).
- **US8 (P10)**: depende de US1/US4/US5 (forms existem para receber o editor); backend de validate é independente.
- **Polish (P11)**: depende de todas as stories desejadas.

### Within Each User Story

Tests primeiro (devem falhar) → Requests/Policy/Resource [P] → Service → Controller → Rotas → Frontend.

### Parallel Opportunities

- Setup: T002/T003/T004 [P].
- Foundational: todas as migrations T006–T016 [P]; todos os models T017–T024 [P]; T026/T027 [P].
- Por story: tasks [P] (requests/policy/resource/frontend/testes) em paralelo; Service/Controller/integração sequenciais.
- Stories P1 (US1→US2→US3) são o caminho do MVP; US4/US5 podem ser tocadas em paralelo após US3 por devs distintos.

---

## Implementation Strategy

### MVP (P1)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. US1 (personas) → 4. US2 (matriz+round-robin) → 5. US3 (IA responde) → **VALIDAR**: a IA atende uma conversa real com segurança clínica mínima.

### Incremental (P2/P3)

US4 (RAG) → US5 (guardrails) → US6 (controle humano) → US7 (logs) → US8 (editor/auxiliar), cada um testável e entregável sem quebrar os anteriores. Fechar com Phase 11 (testes transversais + hardening).

---

## Notes

- `[P]` = arquivos diferentes, sem dependência pendente.
- Cada story é independentemente completável e testável.
- Verificar testes falhando antes de implementar (Princípio IV).
- Commit por task ou grupo lógico (hooks `before_*`/`after_*`).
- Nada de recriar canais/conversas/auth/atendimento humano — apenas plugar a camada de IA.
