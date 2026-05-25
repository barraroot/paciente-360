# Data Model — Integração de Canal: Twilio | Evolution API (014)

Sem tabela nova obrigatória. Estende `messaging_channels` (multi-tenant, já com `tenant_id` + global scope). Reusa `Conversation`/`Message`/`ChannelTemplate`.

## Enum

### `ChannelProvider`
`twilio` (oficial — WhatsApp Business Cloud via Twilio) · `evolution` (não oficial — Evolution API v2 / Baileys).

## Alteração: `messaging_channels`

| Coluna | Tipo | Notas |
|---|---|---|
| `provider` | varchar(20) | **NOVO**. `twilio`\|`evolution`. Default `'twilio'` (retrocompatível). Índice `(tenant_id, type, provider)`. |
| `provider_metadata` | jsonb | **REUSO**. Twilio: `messaging_service_sid`, `whatsapp_sender`, etc. (já existe). Evolution: `instance_name` (determinístico, ex.: `tenant_{id}`), `instance_token` (segredo — cifrado/oculto), `connected_number` (preenchido após pareamento). |
| `credentials_encrypted` | text | **REUSO** (cast `encrypted`). Twilio: account_sid/auth_token. Evolution: não usa credenciais por tenant (servidor é nosso). |
| `status` | varchar | **REUSO**. `ativo`\|`conectando`\|`desconectado`\|`invalido`\|`expirado`\|`degradado`\|`suspenso`. Mapeamento Evolution: `open→ativo`, `connecting→conectando`, `close→desconectado`. |
| `type` | varchar | **INALTERADO**. `'whatsapp'` para ambos os provedores. |

**Constraints**:
- CHECK `provider IN ('twilio','evolution')`.
- **UNIQUE parcial "um WhatsApp ativo por tenant"** (R7): `CREATE UNIQUE INDEX one_active_whatsapp_per_tenant ON messaging_channels (tenant_id) WHERE type='whatsapp' AND status IN ('ativo','conectando') AND deleted_at IS NULL`.
- Índice `(tenant_id, type, provider)` para resolução rápida do canal ativo por provedor.

## Estrutura de `provider_metadata` (Evolution)

```json
{
  "instance_name": "tenant_42",
  "instance_token": "<cifrado/oculto>",
  "connected_number": "+55XXXXXXXXXXX",
  "last_connection_state": "open",
  "webhook_configured_at": "2026-05-25T12:00:00Z"
}
```

- `instance_token` é segredo: **nunca** retornado por `ChannelResource`, nunca logado.
- `connected_number` preenchido após o `connection.update` de pareamento.

## (Opcional) Tabela `channel_connection_events`

Para histórico/auditoria de transições de conexão (avaliada no plano; pode ficar DEFERRED — `audit_logs` já cobre eventos de conexão/desconexão).

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK | global scope |
| `channel_id` | bigint FK | |
| `from_state` / `to_state` | varchar | transição |
| `source` | varchar | `webhook`\|`poll`\|`manual` |
| `created_at` | timestamptz | |

**Decisão**: começar SEM esta tabela (usar `audit_logs` + métricas). Introduzir só se o histórico fino for exigido.

## Entidades reutilizadas (sem alteração de schema)

- **Conversation** — `channel_id` aponta para o canal (de qualquer provedor); inbound do Evolution abre/atualiza conversa como o Twilio.
- **Message** — inalterada; `external_id` recebe o id da mensagem no provedor (Twilio SID ou Evolution message id).
- **ChannelTemplate** — só relevante para Twilio (templates HSM aprovados). Evolution não tem; é o que faz o gate da Fase 13 bloquear proativos fora da janela.
- **OutboundNotification** (Fase 13) — inalterada; o `OutboundChannelResolver` passa a reconhecer o canal Evolution ativo como elegível (R6).

## Transições de estado de conexão (Evolution)

```
(criar instância) ──► conectando ──(QR pareado / connection.update open)──► ativo
        │                   │
        │                   └──(QR expira sem parear)──► conectando (novo QR)
        └──(connection.update close / disconnect)──────► desconectado
ativo ──(sessão cai / connection.update close)─────────► desconectado
desconectado ──(reconnect → novo QR)───────────────────► conectando
```
