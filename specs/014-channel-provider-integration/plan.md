# Implementation Plan: Integração de Canal WhatsApp — Twilio (Oficial) ou Evolution API (Não Oficial)

**Branch**: `014-channel-provider-integration` | **Date**: 2026-05-25 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/014-channel-provider-integration/spec.md`

## Summary

Adicionar a **dimensão de provedor** ao canal de WhatsApp da clínica e uma **tela de configuração** para gerenciá-lo. Hoje o `Channel` tipo `whatsapp` assume sempre o provedor **Twilio** (oficial). Esta feature introduz um segundo provedor — **Evolution API v2** (não oficial, Baileys, conexão por QR Code) — **auto-hospedado por nós** (container no ambiente Docker), com **uma instância por canal de tenant**. A clínica escolhe o provedor; no Twilio informa as próprias credenciais; no Evolution apenas pareia via QR Code (sem credenciais de servidor por tenant — o servidor é nossa infraestrutura).

Abordagem técnica: (1) coluna `provider` em `messaging_channels` (`twilio`|`evolution`, default `twilio` para retrocompatibilidade); (2) novo `EvolutionApiAdapter` implementando o contrato `ChannelAdapter` + uma interface complementar de **ciclo de vida de conexão** (`SupportsQrConnection`: createInstance/getQr/connectionState/disconnect); (3) resolução de adapter **provider-aware** (refatorar `SendOutboundMessageJob::resolveAdapter` + `ProcessInboundMessageJob` para um `ChannelAdapterResolver` keyed por `(type, provider)`); (4) `EvolutionWebhookController` para ingestão de eventos (`messages.upsert`, `connection.update`); (5) endpoints REST de QR/status/disconnect + frontend Vue de configuração; (6) serviço `evolution-api` no `compose.yaml` para teste. A conformidade do Princípio VI no provedor não oficial é **reusada do gate da Fase 13** (sem `ChannelTemplate` aprovado, o `OutboundNotificationDispatcher` já bloqueia proativos fora da janela → `pending_manual`).

## Technical Context

**Language/Version**: PHP 8.5 / Laravel 13; Vue 3 (tela de configuração)
**Primary Dependencies** (já no projeto, salvo Evolution):
- Domínio Messaging (Fase 3): `ChannelAdapter` (interface), `WhatsAppCloudAdapter`, `InstagramGraphAdapter`, `ChannelService`, `MessageDispatchService`, `SendOutboundMessageJob`, `ProcessInboundMessageJob`, `CircuitBreakerService`, models `Channel`/`Conversation`/`Message`.
- Config screen parcial existente: `ChannelsController` (`inbox/channels`: store/update/destroy/reconnect), `ConnectChannelRequest`, `ChannelResource`.
- Outbound (Fase 13): `OutboundNotificationDispatcher` + gate de template aprovado (reusado para a política conservadora do não oficial).
- **Evolution API v2** (NOVA dependência de infra, não pacote PHP): servidor auto-hospedado via Docker (`evoapicloud/evolution-api`), acessado por HTTP (Guzzle). Sem novo pacote Composer.
**Storage**: PostgreSQL — coluna nova `provider` + uso de `provider_metadata` (JSONB) para `instance_name`/`instance_token` cifrados; sem tabela nova obrigatória (avaliar `channel_connection_states` opcional para histórico — ver research).
**Testing**: PHPUnit (feature/unit); HTTP do Evolution **mockado** (Guzzle MockHandler / fake adapter), seguindo o padrão de stubs da Fase 5. Gate de isolamento multi-tenant + gate de roteamento por provedor.
**Target Platform**: Linux server (Sail/Docker), filas Horizon (Redis). Evolution roda como serviço Docker irmão.
**Project Type**: Web (API Laravel + SPA Vue).
**Performance Goals**: Conexão/QR é interativa — gerar/exibir QR ≤ 2s (chamada HTTP ao Evolution). Status reflete realidade em ≤ 1 min (SC-005) via webhook `connection.update` + polling de fallback. Envio/recebimento reusa filas existentes.
**Constraints**: Princípio VI (não oficial NÃO burla a via oficial — proativo fora da janela → `pending_manual`), Princípio I (credenciais/segredos de sessão cifrados; QR não persistido após uso), Princípio II (instância Evolution mapeada 1:1 a um tenant; webhook resolve tenant pela instância), Princípio VII (server URL/api-key do Evolution via env, não input do tenant → sem superfície SSRF nova; webhook validado por apikey).
**Scale/Scope**: Volume modesto por tenant. **Um provedor de WhatsApp ativo por tenant por vez** (FR-018). Uma instância Evolution por canal de tenant.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação | Como o design atende |
|---|---|---|
| **I. LGPD (NON-NEGOTIABLE)** | PASS | Credenciais Twilio e `instance_token` Evolution cifrados (cast `encrypted`, padrão `credentials_encrypted`/`provider_metadata`). QR Code é efêmero (não persistido após pareamento). Mensagens já cifradas em repouso (Fase 3). Eventos auditáveis sem segredos. |
| **II. Isolamento Multi-Tenant (NON-NEGOTIABLE)** | PASS c/ gate | `messaging_channels.tenant_id` + global scope já existem. **Instância Evolution 1:1 com canal de tenant** (`instance_name = "tenant_{id}"` ou UUID); o `EvolutionWebhookController` resolve o tenant **pela instância** (nunca por parâmetro livre). Gate cross-tenant: webhook de uma instância nunca entrega à inbox de outro tenant; envio nunca roteia pela instância de outro. |
| **III. Segurança Clínica / IA (NON-NEGOTIABLE)** | PASS (N/A) | Sem IA. Apenas transporte de mensagens. |
| **IV. Spec-Driven / Test-First** | PASS | Spec 014 aprovada + 3 clarifications. Gates (isolamento, roteamento por provedor, conformidade VI no não oficial, segredos não vazam) codificados antes da implementação. Migração aditiva e idempotente (coluna `provider` com default). |
| **V. Observabilidade** | PASS | Estado de conexão rastreado + métricas Prometheus (conexões ativas/quedas/reconexões por provedor) + eventos auditáveis (conectado/desconectado/troca de provedor) + webhook bruto logado (Princípio V). |
| **VI. Conformidade Meta (NON-NEGOTIABLE)** | PASS c/ nota | **Via oficial (Twilio) inalterada** — janela 24h + template aprovado seguem como hoje. **Via não oficial (Evolution)**: política conservadora (clarify Q1) — fora da janela 24h, proativo é **bloqueado** → `pending_manual`. Isso é **reusado automaticamente** do gate da Fase 13: como o Evolution não tem `ChannelTemplate` aprovado, o `OutboundNotificationDispatcher` já cai em `no_template`/`pending_manual` fora da janela. Dentro da janela, texto livre é permitido (comportamento legítimo de resposta). Aviso explícito de "não oficial + risco" na UI (FR-003). Não há disparo de marketing em massa aqui. |
| **VII. Segurança Operacional (NON-NEGOTIABLE)** | PASS | Server URL + global api-key do Evolution via **env** (`EVOLUTION_API_URL`/`EVOLUTION_API_KEY`), não input do tenant → sem nova superfície SSRF. Webhook do Evolution validado por header `apikey`/segredo. Credenciais cifradas; rate limiting herdado das rotas autenticadas; config restrita a `channel.connect`. |

**Resultado**: PASS 7/7. **Pré-requisito constitucional cumprido (C1 do `/speckit-analyze`)**: a adição da Evolution API como canal externo estava fora da stack fixa enumerada — resolvida pelo **amendment MINOR v1.5.0** (2026-05-25), que admite a Evolution API v2 como transporte **opcional não oficial** de WhatsApp, auto-hospedado, com aviso de risco e preservando o Princípio VI por defesa em profundidade. A nota do Princípio VI é tratada por **reuso** do gate da Fase 13 (sem template HSM aprovado, proativo fora da janela → `pending_manual`). Detalhe em research §6.

## Project Structure

### Documentation (this feature)

```text
specs/014-channel-provider-integration/
├── plan.md              # Este arquivo
├── research.md          # Decisões técnicas (Phase 0)
├── data-model.md        # Mudanças de schema (Phase 1)
├── quickstart.md        # Guia operacional / lotes (Phase 1)
├── contracts/           # Contratos internos + endpoints + gates (Phase 1)
│   └── channel-provider.md
└── tasks.md             # (/speckit-tasks — não criado aqui)
```

### Source Code (repository root)

```text
app/Domain/Messaging/Channel/
├── Adapters/
│   ├── ChannelAdapter.php                 # interface existente (send/validate/parse/getType)
│   ├── SupportsQrConnection.php           # NOVO — contrato de ciclo de vida QR (createInstance/getQr/connectionState/disconnect)
│   ├── EvolutionApiAdapter.php            # NOVO — implementa ChannelAdapter + SupportsQrConnection (Guzzle → Evolution)
│   └── ChannelAdapterResolver.php         # NOVO — resolve adapter por (type, provider)
├── Services/
│   ├── ChannelService.php                 # ESTENDER — connect/disconnect cientes de provider; um-ativo-por-vez
│   └── EvolutionInstanceService.php       # NOVO — orquestra instância (create/qr/state/delete) + persiste em provider_metadata
├── Enums/
│   └── ChannelProvider.php                # NOVO — twilio|evolution
└── Models/Channel.php                     # ESTENDER — coluna provider + scopes

app/Http/Controllers/
├── Api/V1/Inbox/ChannelsController.php     # ESTENDER — provider na criação; ações qr/state
├── Api/V1/Inbox/EvolutionConnectionController.php  # NOVO — POST connect (cria instância+QR), GET qr, GET state, POST disconnect
└── Webhooks/EvolutionWebhookController.php # NOVO — ingestão messages.upsert + connection.update (valida apikey, resolve tenant pela instância)

app/Http/Requests/Inbox/ConnectChannelRequest.php   # ESTENDER — provider + validação por provider
app/Jobs/Messaging/{SendOutboundMessageJob,ProcessInboundMessageJob}.php  # REFATORAR — usar ChannelAdapterResolver
app/Support/Metrics/ (ChannelConnectionMetrics ou estender MessagingMetrics)  # métricas de conexão

database/migrations/                        # add_provider_to_messaging_channels (+ índice; default 'twilio')
config/messaging.php                         # EVOLUTION_API_URL / EVOLUTION_API_KEY / EVOLUTION_WEBHOOK_SECRET

resources/js/
├── pages/settings/ChannelsPage.vue         # NOVO/ESTENDER — tela de configuração de canais
├── components/settings/ProviderPicker.vue   # NOVO — escolha Twilio|Evolution + aviso de risco
├── components/settings/EvolutionQrModal.vue  # NOVO — exibe QR + polling de status
└── stores/channelsStore.js                  # NOVO/ESTENDER

compose.yaml                                 # add serviço evolution-api (+ schema no pgsql, uso do redis)

tests/Feature/Channels/                      # provider routing, isolamento, conformidade VI não oficial, segredos
tests/Unit/Channels/                         # EvolutionApiAdapter (Guzzle mock), resolver
```

**Structure Decision**: Estender o subdomínio `app/Domain/Messaging/Channel/` existente (não criar base nova). O `EvolutionApiAdapter` é irmão do `WhatsAppCloudAdapter` sob o mesmo contrato `ChannelAdapter`, acrescido de `SupportsQrConnection` para o ciclo de vida por QR (que o Twilio não tem). O ponto único de seleção passa a ser `ChannelAdapterResolver` (resolve por `type`+`provider`), eliminando o `match` hardcoded em `SendOutboundMessageJob`. A tela vive em `resources/js/pages/settings/` (SPA Vue, Princípio de arquitetura — nada de Filament para fluxo de tenant).

## Complexity Tracking

> Nenhuma violação constitucional a justificar. Constitution Check = PASS 7/7. Tabela omitida.
