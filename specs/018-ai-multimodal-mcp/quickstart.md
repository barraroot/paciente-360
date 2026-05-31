# Quickstart — Fase 18 — Conversa Reativa, Multimodal e MCP

Roteiro de provisionamento, configuração e validação. Assume Fase 17 já mergeada e funcional.

---

## 1. Pré-requisitos

- Suíte atual verde: `vendor/bin/sail artisan test --compact` ≥ 1700/0 (Fase 17 mergeada).
- Migrations Fase 17 aplicadas (`ai_work_contexts`, `ai_conversation_summaries`, `ai_tool_invocations`).
- Redis up (já é stack).
- Conta de provedor STT (OpenAI Whisper) — chave válida.
- Conta de provedor TTS (ElevenLabs) — chave válida.
- (Opcional dev) Docker profile `mcp` disponível.

---

## 2. Dependências novas

```bash
vendor/bin/sail composer require laravel/mcp:^0.x
```

`config/mcp.php` é publicado:

```bash
vendor/bin/sail artisan vendor:publish --provider="Laravel\Mcp\McpServiceProvider"
```

(Pacote `laravel/mcp` v0 já está listado em CLAUDE.md como `laravel/mcp (MCP) - v0` — confirmar instalação real durante a Phase 2.)

Sem novas libs Composer para STT/TTS — usam `Http::` nativo.

---

## 3. Environment

`.env` (adições):

```
# Coalescência
AI_COALESCE_PASSIVE_DEBOUNCE_S=4
AI_COALESCE_MAX_TURN_S=30
AI_COALESCE_MAX_REPROCESSES=3

# MCP server local
AI_TOOLS_VIA_MCP=false                # default no merge; ativar só após paridade verificada
MCP_LOCAL_URL=http://mcp-server:8090
MCP_CIRCUIT_BREAKER_FAILURE_THRESHOLD=3
MCP_CIRCUIT_BREAKER_FAILURE_WINDOW_S=30
MCP_CIRCUIT_BREAKER_INITIAL_COOLDOWN_S=60
MCP_CIRCUIT_BREAKER_MAX_COOLDOWN_S=600

# STT — OpenAI Whisper
MESSAGING_STT_PROVIDER=openai_whisper
MESSAGING_STT_OPENAI_API_KEY=sk-...
MESSAGING_STT_TIMEOUT_S=15
MESSAGING_STT_MAX_DURATION_S=120

# TTS — ElevenLabs
MESSAGING_TTS_PROVIDER=elevenlabs
MESSAGING_TTS_ELEVENLABS_API_KEY=eleven-...
MESSAGING_TTS_MAX_TEXT_LENGTH=2000
MESSAGING_TTS_ENABLED=true            # tenant pode override em runtime

# Rate limit (defaults; tenant override em config DB futura)
MESSAGING_RATE_PER_CONVERSATION=30
MESSAGING_RATE_PER_IDENTIFIER=100
MESSAGING_RATE_WINDOW_MINUTES=10
MESSAGING_COOLDOWN_MINUTES=15
```

---

## 4. Migrations

10 migrations novas (ordem cronológica em `database/migrations/2026_06_*`):

```bash
vendor/bin/sail artisan migrate
```

Ordem importa para FKs (voice_catalog antes de ai_personas; persona_test_sessions antes de messaging_messages.sandbox_session_id).

Migration do enum `transcricao` é a única **não-reversível** (PostgreSQL não suporta `DROP VALUE`); documentado no docblock.

---

## 5. Seeds

```bash
vendor/bin/sail artisan db:seed --class=VoiceCatalogSeeder
vendor/bin/sail artisan db:seed --class=DefaultKanbanPipelineMappingSeeder
```

- `VoiceCatalogSeeder`: ~4 vozes ElevenLabs PT-BR.
- `DefaultKanbanPipelineMappingSeeder`: itera tenants existentes, idempotente; cria mappings padrão se ausentes.

---

## 6. Validação por user story

### US1 — Coalescência híbrida

```bash
vendor/bin/sail artisan test --compact --filter=TurnCoalescenceTest
vendor/bin/sail artisan test --compact --filter=CancelAndReprocessTest
```

Manual (em conversa real ou via Tinker):
1. Em uma conversa-teste, enviar 3 mensagens com intervalo de 2s entre elas.
2. Verificar via `Cache::store('redis')->get('ai:turn:{conv}:msgs')` que as 3 IDs estão na lista.
3. Verificar log estruturado `turn.coalesced` com `messages=3, reprocesses=0, flush_reason=passive_debounce_elapsed`.
4. Confirmar **1** outbound dispatch (não 3).

### US2 — Auto-criação de lead

```bash
vendor/bin/sail artisan test --compact --filter=LeadOnboardingTest
```

Manual:
1. Enviar webhook fake do WhatsApp com número que não existe.
2. Verificar `pacientes` ganhou linha com `status=lead`, `funil_coluna_atual_id` = coluna inicial.
3. Repetir com mesmo número → confirmar idempotência (1 linha só).
4. Repetir com handle Instagram → confirmar entrada por handle.
5. Enviar com número de paciente já existente (não-lead) → confirmar **sem** card no kanban (Q-clarify-3=B).

### US3 — Auto-curadoria do kanban

```bash
vendor/bin/sail artisan test --compact --filter=AutoTransitionTest
```

Manual: simular conversa que chega a `hold-slot` (via tool MCP) → verificar `paciente.funil_coluna_atual_id` virou "agendado" + linha em `kanban_curation_events`.

### US4 — STT

```bash
vendor/bin/sail artisan test --compact --filter=InboundTranscriptionTest
```

Manual:
1. Webhook WhatsApp com `media_url` apontando para um áudio PT-BR de teste.
2. Verificar `audio_transcriptions` ganhou linha.
3. Verificar `messaging_messages.content` é a transcrição, `is_audio_origin=true`, `transcription_id` setado.
4. Verificar resposta da IA em texto coerente com o áudio.

### US5 — TTS

```bash
vendor/bin/sail artisan test --compact --filter=OutboundSynthesisTest
```

Manual:
1. Conversa em que paciente diz "não sei ler, manda áudio".
2. Verificar log `audio_preference.detected=true`.
3. Verificar `audio_syntheses` ganhou linha + `messaging_messages` outbound com `media_id` para o áudio.
4. Forçar falha do TTS (chave inválida) → verificar `fallback_to_text=true` em `audio_syntheses` + mensagem texto enviada normalmente.

### US6 — Persona Test Chat

```bash
vendor/bin/sail artisan test --compact --filter=PersonaTestChatTest
```

Manual (browser):
1. `/panel/ia/personas` → clicar "Testar" em uma persona.
2. Modal abre; digitar "oi" + enviar.
3. IA responde no modal (via Reverb).
4. Verificar `persona_test_sessions` com sessão `open`.
5. Tentar verificar `pacientes` real — confirmar **0 leads sandbox** criados.
6. Tentar verificar `slot_reservations` — confirmar **0 holds sandbox** criados.
7. Fechar modal → token MCP da sessão é revogado (verificar em `personal_access_tokens`).

### US7 — Servidor MCP + circuit breaker

```bash
vendor/bin/sail artisan test --compact --filter=ParityWithNativeToolsTest
vendor/bin/sail artisan test --compact --filter=CircuitBreakerTest
vendor/bin/sail artisan test --compact --filter=SandboxNeutralizationTest
```

#### Validação de paridade comportamental (gate FR-053)

```bash
AI_TOOLS_VIA_MCP=true vendor/bin/sail artisan test --compact --testsuite=Feature
```

Esperado: mesmas asserções da Fase 15 + Fase 17 verdes (paridade 100%). Se algo falhar, **NÃO mover o flag em produção**.

#### Cut-over em produção (sequência recomendada)

1. Deploy com `AI_TOOLS_VIA_MCP=false` (default). MCP server sobe mas não recebe tráfego de IA de produção (só sandbox de teste).
2. Admins testam Persona via US6 — exercita o MCP por dias/semanas em load real (porém isolado).
3. Métricas: `ai_mcp_request_duration_seconds` p95 < 500ms; `ai_mcp_request_total{outcome="error"}` baixo; circuit breaker fica `closed` por janela contínua.
4. Suíte de paridade roda em CI noturno com flag `=true` para detectar drift.
5. Promote `AI_TOOLS_VIA_MCP=true` em horário de baixa carga.
6. Monitorar 24-48h:
   - `ai_mcp_circuit_state` — não pode abrir (gauge=2) sob carga normal.
   - Latência fim-a-fim p95 ≤ 8s (FR-053a).
   - Asserções dos guardrails Fase 15 verdes na suíte automática.
7. Se circuit breaker abrir: revisar logs, alerta Sentry para o time. O fallback runtime para tools nativas já está ativo (FR-053b) — atendimento NÃO para.
8. Se necessário rollback: `AI_TOOLS_VIA_MCP=false` (efeito em <1min via cache config).

---

## 7. Permissions (Spatie)

Adicionar via `PermissionSeeder` ou migration:

```php
Permission::firstOrCreate(['name' => 'ai.persona.test', 'guard_name' => 'web']);
// 'funil.manage' já existe (Fase 2)
```

Atribuir a roles:
- `ai.persona.test` → role "Admin Clínica" (default).

---

## 8. Smoke E2E

Playwright:

```bash
vendor/bin/sail npx playwright test ai-multimodal-conversation.spec.ts persona-test-chat.spec.ts
```

Cenário "multimodal-conversation":
- Paciente novo envia áudio → IA transcreve, responde em texto, card surge no kanban.
- Paciente diz "não sei ler" → próxima resposta vem como áudio.
- Paciente pede horário, IA propõe via tool, IA fecha hold → card vai para "agendado".

Cenário "persona-test-chat":
- Admin loga, abre Persona, clica Testar, conversa 5 turnos, fecha. Verifica zero side effects.

---

## 9. Métricas Prometheus

Adicionar ao scrape config existente (já temos endpoint `/metrics`):

```yaml
- ai_coalesce_messages_per_turn         (histogram)
- ai_coalesce_reprocess_count           (histogram)
- ai_coalesce_flush_reason_total        (counter, label: reason)
- ai_mcp_request_duration_seconds       (histogram, labels: capability, outcome)
- ai_mcp_circuit_state                  (gauge — 0/1/2)
- ai_mcp_circuit_transitions_total      (counter, labels: to, source)
- ai_stt_duration_seconds               (histogram, labels: provider, outcome)
- ai_tts_duration_seconds               (histogram, labels: provider, outcome)
- ai_tts_fallback_to_text_total         (counter)
- ai_rate_limit_cooldown_active_total   (counter)
- ai_kanban_curation_events_total       (counter, labels: source, event_kind)
```

Adicionar painéis Grafana correspondentes (deferred ao /speckit-tasks).

---

## 10. Validação final (Definition of Done)

- [ ] Migrations aplicadas (10 novas + 1 enum); idempotentes.
- [ ] Seeds executados (`VoiceCatalogSeeder`, `DefaultKanbanPipelineMappingSeeder`).
- [ ] Permission `ai.persona.test` criada e atribuída.
- [ ] Suíte completa verde (1800+ tests esperados).
- [ ] Suíte com `AI_TOOLS_VIA_MCP=true` também verde (paridade FR-053).
- [ ] Métricas novas expostas em `/metrics`.
- [ ] Pelo menos 1 smoke real com áudio inbound + áudio outbound em ambiente de staging.
- [ ] Cut-over plan revisado (sequência acima).
- [ ] `AI_TOOLS_VIA_MCP=false` no merge para `main` (cut-over é decisão posterior).
