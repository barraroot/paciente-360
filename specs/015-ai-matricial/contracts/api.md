# Phase 1 — API Contracts: IA Matricial

Todas as rotas do tenant sob `/api/v1/ai/...`, middleware `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']` (stack obrigatório — lição Fase 12: sem `tenant.slug` o team_id do Spatie fica null e a gate falha). Pipeline `Form Request → Controller → Service → Resource`. Respostas via API Resources. Cross-tenant → **404** (route model binding sob `TenantScope`). Erros de validação → 422. Sem permissão → 403.

## Endpoints (tenant)

### Catálogo de modelos
- `GET /ai/models` — lista modelos **ativos** (+ os já referenciados por personas do tenant, mesmo inativos). `ability: ai.persona.view`.

### Personas
- `GET /ai/personas` — lista (filtros: `is_active`, `channel_type`). `ai.persona.view`
- `POST /ai/personas` — cria. `ai.persona.manage`
- `GET /ai/personas/{persona}` — detalha (inclui canais/bases/guardrails associados). `ai.persona.view`
- `PUT /ai/personas/{persona}` — edita. `ai.persona.manage`
- `DELETE /ai/personas/{persona}` — soft delete (se permitido). `ai.persona.manage`
- `POST /ai/personas/{persona}/activate` · `POST /ai/personas/{persona}/deactivate` — `ai.persona.manage`
- `PUT /ai/personas/{persona}/knowledge-bases` — define associações de bases (array de ids). `ai.matrix.manage`
- `PUT /ai/personas/{persona}/guardrails` — define associações de guardrails. `ai.matrix.manage`

### Bases de conhecimento
- `GET /ai/knowledge-bases` · `POST` · `GET /{kb}` · `PUT /{kb}` · `DELETE /{kb}` — `ai.knowledge.view`/`ai.knowledge.manage`
- `POST /ai/knowledge-bases/{kb}/activate` · `/deactivate` — `ai.knowledge.manage`

### Guardrails
- `GET /ai/guardrails` · `POST` · `GET /{g}` · `PUT /{g}` · `DELETE /{g}` — `ai.guardrail.view`/`ai.guardrail.manage`
- `POST /ai/guardrails/{g}/activate` · `/deactivate` — `ai.guardrail.manage`

### Matriz Persona × Canal
- `GET /ai/persona-channels` — estado da matriz (personas × `whatsapp`/`instagram`/`web`). `ai.persona.view`
- `PUT /ai/persona-channels` — define células (lista `{ai_persona_id, channel_type, is_active}`). `ai.matrix.manage`
- `GET /ai/channels/{channel_type}/config` — consulta config de IA por canal (personas ativas, habilitado?). `ai.persona.view`

### Controle de IA na conversa (integra à tela existente)
- `GET /ai/conversations/{conversation}/state` — estado da IA + persona atribuída. `inbox.view`
- `POST /ai/conversations/{conversation}/pause` — pausa a IA. `inbox.respond`
- `POST /ai/conversations/{conversation}/resume` — reativa a IA (quando permitido). `inbox.respond`

### Logs
- `GET /ai/execution-logs` — lista paginada (filtros: conversa, persona, status, período). `ai.log.view`
- `GET /ai/execution-logs/{log}` — detalhe. `ai.log.view`

### Validação/sanitização de Markdown
- `POST /ai/markdown/validate` — valida + **sanitiza** um Markdown e retorna a versão sanitizada + avisos. Gate por `type` (`ai.persona.manage`/`ai.knowledge.manage`/`ai.guardrail.manage`). Rate-limited.
- **Não há** `POST /ai/markdown/assist` — o auxiliar de Markdown é **client-side e determinístico** (FR-037, clarificação 2026-05-26); a sanitização também é aplicada no save de cada recurso (persona/base/guardrail).

## Super-admin (Filament — fora de `/api/v1`)
- `AiModelResource` CRUD do catálogo global. Policy de Super Admin. Não acessível ao tenant.

---

## Gates de aceitação (verificáveis por teste)

| Gate | Descrição | FR/SC/Princípio |
|------|-----------|-----------------|
| **G1** | Toda query `ai_*` escopada por tenant; cross-tenant em persona/base/guardrail/log/conversa → 404; nenhum vazamento | FR-005/019/023/035/040, SC-005, Princ. II |
| **G2** | Associar base/guardrail de outra clínica a persona → bloqueado (co-tenancy no Service) | FR-019/023, SC-005 |
| **G3** | Round-robin distribui ≤1 de diferença entre personas; sob concorrência (lock) não duplica posição; isolado por tenant+canal | FR-012/013/014, SC-002 |
| **G4** | Conversa atribuída mantém a mesma persona até encerrar/pausar/transferir/reatribuir | FR-015, SC-003 |
| **G5** | Canal sem persona ativa → IA não responde, fluxo humano intacto | FR-011/030, SC-006 |
| **G6** | Inbound → job de IA assíncrono; webhook nunca bloqueia | FR-028/029, perf |
| **G7** | Guardrails mínimos médicos sempre aplicados, mesmo sem guardrails da clínica; intenção clínica redirecionada+marcada; baixa confiança/urgência → escala | FR-026/027, SC-004/013, Princ. III |
| **G8** | Bases/guardrails inativos nunca usados/recuperados em novas respostas | FR-021/021b/025, SC-012 |
| **G9** | PII pseudonimizada antes do envio ao LLM; log sem PII clínica; contexto pseudonimizado | FR-042, SC-011, Princ. I/III |
| **G10** | Pausa: humano assume → IA pausa automática; pausada não responde; pausa **indefinida** sem auto-resume; só reativação **manual** (`inbox.respond`) volta a responder | FR-032/033, SC-007, Princ. III |
| **G10b** | Persona desativada/removida do canal → conversas em andamento **reatribuídas** a outra persona ativa; sem outra ativa → humano | FR-016a, US2 |
| **G10c** | Falha do provedor → **retry com backoff** sem mensagem ao paciente; só escala ao esgotar | FR-030c |
| **G11** | Log de execução completo (prompt/contexto/intenção/confiança/resposta/ação, latência, tokens), retenção ≥6m, isolado por tenant | FR-034/035, SC-008, Princ. III/V |
| **G12** | Markdown sanitizado no back-end (sem script/HTML inseguro/eventos/js: URLs); assistente nunca auto-salva | FR-038/041, SC-009/010, Princ. VII |
