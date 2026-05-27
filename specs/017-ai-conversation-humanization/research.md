# Phase 0 — Research: Humanização da Conversa da IA

Resolve as incógnitas técnicas do `plan.md`. Cada item: **Decisão · Rationale · Alternativas**.

---

## R1 — Mecanismo de tool-calling: laravel/ai Tools vs. laravel/mcp

**Decisão**: Usar **laravel/ai Tools** (`HasTools`) para as ferramentas que a IA invoca *durante* a conversa. Expor um servidor **laravel/mcp** é **opcional/deferred** (clientes externos/Boost), não no caminho crítico.

**Rationale**:
- O `PersonaAgent` já é um agente `laravel/ai`. O contrato `HasTools::tools(): iterable` retorna instâncias de `Laravel\Ai\Contracts\Tool` (`description()`, `handle(Request)`, `schema(JsonSchema)`) que o SDK orquestra em loop modelo↔tool no mesmo `prompt()`. Zero servidor HTTP extra, zero handshake MCP, mesma transação/contexto de tenant do job.
- `laravel/mcp` cria um **servidor** MCP para *clientes* externos (IDE, Boost, outro app). Seria uma camada de transporte desnecessária para o agente chamar a própria base — e introduziria autenticação/rota próprias.
- Para preservar a intenção do pedido ("considerar laravel-mcp") sem reescrita futura, cada Tool delega a um **Service fino** de domínio; o mesmo Service pode lastrear uma `Laravel\Mcp\Server\Tool` se um dia quisermos expor via MCP.

**Alternativas**: (a) MCP server in-process consumido pelo agente — overhead de transporte sem ganho; (b) function-calling manual fora do SDK — reimplementaria o loop de steps que o `laravel/ai` já entrega com `MaxSteps`.

---

## R2 — Histórico de conversa no agente

**Decisão**: `PersonaAgent implements Conversational`; `messages()` retorna **[mensagem de resumo (se houver)] + janela verbatim** (default 6 mensagens / ~3 turnos), cada uma como `Laravel\Ai\Messages\Message($role, $content)` com `role` ∈ {`user` (inbound), `assistant` (saída IA/sistema)} e **conteúdo pseudonimizado**.

**Rationale**: O SDK suporta `messages()` nativamente (visto na doc do `SalesCoach`). Controlamos exatamente o que entra → satisfaz FR-001/FR-002 e o "mínimo suficiente". O resumo entra como uma `Message('assistant'|'system', "Resumo da conversa até aqui: ...")` no topo. A mensagem atual continua indo via `prompt($context->prompt)`.

**Alternativas**: mandar todas as mensagens (viola SC-004/minimal); concatenar histórico no system prompt como texto (perde a estrutura de papéis user/assistant que ajuda o modelo a não repetir perguntas — FR-003).

---

## R3 — Resumo rolante incremental e economia

**Decisão**: Tabela `ai_conversation_summaries` (1 por conversa) com `summary_text`, `key_facts` (jsonb), `funnel_stage`, `covered_up_to_message_id`, `version`. O `ConversationSummarizerService` só roda quando existem mensagens com `id > covered_up_to_message_id` **e** fora da janela verbatim; usa o **modelo mais barato** (Haiku/`UseCheapestModel`) num agente dedicado de sumarização, sob **lock Redis** por conversa para evitar corrida. Caso contrário, reusa o resumo existente (FR-022).

**Rationale**: Incrementalidade = custo sub-linear (SC-004/SC-010). `key_facts` estruturado preserva queixa/respostas/local/preço/intenção mesmo quando o texto é comprimido (FR-004/FR-002a). `funnel_stage` alimenta US4 sem máquina de estados separada.

**Alternativas**: re-sumarizar tudo a cada turno (custo linear, viola FR-022); summary só em memória/cache (perde durabilidade em gaps de horas/dias — edge case "multi-hora/dia").

---

## R4 — Orçamento de tokens e shedding

**Decisão**: `AiContextBudget` aplica um teto configurável (`ai.matricial.history.input_token_ceiling`) sobre o contexto montado e **descarta por prioridade**: (1º) trechos RAG menos relevantes → (2º) granularidade do resumo → **nunca** os guardrails mínimos nem a mensagem atual (FR-023). Estimativa de tokens reusa o heurístico ~4 chars/token já presente no `AiEmbeddingService`.

**Rationale**: Garante FR-021/SC-003 (teto 100%) com fallback determinístico e seguro (Princípio III).

**Alternativas**: contar tokens com tokenizer real por provedor (precisão maior, custo/dependência) — deferred; o heurístico já é usado no projeto e é suficiente para o teto.

---

## R5 — Prompt caching e aplicação de `model_settings`

**Decisão**: `PersonaAgent implements HasProviderOptions`; em `Lab::Anthropic` retorna `cache_control: ['type' => 'ephemeral']` para o **bloco estático** (guardrails + work context + persona), e `temperature`/`max_tokens` derivados de `ai_personas.model_settings` quando suportado pelo SDK na chamada. `#[MaxSteps]` lê o cap de round-trips (default 3) de config.

**Rationale**: O bloco estático é o maior e o mais estável do prompt; cacheá-lo corta tokens de entrada repetidos turno a turno (reforça SC-004). Finalmente **usa** `model_settings` (hoje ignorado).

**Alternativas**: sem caching (mais caro); aplicar settings via atributos estáticos (não permite override por persona).

---

## R6 — Lead = registro CRM existente

**Decisão**: `CreateOrFindLeadTool` faz lookup por `tenant_id + telefone_primario_normalizado`; se não houver, cria `paciente` com `status='lead'`, `origem` = tipo do canal (`whatsapp`/`instagram`), `origem_origem='canal'`, posicionando no funil (`funil_coluna_atual_id`). Reusa o `PacienteService` (Fase 2). **Sem entidade paralela.**

**Rationale**: O CHECK de `pacientes.status` já inclui `'lead'`; `telefone_primario_normalizado` tem índice GIN por tenant; o funil (kanban) já existe. Confirmação de consulta promove `lead→ativo` downstream (fora do escopo da IA).

**Alternativas**: tabela `leads` nova — duplicaria e exigiria reconciliação (rejeitado na clarificação Q4).

---

## R7 — Hold provisório de slot = `SlotReservation` holder_type='ia'

**Decisão**: `HoldSlotTool` cria uma `SlotReservation` com `holder_type='ia'`, `holder_id` referenciando a conversa, `idempotency_key`, `expires_at` (TTL config Fase 5). A unicidade `sr_active_unique (tenant, professional, starts_at)` é o gate de corrida. Confirmação/commit (`release_reason='committed'`) é **handoff** (humano/fluxo Fase 5), nunca a IA (FR-018/FR-030).

**Rationale**: A Fase 5 **já** previu `holder_type='ia'` e o TTL — o hold provisório é exatamente esse mecanismo, sem schema novo. Edge case "slot tomado entre hold e confirmação" é coberto pela unicidade + expiração.

**Alternativas**: criar `Appointment` direto (viola Q2/FR-018 — seria confirmação autônoma).

---

## R8 — Fronteira de dados das tools (LGPD/tenant)

**Decisão**: Toda Tool recebe um `ToolContext` imutável `{tenant_id, conversation_id, patient_id?, contact_phone}` no construtor (via `ChannelAdapterResolver`-style factory no `AiMessageProcessor`). Reads clínic-level filtram `tenant_id` explícito. `GetCurrentPatientTool` resolve **somente** `messaging_conversations.patient_id` (ou lookup pelo `contact_phone` da própria conversa) e respeita `share_with_integrations_consent`; **nunca** busca por nome nem retorna outro paciente (FR-029/FR-034). I/O pseudonimizado antes de auditar.

**Rationale**: Isolamento no data layer (não só no prompt) é exigência do Princípio II/FR-034. Resolver paciente pela conversa elimina a superfície de busca cross-patient.

**Alternativas**: confiar no global scope apenas — insuficiente como única defesa; permitir busca por nome (rejeitado, risco de colisão/vazamento).

---

## R9 — Personalização por nome sem vazar PII

**Decisão**: O system prompt instrui o modelo a usar o placeholder literal `{{primeiro_nome}}`. Após `evaluate()`, o `OutboundNameInjector` substitui `{{primeiro_nome}}` pelo primeiro nome real do contato (de `pacientes.nome`) **somente na mensagem de saída**, antes do `MessageDispatchService::send`. A janela/resumo também escondem o nome (scrub). 

**Rationale**: Atende FR-017/FR-026 sem reduzir o pseudonimizador; o provedor nunca recebe o nome real. Determinístico e testável.

**Alternativas**: enviar 1º nome ao modelo sob consentimento (rejeitado na Q3 — amplia PII ao provedor); sem nome (perde o toque dos exemplos).

---

## R10 — Detecção de eco/loop e mensagem inicial

**Decisão**: O assembler marca papéis corretamente (assistant = saídas anteriores da IA); quando a mensagem atual é (quase) idêntica a uma `assistant` recente, um pré-check determinístico no `AiMessageProcessor` injeta uma dica no contexto ("o paciente repetiu sua mensagem anterior; avance") — sem custo de modelo extra. A `initial_message` da persona continua sendo o disparo inicial.

**Rationale**: Cobre o edge case real visto em `conversa1.txt` (paciente cola a pergunta do bot). Barato e auditável (FR-005).

**Alternativas**: deixar o modelo lidar sozinho (frágil/estocástico).

---

## R11 — Compatibilidade com guardrails, escala e auto-pause

**Decisão**: Nenhuma mudança em `minimalGuardrails()`, no `evaluate()` determinístico, no fluxo de escala/`ai_paused_until`/auto-pause, nem nos `blocked_intents`. O contexto novo (histórico/work context/tools) é **aditivo** ao system prompt; a saída continua estruturada e passa pelo mesmo pós-processamento. Se uma tool falha/timeout, a resposta degrada (pergunta/escala) sem inventar (FR-033).

**Rationale**: Preserva Princípio III e SC-006 (100% dos testes de segurança verdes). 

**Alternativas**: reescrever o enforcer — risco regressivo desnecessário.

---

## R12 — Fonte de dados do `get-clinic-info`

**Decisão**: Serviços e preços vêm de **`appointment_types`** (DB — `nome`, `descricao`, `valor_particular`, `valor_convenio_default`, `duration_minutes`, `is_active`). Horário e endereço **não têm tabela dedicada confirmada** → caem no **work context** (FR-011); horário pode opcionalmente derivar dos horários de trabalho dos profissionais (Fase 5).

**Rationale**: Confirmado no schema que `appointment_types` carrega serviço + valores. Ter o tool (e não só o work context no prompt) se justifica porque os valores podem mudar no DB sem reeditar o work context — o dado fica sempre vivo (SC-009). Evita a redundância apontada na análise (U1): o tool não duplica o work context para serviços/preços; complementa com o dado autoritativo do DB.

**Alternativas**: criar tabela de "perfil da clínica" (horário/endereço estruturados) — **deferred**; por ora o work context cobre esses campos sem schema novo.

## Resumo das decisões → requisitos

| Requisito | Coberto por |
|---|---|
| FR-001/002/002a/002b, SC-010 | R2, R3 |
| FR-003/004/005 | R2, R3, R10 |
| FR-007..012, US2 | data-model (`ai_work_contexts`), R5 (composeInstructions) |
| FR-013..016 (humanização) | work context + persona; tom/perguntas |
| FR-017/026 | R9 |
| FR-018/019/020 | R7, R11 |
| FR-021..024, SC-003/004 | R3, R4, R5 |
| FR-027..034, US5, SC-009 | R1, R6, R7, R8 |
| FR-025, auditoria | `ai_tool_invocations` + `AiExecutionLog` estendido |
| SC-008 (latência/round-trips) | R1 (`MaxSteps`), R5 |
