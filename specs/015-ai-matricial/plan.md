# Implementation Plan: IA Matricial

**Branch**: `015-ai-matricial` | **Date**: 2026-05-26 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/015-ai-matricial/spec.md`

## Summary

Camada incremental de **configuração e orquestração de IA** plugada sobre o omnichannel existente (canais `whatsapp`/`instagram`/`web`, conversas, mensagens, atendimento humano), sem recriar nenhum módulo. A clínica configura uma **matriz** que cruza Persona × (Canal, Base de Conhecimento, Guardrail, Modelo de IA). Quando chega mensagem inbound em canal com IA habilitada, um **listener** dispara um **job em fila** que: resolve/atribui persona via **round-robin persistente por tenant+canal** (lock pessimista), monta o contexto por **recuperação semântica (RAG/pgvector)** das bases ativas, aplica **guardrails médicos mínimos obrigatórios + guardrails da clínica**, pseudonimiza PII, chama o **provedor de IA** (credenciais globais da plataforma), e **auto-envia** a resposta pelo `MessageDispatchService` existente — escalando para humano (pausando a IA) em risco/dúvida/baixa confiança/urgência. Toda execução gera **log auditável** (prompt, contexto pseudonimizado, intenção, score de confiança, resposta, ação; retenção ≥ 6 meses — Princípio III). Front-end Vue: telas de Personas/Bases/Guardrails, **tela matricial Persona×Canal**, **editor Markdown reutilizável** + **assistente de criação**, e integração de status/pausar/reativar IA na tela de conversa existente. O catálogo de modelos é gerido pelo **super-admin (Filament)**; tenants apenas selecionam modelos ativos.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), JavaScript/Vue 3 (Composition API)
**Primary Dependencies**: Sanctum (Bearer), Spatie Permissions, Horizon (filas), Reverb (broadcast de status), Filament 5 (catálogo de modelos no super-admin), Pinia/Vue Router/Tailwind v4, Luxon/dayjs. **APROVADO pelo owner (2026-05-26)**: (1) extensão PostgreSQL **`pgvector`** (DB-level) para armazenamento/similaridade dos embeddings RAG; (2) pacote oficial **`laravel/ai`** (Laravel AI SDK, primeira-parte — mesma suíte de `laravel/boost`+`laravel/mcp` já presentes) como **única** abstração de IA: geração de texto, **structured output**, **embeddings** e testes via fakes. Provider configurável (Anthropic default) com credenciais **globais via env** (Restrição Técnica "LLM provider configurável"). Opcional `pgvector/pgvector` (cast Eloquent) — contornável com SQL cru.
**Storage**: PostgreSQL (já com `pg_trgm`/`unaccent`; **adicionar extensão `vector`**) + Redis (debounce, lock auxiliar, cache de matriz, métricas).
**Testing**: PHPUnit (feature predominante + unit p/ round-robin/context/sanitização/guardrails), testes de isolamento multi-tenant obrigatórios, **fakes do próprio `laravel/ai`** (`Embeddings::fake()`/`assertGenerated`, fakes de resposta de agente) para determinismo nos testes de bypass de guardrail e orquestração. Playwright para a jornada de IA no chat (ver Constitution Check — Princípio IV).
**Target Platform**: Linux server (Sail/Docker), SPA Vue servida como app do tenant; Horizon para workers.
**Project Type**: Web application (backend Laravel API + frontend Vue SPA) + painel Filament super-admin.
**Performance Goals**: Resposta da IA com **p95 ≤ 5s** (Princípio V); webhook/inbound NUNCA bloqueado pela IA (processamento 100% assíncrono); round-robin sem duplicação sob concorrência.
**Constraints**: Isolamento multi-tenant total (Princípio II); pseudonimização de PII antes de qualquer envio ao LLM (Princípios I/III); IA proibida de diagnóstico/prescrição/orientação clínica (Princípio III); sanitização de Markdown no back-end e preview (Princípio VII); auto-envio apenas dentro da janela 24h via canal reativo (Princípio VI preservado — IA é reativa a inbound).
**Scale/Scope**: Multi-tenant; dezenas a centenas de clínicas; múltiplas personas/bases/guardrails por clínica; ~10 tabelas novas, 8 controllers de API, 1 Filament Resource, ~7 telas/componentes Vue, 1 listener + 1 job principal + jobs de (re)indexação.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

A constituição **v1.5.0** já antecipa explicitamente esta feature ("**A camada de IA matricial** opera dentro de limites estritos de escopo CRM" — Princípio III; "IA: Camada de orquestração matricial sobre LLM (provider configurável) com pseudonimização obrigatória" — Restrições Técnicas). **Nenhum amendment é necessário.**

| Princípio | Status | Como o plano satisfaz |
|-----------|--------|------------------------|
| **I. LGPD (NN)** | ✅ PASS | `AiContextBuilderService` pseudonimiza CPF/RG/carteirinha/telefone via `PiiScrubber` antes do envio ao LLM; `ai_execution_logs` armazena **contexto pseudonimizado** (nunca PII bruta) com retenção ≥ 6 meses; Markdown sanitizado no save (`MarkdownSanitizerService`); logs sem dado sensível desnecessário; bases/guardrails não armazenam PII de paciente. |
| **II. Multi-Tenant (NN)** | ✅ PASS | Todas as tabelas `ai_*` usam `BelongsToTenant` + `TenantScope`, **exceto `ai_models`** (catálogo global da plataforma, sem `tenant_id`, somente leitura para tenant). Round-robin escopado por `(tenant_id, channel_type)`. Jobs restauram contexto de tenant (`setPermissionsTeamId`). Associações validam co-tenancy (persona/base/guardrail mesma clínica). **Teste de leitura cruzada obrigatório** em toda PR que toca persistência/fila. |
| **III. Segurança Clínica IA (NN)** | ✅ PASS | Guardrails médicos mínimos **em código** (sempre aplicados, independem de config); classificação de intenção + **score de confiança**; abaixo do limiar → escala para humano; **urgência → escalonamento imediato, prioridade alta**; intenções clínicas (diagnóstico/prescrição/posologia/exame) **redirecionadas a agendamento + marcadas para revisão**; IA **pausa automaticamente** ao `ConversaAssumidaPorHumano`; log de cada decisão com prompt/contexto/intenção/confiança/resposta/ação (≥ 6 meses); **testes de bypass** (prompt injection, role-play, tradução). |
| **IV. Spec-Driven Test-First** | ✅ PASS (com nota) | Testes unit (round-robin, context builder, sanitização, guardrails ativos/inativos, associações), feature (CRUD/matriz/pausar-reativar/logs/assistente), multi-tenant e integração (inbound→job→persona→contexto→envio→log; fallback). Cobertura ≥ 70%. **Nota Princípio IV (E2E "agendamento via IA no chat")**: esta feature entrega a **resposta conversacional da IA**; o *tool-calling* que cria o agendamento é incremento futuro — a IA aqui **redireciona** intenção de agendamento conforme guardrails. E2E Playwright da jornada "IA responde no chat + humano pausa/reativa" incluída em quickstart; a jornada completa de agendamento-via-IA permanece coberta pelos E2E da Fase 5 e será estendida quando o tool-calling existir. |
| **V. Observabilidade** | ✅ PASS | Logs estruturados com `tenant_id`/`correlation_id` por decisão de IA; eventos auditáveis (`PersonaAtribuidaAConversa`, `RespostaIAEnviada`, `IAEscalouParaHumano`, `ExecucaoIAFalhou`); métricas Prometheus (tempo de resposta IA p95, taxa de escalonamento, consumo mensal de mensagens IA por tenant); Sentry em erro não-tratado; alvo de resposta ≤ 5s. |
| **VI. Conformidade Meta (NN)** | ✅ PASS | A IA é **reativa a mensagem inbound** → sempre dentro da janela de 24h → texto livre permitido. **Não** adiciona disparo proativo/massa. O envio passa pelo `MessageDispatchService` existente, que mantém o gate de template/janela da Fase 13/14 intacto. |
| **VII. Segurança Operacional (NN)** | ✅ PASS | Form Request + Policy por recurso; cross-check tenant no middleware existente; rate limiting nos endpoints de IA e no assistente de Markdown; credenciais do provedor **globais via env** (nunca input do tenant); preview Markdown sanitizado com DOMPurify (já mandatório); sem nova superfície de auth. |

**Pipeline obrigatório** respeitado: `Form Request → Controller → Service → (Eloquent/Job/Integração) → Resource`. Catálogo de modelos no **Filament super-admin** (não no app do tenant). Strings de UI em pt-BR via i18n; identificadores de código em inglês.

**Resultado: PASS 7/7, sem amendment.** As mudanças de dependência (`pgvector` + `laravel/ai`) foram **aprovadas pelo owner em 2026-05-26**; não exigem amendment (a Restrição Técnica já prevê "LLM provider configurável" e só exige aprovação explícita de dependência na PR — cumprida). Registrado em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/015-ai-matricial/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — R1..R12
├── data-model.md        # Phase 1 — entidades, colunas, índices, constraints, estados
├── quickstart.md        # Phase 1 — lotes de validação A..I
├── contracts/
│   └── api.md           # Phase 1 — contrato das APIs de IA (endpoints, gates G1..G12)
├── checklists/
│   └── requirements.md  # da fase /speckit-specify
└── tasks.md             # Phase 2 — /speckit-tasks (NÃO criado por /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Domain/
│   └── Ai/                                  # NOVO bounded context (espelha app/Domain/Messaging)
│       ├── Model/
│       │   └── Models/AiModel.php           # catálogo global (sem tenant)
│       ├── Persona/
│       │   ├── Models/AiPersona.php
│       │   └── Events/PersonaAtribuidaAConversa.php
│       ├── KnowledgeBase/
│       │   ├── Models/AiKnowledgeBase.php
│       │   └── Models/AiKnowledgeChunk.php  # chunks + embedding vector (RAG)
│       ├── Guardrail/
│       │   └── Models/AiGuardrail.php
│       ├── Matrix/
│       │   ├── Models/AiPersonaChannel.php
│       │   ├── Models/AiPersonaKnowledgeBase.php   # pivot
│       │   ├── Models/AiPersonaGuardrail.php       # pivot
│       │   └── Services/AiMatrixService.php
│       ├── Distribution/
│       │   ├── Models/AiChannelDistributionState.php
│       │   └── Services/AiPersonaSelectorService.php   # round-robin + lock
│       ├── Assignment/
│       │   ├── Models/AiConversationAssignment.php
│       │   ├── Services/AiConversationAssignmentService.php
│       │   └── Events/{IAEscalouParaHumano,RespostaIAEnviada,ExecucaoIAFalhou}.php
│       ├── Execution/
│       │   └── Models/AiExecutionLog.php
│       ├── Agents/
│       │   └── PersonaAgent.php                 # implements Agent, HasStructuredOutput (laravel/ai)
│       │                                        #   instructions = system prompt composto; provider/model
│       │                                        #   da persona; schema → {resposta, intencao, confidence}
│       └── Services/
│           ├── AiContextBuilderService.php      # persona+RAG+guardrails+pseudonimização → instructions
│           ├── AiGuardrailEnforcer.php          # guardrails mínimos + pós-processamento determinístico
│           ├── AiEmbeddingService.php           # Laravel\Ai\Embeddings → ai_knowledge_chunks (pgvector)
│           ├── AiMessageProcessor.php           # orquestra: contexto → PersonaAgent->prompt() → envio
│           └── MarkdownSanitizerService.php
├── Jobs/Ai/
│   ├── ProcessAiResponseJob.php             # disparado pelo listener (debounce)
│   ├── EmbedKnowledgeBaseJob.php            # (re)indexação RAG ao salvar base
│   └── ...
├── Listeners/Ai/
│   ├── TriggerAiResponseOnInboundMessage.php   # ouve MensagemRecebida
│   └── PauseAiOnHumanTakeover.php              # ouve ConversaAssumidaPorHumano
├── Http/
│   ├── Controllers/Api/V1/Ai/
│   │   ├── AiModelController.php             # index (somente ativos)
│   │   ├── AiPersonaController.php
│   │   ├── AiKnowledgeBaseController.php
│   │   ├── AiGuardrailController.php
│   │   ├── AiPersonaChannelController.php    # matriz Persona×Canal
│   │   ├── AiConversationController.php      # status/pausar/reativar
│   │   ├── AiExecutionLogController.php
│   │   └── AiMarkdownController.php          # validar/sanitizar (sem geração por IA)
│   ├── Requests/Ai/*.php
│   └── Resources/Ai/*.php
├── Policies/Ai/*.php                        # AiPersonaPolicy, AiKnowledgeBasePolicy, ...
└── Filament/Resources/AiModelResource.php   # catálogo global (super-admin)

database/migrations/                          # ai_models, ai_personas, ai_knowledge_bases,
                                              # ai_knowledge_chunks, ai_guardrails, ai_persona_channels,
                                              # ai_persona_knowledge_base, ai_persona_guardrail,
                                              # ai_channel_distribution_states, ai_conversation_assignments,
                                              # ai_execution_logs (+ enable vector extension)
config/ai.php                                 # provider, modelo default, dimensão de embedding,
                                              # limiar de confiança, debounce, guardrails mínimos
lang/pt_BR/ai.php                             # strings de UI/erros

resources/js/
├── pages/Ia/
│   ├── PersonasIndex.vue / PersonaForm.vue
│   ├── BasesIndex.vue / BaseForm.vue
│   ├── GuardrailsIndex.vue / GuardrailForm.vue
│   └── MatrizCanais.vue                      # tela matricial Persona×Canal
├── components/Ia/
│   ├── MarkdownEditor.vue                    # reutilizável: edição+preview sanitizado+toolbar+templates
│   ├── MarkdownToolbar.vue                   # auxiliar determinístico (título/subtítulo/ênfase/checklist/lista/link/tabela) → emite Markdown (sem IA)
│   ├── PersonaChannelMatrix.vue              # grid de switches
│   └── ConversationAiPanel.vue              # status/persona/pausar/reativar na tela de conversa
└── stores/ia.js                              # Pinia store da camada de IA
```

**Structure Decision**: Web application. Backend segue o **bounded context novo `app/Domain/Ai/`** espelhando a organização de `app/Domain/Messaging/` (Models/Events/Services por agregado), com Controllers/Requests/Resources sob `Api/V1/Ai`. O **catálogo de modelos** é a única superfície no **Filament super-admin** (decisão arquitetural da constituição — config de plataforma). Frontend adiciona telas em `resources/js/pages/Ia/` e componentes reutilizáveis em `components/Ia/`, integrando-se à tela de conversa existente sem recriá-la.

## Complexity Tracking

> Itens que exigem decisão/aprovação do owner (mudança de dependência) ou que adicionam complexidade justificada.

| Violation / Decisão | Why Needed | Status / Alternativa rejeitada |
|---------------------|------------|--------------------------------|
| Extensão PostgreSQL `pgvector` (DB) + opcional pacote `pgvector/pgvector` | RAG semântico (clarificação 2026-05-25) exige similaridade vetorial sobre os chunks das bases | **APROVADO 2026-05-26.** Full-text `pg_trgm` (presente) não capta semântica; vetor externo (Pinecone/Qdrant) adicionaria infra e quebraria isolamento por tenant. Reusar o PostgreSQL é o menor incremento e mantém o `TenantScope` na query de similaridade. |
| Pacote oficial **`laravel/ai`** (Laravel AI SDK) como abstração de IA | Geração de texto, **structured output** (intenção+confiança — Princípio III), **embeddings** (RAG) e **fakes de teste** numa única API de primeira-parte, provider-configurável | **APROVADO 2026-05-26.** Substitui o `HttpAiGateway` cru que eu havia proposto. Mesma suíte de `laravel/boost`+`laravel/mcp` já instalados. **Atenção: v0.x (0.7.0)** — fixar versão e revisar breaking changes ao atualizar. SDK de terceiros (Prism etc.) rejeitado em favor do oficial. |
| Armazenar embeddings em `ai_knowledge_chunks` (pgvector) em vez do "vector store" gerenciado do SDK | Isolamento multi-tenant total (Princípio II) na própria query de similaridade | O vector store do SDK abstrai o armazenamento; mantê-lo no nosso PG tenant-scoped garante que `TenantScope` + filtro por base ativa governem a recuperação. Usamos o SDK só para **gerar** embeddings. |
| `app/Domain/Ai/` como novo contexto | Volume de agregados (modelo/persona/base/guardrail/matriz/distribuição/atribuição/execução) | Colocar tudo em `app/Services` plano dificultaria navegação/testes; o projeto já adota DDD-lite em `app/Domain/Messaging`. |
