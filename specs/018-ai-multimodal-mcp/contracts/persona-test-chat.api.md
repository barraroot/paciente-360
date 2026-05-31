# Contract — Persona Test Chat (US6)

REST endpoints da SPA Vue para o chat sandbox de teste de Persona. Pipeline constitucional: `Form Request → Controller → Service → Resource`. Middleware obrigatório: `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`. Permission Spatie: `ai.persona.test`.

---

## 1. `POST /api/v1/ai/personas/{persona}/test/sessions` — abrir sessão

Cria uma `persona_test_session`, emite token Sanctum MCP scoped sandbox.

### Request

```json
{
  "use_draft": true,
  "persona_draft": {
    "name": "Camila — Atendente Acolhedora",
    "system_prompt": "...",
    "guardrails": "...",
    "voice_id": 3
  }
}
```

- `use_draft` (bool, opcional, default `false`): se `true`, usa o `persona_draft` enviado pelo cliente como snapshot (FR-039) — para testar mudanças ainda não salvas no formulário.
- `persona_draft` (object, condicional): obrigatório quando `use_draft=true`.

### Response 201

```json
{
  "data": {
    "id": "0f2ee3c0-...",
    "persona_id": 12,
    "persona_snapshot": { /* o snapshot vigente */ },
    "status": "open",
    "mcp_token": "1|abc123...",   // PAT, devolvido UMA vez (não consultável depois)
    "echo_channel": "private-persona-test.0f2ee3c0-..."
  }
}
```

### Errors

| HTTP | Code | Quando |
|---|---|---|
| 403 | `permission_denied` | Usuário não tem `ai.persona.test` |
| 404 | `persona_not_found` | persona não pertence ao tenant |
| 422 | `validation_error` | `use_draft=true` sem `persona_draft` |

---

## 2. `POST /api/v1/ai/personas/test/sessions/{session}/messages` — enviar mensagem

Envia mensagem do admin (atuando como "paciente") para a IA.

### Request

```json
{
  "content_type": "text",
  "text": "oi, queria saber sobre consulta"
}
```

OU

```json
{
  "content_type": "audio",
  "audio_base64": "...",
  "audio_mime_type": "audio/ogg"
}
```

### Response 202 (assíncrono)

```json
{
  "data": {
    "message_id": 7891,
    "sandbox": true,
    "echo_channel": "private-persona-test.0f2ee3c0-..."
  }
}
```

Resposta da IA chega via WebSocket (Reverb) em `echo_channel`, broadcast `PersonaTestMessageBroadcasted`:

```json
{
  "type": "ia_response",
  "content_type": "text" | "audio",
  "text": "Oi! Tudo bem? Em que posso ajudar?",
  "audio_url": null,
  "coalesced_messages": 1,
  "reprocess_count": 0,
  "tools_used": ["GetClinicInfoCapability"],
  "sandbox": true
}
```

### Errors

| HTTP | Code | Quando |
|---|---|---|
| 404 | `session_not_found` | Session não existe, não é do admin, ou está closed |
| 410 | `session_closed` | Session foi fechada entre listagem e envio |
| 422 | `validation_error` | Payload inválido |
| 429 | `rate_limit_exceeded` | Mesma rate-limit chain (R10), por session_id |

---

## 3. `POST /api/v1/ai/personas/test/sessions/{session}/close` — fechar sessão

Marca `status='closed'`, revoga o token MCP, sinaliza fim no echo channel.

### Response 200

```json
{
  "data": {
    "id": "0f2ee3c0-...",
    "status": "closed",
    "closed_at": "2026-05-30T15:32:18Z"
  }
}
```

---

## 4. `GET /api/v1/ai/personas/{persona}/test/sessions` — listar sessões do admin

Pagina sessões do **usuário autenticado** apenas (FR-043 isolamento).

### Query params

- `status` (optional): `open | closed | archived`
- `per_page` (default 20)

### Response 200

```json
{
  "data": [
    { "id": "...", "status": "closed", "created_at": "...", "closed_at": "...", "message_count": 14 }
  ],
  "meta": { "current_page": 1, "total": 23 }
}
```

---

## 5. `POST /api/v1/ai/personas/test/sessions/{session}/archive` — arquivar

Sessão `closed` vira `archived` (não auto-purga).

---

## Eventos WebSocket (Reverb)

### `private-persona-test.{session_id}`

Autorização: somente `admin_user_id` igual ao da session.

Eventos emitidos:
- `PersonaTestMessageBroadcasted` — resposta da IA pronta (payload acima).
- `PersonaTestThinking` — IA está pensando (debounce → processamento) — útil para "IA digitando…" no modal.
- `PersonaTestSessionClosed` — quando outra aba/dispositivo fechar.

---

## Notas

- **Sandbox flag propagada** (FR-040, R6): token MCP carrega `metadata.sandbox=true`; capabilities de escrita são neutralizadas pelo `SandboxNeutralizer`.
- **Métricas isoladas** (FR-042): toda métrica derivada de `messaging_messages` ou `ai_execution_logs` precisa de `WHERE sandbox=false` ou equivalente.
- **Limites**: 1 sessão `open` simultânea por (admin, persona) — se admin abre nova, a anterior é auto-`closed` com motivo `superseded`.
