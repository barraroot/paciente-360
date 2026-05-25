---
description: "Task list — Integração de Canal: Twilio | Evolution API (014)"
---

# Tasks: Integração de Canal WhatsApp — Twilio (Oficial) ou Evolution API (Não Oficial)

**Input**: Design documents from `/specs/014-channel-provider-integration/`
**Prerequisites**: plan.md ✅, spec.md ✅ (4 US, 21 FR, 7 SC, 5 clarifications), research.md ✅ (R1–R10), data-model.md ✅ (coluna `provider` + UNIQUE parcial), contracts/ ✅ (8 gates G1–G8), quickstart.md ✅ (lotes A–H)

**Tests**: INCLUÍDOS — contracts §5 define os gates G1–G8 como obrigatórios e o Princípio IV (Test-First) é NON-NEGOTIABLE. HTTP do Evolution é mockado (Guzzle MockHandler) — testes não dependem do container.

**Organization**: Tarefas por user story (US1 Twilio P1 → US2 Evolution QR P1 → US3 lifecycle P2 → US4 mensagens P2). O `EvolutionApiAdapter` + `ChannelAdapterResolver` + `EvolutionInstanceService` são o motor compartilhado e vivem na Foundational; cada US conecta endpoints/webhook/telas.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivo diferente, sem dependência incompleta)
- **[Story]**: US1..US4 (somente nas fases de user story)

## Path Conventions

Estende o subdomínio `app/Domain/Messaging/Channel/` (adapters, services, models, enums). Frontend em `resources/js/pages/settings/`. Testes em `tests/Feature/Channels/` e `tests/Unit/Channels/`. Toda mudança PHP → `vendor/bin/sail bin pint --dirty --format agent` + teste do arquivo afetado.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Ambiente Docker do Evolution + configuração + scaffolding.

- [X] T001 [P] Adicionar serviço `evolution-api` (`evoapicloud/evolution-api:latest`) ao `compose.yaml`, reusando `pgsql` (DB `evolution`) + `redis`, porta 8080 na rede `sail`, env `SERVER_URL`/`AUTHENTICATION_API_KEY`/`DATABASE_*`/`CACHE_REDIS_*`
- [X] T002 [P] Criar `config/messaging.php` (ou estender existente) com `evolution.api_url`, `evolution.api_key`, `evolution.webhook_secret` (env `EVOLUTION_API_URL`/`EVOLUTION_API_KEY`/`EVOLUTION_WEBHOOK_SECRET`) + entradas em `.env.example`
- [X] T003 Criar diretórios de teste `tests/Feature/Channels/` e `tests/Unit/Channels/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, modelo de provedor e o motor de adapter (Evolution + resolver + instância) que todas as US consomem.

**⚠️ CRITICAL**: Nenhuma US pode começar antes desta fase concluir.

### Schema & Model

- [X] T004 [P] Criar enum `ChannelProvider` (`twilio`, `evolution`) em `app/Domain/Messaging/Channel/Enums/ChannelProvider.php`
- [X] T005 Criar migration `add_provider_to_messaging_channels` (coluna `provider` varchar(20) default `'twilio'`; CHECK `provider IN ('twilio','evolution')`; índice `(tenant_id, type, provider)`; UNIQUE parcial `one_active_whatsapp_per_tenant` em `(tenant_id) WHERE type='whatsapp' AND status IN ('ativo','conectando') AND deleted_at IS NULL`) em `database/migrations/`
- [X] T006 [P] Estender `Channel` model: cast/atributo `provider` (enum), scopes `byProvider()` e `activeWhatsapp()`, helper de mapeamento de status Evolution (open/connecting/close) em `app/Domain/Messaging/Channel/Models/Channel.php`

### Motor de adapter

- [X] T007 [P] Criar interface `SupportsQrConnection` (`createInstance(Channel): InstanceConnection`, `getQrCode(Channel): QrPayload`, `connectionState(Channel): string`, `disconnect(Channel): void`) em `app/Domain/Messaging/Channel/Adapters/SupportsQrConnection.php`
- [X] T008 Criar `EvolutionApiAdapter implements ChannelAdapter, SupportsQrConnection` (Guzzle → Evolution: instance/connect/connectionState/logout + `send` texto **e mídia** + `parseInboundWebhook` para `messages.upsert` incl. mídia; `getType()='whatsapp'`; chamadas dentro do CircuitBreaker) em `app/Domain/Messaging/Channel/Adapters/EvolutionApiAdapter.php`
- [X] T009 [P] Criar `ChannelAdapterResolver::for(Channel): ChannelAdapter` (resolve por `type`+`provider`) em `app/Domain/Messaging/Channel/Adapters/ChannelAdapterResolver.php`
- [X] T010 Criar `EvolutionInstanceService` (orquestra create/qr/state/delete via adapter; persiste `instance_name`/`instance_token`/`connected_number` em `provider_metadata` com segredo cifrado) em `app/Domain/Messaging/Channel/Services/EvolutionInstanceService.php`
- [X] T011 Estender `ChannelService::connect/disconnect` para serem cientes de `provider` e aplicar a regra **um WhatsApp ativo por tenant** (recusa com mensagem orientativa se já houver ativo/conectando) em `app/Domain/Messaging/Channel/Services/ChannelService.php`
- [X] T012 [P] Criar eventos auditáveis `CanalConectado`, `CanalDesconectado`, `ProvedorDeCanalAlterado` (payload `channel_id, tenant_id, provider` — sem segredos) em `app/Domain/Messaging/Channel/Events/`
- [X] T013 [P] Adicionar métricas de conexão (`channel_connections_total{tenant,provider,status}`, `channel_disconnections_total`, `channel_reconnects_total`, `channel_active{provider}`) estendendo `MessagingMetrics`/contrato em `app/Support/Metrics/`

### Gate do núcleo (Test-First)

- [X] T014 [P] Unit test do `EvolutionApiAdapter` (Guzzle `MockHandler`: createInstance retorna QR; connectionState mapeia open/connecting/close; send texto/mídia monta payload correto; parseInboundWebhook normaliza `messages.upsert`) em `tests/Unit/Channels/EvolutionApiAdapterTest.php`
- [X] T015 [P] Unit test do `ChannelAdapterResolver` (whatsapp+twilio→WhatsAppCloudAdapter; whatsapp+evolution→EvolutionApiAdapter; instagram/web) em `tests/Unit/Channels/ChannelAdapterResolverTest.php`

**Checkpoint**: Motor de adapter + schema prontos. US1–US4 podem começar.

---

## Phase 3: User Story 1 - Clínica conecta WhatsApp oficial (Twilio) pela tela (Priority: P1) 🎯 MVP

**Goal**: A clínica conecta o canal oficial (Twilio) pela tela de Configurações → Canais, com credenciais validadas e status exibido.

**Independent Test**: Sem canal, escolher Twilio, inserir credenciais válidas → "conectado" na lista; inválidas → erro sem conectar.

- [X] T016 [US1] Estender `ConnectChannelRequest`: campo `provider` (`twilio`|`evolution`) + validação condicional por provider (Twilio = credenciais atuais; Evolution = sem credenciais) em `app/Http/Requests/Inbox/ConnectChannelRequest.php`
- [X] T017 [US1] Expor `provider`, `status` e `connected_number` no `ChannelResource` (sem segredos `auth_token`/`instance_token`) em `app/Http/Resources/Inbox/ChannelResource.php`
- [X] T018 [US1] Ajustar `ChannelsController::store`/`index` para receber/expor `provider` (delegando ao `ChannelService`) em `app/Http/Controllers/Api/V1/Inbox/ChannelsController.php`
- [X] T019 [US1] Criar tela `ChannelsPage.vue` (lista canais + status + provider + ação "Conectar") em `resources/js/pages/settings/ChannelsPage.vue` + item na sidebar (Configurações → Canais, ability `channel.connect`) em `config/navigation.js` + chaves i18n em `resources/js/i18n/pt-BR.json`
- [X] T020 [US1] Criar `ProviderPicker.vue` (escolha Twilio/Evolution + form de credenciais Twilio) e `channelsStore.js` em `resources/js/components/settings/` e `resources/js/stores/`
- [X] T021 [P] [US1] **G8** `ChannelProviderConfigCrudTest` — criação por provider via API + isolamento por tenant + `provider` no resource em `tests/Feature/Channels/ChannelProviderConfigCrudTest.php`

**Checkpoint**: US1 funcional — tela + conexão Twilio ponta-a-ponta (MVP).

---

## Phase 4: User Story 2 - Clínica conecta WhatsApp não oficial (Evolution) por QR Code (Priority: P1)

**Goal**: A clínica escolhe Evolution, vê o QR Code, escaneia e o status vira "conectado" automaticamente ao parear.

**Independent Test**: Escolher Evolution → QR renderizado; simular pareamento (webhook `connection.update open`) → "conectado"; QR expirado → regenerar.

- [X] T022 [US2] Criar `EvolutionConnectionController` (`POST channels` provider=evolution → cria instância + retorna QR via `EvolutionInstanceService`; `POST channels/{id}/qr` regenera QR) em `app/Http/Controllers/Api/V1/Inbox/EvolutionConnectionController.php` + rotas
- [X] T023 [US2] Criar `EvolutionWebhookController` tratando `connection.update` (atualiza `Channel.status`; resolve tenant pela instância; valida header `apikey`/segredo; 200 idempotente) em `app/Http/Controllers/Webhooks/EvolutionWebhookController.php` + rota `POST /webhooks/evolution/{instance?}`
- [X] T024 [US2] Criar `EvolutionQrModal.vue` (exibe QR base64 + instruções + aviso "não oficial/risco" FR-003 + polling de `connection-state` até conectar) em `resources/js/components/settings/EvolutionQrModal.vue` (integra ao `ProviderPicker`/`channelsStore`)
- [X] T025 [P] [US2] **G3** `EvolutionConnectionLifecycleTest` — connect cria instância + QR; `connection.update open`→`ativo`; `close`→`desconectado`; regenerar QR (Guzzle mock) em `tests/Feature/Channels/EvolutionConnectionLifecycleTest.php`
- [X] T026 [P] [US2] **G7** `EvolutionWebhookAuthTest` — webhook sem `apikey` válido rejeitado; instância desconhecida não cria dados em `tests/Feature/Channels/EvolutionWebhookAuthTest.php`
- [X] T027 [P] [US2] **G6** `ChannelSecretsNotLeakedTest` — `instance_token`/`auth_token` ausentes em `ChannelResource` e em logs; QR não persistido após pareamento em `tests/Feature/Channels/ChannelSecretsNotLeakedTest.php`

**Checkpoint**: US1 e US2 — ambos os provedores conectáveis pela tela, independentes.

---

## Phase 5: User Story 3 - Status, reconectar e desconectar (Priority: P2)

**Goal**: A clínica vê o estado em tempo quase real e pode reconectar/desconectar; trocar de provedor exige desconectar (um ativo por vez).

**Independent Test**: Canal conectado → desconectar → "desconectado"; reconectar → fluxo de pareamento; segundo WhatsApp ativo é recusado.

- [X] T028 [US3] Adicionar endpoints `GET channels/{id}/connection-state` (estado para polling) e estender `reconnect` (Evolution → novo QR; Twilio → revalida) + `destroy` (logout/delete da instância no Evolution) em `app/Http/Controllers/Api/V1/Inbox/ChannelsController.php`/`EvolutionConnectionController.php`
- [X] T029 [US3] Criar command/cron de fallback de estado `channels:reconcile-evolution-state` (reconcilia canais Evolution `conectando`/`ativo` via `connectionState`, garante refletir queda em ≤ 1 min — SC-005) em `app/Console/Commands/` + agendar em `routes/console.php`
- [X] T030 [US3] Adicionar ações de reconectar/desconectar + indicador de status reativo na `ChannelsPage.vue` em `resources/js/pages/settings/ChannelsPage.vue`
- [X] T031 [P] [US3] **G4** `OneActiveWhatsAppPerTenantTest` — segundo canal WhatsApp ativo recusado enquanto houver um ativo; trocar exige desconectar em `tests/Feature/Channels/OneActiveWhatsAppPerTenantTest.php`

**Checkpoint**: US1–US3 — conexão totalmente gerenciável pela tela.

---

## Phase 6: User Story 4 - Mensagens fluem pelo provedor conectado (Priority: P2)

**Goal**: Inbound (texto + mídia) chega na inbox e outbound (incl. notificações) sai pelo provedor ativo da clínica; via não oficial respeita o gate de conformidade (proativo fora da janela → pendente manual).

**Independent Test**: Com Evolution conectado, simular `messages.upsert` (texto e mídia) → aparece na inbox; enviar → roteia pelo Evolution; com Twilio → roteia pelo Twilio; notificação proativa fora da janela no Evolution → `pending_manual`.

- [X] T032 [US4] Refatorar `SendOutboundMessageJob` para usar `ChannelAdapterResolver::for($channel)` (remove `match($channel->type)` hardcoded) em `app/Jobs/Messaging/SendOutboundMessageJob.php`
- [X] T033 [US4] Refatorar `ProcessInboundMessageJob` para resolver o adapter via `ChannelAdapterResolver` (suportar inbound Evolution) em `app/Jobs/Messaging/ProcessInboundMessageJob.php`
- [X] T034 [US4] Estender `EvolutionWebhookController` para tratar `messages.upsert` → `parseInboundWebhook` (texto + mídia) → `ProcessInboundMessageJob` na conversa/canal corretos em `app/Http/Controllers/Webhooks/EvolutionWebhookController.php`
- [X] T035 [US4] Ajustar `OutboundChannelResolver` (Fase 13) para reconhecer o canal **Evolution ativo** do tenant como elegível (mantendo o gate de template aprovado, que bloqueia proativos fora da janela no não oficial) em `app/Domain/Messaging/Notification/Services/OutboundChannelResolver.php`
- [X] T036 [P] [US4] **G1** `ChannelProviderRoutingTest` — `provider=evolution` envia via EvolutionApiAdapter; `twilio` via WhatsAppCloudAdapter em `tests/Feature/Channels/ChannelProviderRoutingTest.php`
- [X] T037 [P] [US4] **G2** `EvolutionCrossTenantTest` (Princípio II) — webhook da instância de A nunca entrega à inbox de B; envio nunca usa instância de B em `tests/Feature/Channels/EvolutionCrossTenantTest.php`
- [X] T038 [P] [US4] **G5** `UnofficialOutboundComplianceTest` (Princípio VI) — proativo fora da janela no Evolution → `pending_manual`; dentro da janela → texto livre enviado em `tests/Feature/Channels/UnofficialOutboundComplianceTest.php`

**Checkpoint**: Todas as US funcionais — provedor selecionável com tráfego completo (texto+mídia).

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T039 [P] Verificar métricas de conexão expostas no exporter + eventos auditáveis emitidos no connect/disconnect/troca
- [X] T040 Rodar `vendor/bin/sail bin pint --dirty --format agent` nos arquivos PHP modificados
- [X] T041 Rodar a suíte da feature: `vendor/bin/sail artisan test --compact tests/Feature/Channels tests/Unit/Channels` (G1–G8 verdes)
- [X] T042 Rodar a suíte completa (`vendor/bin/sail artisan test --compact`) — não regredir o baseline (~1615) — atenção a regressões no domínio Messaging/Inbox (refatoração dos jobs)
- [X] T043 Smoke browser (quickstart §H): subir `evolution-api`, conectar por QR real, enviar/receber (texto+mídia), trocar para Twilio
- [X] T044 Constitution Re-Check (PASS 7/7) + `.specify/feature.json` → DELIVERED + bloco "Key Patterns" no `CLAUDE.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências (compose/config/dirs).
- **Foundational (Phase 2)**: depende do Setup — **BLOQUEIA todas as US**. T008 depende de T004/T006/T007; T009 de T008; T010/T011 de T008.
- **User Stories (Phase 3–6)**: dependem da Foundational. US1 e US2 independentes (paths de conexão distintos). US3 depende de US2 (reconnect/disconnect operam sobre conexões). US4 depende da Foundational (resolver) + reusa webhook criado em US2.
- **Polish (Phase 7)**: depende das US desejadas.

### User Story Dependencies

- **US1 (P1)**: só Foundational. MVP (tela + Twilio).
- **US2 (P1)**: só Foundational. Independente de US1 (caminho Evolution/QR).
- **US3 (P2)**: usa o webhook/conexões de US2; reconnect/disconnect aplicáveis a ambos os provedores.
- **US4 (P2)**: Foundational (resolver) + estende o webhook de US2; toca jobs compartilhados (cuidado com regressão).

### Within Each User Story

- Tests escritos para FALHAR antes da implementação (Princípio IV).
- Endpoints/serviços antes da UI; UI consome o store.

### Parallel Opportunities

- T001–T002 (compose/config) em paralelo.
- T004, T006, T007, T012, T013 (enum/model/interface/eventos/métricas) em paralelo após migração T005.
- US1 e US2 por devs distintos após o checkpoint da Foundational.
- Gates `[P]` (arquivos de teste distintos) em paralelo dentro de cada US.

---

## Parallel Example: Foundational

```bash
Task: "Criar enum ChannelProvider (T004)"
Task: "Estender Channel model (T006)"
Task: "Criar interface SupportsQrConnection (T007)"
Task: "Criar eventos auditáveis (T012)"
Task: "Adicionar métricas de conexão (T013)"
# depois: EvolutionApiAdapter (T008) → resolver (T009) → instance service (T010) / ChannelService (T011)
```

## Implementation Strategy

### MVP First (US1 + US2)

1. Setup + Foundational (motor de adapter + schema).
2. US1 (Twilio pela tela) → valida tela + modelo de provider.
3. US2 (Evolution por QR) → entrega a novidade central.
4. **PARAR e VALIDAR**: conectar ambos os provedores pela tela.

### Incremental Delivery

1. Foundational → motor pronto.
2. + US1 → Twilio configurável pela tela.
3. + US2 → Evolution conectável por QR (novidade).
4. + US3 → gestão de status/reconexão.
5. + US4 → tráfego completo (texto+mídia) pelo provedor ativo.

---

## Notes

- HTTP do Evolution mockado nos testes (Guzzle `MockHandler`) — não depender do container em CI.
- `EvolutionApiAdapter` é irmão do `WhatsAppCloudAdapter` sob o mesmo contrato; o `ChannelAdapterResolver` é o ponto único de seleção (remove `match` hardcoded).
- Server URL/api-key do Evolution via **env** (nunca input do tenant) — sem nova superfície SSRF.
- Princípio VI no não oficial é **reuso** do gate da Fase 13 — NÃO criar bypass.
- Segredos cifrados e fora do `ChannelResource`/logs; QR efêmero.
- Refatoração dos jobs (T032/T033) é o maior risco de regressão — rodar a suíte Messaging/Inbox.
