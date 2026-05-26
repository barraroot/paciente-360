# Phase 1 — Quickstart / Validação: IA Matricial

Lotes de validação incremental (mapeiam às 9 fases do pedido e às user stories). Cada lote é demonstrável de forma independente. Comandos sempre via `vendor/bin/sail`.

## Pré-requisito (APROVADO 2026-05-26)
- `composer require laravel/ai` (Laravel AI SDK) + publicar/config `config/ai.php` com provider/credenciais **globais via env**. Usado nos Lotes C (embeddings) e E (geração/structured output).
- Habilitar extensão `pgvector` (migration `CREATE EXTENSION IF NOT EXISTS vector`) + opcional pacote `pgvector/pgvector`. **Bloqueante para o Lote C (RAG)**; os demais lotes não dependem dele.

## Lote A — Fundação CRUD (US1, P1)
1. Migrations: `ai_models`, `ai_personas`, `ai_knowledge_bases`, `ai_guardrails` (+ `created_by/updated_by`, soft delete).
2. Models + `BelongsToTenant` (exceto `ai_models`), factories, policies, Form Requests, Resources, controllers CRUD.
3. `AiModelResource` no Filament super-admin; seed de ≥1 modelo ativo.
4. **Valida**: criar persona com modelo ativo via API; `GET /ai/models` só ativos; cross-tenant 404 (G1); `model_settings` validado contra `config_schema`.
5. Testes: feature CRUD + unit de validação de settings + multi-tenant.

## Lote B — Matrizes de associação (US4/US5 + matriz canal, P1/P2)
1. Migrations pivots: `ai_persona_channels`, `ai_persona_knowledge_base`, `ai_persona_guardrail` (UNIQUEs).
2. `AiMatrixService` + endpoints `PUT /ai/persona-channels`, `PUT /ai/personas/{p}/knowledge-bases`, `/guardrails`.
3. **Valida**: configurar células da matriz; co-tenancy bloqueada (G2); `GET /ai/channels/{type}/config`.
4. Testes: associação válida/ inválida cross-tenant; matriz idempotente.

## Lote C — RAG / indexação de bases (US4, P2) — depende do pré-requisito
1. Migration `ai_knowledge_chunks` (coluna `vector(N)` + índice HNSW).
2. `AiEmbeddingService` (`Laravel\Ai\Embeddings::for()->dimensions()`, `Embeddings::fake()` nos testes) + `EmbedKnowledgeBaseJob` (chunking + substituição transacional).
3. Recuperação top-K filtrada por tenant + bases ativas associadas.
4. **Valida**: salvar base dispara reindex; desativar base remove-a da recuperação (G8); base não indexada não contribui.
5. Testes: unit de chunking; integração de recuperação só-ativas; multi-tenant na similaridade.

## Lote D — Round-robin + atribuição (US2, P1)
1. Migrations `ai_channel_distribution_states`, `ai_conversation_assignments` (UNIQUE parcial por conversa).
2. `AiPersonaSelectorService` (transação + `lockForUpdate`) + `AiConversationAssignmentService`.
3. **Valida**: 4 conversas em canal com 2 personas → 2/2 (G3); concorrência não duplica posição; conversa atribuída mantém persona (G4); canal sem persona → sem IA (G5).
4. Testes: unit round-robin (incl. concorrência simulada), continuidade, isolamento tenant+canal.

## Lote E — Orquestração assíncrona da resposta (US3, P1)
1. Listener `TriggerAiResponseOnInboundMessage` (ouve `MensagemRecebida`) + debounce Redis.
2. `AiContextBuilderService` (persona + RAG + guardrails + pseudonimização via `PiiScrubber`) → `instructions()`.
3. `PersonaAgent` (`laravel/ai`: `Agent`+`HasStructuredOutput` → `{resposta, intencao, confidence, needs_human}`) + `AiGuardrailEnforcer` (mínimos em código + pós-processamento determinístico + escalonamento) — Princípio III.
4. `AiMessageProcessor` + `ProcessAiResponseJob` (fila `ai`) → envia via `MessageDispatchService` (G6).
5. **Valida**: inbound gera resposta `sender_type=ai` enviada pelo canal; webhook não bloqueia; guardrails mínimos aplicados sem guardrails da clínica (G7); intenção clínica redirecionada; PII pseudonimizada (G9).
6. Testes: integração inbound→envio; bypass de guardrail (prompt injection/role-play/tradução — R7); pseudonimização.

## Lote F — Estado/pausa, reatribuição, falha e logs (US6/US7, P2/P3)
1. Listener `PauseAiOnHumanTakeover` (ouve `ConversaAssumidaPorHumano`) reusando `ai_paused_until`; pausa **indefinida** (sem auto-resume), reativação **manual** por `inbox.respond`.
2. Fluxo de **reatribuição** ao desativar/remover persona do canal (FR-016a, G10b).
3. **Falha** do provedor: retry com backoff sem mensagem ao paciente; ao esgotar → `error` + handoff humano (FR-030c, G10c).
4. `ai_execution_logs` + eventos `Auditable`/`ContainsNoClinicalData`; endpoints de estado/pausar/reativar e de logs.
5. **Valida**: humano assume → IA pausa; pausada não responde; só reativação manual volta (G10); reatribuição (G10b); falha→retry→escala (G10c); log completo + retenção (G11); cross-tenant em logs 404 (G1).
6. Testes: pausa/reativação manual; reatribuição (com e sem outra persona ativa); retry/escala de falha; conteúdo do log.

## Lote G — Front-end de configuração (US1/US4/US5 + matriz, P1/P2)
1. `pages/Ia/PersonasIndex+Form`, `BasesIndex+Form`, `GuardrailsIndex+Form`, `MatrizCanais.vue`; store Pinia `ia.js`.
2. `components/Ia/PersonaChannelMatrix.vue` (grid de switches WhatsApp/Widget/Instagram).
3. **Valida**: criar/editar/ativar/desativar; configurar matriz visualmente; navegação por permissões (`config/navigation.js`).

## Lote H — Editor Markdown + auxiliar de formatação (US8, P3)
1. `components/Ia/MarkdownEditor.vue` (edição + preview sanitizado DOMPurify + templates + validação de obrigatório + copiar/colar).
2. `components/Ia/MarkdownToolbar.vue` — auxiliar **determinístico** (título/subtítulo/ênfase/parágrafo/citação/lista/checklist/link/tabela) que emite Markdown no cliente (**sem IA**). Endpoint `POST /ai/markdown/validate` (sanitização) + `MarkdownSanitizerService` no save de cada recurso.
3. **Valida**: preview não renderiza script/HTML inseguro (G12); controles inserem Markdown; salvar é **ação explícita** e o back-end sanitiza (G12).
4. Templates pré-carregados (persona/base/guardrail) conforme spec.

## Lote I — Integração na conversa + hardening (US6, P2/P3)
1. `components/Ia/ConversationAiPanel.vue` embutido na tela de conversa existente: indicador IA ativa, persona, canal, status, botões Pausar/Reativar.
2. Métricas Prometheus (`ai_response_latency_seconds`, `ai_escalation_total`, `ai_messages_total{tenant}`) + Sentry.
3. **Valida**: smoke da jornada "IA responde → atendente pausa → assume → reativa"; auditoria a11y dos modais/editor; Constitution Re-Check 7/7.
4. Suíte completa: `vendor/bin/sail artisan test --compact` + `vendor/bin/sail bin pint --dirty --format agent`.

## Métricas de pronto
- G1–G12 verdes; cobertura ≥ 70%; resposta IA p95 ≤ 5s em ambiente de teste; nenhum teste pré-existente quebrado; navegação SPA por permissões.
