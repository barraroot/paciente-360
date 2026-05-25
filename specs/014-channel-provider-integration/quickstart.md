# Quickstart — Integração de Canal: Twilio | Evolution API (014)

**Status**: Plan complete | **Date**: 2026-05-25

Guia operacional. Reusa Messaging (Fase 3) + outbound (Fase 13). Toda mudança PHP → `pint --dirty` + teste.

## Pré-requisitos

- ✅ Branch `014-channel-provider-integration`
- ✅ Spec + 3 clarifications + plan + research + data-model + contracts
- ✅ Fase 3 (Messaging/Inbox) e Fase 13 (Outbound) entregues
- ✅ Sail + Horizon rodando
- Evolution API container provisionado no `compose.yaml` (Lote A) para smoke real

## Ordem sugerida (Lotes)

### Lote A — Ambiente Docker do Evolution (infra de teste)
1. Adicionar serviço `evolution-api` (`evoapicloud/evolution-api:latest`) ao `compose.yaml`, reusando `pgsql` (DB `evolution`) + `redis`; expor porta 8080 na rede `sail`.
2. Env da app: `EVOLUTION_API_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_WEBHOOK_SECRET` em `config/messaging.php`.
3. `vendor/bin/sail up -d evolution-api` + smoke manual `GET /` no servidor.

### Lote B — Schema + provider (foundation)
4. Enum `ChannelProvider` (`twilio`|`evolution`).
5. Migration `add_provider_to_messaging_channels` (default `twilio`; índice `(tenant_id,type,provider)`; UNIQUE parcial "um WhatsApp ativo por tenant").
6. `Channel` model: cast/scope de `provider`; helpers de status.

### Lote C — Adapter Evolution + resolver (núcleo)
7. Interface `SupportsQrConnection` + `EvolutionApiAdapter` (Guzzle → Evolution: createInstance/getQr/connectionState/disconnect/send/parseInboundWebhook).
8. `ChannelAdapterResolver::for(Channel)` por `(type, provider)`.
9. Refatorar `SendOutboundMessageJob` e `ProcessInboundMessageJob` para usar o resolver.
10. `EvolutionInstanceService` (orquestra instância + persiste `provider_metadata` cifrado).

### Lote D — Gates do núcleo (Test-First)
11. G1 (routing), G2 (cross-tenant), G4 (um ativo por vez), G6 (segredos), G7 (webhook auth). HTTP do Evolution mockado (Guzzle MockHandler).

### Lote E — Conexão + webhook
12. `EvolutionConnectionController` (connect/qr/connection-state/reconnect) + estender `ChannelsController`/`ConnectChannelRequest` com `provider`.
13. `EvolutionWebhookController` (`messages.upsert`→inbound, `connection.update`→status; valida apikey; resolve tenant pela instância) + rota webhook.
14. Cron de fallback de estado (reconcilia canais Evolution `conectando`/`ativo` via `connectionState`). Gates G3, G8.

### Lote F — Conformidade outbound (reuso Fase 13)
15. Ajustar `OutboundChannelResolver` (Fase 13) para reconhecer canal Evolution ativo como elegível. Gate G5 (proativo fora da janela → `pending_manual`; dentro da janela → texto livre).

### Lote G — Frontend
16. `ChannelsPage.vue` (lista + status + ações), `ProviderPicker.vue` (escolha + aviso "não oficial/risco" — FR-003), `EvolutionQrModal.vue` (QR + polling de `connection-state`), `channelsStore.js`. Item na sidebar (Configurações → Canais, ability `channel.connect`).

### Lote H — Métricas + polish + gates finais
17. Métricas de conexão (estender `MessagingMetrics`) + eventos auditáveis (`CanalConectado`/`Desconectado`/`ProvedorAlterado`).
18. `pint --dirty` + suíte dos novos testes + suíte cheia (não regredir baseline ~1615).
19. Smoke browser: conectar Evolution por QR real (container) → enviar/receber → trocar para Twilio.
20. Constitution Re-Check + `.specify/feature.json` → DELIVERED.

## Comandos úteis

```bash
vendor/bin/sail up -d evolution-api
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact tests/Feature/Channels
vendor/bin/sail bin pint --dirty --format agent
```

## Riscos / Notas

- **Não oficial = risco de bloqueio**: a UI deve deixar explícito (FR-003); SLA/uptime não garantidos (out of scope).
- **Princípio VI**: proativos fora da janela no Evolution são bloqueados pelo gate da Fase 13 (sem template aprovado) — NÃO criar bypass.
- **Segredos**: `instance_token` cifrado e nunca em `ChannelResource`/logs; QR efêmero.
- **Webhook**: validar `apikey`; resolver tenant pela instância (nunca por parâmetro livre).
- **DEFERRED candidato**: tabela `channel_connection_events` (histórico fino) — `audit_logs` + métricas cobrem o MVP.
