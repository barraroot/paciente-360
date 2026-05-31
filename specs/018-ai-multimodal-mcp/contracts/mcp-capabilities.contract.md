# Contract — MCP Server Capabilities (US7)

Servidor MCP local (laravel-mcp v0) — substitui o caminho de tools `laravel/ai` em produção (Q2=B), com circuit breaker auto-revert (FR-053b/c/d). 6 capabilities equivalentes às 6 tools da Fase 17 + 1 nova capability de escrita (`UpdateLeadProfile`) para US3.

---

## Modelo de autenticação

- **Transport**: HTTP local (não exposto à internet por default).
- **Auth**: header `Authorization: Bearer <token>` onde `<token>` é Sanctum PAT.
- **Required ability**: `mcp.invoke`.
- **Tenant scoping** (FR-046, R4): `tenant_id` derivado do token (claim ou tabela `personal_access_tokens.tenant_id` — nova coluna se necessário, ou via `tokenable` polimórfico). **Cliente nunca passa `tenant_id` no input** — schemas declaram apenas os campos legítimos.
- **Sandbox metadata**: tokens emitidos para Persona Test Session carregam `metadata.sandbox=true` (FR-040). Capabilities de escrita inspecionam essa flag.
- **Revogação** (FR-051): `personal_access_tokens.delete()` — efeito imediato (não há cache de auth no servidor MCP).
- **Auditoria** (FR-049): cada chamada grava em `ai_tool_invocations` (Fase 17 reusada) com `source='mcp'`.

---

## Listagem de capabilities

`POST /` com payload MCP `list_capabilities` retorna:

```json
{
  "capabilities": [
    { "name": "get-clinic-info",        "type": "read",  "description": "Informações da clínica..." },
    { "name": "list-professionals",     "type": "read",  "description": "..." },
    { "name": "get-availability",        "type": "read",  "description": "..." },
    { "name": "get-current-patient",     "type": "read",  "description": "..." },
    { "name": "create-or-find-lead",     "type": "write", "description": "..." },
    { "name": "hold-slot",               "type": "write", "description": "..." },
    { "name": "update-lead-profile",    "type": "write", "description": "..." }
  ]
}
```

Listagem TAMBÉM exige auth (FR-046).

---

## Capability 1 — `get-clinic-info`

### Input schema

```json
{
  "type": "object",
  "properties": {},
  "additionalProperties": false
}
```

(Sem input — tenant vem do token.)

### Output

```json
{
  "clinic_name": "Instituto Dor",
  "address": { "street": "...", "city": "...", "uf": "..." },
  "business_hours": { "monday": "08:00-18:00", ... },
  "services": [
    { "id": 1, "name": "Consulta Particular", "price_centavos": 30000, "duration_minutes": 60 }
  ]
}
```

### Origem dos dados (R7)

`AppointmentType` (preços/duração) + `tenant.address`, `tenant.business_hours` (config tenant) + work_context (Fase 17) para info livre.

---

## Capability 2 — `list-professionals`

### Input

```json
{ "type": "object", "properties": { "specialty": { "type": "string" } }, "additionalProperties": false }
```

### Output

```json
{
  "professionals": [
    { "id": 12, "name": "Dr. João Silva", "specialty": "Neurologia", "councils": [...] }
  ]
}
```

---

## Capability 3 — `get-availability`

### Input

```json
{
  "type": "object",
  "properties": {
    "professional_id": { "type": "integer" },
    "from_date": { "type": "string", "format": "date" },
    "to_date":   { "type": "string", "format": "date" },
    "appointment_type_id": { "type": "integer" }
  },
  "required": ["from_date", "to_date"],
  "additionalProperties": false
}
```

### Output

```json
{
  "slots": [
    { "professional_id": 12, "start_at": "2026-06-02T14:00:00-03:00", "end_at": "2026-06-02T15:00:00-03:00" }
  ]
}
```

---

## Capability 4 — `get-current-patient`

### Input

```json
{ "type": "object", "properties": {}, "additionalProperties": false }
```

(Tenant + paciente vêm do token/conversa — FR-029/047.)

### Output

```json
{
  "patient_id": 145,
  "phone_masked": "(83) ****-1234",
  "is_lead": true,
  "status": "lead",
  "kanban_stage": { "id": 12, "name": "Novos Leads" },
  "consents": { "comunicacao": true, "marketing": false, "transcricao": false }
}
```

**Nunca retorna nome** (FR-029, R7) — o nome é injetado no outbound via placeholder fora da IA.

---

## Capability 5 — `create-or-find-lead`

### Input

```json
{
  "type": "object",
  "properties": { "phone": { "type": "string" } },
  "additionalProperties": false
}
```

### Output (caso real)

```json
{
  "patient_id": 145,
  "created": true,
  "status": "lead",
  "kanban_stage": { "id": 12, "name": "Novos Leads" }
}
```

### Output (caso sandbox — FR-041)

```json
{
  "patient_id": "sandbox-uuid",
  "created": true,
  "status": "lead",
  "sandbox": true,
  "kanban_stage": { "id": null, "name": "Novos Leads" }
}
```

(Nada persistido em `pacientes` real.)

---

## Capability 6 — `hold-slot`

### Input

```json
{
  "type": "object",
  "properties": {
    "professional_id": { "type": "integer" },
    "appointment_type_id": { "type": "integer" },
    "start_at": { "type": "string", "format": "date-time" }
  },
  "required": ["professional_id", "appointment_type_id", "start_at"],
  "additionalProperties": false
}
```

### Output (caso real)

```json
{
  "slot_reservation_id": 4521,
  "expires_at": "2026-06-02T13:30:00-03:00",
  "kanban_promoted_to": "agendado"
}
```

### Output (sandbox)

```json
{
  "slot_reservation_id": "sandbox-uuid",
  "expires_at": "2026-06-02T13:30:00-03:00",
  "sandbox": true,
  "kanban_promoted_to": null
}
```

---

## Capability 7 — `update-lead-profile` (NOVA, US3)

Atualiza campos do paciente/lead com fatos coletados na conversa. Allow-list de campos NÃO clínicos (FR-016/017).

### Input

```json
{
  "type": "object",
  "properties": {
    "field": {
      "type": "string",
      "enum": ["name", "complaint", "preferred_city", "urgency", "procedure", "price_range"]
    },
    "value": { "type": "string", "maxLength": 500 }
  },
  "required": ["field", "value"],
  "additionalProperties": false
}
```

### Output (caso real)

```json
{
  "patient_id": 145,
  "field_updated": "complaint",
  "value_before": null,
  "value_after": "Enxaqueca há 3 meses",
  "curation_event_id": 88124
}
```

### Validation rules

- `name` aceita apenas `[a-zA-ZÀ-ÿ\s'-]{2,100}`.
- `complaint`, `procedure`, `urgency`, `preferred_city`, `price_range` — strings curtas; rejeita se `PiiScrubber::detectClinical($value)` flagar dado clínico estrito (CID, dosagem, diagnóstico explícito) — `error_code: clinical_pii_blocked`.

### Sandbox

Em sandbox, no-op com `{ "sandbox": true, "applied": false }`.

---

## Códigos de erro padronizados

| `error_code` | HTTP-equivalent | Quando |
|---|---|---|
| `auth_missing` | 401 | Sem token |
| `auth_invalid` | 401 | Token inválido/expirado/revogado |
| `permission_denied` | 403 | Token não tem ability `mcp.invoke` |
| `tenant_required` | 403 | Token não carrega tenant |
| `capability_not_found` | 404 | Nome não existe ou está desativado |
| `validation_error` | 422 | Schema input não conforme |
| `clinical_pii_blocked` | 422 | `update-lead-profile` detectou dado clínico |
| `cross_tenant_access_attempt` | 403 | Algum input tenta referenciar outra tenant_id |
| `circuit_breaker_open` | 503 | Cliente chamou MCP diretamente quando o flag está rebatendo p/ nativa (raro, defensivo) |

---

## Métricas Prometheus expostas

| Métrica | Tipo | Labels |
|---|---|---|
| `ai_mcp_request_duration_seconds` | histogram | `capability`, `outcome` (success/error), `source` (production/sandbox) |
| `ai_mcp_request_total` | counter | `capability`, `outcome` |
| `ai_mcp_circuit_state` | gauge | (sem labels) — 0=closed, 1=half_open, 2=open |
| `ai_mcp_circuit_failures_total` | counter | (sem labels) |
| `ai_mcp_circuit_transitions_total` | counter | `to` (open/half_open/closed), `source` (automatic/manual_flag) |

---

## Notas de implementação

- **Não há endpoint REST público para o MCP** — é stream MCP via HTTP. Cliente Claude Desktop, IA de produção (via `McpToolBridge`), e chat de teste (via `McpToolBridge` com sandbox token) são os 3 consumers.
- **Versionamento**: o MCP v0 não tem versionamento de schema formal; adicionar capability é additivo. Mudar input schema de capability existente exige migração coordenada com os clientes (no caso, só o `McpToolBridge` interno — fácil).
- **Latência alvo**: cada capability p95 ≤ 500ms (somando ao baseline da Fase 17 dá ≤ 8s p95 fim-a-fim — FR-053a). Capabilities que excedam emitem alerta.
