# Runbook — Cut-over do servidor MCP (Fase 18 — US7)

> **T216 (Fase 18 — Polish, FR-053a..d)** — sequência operacional para promover o servidor MCP local a caminho de tools de produção (`AI_TOOLS_VIA_MCP=true`) sob a estratégia **Q2=B (substituição)** com **circuit breaker auto-revert** e tools nativas mantidas como fallback runtime (FR-052/053b).

---

## 0. TL;DR — quando rodar este runbook

Você está aqui porque:

- A Fase 18 acaba de fazer merge em `main` com `AI_TOOLS_VIA_MCP=false` (default).
- O servidor MCP (`mcp-server` no compose) está rodando mas só recebe tráfego do **chat de teste de Persona (US6)** — produção segue usando as tools nativas da Fase 17.
- Você quer promover o flag a `true` em produção.

**Antes de tudo, leia §1 — gates obrigatórios. NÃO promova flag se algum gate falhar.**

---

## 1. Gates de cut-over (todos devem passar)

| Gate | Como verificar | Bloqueante? |
|---|---|---|
| **G1 — Suíte completa verde** | `vendor/bin/sail artisan test --compact` em CI | SIM |
| **G2 — Paridade comportamental (FR-053)** | `AI_TOOLS_VIA_MCP=true vendor/bin/sail artisan test --compact --testsuite=Feature --group=parity-gate` em CI noturno por **≥7 noites consecutivas** sem falhas | SIM |
| **G3 — SLA bench** | `T222a — Phase18BenchmarkSlaTest` (`@group=sla-benchmark`) executado contra ambiente staging com carga simulada — p95 ≤ 12s fim-a-fim, burst de 1000 msg em 60s aciona cooldown sem travar | SIM |
| **G4 — MCP infra estável em sandbox** | Painel Grafana `paciente360-fase18-overview`: por **≥14 dias contínuos** circuit `closed` (gauge=0), p95 MCP < 500ms, erros < 0.1% | SIM |
| **G5 — Rate limit + cooldown em produção** | Permission `messaging.cooldown.manage` atribuída a admin-clinica/atendente, badge funcional no inbox, alarmes de cooldown ativo no Grafana | SIM (gate de produção em si) |

**Se qualquer gate falhar — não prossiga.** Reporte no canal `#paciente360-ops` com link do dashboard ou do test report.

---

## 2. Pré-condições operacionais

```bash
# Confirmar que MCP está rodando
docker compose ps mcp-server
# Esperado: STATUS = Up (healthy)

# Confirmar versão deployed
git rev-parse HEAD
git log --oneline -5

# Validar que circuit breaker está em closed
redis-cli -h redis GET mcp:cb:state
# Esperado: vazio OU "closed"

# Confirmar flag atual
vendor/bin/sail artisan tinker --execute "echo config('ai.matricial.mcp.enabled') ? 'ON' : 'OFF';"
# Esperado: OFF (default no cut-over)
```

---

## 3. Janela de execução

- **Dia da semana**: terça a quinta (evita weekend para resposta rápida a incidentes).
- **Horário**: 03:00–05:00 BRT (baixa carga; ~70% menos inbound vs 14:00–18:00 conforme métricas de inbox).
- **Janela de monitoramento**: 48h após flip.
- **Comunique**: `#paciente360-ops` ao iniciar e ao final de cada fase abaixo.

---

## 4. Sequência de cut-over

### Etapa 4.1 — Smoke MCP via sandbox (último gate manual)

Mesmo após CI verde, faça um smoke real:

1. Logue como admin de clínica em ambiente staging.
2. Vá em **Personas → [qualquer persona ativa] → Editar → Testar persona em edição**.
3. Envie 5 mensagens simulando paciente novo (pedindo agendamento, faixa de preço, etc.).
4. Confirme no painel Grafana:
   - `ai_mcp_request_total{outcome="success", source="sandbox"}` incrementou.
   - `ai_mcp_request_duration_seconds` p95 (fenced ao último 5min) < 500ms.
   - `ai_mcp_circuit_state` = 0 (closed).
5. Verifique em `audit_logs` que `ai_tool_invocations` tem linhas com `source='mcp'` e `sandbox=true`.

**Se algo aqui falhar — pare. Investigue.** Não promova.

### Etapa 4.2 — Promover o flag

```bash
# Em produção
export AI_TOOLS_VIA_MCP=true

# Aplicar imediatamente (Laravel lê de cache):
vendor/bin/sail artisan config:cache
# OU restart workers para garantir relê do .env:
vendor/bin/sail artisan horizon:terminate
# (Horizon respawn automaticamente lê config atualizada)
```

**Efeito imediato**:

- `ToolRunner::shouldUseMcp()` passa a retornar `true` quando `circuit=closed`.
- Próximas chamadas de tool da IA roteiam via `McpToolBridge` → `mcp-server`.
- Em caso de falha (timeout/5xx/connection refused), `ToolRunner` cai para tool nativa runtime (FR-053b) — atendimento NÃO para.

### Etapa 4.3 — Monitoramento 48h ativo

Painel Grafana `paciente360-fase18-overview` deve mostrar **continuamente**:

- **Circuit state** = 0 (closed). Pico transiente para 1 (half_open) é tolerável; sustentado em 2 (open) = problema.
- **Latência p95 MCP** < 500ms por capability.
- **Taxa de erro MCP** < 1%.
- **ai_mcp_circuit_transitions_total{to="open"}** = 0 por hora (a primeira transição open dispara alerta — ver §5).

Alarmes Prometheus configurados em `docs/observability/prometheus/fase18-alerts.yml`:

- `ai_mcp_circuit_state > 0 for 5m` → **page** ao oncall.
- `histogram_quantile(0.95, ai_mcp_request_duration_seconds) > 1.0 for 10m` → **warn** ao time.

Cheque também:

- **Latência fim-a-fim** (`ai_response_duration_seconds` da Fase 17) — p95 ≤ 12s (FR-053a target, sem amendment constitucional).
- **Métricas de IA já existentes** (escalações, auto-pause) — sem regressão.

### Etapa 4.4 — Sinalizar sucesso (após 48h)

Se métricas estiverem dentro do esperado:

1. Postar resumo no `#paciente360-ops` com link para o snapshot do Grafana.
2. Atualizar `specs/018-ai-multimodal-mcp/feature.json` → `"production_cutover_at": "<data>"`.
3. Encerrar o monitoramento ativo (mantém alarmes automáticos).

---

## 5. Critério e procedimento de ROLLBACK

### Quando rollback

- **Imediato (sem deliberação)**:
  - Circuit breaker abre 3× em 30min (cascata indicando MCP fundamentalmente quebrado).
  - Latência fim-a-fim p95 > 20s sustentado por 5min.
  - Aumento de >50% em escalações para humano vs baseline pré-cutover (suggere quebra de tool calling).

- **Deliberado (com consulta ao tech lead)**:
  - Circuit abre 1× e fecha; investigação aponta problema sistêmico (não transiente).
  - Latência p95 entre 8s e 20s sustentada por 30min.

### Procedimento de rollback

```bash
# 1. Revert do flag
export AI_TOOLS_VIA_MCP=false
vendor/bin/sail artisan config:cache
vendor/bin/sail artisan horizon:terminate

# 2. (Opcional, anula o flag mesmo se config:cache não pegou) — marca CB manual
vendor/bin/sail artisan tinker --execute '
$cb = app(\App\Domain\Ai\Mcp\CircuitBreaker\McpCircuitBreaker::class);
$cb->recordManualRollback(actorUserId: 1); // ID do super-admin executando
'
# Isso emite McpCircuitOpened(source=manual_flag) → snapshot auditável.

# 3. Confirmar
vendor/bin/sail artisan tinker --execute "echo config('ai.matricial.mcp.enabled') ? 'ON' : 'OFF';"
# Esperado: OFF
```

**Efeito imediato**:

- Próximas chamadas de tool usam tools nativas diretamente (FR-052).
- MCP server continua rodando (sandbox de teste segue funcional).
- Snapshot `mcp_circuit_breaker_snapshots(source='manual_flag', actor_user_id=...)` registra a operação.

### Pós-rollback

1. Postar no `#paciente360-ops` com motivo + link para o snapshot do alerta.
2. Abrir issue no GitHub com tag `fase18:rollback` documentando:
   - Hora do flip.
   - Hora do rollback.
   - Métricas observadas.
   - Hipótese de causa raiz.
3. **Não promover novamente** sem investigar e fixar a causa raiz, depois rodar Etapa 4.1 fresh.

---

## 6. Troubleshooting

### "Circuit abriu mas as métricas pareciam OK"

- Cheque `ai_mcp_request_total{outcome="error"}` por capability — pode estar concentrado em UMA tool (ex: `get-availability` com query lenta).
- Veja `audit_logs` recentes para `mcp.circuit.opened` — campo `last_error_code` indica a causa imediata (timeout, 5xx, connection_refused).
- Se for timeout → aumentar `MCP_REQUEST_TIMEOUT_S` em `.env` (default 10s).

### "Latência p95 subiu para 8–12s — está dentro do target mas alto"

- Verifique se há HTTP retry no `McpToolBridge` — não deve haver (timeout único, fallback runtime).
- Cheque carga do `mcp-server` no compose — processos saudáveis devem manter <30% CPU.
- Se persistir, considere escalar `mcp-server` (replicas) ou adicionar Redis caching de respostas MCP idempotentes (capabilities de leitura).

### "MCP server caiu (container down)"

- O `ToolRunner` cai para tools nativas automaticamente (FR-053b). Atendimento NÃO para.
- Cheque `docker compose ps mcp-server` + `docker compose logs mcp-server --tail=100`.
- Restart: `docker compose restart mcp-server`.
- Se causa for OOM → revisar `memory_limit` no Dockerfile do MCP server.

---

## 7. Referências

- Spec: `specs/018-ai-multimodal-mcp/spec.md` (FR-052/053a..d)
- Plan: `specs/018-ai-multimodal-mcp/plan.md`
- Quickstart §6 US7: `specs/018-ai-multimodal-mcp/quickstart.md#us7--servidor-mcp--circuit-breaker`
- Dashboard: `docs/observability/grafana/paciente360-fase18-overview.json` (T214)
- Alertas: `docs/observability/prometheus/fase18-alerts.yml` (T215)
- Constitution: `.specify/memory/constitution.md` (Princípio V — observabilidade)
