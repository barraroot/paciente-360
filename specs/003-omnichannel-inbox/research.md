# Research: Fase 3 — Atendimento Omnichannel

**Branch**: `003-omnichannel-inbox` | **Data**: 2026-05-11 | **Status**: Phase 0 — completo

9 decisões técnicas que orientam `data-model.md`, `contracts/openapi.yaml` e a implementação. Cada decisão tem: contexto, alternativas avaliadas, decisão final, racional, implicações práticas.

> Este documento **não revisita decisões de produto** (já cobertas pelos 17 NCs resolvidos via `/speckit.clarify`). Foca em decisões de engenharia que projetam aquelas decisões em código.

---

## R1 — WebSocket layer: Reverb (vs. Soketi, Pusher cloud)

**Decisão**: usar **Laravel Reverb** já provisionado no Docker do projeto.

**Alternativas consideradas**:
- **Pusher cloud**: serviço gerenciado, pago por mensagem. Custaria ~$50–200/mês para 1000 conversas simultâneas × N tenants.
- **Soketi**: alternativa self-hosted compatível Pusher protocol. Maduro mas menos integração nativa Laravel.
- **Reverb**: oficial Laravel, self-hosted, mesmo Pusher protocol no client (Echo).

**Racional**:
- Reverb **já está rodando** no container Docker deste projeto (confirmado pelo usuário). Zero custo de provisioning adicional.
- Integração nativa com `php artisan reverb:start`, queue de broadcasting compartilha Redis do projeto.
- Auth de canal via `routes/channels.php` reusa Sanctum SPA stateful da Fase 0.
- Self-hosted preserva dados (LGPD — mensagem nunca sai do nosso infra), diferente de Pusher cloud onde payload trafega por servidor terceiro.

**Trade-offs aceitos**:
- Reverb é mais novo que Pusher/Soketi — possíveis bugs em escala extrema. Mitigação: stress test obrigatório (R8) antes do go-live.
- Self-hosted exige monitoramento (uptime do container Reverb) — Prometheus gauge `paciente360_reverb_connections` + Sentry para erros do servidor.

**Implicação prática**:
- `routes/channels.php` ganha 2 canais privados: `tenant.{id}.inbox` e `tenant.{id}.conversa.{cid}` validando ability `inbox.view` + pertencimento ao tenant.
- Eventos `ShouldBroadcast` em domínio: `MensagemRecebidaParaInbox`, `MensagemEnviadaParaInbox`, `ConversaCriadaParaInbox`, `ConversaAtribuidaParaInbox`, `UsuarioDigitando`, `MensagemLida`. **Distintos** dos eventos de domínio puros (que vão para `audit_logs` e `eventos_timeline` via listener wildcard) — separação domínio/transporte.

---

## R2 — Provider WhatsApp: Twilio (decidido em NC-1) vs. Meta Cloud API direta

**Decisão (já tomada em `/speckit.clarify` Q1.a)**: **Twilio Programmable Messaging** como provedor da WhatsApp Business API.

**Alternativas consideradas**:
- **Meta Cloud API direta**: integração direta com Graph API da Meta (`graph.facebook.com/v21.0/{phone_number_id}/messages`). Mais barato (~$0.005/msg só Meta). Onboarding via Embedded Signup.
- **Twilio Programmable Messaging**: abstração sobre Meta. Tokens Twilio + Sender configurado no console Twilio. Custa Twilio markup (~$0.005/msg) + Meta. Webhook do Twilio (`X-Twilio-Signature` HMAC).
- **WATI / Wati.io / 360dialog**: outras BSPs (Business Solution Providers). Não consideradas — Twilio é padrão Brasil.

**Racional**:
- Decisão do usuário (Q1.a recomendada A do `/speckit.clarify`). Justificativas registradas:
  - Twilio entrega SDK PHP oficial maduro (`twilio/sdk`).
  - Onboarding mais simples para o cliente final (Twilio Console é mais amigável que Meta Business Manager para clínicas brasileiras).
  - Suporte 24/7 da Twilio cobre incidentes de produção.
  - Permite escalar para outros canais Twilio (SMS, voz) sem nova integração.

**Trade-offs aceitos**:
- **Custo extra ~$0.005/segmento** sobre o preço Meta. Documentado no painel para Admin Clínica entender billing.
- **Meta Quality Rating** continua sendo da conta WABA — Twilio surface o status via webhook próprio mas não controla. Quality Rating queda suspende o número Meta independentemente do Twilio.
- **Twilio tem rate limit próprio**: 5 req/s por account em sandbox, mais em produção. Acumula com rate limit Meta. Mitigação: fila `outbound-messages` com 1 supervisor, throttle interno.

**Implicação prática**:
- `WhatsAppCloudAdapter` em `app/Domain/Messaging/Channel/Adapters/` usa `Twilio\Rest\Client` para envio + listagem de Content Templates.
- Webhook em `POST /api/v1/webhooks/twilio/whatsapp` valida `X-Twilio-Signature` (HMAC SHA-256 com `TWILIO_AUTH_TOKEN`).
- `channels.credentials_encrypted` JSONB armazena `{account_sid, auth_token_encrypted, messaging_service_sid, whatsapp_sender}`.
- Templates aprovados pela Meta aparecem no Twilio Content API (`/v1/Content`) com status `approved`/`pending`/`rejected`. `ChannelTemplateSyncJob` periódico sync.

---

## R3 — Provider Instagram Direct: Meta Graph API direta (vs. Twilio)

**Decisão**: **Meta Graph API direta** para Instagram. Twilio **não suporta** Instagram Direct.

**Alternativas consideradas**:
- **Twilio Conversations** (produto separado do Programmable Messaging): suporta Facebook Messenger mas **não Instagram DM**.
- **Meta Graph API direta**: integração via `graph.facebook.com/v21.0/{ig_business_id}/messages`. Webhook em `/api/v1/webhooks/instagram` valida `X-Hub-Signature-256` (HMAC SHA-256 com `META_APP_SECRET`).
- **MessageBird, Sinch, Sendbird**: outros BSPs com IG DM. Descartados — adicionar mais 1 vendor multiplica complexidade.

**Racional**:
- Twilio não cobre o use case → Meta direto é única opção viável para IG no MVP.
- Onboarding requer Facebook Login OAuth → escopo `instagram_basic + instagram_manage_messages + pages_messaging + pages_show_list`.
- Conta Instagram **precisa ser Profissional** (Business ou Creator) vinculada a uma Página do Facebook (validação no AC-4.2.2).

**Trade-offs aceitos**:
- **2 providers diferentes** (Twilio para WhatsApp + Meta direto para Instagram) → 2 SDKs + 2 webhooks + 2 padrões de auth. Mitigação: interface `ChannelAdapter` abstrai isso para o domínio.
- **Versionamento Graph API independente**: WhatsApp via Twilio ganha proteção do Twilio contra breaking changes Meta; Instagram fica exposto às quebras (R7 cobre estratégia de versão pinada).

**Implicação prática**:
- `InstagramGraphAdapter` usa Guzzle direto com retry middleware (sem SDK Meta oficial PHP maduro).
- Webhook `POST /api/v1/webhooks/instagram` valida `X-Hub-Signature-256` (HMAC SHA-256 com `META_APP_SECRET`).
- Handshake inicial via `GET /api/v1/webhooks/instagram?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...` — retorna `hub.challenge` se `verify_token` bate com `META_WEBHOOK_VERIFY_TOKEN`.
- `channels.credentials_encrypted` para canal Instagram armazena `{page_id, page_access_token_encrypted, instagram_business_account_id, ig_username}`.

---

## R4 — Criptografia em repouso para mensagens: cast `encrypted` Laravel (vs. pgcrypto + coluna binária)

**Decisão**: **cast `encrypted` Laravel** (AES-256-CBC com `APP_KEY`) no campo `messages.body`.

**Alternativas consideradas**:
- **Laravel cast `encrypted`**: aplicação encripta/decripta transparentemente; chave única no `APP_KEY`; valor em coluna `TEXT` base64.
- **PostgreSQL pgcrypto**: extensão `pgcrypto` permite `PGP_SYM_ENCRYPT(body, key)`; busca por conteúdo ainda funciona via funções criptográficas; chaves separadas no DB.
- **Plain text + cifragem no disco** (LUKS/EBS): protege em repouso mas não contra leitura de DB dump.

**Racional**:
- Cast `encrypted` é **padrão Laravel** já testado em milhões de apps; transparente para Eloquent (`$msg->body` retorna texto claro).
- **APP_KEY já existe** + já é critical secret no projeto (Fase 0 cobre).
- **Busca trigram funciona** porque a query é feita em coluna **separada** `messages.body_searchable` (texto normalizado, pode ser plain — research R5 detalha decisão).
- pgcrypto é mais complexo: chave precisa estar no SQL (ou variável de sessão); decifra em todo SELECT (overhead); dump do DB com chave embarcada não protege em backup.

**Trade-offs aceitos**:
- **Busca em conteúdo** requer coluna paralela `messages.body_searchable` plain text — vide R5. Riscos LGPD: essa coluna é tão sensível quanto o `body` plain. Mitigação: mesma retenção 2 anos; anonimização granular cascateia (R9 / NC-14).
- **APP_KEY rotation** é processo crítico — `php artisan key:rotate` da Fase 0 cobre, mas vale documentar no quickstart.

**Implicação prática**:
- `messages.body TEXT` (cifrado via cast `encrypted` — Laravel base64 da AES-CBC com IV embutido).
- `messages.body_searchable TEXT` (plain — usado **apenas** para `pg_trgm` similarity search; nunca exposto ao cliente; nunca em log de aplicação).
- `messages.body_preview VARCHAR(140)` (plain, primeiros 140 chars — para exibir trecho na inbox sem decriptar coluna pesada; mesma classificação LGPD).
- Anonimização cascateia: ao receber `PacienteAnonimizado`, listener zera `body`, `body_searchable` E `body_preview` das mensagens recebidas; mantém metadados.

---

## R5 — Busca full-text em mensagens: pg_trgm (vs. tsvector, ElasticSearch)

**Decisão**: **`pg_trgm` GIN composto** (reuso direto da extensão habilitada na Fase 2).

**Alternativas consideradas**:
- **pg_trgm**: similarity search (`%` operator + `similarity()` function). Suporta erro de digitação, busca parcial. Reuso direto da extensão Fase 2.
- **tsvector + tsquery (FTS nativo PG)**: full-text padrão PG com stemming pt-BR via dicionários. Melhor para texto longo articulado.
- **ElasticSearch dedicado**: index externo. Over-engineering para volume MVP.

**Racional**:
- **pg_trgm cobre casos comuns de inbox**: atendente busca "vacina HPV", "comprovante", "nome do exame" — termos curtos, frequentemente com erro de digitação. Trigram resolve.
- **Reuso direto** da Fase 2 (pacientes) — extensão `pg_trgm`, `unaccent`, `btree_gin` + função `immutable_unaccent(text)` já existem. Zero custo de infra.
- Performance suficiente para 50k conversas/tenant × média 10 mensagens/conversa = 500k mensagens. GIN composto `(tenant_id, body_searchable_normalized gin_trgm_ops)` mantém p95 < 500ms.
- FTS tsvector seria útil para 5M+ mensagens com texto longo; não é o caso aqui.

**Trade-offs aceitos**:
- Trigram pior em frases longas — busca por "consulta de retorno do paciente Maria" pode dar match parcial confuso. Atendentes em onboarding são instruídos a usar termos curtos.
- Coluna paralela `body_searchable` para indexar (vide R4) — overhead de storage estimado +30%.

**Implicação prática**:
- Migration `2026_05_11_create_messaging_messages_table.php` cria coluna `body_searchable_normalized VARCHAR(2000) GENERATED ALWAYS AS (lower(immutable_unaccent(body_searchable))) STORED`.
- Migration trigram indexes: `2026_05_11_add_messaging_trigram_indexes.php` cria `CREATE INDEX messages_body_trgm_idx ON messages USING GIN (tenant_id, body_searchable_normalized gin_trgm_ops)`.
- Query híbrida em `MessageSearchService`:
  ```sql
  SELECT ... FROM messages
  WHERE tenant_id = :tenant_id
    AND (body_searchable_normalized % :q
         OR body_searchable_normalized ILIKE :q_like)
  ORDER BY similarity(body_searchable_normalized, :q) DESC
  LIMIT 50
  ```
- Debounce cliente 350ms (igual busca pacientes Fase 2).

---

## R6 — Circuit breaker: implementação própria (vs. `stechstudio/laravel-circuit-breaker`)

**Decisão**: **implementação própria** (`CircuitBreakerService`) baseada em **Redis**.

**Alternativas consideradas**:
- **`stechstudio/laravel-circuit-breaker`**: lib popular, mas **não atualizada para Laravel 11+** (último release suporta Laravel 9). Forks não estáveis.
- **Hystrix port para PHP**: muito pesado, abstrações Java-style desnecessárias.
- **`league/event-dispatcher` + manual state**: reinventaria a roda.
- **Implementação própria minimal**: ~150 linhas usando Redis para estado.

**Racional**:
- Pattern circuit breaker é simples: 3 estados (`closed`, `open`, `half-open`), contadores de falha em janela deslizante, threshold de abertura, timeout de recovery.
- Apenas 2 providers (Twilio + Meta) precisam de circuit breaker — não justifica peso de lib.
- Redis já está disponível e é rápido para read/write de estado.
- Manutenção própria: 150 linhas + testes vs. dependência abandonada.

**Trade-offs aceitos**:
- Não temos métricas/dashboard pré-construídos como em libs maduras — fazemos métricas Prometheus manuais (R7 cobre).
- Bug em implementação própria pode causar falha silenciosa — testes de unit obrigatórios cobrindo todos os state transitions.

**Implicação prática**:
- `app/Domain/Messaging/Infrastructure/CircuitBreaker/CircuitBreakerService.php`:
  - `Cache::tags(['cb', $provider])->put('state', 'open', $ttl)` para estado.
  - `Cache::tags(['cb', $provider])->increment('failure_count')` em janela 60s.
  - Threshold default: 5 falhas em 60s → abre. Timeout recovery: 30s → meio-aberto.
  - Em meio-aberto, 1 chamada test → se passa, fecha; se falha, reabre.
- Uso:
  ```php
  $cb = app(CircuitBreakerService::class)->for('twilio');
  $cb->call(fn () => $this->twilioClient->messages->create(...));
  // Throws CircuitOpenException se aberto; emite métrica Prometheus
  ```
- Adapter wrapper: `WhatsAppCloudAdapter::send()` e `InstagramGraphAdapter::send()` usam circuit breaker. Cada provider tem instância separada.
- Métrica Prometheus `paciente360_circuit_breaker_state{provider}` reflete estado em tempo real.

---

## R7 — Versionamento Graph API Meta: estratégia de lock por versão + deprecação programada

**Decisão**: **lock pinado** via env var `META_GRAPH_API_VERSION=v21.0` + sentry alerts para deprecação.

**Contexto**:
- Meta libera versão nova da Graph API a cada ~3 meses, com **deprecação após 24 meses** de versões antigas.
- Twilio absorve algumas mudanças via abstraction (mais resistente). Meta direto (Instagram) está totalmente exposto.

**Decisão de processo**:
- `config('messaging.meta.graph_api_version')` lê de env `META_GRAPH_API_VERSION` (default `v21.0`).
- **Lock por versão** em todas as chamadas Graph API: `https://graph.facebook.com/{version}/{path}`.
- **Monitoramento**:
  - Endpoint `/api/health/meta-graph` (interno) faz call dummy `GET /me?access_token=...` mensalmente; se Meta retornar header `X-Fb-Api-Version-Mismatch` ou similar → alerta Sentry.
  - Lista de versões deprecadas vigiada manualmente trimestralmente; PR de upgrade quando versão atual entra nos últimos 6 meses.
- **Plano de migração quando deprecação chega**:
  1. PR upgrade `META_GRAPH_API_VERSION` para versão N+1.
  2. Testes integrados rodam contra sandbox Meta com versão nova.
  3. Deploy em ambiente staging para validar contra tenants reais.
  4. Switch de produção em janela de baixa atividade.

**Alternativas consideradas**:
- **Sempre na última versão** (`vLATEST`): Meta não suporta esse alias para Graph; cada call exige versão explícita.
- **Auto-upgrade via release notes**: sem garantia de retro-compatibilidade; risco alto.

**Implicação prática**:
- Toda chamada `InstagramGraphAdapter` injeta versão: `$client->get("/{$version}/{$path}")`.
- Quando Twilio for atualizado, padrão idêntico aplicado em `TWILIO_CONTENT_API_VERSION` (já está pinado).

---

## R8 — Plano de carga: validar RNF-003 (1000 conversas simultâneas/tenant)

**Decisão**: **Artillery** para stress test orientado a usuário; **k6** como alternativa se Artillery falhar.

**Alternativas consideradas**:
- **Artillery**: YAML-based, simula fluxo de usuário (HTTP + WebSocket), boa para "1000 atendentes em 50 tenants enviam/recebem mensagens em paralelo". Ecosystem JS.
- **k6**: Grafana stack, scripts JS, mais features mas overkill para o cenário.
- **Laravel Octane stress test**: dispara N requests internamente; útil para isolar performance do app, não cobre rede ou WebSocket.

**Racional**:
- Artillery cobre **WebSocket nativamente** — crítico porque RNF-003 é sobre 1000 conversas com inbox aberta + Reverb subscribers.
- Script YAML mais legível para o time; tooling do CI mais simples.

**Cenários a validar**:
1. **Webhook flood**: 1000 webhooks Twilio chegando em 60s, processamento dentro de 5s (limite Twilio).
2. **Inbox load**: 50 tenants × 20 atendentes online cada × inbox com 200 conversas → todos veem update em tempo real < 2s.
3. **Outbound dispatch**: 100 mensagens/s saindo concorrentemente (10 tenants × 10 msg/s) → fila `outbound-messages` mantém SLA.
4. **Reverb broadcast**: 1000 conversas com 5 subscribers cada (5000 conexões WebSocket simultâneas), 100 broadcasts/s → latência p95 < 2s.

**Métricas-alvo**:
- p95 latency mensagem→recipient: < 2s.
- p95 latency webhook→DB: < 1s.
- 0 mensagens perdidas (correlação webhook count vs. messages count).
- Fila `webhooks-meta` nunca > 100 jobs pendentes.
- CPU container Reverb < 70% sustained.
- Erro rate < 0.5%.

**Implicação prática**:
- Diretório `tests/load/` com `inbox-load.yaml`, `webhook-flood.yaml`, `outbound-burst.yaml`, `reverb-broadcast.yaml`.
- Comando `vendor/bin/sail artisan load:run --scenario=inbox-load` invoca Artillery.
- Roda em CI **manualmente** (não bloqueante), mas gera relatório PDF arquivado.
- **Antes do go-live de produção**: rodar todos os 4 scenarios; relatório anexado ao PR de release.

---

## R9 — Idempotência de webhook + dedup + retry strategy

**Decisão**: **tabela `webhook_events` com UNIQUE constraint** + INSERT ON CONFLICT DO NOTHING + retry com backoff exponencial via Horizon.

**Alternativas consideradas**:
- **Cache Redis** com TTL: rápido mas sem persistência (reinício perde dedup window).
- **Tabela dedicada** + UNIQUE: persiste; retomada após reinício; rastreamento de status.
- **Idempotência no domain service**: redundante; melhor parar duplicata o mais cedo possível.

**Racional**:
- Twilio retry quando timeout > 15s; Meta retry > 5s. Sem dedup, 2x retries = 2 mensagens duplicadas.
- Tabela `webhook_events`:
  - `provider VARCHAR(20)` (`twilio` ou `meta` ou `widget`).
  - `external_id VARCHAR(255) NOT NULL` (Twilio `MessageSid`, Meta `message_id`, widget `event_id`).
  - **UNIQUE composto `(provider, external_id)`**.
  - `raw_payload JSONB` (cifrado se contém PII).
  - `received_at TIMESTAMPTZ`.
  - `status VARCHAR(20)` (`received`, `processing`, `processed`, `failed`).
  - `attempts INT DEFAULT 0`.
- Webhook handler:
  ```php
  $inserted = DB::table('webhook_events')
      ->insertOrIgnore([
          'provider' => 'twilio',
          'external_id' => $payload['MessageSid'],
          ...
      ]);
  
  if (!$inserted) {
      Log::info('Webhook duplicate ignored');
      return response()->json(['ok' => true]); // 200 para Twilio parar retry
  }
  
  ProcessInboundMessageJob::dispatch($webhookEventId)
      ->onQueue('webhooks-meta');
  ```
- Job falha → Horizon retry 3x com backoff 5s/30s/300s → após 3 falhas, marca `status='failed'` e dispara `WebhookFalhou` event para audit + Sentry.

**Trade-offs aceitos**:
- INSERT ON CONFLICT custa 1 lookup extra — desprezível.
- Tabela cresce: ~10x volume mensagens. Mitigação: job de purge mensal deleta `webhook_events` com `received_at > 30 days` (não precisa retenção longa, audit já está em `audit_logs`).

**Implicação prática**:
- Migration `2026_05_11_create_messaging_webhook_events_table.php`.
- `WebhookEvent` model em `app/Domain/Messaging/Infrastructure/Webhook/WebhookEvent.php` — sem `BelongsToTenant` (resolvido no job via lookup do canal).
- 4 controllers: `TwilioWhatsAppWebhookController`, `MetaInstagramWebhookController`, `WidgetMessageController`, `TwilioStatusCallbackController` (status delivered/read/failed).

---

## R10 — Resumo das decisões (referência rápida)

| ID  | Tópico                        | Decisão                                                                                  |
|-----|-------------------------------|------------------------------------------------------------------------------------------|
| R1  | WebSocket                     | Laravel Reverb (já provisionado no Docker)                                               |
| R2  | Provider WhatsApp             | Twilio Programmable Messaging (NC-1 Q1)                                                  |
| R3  | Provider Instagram            | Meta Graph API direta (Twilio não suporta IG DM)                                         |
| R4  | Cripto em repouso             | cast `encrypted` Laravel + coluna paralela `body_searchable` para trigram                |
| R5  | Full-text search              | `pg_trgm` GIN composto (reuso Fase 2)                                                    |
| R6  | Circuit breaker               | Implementação própria Redis-backed (~150 linhas)                                         |
| R7  | Versionamento Graph API       | Lock por env `META_GRAPH_API_VERSION`; monitoring deprecação trimestral                  |
| R8  | Plano de carga (RNF-003)      | Artillery YAML; 4 scenarios; rodar manual antes de go-live                                |
| R9  | Idempotência webhook          | Tabela `webhook_events` + UNIQUE `(provider, external_id)` + INSERT ON CONFLICT DO NOTHING |

Nenhuma decisão revisita contratos públicos do spec. Todas projetam decisões já tomadas em escolhas de engenharia. Pronto para Phase 1 (`data-model.md`).
