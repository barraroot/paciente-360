# Quickstart — Humanização da Conversa da IA

Como configurar e validar a feature. Tudo via Sail.

## 1. Pré-requisitos

- Fase 15 (IA Matricial) operante: persona ativa no canal, `ai_models` semeado, fila `ai` no Horizon.
- Um canal WhatsApp ativo (Twilio ou Evolution) com uma conversa de teste.

## 2. Migrations e config

```bash
vendor/bin/sail artisan migrate            # cria ai_work_contexts, ai_conversation_summaries, ai_tool_invocations
```

Novos envs (defaults sensatos em `config/ai.php`):

```env
# Histórico mínimo (FR-002/SC-010)
AI_HISTORY_WINDOW_MESSAGES=6        # ~3 turnos verbatim
AI_HISTORY_INPUT_TOKEN_CEILING=6000 # teto do contexto montado (FR-021)
AI_SUMMARY_MAX_TOKENS=400           # tamanho-alvo do resumo rolante
# Tools (US5)
AI_TOOLS_MAX_ROUND_TRIPS=3          # cap de round-trips por resposta (FR-032/SC-008)
AI_TOOLS_ENABLED=true
```

## 3. Configurar o "contexto de trabalho" da clínica (US2)

Na SPA: **Painel → IA → Contexto de Trabalho** (`/panel/ia/contexto-trabalho`). Preencha os campos híbridos (serviços, valores, locais, política de sinal, tom, perguntas de qualificação + texto livre). Salvar = `PUT /api/v1/ai/work-context`.

Exemplo (dados das conversas de referência): consulta enxaqueca/cefaleia, R$300 à vista / R$330 cartão, Aracaju + Itabaiana, sinal de 20% via PIX, tom acolhedor com emojis, 3 perguntas de qualificação.

## 4. Validar humanização + contexto (US1)

Numa conversa de teste, envie em turnos:
1. "Enxaqueca" → IA acolhe + 1ª pergunta de qualificação.
2. "Quase todo dia" → IA **não** re-pergunta a queixa; aprofunda.
3. "Atrapalha sim" → IA constrói valor (texto livre/diferenciais).
4. "Qual o valor?" → IA quota preço **depois** do valor (FR-015), usando o work context.

Confirme: sem repetição de perguntas (SC-001), coerência após gap, tom configurado.

## 5. Validar dados ao vivo + ações reversíveis (US5)

5. "Tem horário quinta?" → IA chama `get-availability` e oferece **slots reais**.
6. "Pode ser 15h" → IA chama `create-or-find-lead` (cria `pacientes status='lead'`) + `hold-slot` (`slot_reservations holder_type='ia'`), e **encaminha** confirmação/sinal (handoff — não pede PIX).

Verifique no banco:
```bash
vendor/bin/sail artisan tinker --execute 'echo \App\Models\Paciente::where("status","lead")->count();'
```
E a auditoria: `ai_tool_invocations` e `ai_execution_logs.tools_used`.

## 6. Validar economia/perf (US3)

```bash
# Conversa longa (40 msgs) — checar teto de tokens e reuso de resumo
vendor/bin/sail artisan test --compact --filter=AiContextBudgetTest
vendor/bin/sail artisan test --compact --filter=ConversationSummarizerTest
```
Confirme: input tokens dentro do teto (SC-003), resumo reusado sem re-sumarizar a cada turno (FR-022), payload de histórico ~constante (SC-010).

## 7. Garantir que segurança não regrediu

```bash
vendor/bin/sail artisan test --compact --filter=Ai   # inclui guardrails/escala (SC-006) + isolamento tenant/paciente (SC-007)
vendor/bin/sail bin pint --dirty --format agent
```

## 8. E2E (jornada crítica — Constituição IV)

```bash
vendor/bin/sail npm run test:e2e -- ai-scheduling-conversation
```
Cobre greeting → qualificação → valor → preço → horário → lead + hold + handoff.

## Pontos de atenção

- **Nenhum PII bruto ao provedor**: janela e resumo são pseudonimizados; o nome real só entra na saída via `{{primeiro_nome}}` (FR-017). Há teste de gate verificando o payload.
- **Precedência (FR-011)**: dado vivo (tools) > work context > persona/RAG. Se a clínica não tem o dado no DB, a IA usa o work context; nunca inventa.
- **Tools são read + escrita reversível**: confirmação de consulta e pagamento são sempre handoff.
