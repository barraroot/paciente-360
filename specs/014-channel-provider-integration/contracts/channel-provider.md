# Contracts — Integração de Canal: Twilio | Evolution API (014)

Contratos internos (adapter/resolver), endpoints REST (config + QR/estado), webhook e **gates de aceite** (testes obrigatórios).

## 1. Contrato interno — `SupportsQrConnection` (novo)

Complementa `ChannelAdapter` para provedores com pareamento por sessão (Evolution). Twilio NÃO implementa.

```
createInstance(Channel $channel): InstanceConnection   // cria instância no servidor Evolution; configura webhook por instância
getQrCode(Channel $channel): QrPayload                 // { base64, code, pairingCode?, expiresAt }
connectionState(Channel $channel): string              // 'open'|'connecting'|'close'
disconnect(Channel $channel): void                     // logout/delete da instância
```

`EvolutionApiAdapter implements ChannelAdapter, SupportsQrConnection`:
- `send()` → `POST /message/sendText` (ou template-livre) na instância; retorna `MessageDispatchResult`.
- `parseInboundWebhook()` → mapeia `messages.upsert` para `InboundMessageDto`.
- `getType()` → `'whatsapp'`.

## 2. Contrato interno — `ChannelAdapterResolver` (novo)

```
ChannelAdapterResolver::for(Channel $channel): ChannelAdapter
```
Resolve por `(type, provider)`: `whatsapp+twilio→WhatsAppCloudAdapter`, `whatsapp+evolution→EvolutionApiAdapter`, `instagram→InstagramGraphAdapter`, `web→WebWidgetAdapter`. Consumido por `SendOutboundMessageJob` e `ProcessInboundMessageJob` (substitui o `match($type)` hardcoded).

## 3. Endpoints REST

Middleware: `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`. Permissão: `channel.connect` (admin da clínica).

| Método | Rota | Ação |
|---|---|---|
| GET | `/api/v1/inbox/channels` | lista canais do tenant (já existe; passa a expor `provider`) |
| POST | `/api/v1/inbox/channels` | cria canal — body inclui `provider` (`twilio`\|`evolution`). Twilio: credenciais; Evolution: sem credenciais (cria instância + retorna QR) |
| POST | `/api/v1/inbox/channels/{id}/qr` | (Evolution) gera/regenera QR Code |
| GET | `/api/v1/inbox/channels/{id}/connection-state` | estado atual da conexão (para polling do front) |
| POST | `/api/v1/inbox/channels/{id}/reconnect` | reconecta (Evolution → novo QR; Twilio → revalida) |
| DELETE | `/api/v1/inbox/channels/{id}` | desconecta/remove (logout da instância no Evolution) |

`ChannelResource`: inclui `provider`, `status`, `connected_number` (quando houver) — **sem** segredos (`instance_token`, `auth_token`).

## 4. Webhook

| Método | Rota | Ação |
|---|---|---|
| POST | `/webhooks/evolution/{instance?}` | recebe `messages.upsert` (→ inbound) e `connection.update` (→ status). Valida header `apikey`. Resolve tenant pela instância. Responde 200 sempre (idempotente). |

## 5. Gates de aceite (testes obrigatórios)

| Gate | Teste | Valida |
|---|---|---|
| **G1** | `ChannelProviderRoutingTest` | tenant `provider=evolution` envia via `EvolutionApiAdapter`; `twilio` via `WhatsAppCloudAdapter` (resolver). |
| **G2** | `EvolutionCrossTenantTest` (Princípio II) | webhook da instância de A nunca entrega à inbox de B; envio nunca usa instância de B; resolução escopada. |
| **G3** | `EvolutionConnectionLifecycleTest` | connect cria instância + retorna QR; `connection.update open`→`ativo`; `close`→`desconectado`; reconnect gera novo QR. |
| **G4** | `OneActiveWhatsAppPerTenantTest` (R7) | segundo canal WhatsApp ativo é recusado enquanto houver um ativo; trocar exige desconectar. |
| **G5** | `UnofficialOutboundComplianceTest` (Princípio VI) | proativo fora da janela 24h no Evolution → `pending_manual` (reuso gate Fase 13); dentro da janela → texto livre enviado. |
| **G6** | `ChannelSecretsNotLeakedTest` (Princípios I/VII) | `instance_token`/`auth_token` não aparecem em `ChannelResource` nem em logs; QR não persistido após pareamento. |
| **G7** | `EvolutionWebhookAuthTest` | webhook sem `apikey` válido é rejeitado; instância desconhecida não cria dados. |
| **G8** | `ChannelProviderConfigCrudTest` | criação por provider via API + isolamento por tenant + aviso "não oficial" presente no fluxo. |

## 6. Métricas (Prometheus)

`channel_connections_total{tenant, provider, status}` · `channel_disconnections_total{tenant, provider, reason}` · `channel_reconnects_total{tenant, provider}` · `channel_active{provider}` (gauge). Reusar/estender `MessagingMetrics`.

## 7. Eventos auditáveis (sem segredos)

`CanalConectado{provider}`, `CanalDesconectado{provider, motivo}`, `ProvedorDeCanalAlterado{de, para}` — payload com `channel_id, tenant_id, provider` (nunca token/credencial).

## 8. Config (env)

`EVOLUTION_API_URL` (ex.: `http://evolution-api:8080`), `EVOLUTION_API_KEY` (global), `EVOLUTION_WEBHOOK_SECRET`. Lidos via `config/messaging.php` (ou `config/services.php`). Nunca input do tenant.
