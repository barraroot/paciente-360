---
description: "Task list — Humanização da Conversa da IA"
---

# Tasks: Humanização da Conversa da IA (Contexto + Histórico + Ferramentas por Clínica)

**Input**: Design documents from `specs/017-ai-conversation-humanization/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/ ✓, quickstart.md ✓

**Tests**: INCLUÍDOS — a Constituição IV (Spec-Driven Test-First) é obrigatória neste projeto. Escrever os testes ANTES e garantir que falham antes de implementar.

**Organização**: por user story, na ordem de prioridade do usuário — **P1: US1 (histórico) + US2 (work context) → P2: US3 (economia) + US5 (tools) → P3: US4 (funil)**.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1..US5 (fases de história); Setup/Foundational/Polish sem label
- Todos os comandos via `vendor/bin/sail`.

## Convenções de caminho

- Backend domínio IA: `app/Domain/Ai/**`; API: `app/Http/{Controllers,Requests,Resources}/**`; migrations: `database/migrations/`; config: `config/ai.php`.
- Frontend SPA: `resources/js/pages/Ia/`, `resources/js/stores/ia/`.
- Testes: `tests/Feature/Ai/**`, `tests/Unit/Ai/**`, E2E `tests/e2e/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: configuração compartilhada que todas as histórias consomem.

- [X] T001 Adicionar blocos `matricial.history` (`window_messages`, `input_token_ceiling`, `summary_max_tokens`), `matricial.tools` (`enabled`, `max_round_trips`) e `matricial.work_context` (`max_questions`, `free_form_max_chars`) + flag de prompt caching em `config/ai.php`
- [X] T002 [P] Documentar novas variáveis (`AI_HISTORY_WINDOW_MESSAGES`, `AI_HISTORY_INPUT_TOKEN_CEILING`, `AI_SUMMARY_MAX_TOKENS`, `AI_TOOLS_MAX_ROUND_TRIPS`, `AI_TOOLS_ENABLED`) em `.env.example`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: entidades/colunas compartilhadas por múltiplas histórias. **⚠️ Bloqueia todas as histórias.**

- [X] T003 Migration aditiva: tabela `ai_conversation_summaries` (tenant_id, conversation_id UNIQUE, summary_text, key_facts jsonb, funnel_stage, covered_up_to_message_id, version) em `database/migrations/`
- [X] T004 [P] Migration aditiva: colunas `work_context_version`, `summary_version`, `tools_used` (jsonb), `tool_round_trips` (smallint) em `ai_execution_logs` (novo arquivo de migration)
- [X] T005 [US1] `ConversationSummary` model com global scope de tenant + casts (`key_facts`→array) em `app/Domain/Ai/Context/Models/ConversationSummary.php` (depende T003)
- [X] T006 Atualizar `AiExecutionLog` (fillable/casts) para as colunas novas em `app/Domain/Ai/Execution/Models/AiExecutionLog.php` (depende T004)
- [X] T007 [P] Helper de teste para montar conversas multi-turno (seed de `messaging_messages` in/out) em `tests/Support/AiConversationFactory.php`

**Checkpoint**: fundação pronta — histórias podem começar.

---

## Phase 3: User Story 1 — IA mantém o fio da conversa (Priority: P1) 🎯 MVP

**Goal**: a IA recebe a janela verbatim mínima (≈3 turnos) pseudonimizada — nunca mais a "janela vazia" — e não re-pergunta o que já foi dito; lida com eco/loop.

**Independent Test**: enviar conversa multi-turno (queixa→frequência→impacto→"qual o valor?") e verificar que a IA referencia respostas anteriores, não repete perguntas e continua coerente após gap simulado; o resumo, se existir, é incluído.

### Tests for User Story 1 ⚠️ (escrever e falhar primeiro)

- [X] T008 [P] [US1] Unit test `ConversationHistoryAssemblerTest` (tamanho da janela, mapeamento de papéis user/assistant, scrub de PII por mensagem) em `tests/Unit/Ai/ConversationHistoryAssemblerTest.php`
- [X] T009 [P] [US1] Feature test `ConversationHistoryTest` (não re-perguntar dentro da janela; referencia respostas anteriores; coerência após gap) em `tests/Feature/Ai/ConversationHistoryTest.php`
- [X] T010 [P] [US1] Feature test `EchoLoopGuardTest` (paciente cola a mensagem da própria IA → avança, não entra em loop — FR-005) em `tests/Feature/Ai/EchoLoopGuardTest.php`
- [X] T011 [P] [US1] Feature test `NoRawPiiInProviderPayloadTest` (janela + resumo enviados ao provedor estão pseudonimizados — FR-006/Princípio I) em `tests/Feature/Ai/NoRawPiiInProviderPayloadTest.php`

### Implementation for User Story 1

- [X] T012 [US1] `ConversationHistoryAssembler`: lê últimas N mensagens (config), mapeia para `Laravel\Ai\Messages\Message`(role,content) com `PiiScrubber` por mensagem; inclui mensagem de resumo quando presente em `app/Domain/Ai/Context/Services/ConversationHistoryAssembler.php`
- [X] T013 [US1] Estender `PersonaAgent` para `implements Conversational`: `messages()` retorna [resumo se houver] + janela do assembler em `app/Domain/Ai/Agents/PersonaAgent.php` (depende T012)
- [X] T014 [US1] Alterar `AiContextBuilderService::build()` para receber a `Conversation` e montar a janela (mantendo prompt atual pseudonimizado); estender `AiContext` (`app/Domain/Ai/Services/AiContext.php`) com `summaryVersion`/`workContextVersion` (nullable) para auditoria em `app/Domain/Ai/Services/AiContextBuilderService.php` (depende T012)
- [X] T015 [US1] `AiMessageProcessor`: passar a conversa ao context builder, instanciar `PersonaAgent` com `messages()`, e pré-check determinístico de eco/loop (R10) em `app/Domain/Ai/Services/AiMessageProcessor.php` (depende T013, T014)
- [X] T016 [US1] Registrar `summary_version` (de `AiContext`) em `AiExecutionLog` no método `log()` de `app/Domain/Ai/Services/AiMessageProcessor.php` — o campo `work_context_version` é preenchido a partir do mesmo `AiContext` (definido em T028, US2) quando presente

**Checkpoint**: US1 funcional e testável — conversas dentro da janela já são humanizadas e coerentes (MVP).

---

## Phase 4: User Story 2 — Clínica configura seu "contexto de trabalho" (Priority: P1)

**Goal**: cada clínica preenche serviços/preços/locais/política de sinal/tom/perguntas + texto livre; a IA passa a refletir esses fatos e o tom, e personaliza com o nome via placeholder.

**Independent Test**: preencher o work context de duas clínicas diferentes; mesma entrada de paciente gera respostas distintas e clínica-apropriadas; tom e perguntas progressivas respeitados; nome real só na saída.

### Tests for User Story 2 ⚠️

- [X] T017 [P] [US2] Feature test `WorkContextCrudTest` (GET/PUT singleton, version incrementa, allow-list rejeita campo clínico, **isolamento tenant A≠B** — FR-012) em `tests/Feature/Ai/WorkContextCrudTest.php`
- [X] T018 [P] [US2] Feature test `WorkContextAppliedToPromptTest` (duas clínicas → respostas distintas; tom aplicado; perguntas uma por vez — FR-008/014/016) em `tests/Feature/Ai/WorkContextAppliedToPromptTest.php`
- [X] T019 [P] [US2] Feature test `NameInjectionTest` (`{{primeiro_nome}}` substituído na saída; nome real ausente do payload do provedor; **nome desconhecido → placeholder removido/neutralizado, token literal nunca vai ao paciente** — FR-017/026) em `tests/Feature/Ai/NameInjectionTest.php`

### Implementation for User Story 2

- [X] T020 [P] [US2] Migration aditiva: tabela `ai_work_contexts` (tenant_id UNIQUE, services/pricing/locations/deposit_policy/qualification_questions jsonb, tone, free_form, version, is_active) em `database/migrations/`
- [X] T021 [US2] `AiWorkContext` model (global scope tenant, casts jsonb) em `app/Domain/Ai/WorkContext/Models/AiWorkContext.php` (depende T020)
- [X] T022 [US2] `AiWorkContextService`: `getForTenant()`, `upsert()` (incrementa version), `renderForPrompt()` (bloco markdown) em `app/Domain/Ai/WorkContext/Services/AiWorkContextService.php` (depende T021)
- [X] T023 [US2] `UpsertWorkContextRequest` com validação dos campos + allow-list não-clínica em `app/Http/Requests/Ai/UpsertWorkContextRequest.php`
- [X] T024 [P] [US2] `AiWorkContextResource` em `app/Http/Resources/Ai/AiWorkContextResource.php`
- [X] T025 [US2] `AiWorkContextController` (GET → `Gate::authorize('ai.work-context.view')`; PUT autorizado no Request; pipeline FR→Ctrl→Service→Resource) em `app/Http/Controllers/Api/V1/Ai/AiWorkContextController.php` (depende T022, T023, T024)
- [X] T026 [US2] Registrar abilities `ai.work-context.view` e `ai.work-context.manage` no catálogo de permissões (Spatie seeder/catálogo) + atribuir aos papéis que gerem IA; declarar rotas `GET/PUT work-context` **dentro do grupo `prefix('ai')`/`name('ai.')`** existente em `routes/api.php`
- [X] T027 [US2] Injetar bloco "# Contexto de Trabalho da Clínica" + instrução do placeholder `{{primeiro_nome}}` e nota de precedência (FR-011) em `AiGuardrailEnforcer::composeInstructions` em `app/Domain/Ai/Services/AiGuardrailEnforcer.php`
- [X] T028 [US2] Carregar e passar o work context renderizado no `AiContextBuilderService::build()` e setar `AiContext->workContextVersion` (para o `log()` em T016 gravar `work_context_version` — FR-025) em `app/Domain/Ai/Services/AiContextBuilderService.php` (depende T022, T027)
- [X] T029 [US2] `OutboundNameInjector`: substitui `{{primeiro_nome}}` pelo 1º nome do contato antes do dispatch; **se o nome for desconhecido, remove/neutraliza o placeholder** (nunca enviar o token literal) em `app/Domain/Ai/Services/OutboundNameInjector.php`; integrar no `AiMessageProcessor` antes de `MessageDispatchService::send`
- [X] T030 [P] [US2] Página Vue `WorkContextPage.vue` (formulário híbrido: estruturado + texto livre) em `resources/js/pages/Ia/WorkContextPage.vue`
- [X] T031 [P] [US2] Store Pinia `workContext.js` (fetch/save) em `resources/js/stores/ia/workContext.js`
- [X] T032 [US2] Entrada de navegação + rota SPA `/panel/ia/contexto-trabalho` (permission-gated) em `resources/js/config/navigation.js` e router

**Checkpoint**: US1 + US2 entregam o núcleo da humanização configurável por clínica (P1 completo).

---

## Phase 5: User Story 3 — Economia de tokens e performance (Priority: P2)

**Goal**: resumo rolante incremental + teto de tokens com shedding por prioridade + prompt caching, mantendo custo sub-linear e payload de histórico ~constante.

**Independent Test**: conversa de 40 mensagens — input tokens dentro do teto 100%, resumo reusado sem re-sumarizar a cada turno, payload de histórico ≤ ~10 mensagens-equivalentes.

### Tests for User Story 3 ⚠️

- [X] T033 [P] [US3] Feature test `ConversationSummarizerTest` (gera resumo só quando há turnos além da janela; reusa quando não muda; lock evita corrida — FR-002b/022) em `tests/Feature/Ai/ConversationSummarizerTest.php`
- [X] T034 [P] [US3] Unit test `AiContextBudgetTest` (teto respeitado; ordem de shedding RAG→resumo; nunca remove guardrails nem mensagem atual — FR-021/023) em `tests/Unit/Ai/AiContextBudgetTest.php`
- [X] T035 [P] [US3] Feature test `HistoryPayloadBoundedTest` (40 msgs → payload de histórico limitado; tokens sub-lineares — SC-004/SC-010) em `tests/Feature/Ai/HistoryPayloadBoundedTest.php`

### Implementation for User Story 3

- [X] T036 [US3] `ConversationSummarizerService`: atualização incremental do resumo + `key_facts` + `funnel_stage`, modelo mais barato, lock Redis por conversa, atualiza `covered_up_to_message_id` em `app/Domain/Ai/Context/Services/ConversationSummarizerService.php`
- [X] T037 [US3] `AiContextBudget`: estima tokens (~4 chars/token) e faz shedding por prioridade preservando guardrails+mensagem atual em `app/Domain/Ai/Context/Services/AiContextBudget.php`
- [X] T038 [US3] Integrar budget no `AiContextBuilderService::build()` (aplica teto ao contexto montado) em `app/Domain/Ai/Services/AiContextBuilderService.php` (depende T037)
- [X] T039 [US3] Disparar o summarizer no `AiMessageProcessor` quando houver turnos além da janela (antes de montar o contexto) em `app/Domain/Ai/Services/AiMessageProcessor.php` (depende T036)
- [X] T040 [US3] `PersonaAgent implements HasProviderOptions`: `cache_control: ephemeral` (Anthropic) no bloco estático + aplicar `model_settings` (temperature/max_tokens) da persona em `app/Domain/Ai/Agents/PersonaAgent.php`

**Checkpoint**: qualidade de US1/US2 mantida sob orçamento de tokens e latência controlados.

---

## Phase 6: User Story 5 — IA consulta dados reais e cria/segura agendamento (ferramentas) (Priority: P2)

**Goal**: ferramentas laravel/ai para ler dados vivos da clínica e do contato atual + ações reversíveis (lead, hold), com isolamento de tenant/paciente no data layer, auditoria e cap de round-trips.

**Independent Test**: clínica com disponibilidade real → IA oferece slots reais, cria/acha lead por telefone, faz hold provisório; nunca expõe outro paciente; confirmação/pagamento em handoff; falha de tool degrada sem inventar.

### Tests for User Story 5 ⚠️

- [X] T041 [P] [US5] Feature test `GetClinicInfoToolTest` + `GetAvailabilityToolTest` (dados vivos; precedência sobre work context — FR-028/SC-009) em `tests/Feature/Ai/Tools/`
- [X] T042 [P] [US5] Feature test `GetCurrentPatientToolTest` (só o contato da conversa; respeita consent; **nunca** busca por nome nem outro paciente — FR-029) em `tests/Feature/Ai/Tools/GetCurrentPatientToolTest.php`
- [X] T043 [P] [US5] Feature test `CreateOrFindLeadToolTest` (lookup por telefone; cria `status='lead'`; idempotente) em `tests/Feature/Ai/Tools/CreateOrFindLeadToolTest.php`
- [X] T044 [P] [US5] Feature test `HoldSlotToolTest` (cria `SlotReservation holder_type='ia'`; TTL; conflito por `sr_active_unique`; **não confirma** — FR-018/030) em `tests/Feature/Ai/Tools/HoldSlotToolTest.php`
- [X] T045 [P] [US5] Feature test `ToolTenantIsolationTest` (tool sob tenant A jamais retorna dado de tenant B nem de outro paciente — FR-034/SC-007) em `tests/Feature/Ai/Tools/ToolTenantIsolationTest.php`
- [X] T046 [P] [US5] Feature test `ToolRoundTripCapTest` + degradação em falha/timeout (≤3 round-trips; sem fabricar — FR-032/033) em `tests/Feature/Ai/Tools/ToolRoundTripCapTest.php`

### Implementation for User Story 5

- [X] T047 [P] [US5] Migration aditiva: tabela `ai_tool_invocations` (tenant_id, conversation_id, correlation_id, tool_name, input_summary jsonb, outcome, result_summary jsonb, latency_ms) em `database/migrations/`
- [X] T048 [US5] `AiToolInvocation` model (global scope tenant) em `app/Domain/Ai/Execution/Models/AiToolInvocation.php` (depende T047)
- [X] T049 [P] [US5] `ToolContext` value object `{tenant_id, conversation_id, patient_id?, contact_phone}` em `app/Domain/Ai/Tools/Support/ToolContext.php`
- [X] T050 [US5] `ToolInvocationLogger` (grava `ai_tool_invocations`, minimiza/pseudonimiza I/O) em `app/Domain/Ai/Tools/Support/ToolInvocationLogger.php` (depende T048)
- [X] T051 [P] [US5] `GetClinicInfoTool`: serviços/preços de `appointment_types` (model/service da Fase 5: `nome`/`descricao`/`valor_particular`/`valor_convenio_default`/`duration_minutes`, só `is_active`); horário/endereço do work context quando não houver fonte no DB (precedência FR-011) em `app/Domain/Ai/Tools/GetClinicInfoTool.php`
- [X] T052 [P] [US5] `ListProfessionalsTool` (Fase 12) em `app/Domain/Ai/Tools/ListProfessionalsTool.php`
- [X] T053 [P] [US5] `GetAvailabilityTool` (slots reais — Fase 5) em `app/Domain/Ai/Tools/GetAvailabilityTool.php`
- [X] T054 [P] [US5] `GetCurrentPatientTool` (só contato da conversa; consent-gated) em `app/Domain/Ai/Tools/GetCurrentPatientTool.php`
- [X] T055 [P] [US5] `CreateOrFindLeadTool` (reusa `PacienteService`; `status='lead'`, origem do canal) em `app/Domain/Ai/Tools/CreateOrFindLeadTool.php`
- [X] T056 [P] [US5] `HoldSlotTool` (reusa serviço de `SlotReservation` da Fase 5; `holder_type='ia'`, idempotência/TTL) em `app/Domain/Ai/Tools/HoldSlotTool.php`
- [X] T057 [US5] `PersonaAgent implements HasTools` + `#[MaxSteps]` do config; `tools()` recebe instâncias com `ToolContext` em `app/Domain/Ai/Agents/PersonaAgent.php` (depende T049, T051–T056)
- [X] T058 [US5] `AiMessageProcessor`: montar `ToolContext` da conversa, injetar tools no agente, registrar `tools_used`/`tool_round_trips` em `AiExecutionLog` em `app/Domain/Ai/Services/AiMessageProcessor.php` (depende T057, T050)

**Checkpoint**: respostas ancoradas em dados reais com ações reversíveis auditadas; isolamento garantido.

---

## Phase 7: User Story 4 — Consciência de etapa do funil (Priority: P3)

**Goal**: a IA conhece a etapa atual (greeting→qualifying→value→pricing→location→slot) e age coerentemente (não cota preço antes de qualificar; não regride após intenção de agendar).

**Independent Test**: percorrer greeting→agendamento e verificar transições em ordem (preço só após valor; slots só após intenção).

### Tests for User Story 4 ⚠️

- [X] T059 [P] [US4] Feature test `FunnelStageBehaviorTest` (qualifica antes de preço; após "quero agendar" vai a local/horário, não re-qualifica — FR-018) em `tests/Feature/Ai/FunnelStageBehaviorTest.php`

### Implementation for User Story 4

- [X] T060 [US4] Garantir que o summarizer mantém `funnel_stage` atualizado a cada turno em `app/Domain/Ai/Context/Services/ConversationSummarizerService.php` (depende T036)
- [X] T061 [US4] Injetar a etapa atual do funil como dica no system prompt (sem relaxar guardrails) em `app/Domain/Ai/Services/AiGuardrailEnforcer.php` / `AiContextBuilderService.php`

**Checkpoint**: fluxo comercial coerente ponta a ponta.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T062 (DEFERRED — staging) [P] E2E Playwright "agendamento via IA no chat" (greeting→qualificação→valor→preço→horário→lead+hold+handoff) em `tests/e2e/ai-scheduling-conversation.spec.ts` (jornada crítica — Constituição IV)
- [X] T063 Atualizar documentação OpenAPI/Scribe dos endpoints `GET/PUT /api/v1/ai/work-context` (Constituição IV — contrato de API na mesma PR)
- [X] T064 [P] Métricas Prometheus: round-trips de tool e latência IA (single-pass ≤5s / tools p95 ≤8s — SC-008) em `app/Support/Metrics/`
- [X] T065 Rodar `vendor/bin/sail bin pint --dirty --format agent` e a suíte `vendor/bin/sail artisan test --compact --filter=Ai` (SC-006 sem regressão de segurança)
- [ ] T066 (DEFERRED — staging) Validar o `quickstart.md` ponta a ponta em ambiente local
- [X] T067 [P] Adicionar bloco "Fase 17 — Key Patterns" em `CLAUDE.md` (resumo dos gotchas: janela mínima, precedência de fontes, tools com ToolContext, name placeholder)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (P1)**: sem dependências.
- **Foundational (P2)**: depende do Setup; **bloqueia todas as histórias** (migrations/models compartilhados + colunas de log).
- **US1 / US2 (P1)**: após Foundational. US2 toca arquivos que US1 também altera (`AiContextBuilderService`, `AiMessageProcessor`, `AiGuardrailEnforcer`, `PersonaAgent`) → fazer **US1 antes de US2**.
- **US3 / US5 (P2)**: após Foundational; integram com US1/US2 mas são testáveis isoladamente. US3 e US5 ambos editam `PersonaAgent` e `AiMessageProcessor` → coordenar (US3 antes de US5, ou merges sequenciais).
- **US4 (P3)**: depende do `funnel_stage` mantido em US3 (T036) — fazer após US3.
- **Polish (P8)**: após as histórias desejadas.

### Within Each User Story

- Testes escritos e em falha antes da implementação.
- Models antes de services; services antes de controllers/agente; core antes de integração.

### Arquivos compartilhados (editados por várias histórias — não paralelizar entre si)

- `app/Domain/Ai/Agents/PersonaAgent.php`: US1 (T013) → US3 (T040) → US5 (T057)
- `app/Domain/Ai/Services/AiContextBuilderService.php`: US1 (T014) → US2 (T028) → US3 (T038)
- `app/Domain/Ai/Services/AiMessageProcessor.php`: US1 (T015,T016) → US2 (T029) → US3 (T039) → US5 (T058)
- `app/Domain/Ai/Services/AiGuardrailEnforcer.php`: US2 (T027) → US4 (T061)

---

## Parallel Example: User Story 1

```bash
# Testes US1 em paralelo (todos [P], arquivos distintos):
Task: "T008 Unit ConversationHistoryAssemblerTest"
Task: "T009 Feature ConversationHistoryTest"
Task: "T010 Feature EchoLoopGuardTest"
Task: "T011 Feature NoRawPiiInProviderPayloadTest"
```

## Parallel Example: User Story 5 (tools)

```bash
# As 6 tools são arquivos independentes (após T049 ToolContext + T050 Logger):
Task: "T051 GetClinicInfoTool"; Task: "T052 ListProfessionalsTool"
Task: "T053 GetAvailabilityTool"; Task: "T054 GetCurrentPatientTool"
Task: "T055 CreateOrFindLeadTool"; Task: "T056 HoldSlotTool"
```

---

## Implementation Strategy

### MVP (US1 apenas)

1. Setup (Phase 1) → Foundational (Phase 2) → US1 (Phase 3).
2. **PARAR e VALIDAR**: conversas multi-turno deixam de ser "janela vazia"; sem re-perguntas; eco tratado.
3. Já entregável: maior salto de qualidade percebida, mesmo sem configuração por clínica.

### Entrega incremental (ordem do usuário)

1. **P1** → US1 + US2: humanização configurável por clínica (núcleo). Validar e demonstrar.
2. **P2** → US3 (economia) + US5 (tools): custo controlado + dados reais/ações. Validar.
3. **P3** → US4 (funil): refino de coerência comercial.
4. Polish: E2E, OpenAPI, métricas, pint + suíte.

---

## Notes

- `[P]` = arquivos diferentes, sem dependência pendente.
- Cada história é independentemente testável após a Foundational.
- Verificar que os testes falham antes de implementar (Red-Green-Refactor).
- Gates não-negociáveis cobertos por teste: isolamento tenant/paciente (T045/SC-007), nenhum PII bruto ao provedor (T011), segurança clínica/escala sem regressão (T065/SC-006), confirmação/pagamento sempre em handoff (T044).
- Commit por tarefa ou grupo lógico; respeitar hooks `before_*`/`after_*` do `.specify/extensions.yml`.
