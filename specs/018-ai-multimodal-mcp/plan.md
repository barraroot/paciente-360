# Implementation Plan: Conversa Reativa, Multimodal e Auto-Curadoria do Kanban via IA

**Branch**: `018-ai-multimodal-mcp` | **Date**: 2026-05-30 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/018-ai-multimodal-mcp/spec.md`

## Summary

A Fase 17 trouxe **humanização** (histórico + work context + tools `laravel/ai`). Esta fase resolve 4 defeitos que ainda quebram a percepção humana e o CRM:

1. **Atropelamento de bursts** — a IA hoje processa cada mensagem isoladamente. Vai virar **coalescência híbrida** (Q1=C): debounce passivo de entrada (3-4s sem nova msg) + cancel-and-reprocess durante o pensamento, com tetos absolutos. Implementação: estado de turno em Redis (chave por conversa, versionada com `INCR`); o `ProcessAiResponseJob` valida a versão antes do dispatch e *short-circuita* se mudou.
2. **Multimodalidade** — STT inbound (WhatsApp + Instagram Direct, widget fica fora) processa áudio do paciente; TTS outbound só sob **gatilho explícito** (Q3=A) detectado por matcher de frases PT-BR. Provedores selecionados em research (R1/R2).
3. **CRM cego à conversa** — toda inbound de canal suportado entra como `Paciente status='lead'` na coluna inicial do `funil_colunas` do tenant (já existe — reuso total); paciente **não-lead** existente NÃO entra no kanban (Q-clarify-3=B), conversa anexa ao prontuário Fase 2. A IA passa a popular nome/observações estruturadas via tools auditadas; mudanças de status reagem a eventos do funil (hold colocado → "agendado", PIX confirmado → "confirmado", inatividade N dias → "perdido"). Mapping evento→status **configurável por tenant**.
4. **Sem teste de Persona** — botão "Testar" abre chat sandbox que percorre o ciclo completo da IA com `sandbox=true` propagado pelas tools (efeitos colaterais neutralizados).

**Decisão arquitetural crítica (Q2=B)**: o **servidor MCP (laravel-mcp v0)** **substitui** as tools nativas `laravel/ai` como caminho de produção, com 3 redes de segurança:
- **Feature flag** `AI_TOOLS_VIA_MCP` (rollback operacional em <1min)
- **Circuit breaker** (Q-clarify-1=B) que após N falhas consecutivas reverte runtime para as tools nativas (mantidas no código como fallback — não removidas)
- **Paridade comportamental obrigatória** antes do cut-over (100% das suítes da Fase 15 + 17 verdes sob backend MCP)

**Voz** (Q-clarify-4=B): cada Persona ganha `voice_id` (atributo), com catálogo curado pelo super-admin. **Consentimento LGPD áudio** (Q-clarify-2=B): STT/TTS reusam `ConsentFinalidade::Comunicacao` como base de licitude; **retenção prolongada do áudio bruto** exige nova `ConsentFinalidade::Transcricao` (opt-in). **Rate limit** (Q-clarify-5=C): 2 camadas reusando `ApiPublicRateLimiter` da Fase 8 (por conversa + por identificador-tenant), excedido → cooldown auditável.

**Reuso máximo**: nenhuma nova base estrutural de domínio do CRM (funil já existe; pacientes ganham consents novos; mensagens ganham `transcription_id`). Tabelas novas concentradas em IA, áudio e MCP.

## Technical Context

**Language/Version**: PHP 8.5, Laravel 13 (backend via Sail); Vue 3 + Pinia + Tailwind v4 (SPA tenant)
**Primary Dependencies**: já presentes — `laravel/ai` (Agents/Tools, mantido como fallback runtime — FR-052), Sanctum (auth API + credenciais MCP por token), Redis (Horizon + Reverb), Pinia. **Adicionar** — `laravel/mcp` v0 (servidor MCP local; pacote oficial); decisão R5 sobre pacote de circuit breaker. **STT/TTS** — provedores selecionados em R1/R2 (acessados via Laravel `Http`, sem nova lib pesada).
**Storage**: PostgreSQL — **7 tabelas novas** (`audio_transcriptions`, `audio_syntheses`, `voice_catalog`, `persona_test_sessions`, `kanban_pipeline_mappings`, `kanban_curation_events`, `mcp_circuit_breaker_snapshots`) + **5 alterações** em existentes (`ai_personas.voice_id`; `messaging_messages.{transcription_id, is_audio_origin, sandbox, sandbox_session_id}`; `messaging_conversations.{cooldown_until, cooldown_reason}`; `funil_colunas.is_initial` UNIQUE parcial por tenant; `tenant_settings.default_voice_id` OU nova tabela auxiliar `tenant_voice_settings`) + **1 alteração de enum PostgreSQL** (`consent_finalidade` ADD VALUE `transcricao`). **Total: 13 migrations** (10 estruturais + 1 enum + 2 alterações em tabelas existentes — `messaging_conversations.cooldown_*` separada de `messaging_messages.*` por idempotência). Pode haver migration auxiliar `personal_access_tokens.tenant_id` (decidida em T042) — não fundamental, depende da estratégia de tenant em PAT escolhida. Redis — chaves novas para **estado de turno** (`ai:turn:{conversation}:v`), **debounce passivo** (`ai:turn:debounce:{conversation}`), **rate limit** (reusa namespace Fase 8), **circuit breaker** (`mcp:cb:state` + `mcp:cb:failures`).
**Testing**: PHPUnit (feature predominante + unit p/ matcher de áudio/coalescência/circuit breaker/rate limit); **paridade comportamental** = suítes Fase 15+17 rodadas sob `AI_TOOLS_VIA_MCP=true` (gate de cut-over FR-053). Playwright E2E "paciente envia áudio → IA responde texto/áudio" e "chat de teste de Persona". `Http::fake()` para STT/TTS.
**Target Platform**: Linux server (Docker/Sail) + serviço `mcp-server` no compose (mesma rede, mesma DB, mesma Redis). Filas `ai`, `messaging`, `transcription` (nova) no Horizon.
**Project Type**: Web — API REST Laravel (`/api/v1`) + SPA Vue 3 + serviço MCP local (não exposto à internet por default).
**Performance Goals**: coalescência adiciona ≤+4s percebidos no pior caso (debounce + reprocess); resposta da IA com tools via MCP **p95 ≤ 8s** (mantém alvo Fase 17, overhead MCP +500ms aceitável — FR-053a); STT inbound mediana <5s, p95 <10s (SC-004); TTS outbound não pode estourar limite do canal (WA: 16MB de áudio); latência fim-a-fim **p95 ≤12s** incluindo TTS quando aplicável (SC-010).
**Constraints**: nenhum PII bruto no provedor STT/TTS além da fala do paciente (já é o conteúdo da conversa, já consentida); transcrição passa pelo `PiiScrubber` antes de ir ao modelo (FR-055b); guardrails Fase 15 intactos (FR-057); sandbox **incapaz** de criar efeito colateral real (verificado por teste explícito FR-040/041); MCP credenciado por token Sanctum derivado do contexto (sem novo modelo de auth — FR-051 revogação=token revoke).
**Scale/Scope**: 7 user stories (4×P1, 3×P2); 73 FRs; 11 SCs; multi-tenant; benchmark de bursts até 10 mensagens em 10s; ~6 capabilities MCP equivalentes às 6 tools Fase 17.

## Constitution Check

*GATE: avaliado contra os 7 princípios (v1.5.0). Re-check após Phase 1 ao final.*

| Princípio | Veredito | Como é satisfeito / gate |
|---|---|---|
| **I — LGPD (NON-NEG.)** | ✅ PASS | Áudio bruto retido pelo prazo padrão de mídia (Fase 13) com base de licitude `Comunicacao` (FR-055); retenção prolongada requer **novo opt-in `Transcricao`** (FR-055a/c, decisão Q-clarify-2=B). Transcrição passa pelo `PiiScrubber` antes do modelo (FR-055b). Áudio TTS armazenado sob política de outbound Fase 13 (FR-056). Nome NUNCA enviado ao provedor STT/TTS (TTS recebe texto pós-`OutboundNameInjector` apenas — placeholder ainda no modelo). Auditoria de cada decisão de coalescência/kanban/transcrição/sandbox/MCP (FR-054). Suspeita de PII clínica em áudio: guardrails Fase 15 prevalecem (FR-057, edge case). |
| **II — Isolamento Multi-Tenant (NON-NEG.)** | ✅ PASS | Todas as 7 tabelas novas com `tenant_id` + global scope. Auto-criação de lead idempotente por `(tenant_id, identificador)` (FR-012). MCP autentica via credencial token-scoped que carrega tenant — `tenant_id` NUNCA é input do cliente (FR-046/050). Sandbox por `(admin_user_id, persona_id)` (FR-043). Rate limit chave-versionada por tenant. Teste de leitura cruzada obrigatório (SC-007). |
| **III — Segurança Clínica/Auditabilidade IA (NON-NEG.)** | ✅ PASS | Coalescência só atrasa o disparo — não bypassa nenhum guardrail/escala/auto-pause. Sandbox usa exatamente o mesmo motor; intenção/confiança/escalação rodam (FR-040). STT/TTS são pré/pós-processamento da camada de mensageria, não alteram a saída avaliada pelo `AiGuardrailEnforcer`. Tools via MCP têm auditoria equivalente a `ai_tool_invocations` (FR-049). Circuit breaker abre/fecha auditados (FR-053d). |
| **IV — Spec-Driven Test-First** | ✅ PASS | Specs feitas (8 clarificações resolvidas, 0 NEEDS CLARIFICATION). Tests por US (coalescência, lead auto-criar, abastecimento kanban, STT, TTS, sandbox, MCP) + **paridade comportamental** Fase 15+17 como gate de cut-over (FR-053). Suítes do circuit breaker, rate limit cooldown, voice catalog. Migrations aditivas e idempotentes. |
| **V — Observabilidade** | ⚠️ PASS c/ desvio anotado | Métricas Prometheus novas: `ai_coalesce_messages_per_turn`, `ai_coalesce_reprocess_count`, `ai_mcp_request_duration`, `ai_mcp_circuit_state` (gauge 0/1), `ai_stt_duration_seconds`, `ai_tts_duration_seconds`, `ai_rate_limit_cooldown_active`, `ai_kanban_curation_events_total`. Logs estruturados em todos os caminhos novos. **Desvio**: target "resposta IA ≤5s" do Princípio V vira **p95 ≤12s** com coalescência+TTS (SC-010); refinamento de target, não remoção de gate. Já há precedente (Fase 17 com tools=≤8s). Registrado em Complexity Tracking. |
| **VI — Conformidade Meta (NON-NEG.)** | ✅ PASS | STT é processamento técnico de áudio inbound (já é o conteúdo da conversa que o paciente iniciou). TTS é resposta dentro da janela 24h da conversa em curso (não é disparo proativo). Dispatcher Fase 13 e seus gates de template/opt-in **não mudam**. Auto-criação de lead e mutação de kanban são internas, não enviam nada por canal externo. |
| **VII — Segurança Operacional (NON-NEG.)** | ✅ PASS | Credencial MCP usa Sanctum PAT com ability scope (`mcp.invoke`), tenant derivado do token (FR-046). Revogação = `Token::delete()` (FR-051) — efeito imediato. Rate limit 2 camadas reusa `RateLimiter::for('messaging:inbound:per-conversation')` registrado por `RouteServiceProvider` (FR-008a/b). Sandbox de teste só para usuários com permission `ai.persona.test` (FR-044). Audit log de suspeita de uso de token MCP herda da Fase 4 (Princípio VII v1.4.0). |

**Resultado**: 7/7 PASS (1 desvio de **target de métrica** documentado, sem amendment). Re-check pós-design ao final: inalterado.

## Project Structure

### Documentation (this feature)

```text
specs/018-ai-multimodal-mcp/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — STT/TTS, laravel-mcp v0, circuit breaker, sandbox, coalescência
├── data-model.md        # Phase 1 — 7 tabelas novas + alterações
├── quickstart.md        # Phase 1 — provisionamento, env, gates de paridade
├── contracts/
│   ├── persona-test-chat.api.md      # REST do chat sandbox (US6)
│   ├── voice-catalog.api.md          # CRUD do catálogo de vozes + admin Persona
│   ├── kanban-pipeline-config.api.md # CRUD do mapping evento→status do tenant (US3)
│   ├── mcp-capabilities.contract.md  # Schema das 6 capabilities + auth model
│   └── consent-transcricao.api.md    # extensão do CRUD de consentimentos com nova finalidade
└── checklists/
    └── requirements.md  # (existente, post-clarify)
```

### Source Code (repository root)

```text
app/
├── Domain/
│   ├── Ai/
│   │   ├── Coalescing/                            # NOVO submódulo
│   │   │   ├── Services/
│   │   │   │   ├── ConversationTurnCoordinator.php      # NOVO — gerencia versão do turno + locks (FR-001..008)
│   │   │   │   ├── PassiveDebounceScheduler.php          # NOVO — agenda flush após 3-4s sem nova msg
│   │   │   │   └── TurnVersionGuard.php                  # NOVO — usado pelo ProcessAiResponseJob (cancel-and-reprocess)
│   │   │   └── Events/
│   │   │       ├── TurnCoalesced.php                     # NOVO — auditável (mensagens N, reprocessos M, motivo)
│   │   │       └── TurnDispatched.php                    # NOVO
│   │   ├── Mcp/                                  # NOVO — servidor MCP local (laravel-mcp v0)
│   │   │   ├── Server/
│   │   │   │   ├── McpServerProvider.php                 # NOVO — registra capabilities no servidor
│   │   │   │   └── Auth/McpTokenGuard.php                # NOVO — Sanctum token + ability mcp.invoke + tenant scope
│   │   │   ├── Capabilities/                              # 6 capabilities equivalentes às tools Fase 17
│   │   │   │   ├── GetClinicInfoCapability.php
│   │   │   │   ├── ListProfessionalsCapability.php
│   │   │   │   ├── GetAvailabilityCapability.php
│   │   │   │   ├── GetCurrentPatientCapability.php
│   │   │   │   ├── CreateOrFindLeadCapability.php
│   │   │   │   └── HoldSlotCapability.php
│   │   │   ├── Client/
│   │   │   │   ├── McpToolBridge.php                      # NOVO — adapta `laravel/ai` Tool ↔ MCP capability
│   │   │   │   └── McpCallLogger.php                      # NOVO — auditoria (substitui ToolInvocationLogger no caminho MCP)
│   │   │   ├── CircuitBreaker/
│   │   │   │   ├── McpCircuitBreaker.php                  # NOVO — Redis-backed, FR-053b/c/d
│   │   │   │   └── Events/{McpCircuitOpened,Closed}.php   # NOVO — auditáveis
│   │   │   └── Sandbox/
│   │   │       ├── SandboxContext.php                     # NOVO — propaga sandbox=true via metadata da credencial
│   │   │       └── SandboxNeutralizer.php                 # NOVO — neutraliza escrita real em capabilities (FR-041)
│   │   ├── Persona/
│   │   │   └── Services/PersonaTestSessionService.php     # NOVO — chat sandbox isolado (US6)
│   │   ├── Tools/                                # MODIFICA: tools nativas mantidas como fallback runtime (FR-052)
│   │   │   └── Support/ToolRunner.php                     # MODIFICA: se flag ON → bridge MCP; se OFF/CB aberto → nativa
│   │   └── Voice/                                # NOVO submódulo
│   │       ├── Models/VoiceCatalogEntry.php
│   │       └── Services/PersonaVoiceResolverService.php   # default tenant + override Persona
├── Domain/
│   └── Messaging/
│       ├── Audio/                                # NOVO submódulo
│       │   ├── Inbound/Services/
│       │   │   ├── AudioTranscriptionService.php        # NOVO — orquestra STT (US4)
│       │   │   ├── AudioTranscriptionProvider.php       # interface (Whisper/Google/Azure — R1)
│       │   │   └── AudioPreferenceDetector.php          # NOVO — matcher de gatilhos PT-BR (Q3=A)
│       │   ├── Outbound/Services/
│       │   │   ├── AudioSynthesisService.php           # NOVO — orquestra TTS (US5)
│       │   │   ├── AudioSynthesisProvider.php           # interface (R2)
│       │   │   └── TtsTextNormalizer.php                # NOVO — preços/horários/datas → fala (FR-035)
│       │   └── Models/
│       │       ├── AudioTranscription.php               # NOVO (audio_transcriptions)
│       │       └── AudioSynthesis.php                   # NOVO (audio_syntheses)
│       ├── RateLimiting/
│       │   ├── InboundConversationLimiter.php          # NOVO — FR-008a/b
│       │   └── CooldownService.php                      # NOVO — pausa IA + alerta operador
│       └── Channel/Adapters/
│           ├── WhatsAppCloudAdapter.php                # MODIFICA: download de áudio inbound + upload de áudio outbound
│           ├── EvolutionApiAdapter.php                  # MODIFICA: idem
│           └── InstagramGraphAdapter.php                # MODIFICA: idem (Instagram Direct)
├── Domain/Crm/
│   └── Kanban/                                   # NOVO submódulo (lógica + auditoria de mutação)
│       ├── Services/
│       │   ├── LeadOnboardingService.php                # NOVO — FR-009/011 (cria lead + insere no funil)
│       │   ├── KanbanPipelineMappingService.php         # NOVO — get default + per-tenant (FR-019)
│       │   ├── KanbanAutoTransitionService.php          # NOVO — aplica regras evento→status (FR-018/020)
│       │   └── KanbanCurationService.php                # NOVO — atualiza nome/observações via tool (FR-016/017)
│       ├── Models/
│       │   ├── KanbanPipelineMapping.php
│       │   └── KanbanCurationEvent.php
│       └── Listeners/
│           ├── EnqueueLeadOnInboundMessageListener.php  # NOVO — escuta InboundMessageReceived
│           ├── PromoteToScheduledOnHoldPlaced.php
│           ├── PromoteToConfirmedOnReservationPaid.php
│           ├── DowngradeToLostOnInactivityListener.php
│           └── PromoteToHumanOnEscalation.php
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Ai/Personas/PersonaTestSessionController.php  # NOVO — POST /personas/{id}/test/sessions (US6)
│   │   ├── Ai/Voices/VoiceCatalogController.php          # NOVO — GET catálogo
│   │   ├── Kanban/KanbanPipelineMappingController.php    # NOVO — GET/PUT mapping
│   │   └── Pacientes/ConsentsController.php              # MODIFICA — aceita finalidade Transcricao
│   └── Resources/Ai/PersonaResource.php                  # MODIFICA — inclui voice_id
config/
├── ai.php                                                # MODIFICA — coalesce.{passive_debounce_s, max_turn_s, max_reprocesses}; mcp.{enabled, circuit_breaker}
├── messaging.php                                         # MODIFICA — audio.stt + audio.tts providers + rate_limit
└── voice-catalog.php                                     # NOVO — catálogo seedeado pelo super-admin
database/migrations/                                       # NOVO
├── ..._create_audio_transcriptions_table.php
├── ..._create_audio_syntheses_table.php
├── ..._create_voice_catalog_table.php
├── ..._create_persona_test_sessions_table.php
├── ..._create_kanban_pipeline_mappings_table.php
├── ..._create_kanban_curation_events_table.php
├── ..._create_mcp_circuit_breaker_state_table.php        # snapshot p/ analytics; estado vivo é Redis
├── ..._add_voice_id_to_ai_personas.php
├── ..._add_transcription_columns_to_messaging_messages.php
└── ..._add_transcricao_to_consent_finalidade_enum.php
resources/js/
├── pages/Ia/
│   ├── PersonasIndexPage.vue                              # MODIFICA — botão "Testar" + abrir modal
│   └── PersonaTestChatModal.vue                           # NOVO — sandbox chat (US6)
├── pages/Kanban/
│   └── KanbanPipelineConfigPage.vue                       # NOVO — config mapping evento→status
├── components/Pacientes/ConsentTranscricaoToggle.vue      # NOVO — finalidade Transcricao no fluxo de consentimentos
└── stores/ia/
    ├── personaTest.js                                     # NOVO
    └── voiceCatalog.js                                    # NOVO
routes/api.php                                              # MODIFICA — novas rotas
docker/
└── compose.yaml                                           # MODIFICA — serviço opcional `mcp-server` (profile: mcp)
tests/
├── Feature/Ai/
│   ├── Coalescing/{TurnCoalescenceTest,CancelAndReprocessTest}.php
│   ├── Mcp/{ParityWithNativeToolsTest,CircuitBreakerTest,SandboxNeutralizationTest}.php
│   └── Personas/PersonaTestChatTest.php
├── Feature/Messaging/
│   ├── Audio/{InboundTranscriptionTest,OutboundSynthesisTest}.php
│   └── RateLimiting/InboundCooldownTest.php
├── Feature/Crm/Kanban/
│   ├── LeadOnboardingTest.php                              # FR-009..015 (inclui Q-clarify-3=B)
│   └── AutoTransitionTest.php                              # FR-018/020
├── Unit/Ai/Coalescing/{TurnVersionGuardTest,PassiveDebounceTest}.php
├── Unit/Messaging/Audio/{AudioPreferenceDetectorTest,TtsTextNormalizerTest}.php
└── e2e/{ai-multimodal-conversation,persona-test-chat}.spec.ts
```

**Structure Decision**: estende a arquitetura por domínio existente:
- `app/Domain/Ai/{Coalescing,Mcp,Voice}` para os novos submódulos da IA
- `app/Domain/Messaging/{Audio,RateLimiting}` para multimodalidade e proteção
- `app/Domain/Crm/Kanban/` para abstrair a lógica de funil que hoje vive em `app/Services/Funil/` e `Paciente.funil_coluna_atual_id` (continua usando os models, só centraliza a regra de mutação automática)
- Tools nativas da Fase 17 permanecem em `app/Domain/Ai/Tools/`; `ToolRunner` decide MCP vs nativa em runtime (flag + circuit breaker)
- Pipeline Constitucional `Form Request → Controller → Service → Resource` em todos os endpoints novos
- Serviço Docker `mcp-server` no profile `mcp` (não sobe por default em dev simples)

## Complexity Tracking

| Desvio | Por que é necessário | Alternativa mais simples rejeitada porque |
|---|---|---|
| Target de latência fim-a-fim **p95 ≤ 12s** (vs. "≤5s" do Princípio V) | Coalescência híbrida adiciona ≤+4s percebidos no pior caso (debounce + reprocess); TTS sob gatilho explícito adiciona ~2-3s de geração. Sob bursts é trade-off explícito de Q1=C para conversação humana real (canal assíncrono — WhatsApp). | Forçar ≤5s exigiria remover coalescência (mantém "atropelamento") ou remover TTS (mantém acessibilidade quebrada). Ambas regridem a feature. Target ≤8s é mantido para o caminho **sem coalescência e sem TTS** (resposta direta). É refino de **target de métrica**, não remoção de gate → sem amendment (mesmo precedente da Fase 17). |
| **Substituição** Q2=B do `laravel/ai` por MCP em produção | Decisão do owner para unificar implementação de tools (zero drift entre produção e sandbox de teste) e habilitar reuso por integrações externas (Claude Desktop). | A opção A (aditiva, recomendada) deixaria o MCP só na sandbox — duas implementações para manter; drift garantido com o tempo. A substituição é mitigada por: (1) feature flag `AI_TOOLS_VIA_MCP`, (2) circuit breaker auto-revert (Q-clarify-1=B), (3) paridade verificada antes do cut-over, (4) métrica de overhead +500ms p95 monitorada, (5) cut-over por etapas. |
| **Manter** tools nativas `laravel/ai` no código após cut-over (não remover) | FR-052 ajustado por Q-clarify-1=B — circuit breaker precisa do fallback runtime; remover as nativas eliminaria a rede de segurança em produção. | Removê-las significaria que falha do MCP = falha da IA = atendimento parado. Inaceitável. O custo de manter ~6 classes Tool de baixa complexidade é trivial frente ao ganho de resiliência. |
| **7 tabelas novas** + alterações em existentes | Áudio (transcription/synthesis), catálogo de vozes (curado pelo super-admin), sandbox sessions, mapping evento→status por tenant, audit de auto-curadoria, snapshot de circuit breaker. Cada uma tem dado de domínio próprio + auditoria/retenção próprias. | Reusar tabelas existentes (ex: `messaging_messages` para guardar transcrição metadata) misturaria preocupações e dificultaria queries de qualidade de STT/TTS. Coloca custo de manutenção de uma migration aditiva contra ganho de modelo claro — vence o modelo claro. |
| Novo enum value `ConsentFinalidade::Transcricao` | LGPD Q-clarify-2=B — retenção prolongada de áudio bruto é finalidade distinta da Comunicacao. | Reusar Comunicacao para tudo cruzaria os limites do consentimento original (paciente não consentiu que sua voz fosse arquivada por meses). Risco regulatório material. |
