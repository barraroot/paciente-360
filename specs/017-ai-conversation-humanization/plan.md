# Implementation Plan: Humanização da Conversa da IA (Contexto + Histórico + Ferramentas por Clínica)

**Branch**: `017-ai-conversation-humanization` | **Date**: 2026-05-27 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/017-ai-conversation-humanization/spec.md`

## Summary

Hoje a IA Matricial (Fase 15) responde como se cada mensagem fosse uma janela vazia: o `AiMessageProcessor` só lê a **última** mensagem inbound (`latestInboundText`) e o `AiContextBuilderService` monta o prompt com apenas essa string. Esta feature dá à IA **contexto e memória mínimos-suficientes** (janela verbatim curta + resumo rolante compacto), um **"contexto de trabalho" por clínica** (campos estruturados + texto livre) que rege voz/política/qualificação, e **ferramentas de dados ao vivo** (laravel/ai Tools) para consultar a base real da clínica (procedimentos, preços, horários, endereço, profissionais, disponibilidade) e executar **ações reversíveis** (criar/achar lead = `pacientes.status='lead'`; reservar slot provisório = `slot_reservations.holder_type='ia'`). Confirmação de agendamento e pagamento permanecem em handoff.

**Abordagem técnica** (reuso máximo, zero nova base de dados de domínio):
- O `PersonaAgent` (laravel/ai) passa a implementar `Conversational` (histórico via `messages()`) e `HasTools` (ferramentas), com `#[MaxSteps]` para o teto de round-trips e `providerOptions` para **prompt caching** Anthropic (economia de tokens do bloco estático).
- Um `ConversationHistoryAssembler` monta a janela verbatim (default ~3 turnos/6 mensagens, **pseudonimizada por mensagem**) e um `ConversationSummarizerService` mantém um **resumo rolante incremental** (tabela `ai_conversation_summaries`) regenerado só quando há turnos além da janela (FR-002b, FR-022).
- Um `AiWorkContext` (tabela `ai_work_contexts`, **um por tenant**) é injetado no system prompt pelo `AiGuardrailEnforcer::composeInstructions`, com **precedência determinística** (tools > work context > persona/RAG, FR-011).
- As Tools delegam a Services existentes (Fase 2 CRM, Fase 5 Agenda/`SlotReservation`, Fase 12 Profissionais) e aplicam **isolamento de tenant no data layer** + resolução de paciente **apenas pelo contato da conversa** (telefone). Cada invocação é auditada (`ai_tool_invocations`).
- Personalização por nome via **placeholder `{{primeiro_nome}}`** no prompt + substituição na saída (o nome real nunca vai ao provedor).

> **Decisão de pacote**: o tool-calling *dentro* da conversa usa **laravel/ai Tools** (`HasTools`) — é o mecanismo nativo do agente que já está em uso. **laravel/mcp** serve para *expor* um servidor MCP a clientes externos (Boost/IDE) e é **opcional/deferred** aqui; as Tools são escritas sobre uma fina camada de Services para que o mesmo domínio possa, no futuro, virar um servidor MCP sem reescrita. Isso atende a intenção do pedido ("ferramentas para a IA") com o caminho idiomático.

## Technical Context

**Language/Version**: PHP 8.5, Laravel 13 (backend via Sail); Vue 3 + Pinia + Tailwind v4 (SPA tenant)
**Primary Dependencies**: `laravel/ai` (Agents, `Conversational`, `HasTools`, `HasStructuredOutput`, `HasProviderOptions`, `MaxSteps`), pgvector (RAG existente), Sanctum (auth API tenant). `laravel/mcp` **opcional** (exposição externa — fora do caminho crítico). Nenhuma nova dependência Composer/npm exigida.
**Storage**: PostgreSQL — **3 tabelas novas** (`ai_work_contexts`, `ai_conversation_summaries`, `ai_tool_invocations`); **reuso** de `pacientes` (status `lead`, `telefone_primario_normalizado`, `funil_coluna_atual_id`, `share_with_integrations_consent`), `slot_reservations` (`holder_type='ia'`, TTL), `messaging_conversations`/`messaging_messages`, `professionals`, `appointment_types` (serviços/preços: `valor_particular`/`valor_convenio_default`/`duration_minutes`). Redis para lock/cache do resumo e debounce existente.
**Testing**: PHPUnit (feature predominante + unit p/ assembler/summarizer/budget); testes de isolamento multi-tenant e cross-patient obrigatórios; `Agent::fake()` e tool fakes do laravel/ai; Playwright E2E "agendamento via IA no chat" (jornada crítica da Constituição IV).
**Target Platform**: Linux server (Docker/Sail), fila `ai` (Horizon), `ProcessAiResponseJob`.
**Project Type**: Web — API REST Laravel (`/api/v1`) + SPA Vue 3. Sem superfície Filament (config de IA é do tenant → SPA).
**Performance Goals**: resposta single-pass (sem tool) **target ≤ 5s** (mantém Constituição V); resposta com tools **p95 ≤ 8s e ≤ 3 round-trips** (SC-008); payload de histórico ≤ ~10 mensagens-equivalentes independente do tamanho (SC-010); tokens crescem sub-linearmente (SC-004).
**Constraints**: **nenhum PII bruto ao LLM** — janela, resumo e I/O de tools pseudonimizados; nome real só na saída (FR-017/FR-026). Isolamento de tenant **no data layer** das tools (FR-034) + paciente só o contato da conversa (FR-029). Guardrails/escala/auto-pause da Fase 15 **intactos** (FR-019/FR-020).
**Scale/Scope**: multi-tenant; benchmark de conversas até 40 mensagens; uma work context por clínica; ~6 tools.

## Constitution Check

*GATE: avaliado contra os 7 princípios (v1.5.0). Re-check após Phase 1 ao final.*

| Princípio | Veredito | Como é satisfeito / gate |
|---|---|---|
| **I — LGPD (NON-NEG.)** | ✅ PASS | Pseudonimização **estendida a toda a janela e ao resumo** (cada `Message` passa por `PiiScrubber` antes do provedor) e ao I/O das tools. Nome real **nunca** enviado (placeholder + reinjeção, FR-017). Leitura patient-level respeita `share_with_integrations_consent` e só o contato da conversa (FR-026/FR-029). Resumo e `ai_tool_invocations` com retenção alinhada (≥6m IA) e **sem dado clínico** além do já permitido. Teste de gate "nenhum PII bruto no payload do provedor". |
| **II — Isolamento Multi-Tenant (NON-NEG.)** | ✅ PASS | `ai_work_contexts`/`ai_conversation_summaries`/`ai_tool_invocations` com `tenant_id` + global scope. Tools filtram `tenant_id` **explicitamente no data layer**, não só via prompt (FR-034). Histórico/resumo escopados a `conversation_id`+`tenant_id` (FR-006). Job restaura contexto de tenant antes de montar contexto. Testes de leitura cruzada (tenant **e** paciente) obrigatórios (SC-007). |
| **III — Segurança Clínica/Auditabilidade IA (NON-NEG.)** | ✅ PASS | `minimalGuardrails()` e o pós-processamento determinístico do `AiGuardrailEnforcer` **inalterados**; saída continua estruturada. Tools **não** podem emitir conduta clínica (são dados/ações comerciais) e não relaxam intenções bloqueadas. Funnel stage é dica de fluxo, não bypass. Cada execução **e cada tool** auditadas (intenção, confiança, tools usadas, resultado) ≥6m. Escala/auto-pause preservados (FR-019/020). |
| **IV — Spec-Driven Test-First** | ✅ PASS | Testes por US (history assembler, summarizer incremental, budget shedding, work-context CRUD, name injection, cada tool + isolamento, round-trip cap). E2E Playwright da jornada "agendamento via IA no chat" atualizado. Cobertura ≥70%. Migrations novas (aditivas, idempotentes). |
| **V — Observabilidade** | ⚠️ PASS c/ desvio anotado | Logs estruturados + `AiExecutionLog` estendido (refs de summary/work-context, tokens incluindo histórico) + `ai_tool_invocations` + métricas de round-trips/latência. **Desvio**: a métrica-alvo "resposta IA ≤5s" (Princípio V) é **target de observabilidade, não gate NON-NEGOTIABLE**. Respostas com tool-calling alvo **p95 ≤8s** (decisão de produto Q5, canal assíncrono); single-pass mantém ≤5s. Registrado em Complexity Tracking; **não requer amendment** (não remove nem redefine princípio/gate obrigatório — refina um target de métrica). |
| **VI — Conformidade Meta (NON-NEG.)** | ✅ PASS | A feature atua em **respostas dentro da conversa** (inbound-triggered, dentro da janela 24h → texto livre permitido). Nenhum disparo proativo novo; o dispatcher (Fase 13) e seus gates de template/opt-in **não mudam**. As tools não enviam marketing. |
| **VII — Segurança Operacional (NON-NEG.)** | ✅ PASS | Sem nova superfície de auth: tools rodam server-side sob o contexto de tenant do job (paciente é contato externo, não autenticado). CRUD de work context na API tenant (Bearer Sanctum + `tenant.slug` + `tenant.not-suspended`), permission-gated. Round-trip cap evita loop/abuso. |

**Resultado**: 7/7 PASS (1 desvio de **target de métrica** documentado, sem amendment). Re-check pós-design ao final: inalterado.

## Project Structure

### Documentation (this feature)

```text
specs/017-ai-conversation-humanization/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — decisões técnicas (laravel/ai tools vs mcp, summary, budget, caching)
├── data-model.md        # Phase 1 — tabelas novas + reuso
├── quickstart.md        # Phase 1 — como configurar e validar
├── contracts/
│   ├── work-context.api.md   # CRUD REST do contexto de trabalho
│   └── ai-tools.contract.md  # Contrato de cada Tool (input/output/escopo)
└── checklists/
    └── requirements.md  # (existente)
```

### Source Code (repository root)

```text
app/
├── Domain/Ai/
│   ├── Agents/
│   │   └── PersonaAgent.php                 # MODIFICA: implements Conversational, HasTools, HasProviderOptions; #[MaxSteps]
│   ├── Context/                             # NOVO submódulo de contexto/histórico
│   │   ├── Models/
│   │   │   └── ConversationSummary.php       # NOVO (ai_conversation_summaries)
│   │   └── Services/
│   │       ├── ConversationHistoryAssembler.php  # NOVO — janela verbatim pseudonimizada → list<Message>
│   │       ├── ConversationSummarizerService.php # NOVO — resumo rolante incremental (lock Redis)
│   │       └── AiContextBudget.php               # NOVO — teto de tokens + shedding por prioridade (FR-021/023)
│   ├── WorkContext/                         # NOVO submódulo
│   │   ├── Models/AiWorkContext.php          # NOVO (ai_work_contexts, 1 por tenant)
│   │   └── Services/AiWorkContextService.php  # NOVO — get/upsert + render p/ prompt
│   ├── Tools/                               # NOVO — laravel/ai Tools (live data)
│   │   ├── Support/
│   │   │   ├── ToolContext.php                # NOVO — {tenant_id, conversation_id, patient_id?, contact_phone}
│   │   │   └── ToolInvocationLogger.php       # NOVO — grava ai_tool_invocations (auditoria FR-031)
│   │   ├── GetClinicInfoTool.php              # NOVO (serviços/preços/horários/endereço)
│   │   ├── ListProfessionalsTool.php         # NOVO
│   │   ├── GetAvailabilityTool.php            # NOVO (slots reais — Fase 5)
│   │   ├── GetCurrentPatientTool.php          # NOVO (só o contato da conversa, consent-gated)
│   │   ├── CreateOrFindLeadTool.php           # NOVO (pacientes status='lead' por telefone)
│   │   └── HoldSlotTool.php                   # NOVO (SlotReservation holder_type='ia')
│   ├── Execution/Models/
│   │   ├── AiExecutionLog.php                 # MODIFICA: refs summary/work-context version, tools usadas
│   │   └── AiToolInvocation.php               # NOVO (ai_tool_invocations)
│   └── Services/
│       ├── AiContext.php                      # MODIFICA: + summaryVersion/workContextVersion (auditoria FR-025)
│       ├── AiContextBuilderService.php       # MODIFICA: monta janela+resumo+work context; aplica budget
│       ├── AiGuardrailEnforcer.php            # MODIFICA: injeta bloco "Contexto de Trabalho" + precedência
│       ├── AiMessageProcessor.php             # MODIFICA: usa assembler+tools; reinjeção de nome; cap round-trips
│       └── OutboundNameInjector.php           # NOVO — substitui {{primeiro_nome}} na saída (FR-017)
├── Http/
│   ├── Controllers/Api/V1/Ai/
│   │   └── AiWorkContextController.php        # NOVO (GET/PUT singleton por tenant)
│   ├── Requests/Ai/
│   │   └── UpsertWorkContextRequest.php       # NOVO (valida estruturado + allow-list não-clínica)
│   └── Resources/Ai/
│       └── AiWorkContextResource.php          # NOVO
config/
└── ai.php                                    # MODIFICA: bloco matricial.history + matricial.tools + caching
database/migrations/
├── ..._create_ai_work_contexts_table.php      # NOVO
├── ..._create_ai_conversation_summaries_table.php  # NOVO
└── ..._create_ai_tool_invocations_table.php   # NOVO
resources/js/
├── pages/Ia/WorkContextPage.vue               # NOVO — formulário híbrido (estruturado + texto livre)
└── stores/ia/workContext.js                   # NOVO — Pinia store
routes/api.php                                 # MODIFICA: rotas work-context
tests/
├── Feature/Ai/ConversationHistoryTest.php     # US1
├── Feature/Ai/ConversationSummarizerTest.php  # US3 (incremental/reuse)
├── Feature/Ai/AiContextBudgetTest.php          # US3 (ceiling/shedding)
├── Feature/Ai/WorkContextCrudTest.php          # US2 (+ tenant isolation)
├── Feature/Ai/WorkContextAppliedToPromptTest.php # US2
├── Feature/Ai/Tools/*Test.php                  # US5 — 6 tools + isolamento tenant/paciente + cap
├── Feature/Ai/NameInjectionTest.php            # FR-017
├── Unit/Ai/ConversationHistoryAssemblerTest.php
└── e2e/ai-scheduling-conversation.spec.ts      # E2E jornada crítica (atualiza existente)
```

**Structure Decision**: Mantém a arquitetura por domínio da Fase 15 (`app/Domain/Ai/**`) e o pipeline Constitucional `Form Request → Controller → Service → Resource` para o CRUD de work context. As Tools ficam em `app/Domain/Ai/Tools` (não em `app/Ai/Tools`) para coabitar com o domínio existente; cada Tool é fina e delega a Services de Fase 2/5/12. Nenhuma pasta raiz nova.

## Complexity Tracking

| Desvio | Por que é necessário | Alternativa mais simples rejeitada porque |
|---|---|---|
| Target de latência da IA com tool-calling **p95 ≤ 8s** (vs. "≤5s" do Princípio V) | Tool-calling implica múltiplos round-trips modelo↔tool (decisão de produto Q5); o canal é WhatsApp assíncrono (paciente responde em minutos/horas), logo alguns segundos extras são aceitáveis para ganhar precisão de dados reais. | Forçar ≤5s com tools exigiria ou cortar o nº de tools por resposta abaixo do útil, ou um modelo mais rápido/caro fixo — degradaria qualidade (US5/SC-009) sem ganho real de UX no canal assíncrono. Single-pass mantém ≤5s. É refino de **target de métrica**, não remoção de gate → sem amendment. |
| 3 tabelas novas (`ai_work_contexts`, `ai_conversation_summaries`, `ai_tool_invocations`) | Work context precisa ser versionável/auditável e editável por clínica; o resumo rolante precisa de `covered_up_to_message_id` para incrementalidade (FR-022); auditoria de tools é exigência do Princípio III/FR-031. | Reusar `ai_personas.model_settings`/JSON solto não dá versionamento nem query; derivar resumo on-the-fly a cada turno violaria SC-004 (custo) e FR-022 (reuso). Auditar tools só no `AiExecutionLog` perderia granularidade por invocação. |
