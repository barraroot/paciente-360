# Research — Integração de Canal WhatsApp: Twilio | Evolution API (014)

Decisões técnicas. Reusa o domínio Messaging (Fase 3) e o gate de outbound (Fase 13). Evolution API consultada via Context7 (`/evolution-foundation/evolution-api`, v2).

## R1 — Dimensão `provider` no Channel

- **Decisão**: adicionar coluna `provider` (`varchar(20)`, default `'twilio'`) em `messaging_channels`. Enum `ChannelProvider { twilio, evolution }`. `type` continua `'whatsapp'` para ambos (Evolution é WhatsApp não oficial). Canais existentes recebem `'twilio'` no default da migração (retrocompatível).
- **Rationale**: `type` representa o canal (whatsapp/instagram/web); `provider` representa COMO ele é operado. Separar evita poluir `type` com variantes e mantém a inbox agnóstica.
- **Alternativas rejeitadas**: novo `type='whatsapp_evolution'` (quebra filtros/relatórios existentes por tipo); flag em `provider_metadata` (não indexável/constraint-able para a regra "um ativo por vez").

## R2 — Arquitetura: Evolution auto-hospedado, instância por tenant

- **Decisão**: nós hospedamos **um servidor Evolution API** (container Docker). Cada canal de tenant = **uma instância** (`POST /instance/create` com `instanceName` determinístico, ex.: `tenant_{id}` ou UUID armazenado). `EVOLUTION_API_URL` + `EVOLUTION_API_KEY` (global) vêm de **env**, nunca do tenant.
- **Rationale**: o tenant não opera infra; só pareia o WhatsApp dele via QR. Elimina superfície SSRF (URL não é input) e simplifica a UI (sem credenciais de servidor). Confirma a intenção do usuário ("serviço novo no backend" + "instalar no docker para teste").
- **Persistência**: `provider_metadata` guarda `instance_name` e `instance_token` (token por instância retornado pelo Evolution) — `instance_token` tratado como segredo (cifrado/oculto). `number`/identidade preenchidos após pareamento.
- **Alternativas rejeitadas**: tenant fornece URL/credenciais do próprio Evolution (SSRF + suporte inviável); um Evolution por tenant (desperdício de recursos no MVP).

## R3 — Contrato de adapter + ciclo de vida por QR

- **Decisão**: `EvolutionApiAdapter implements ChannelAdapter` (send/validateCredentials/parseInboundWebhook/getType='whatsapp') **+** nova interface `SupportsQrConnection` com: `createInstance(Channel): InstanceConnection`, `getQrCode(Channel): QrPayload`, `connectionState(Channel): string (open|connecting|close)`, `disconnect(Channel): void`. O Twilio NÃO implementa `SupportsQrConnection` (não tem QR).
- **Rationale**: mantém o contrato uniforme de transporte (`ChannelAdapter`) e isola o que é específico do pareamento por sessão. Endpoints de QR/estado só são oferecidos quando o adapter resolvido `instanceof SupportsQrConnection`.
- **Mapeamento de estado**: Evolution `open→ativo`, `connecting→conectando`, `close→desconectado`; erro HTTP/serviço fora → `degradado`/`invalido` (reusa enum de status do `Channel`).

## R4 — Resolução de adapter provider-aware

- **Decisão**: criar `ChannelAdapterResolver::for(Channel): ChannelAdapter` que resolve por `(type, provider)`: `whatsapp+twilio→WhatsAppCloudAdapter`, `whatsapp+evolution→EvolutionApiAdapter`, `instagram→InstagramGraphAdapter`, `web→WebWidgetAdapter`. Refatorar `SendOutboundMessageJob::resolveAdapter` (hoje `match($channel->type)`) e `ProcessInboundMessageJob` para usar o resolver.
- **Rationale**: ponto único de seleção; remove o `match` hardcoded; aberto a novos provedores. `MessageDispatchService` (que hoje recebe `WhatsAppCloudAdapter` no construtor) passa a depender do resolver OU permanece desacoplado — o adapter real é resolvido no job de envio (que já é onde o adapter é usado). **Decisão**: o resolver atua no `SendOutboundMessageJob` (onde o envio acontece); `MessageDispatchService.send` continua só persistindo+enfileirando (não toca adapter), então sua assinatura não muda.
- **Alternativas rejeitadas**: container binding contextual por tag (mais mágico, menos explícito); manter `match` e adicionar caso (não escala e ignora `provider`).

## R5 — Ingestão de webhook do Evolution

- **Decisão**: `EvolutionWebhookController` (rota webhook pública, fora de auth de tenant) recebe eventos do Evolution configurados na criação da instância (webhook por instância). Trata `messages.upsert` (inbound → `ProcessInboundMessageJob` via `EvolutionApiAdapter::parseInboundWebhook`) e `connection.update` (atualiza `Channel.status`). **Resolve o tenant pela instância** (`instance` no payload → `Channel.provider_metadata->instance_name` → tenant). Valida autenticidade por segredo/apikey configurado.
- **Rationale**: espelha `TwilioWhatsAppWebhookController`/`MetaInstagramWebhookController`. Resolver tenant pela instância garante Princípio II (nunca por parâmetro livre). Registra payload bruto (Princípio V).
- **Fallback de status (SC-005)**: cron leve (`every minute` ou a cada 30s via `connectionState`) para canais Evolution `conectando`/`ativo` reconcilia o estado caso o webhook `connection.update` se perca — garante refletir queda em ≤ 1 min.

## R6 — Conformidade Princípio VI no não oficial (reuso do gate da Fase 13)

- **Decisão**: NÃO criar mecanismo novo. O `OutboundNotificationDispatcher` (Fase 13) já exige um `ChannelTemplate` aprovado para envio **fora da janela 24h**; como o Evolution não possui templates HSM aprovados pela Meta, qualquer notificação proativa fora da janela cai naturalmente em `pending_manual/no_template`. **Dentro da janela**, o dispatcher já permite texto livre (`freeFormBody`) — comportamento legítimo. Resultado: a política conservadora da clarify Q1 é satisfeita por reuso.
- **Ajuste mínimo necessário**: o `OutboundChannelResolver` (Fase 13) hoje resolve **somente WhatsApp Twilio ativo**. Precisa reconhecer o canal Evolution ativo do tenant como canal elegível (mesmo `type='whatsapp'`). A regra "fora da janela exige template aprovado" continua valendo e bloqueia proativos no Evolution automaticamente.
- **Rationale**: defesa em profundidade já existente; menos código, menos risco. Mensagens de saída inbound-triggered (dentro da janela) fluem; proativos não.
- **Nota**: a via oficial (Twilio) permanece 100% inalterada.

## R7 — Regra "um provedor ativo por vez" (clarify Q2)

- **Decisão**: `ChannelService::connect` recusa criar/ativar um canal WhatsApp se já existir outro canal WhatsApp `ativo`/`conectando` para o tenant (independente do provedor). Trocar exige `disconnect` do atual. Enforce com índice parcial UNIQUE `(tenant_id) WHERE type='whatsapp' AND status IN ('ativo','conectando')` (defesa em profundidade) + checagem na aplicação com mensagem orientativa.
- **Rationale**: elimina ambiguidade de roteamento de saída e de qual canal recebe. Simples e seguro.
- **Alternativas rejeitadas**: múltiplos simultâneos (exigiria regra de seleção de canal por mensagem — fora do escopo decidido).

## R8 — Ambiente Docker de teste (Evolution)

- **Decisão**: adicionar serviço `evolution-api` (`evoapicloud/evolution-api:latest`) ao `compose.yaml`, reusando o `pgsql` (schema/DB dedicado `evolution`) e o `redis` existentes; env mínimos: `SERVER_URL`, `AUTHENTICATION_API_KEY`, `DATABASE_PROVIDER=postgresql`, `DATABASE_CONNECTION_URI`, `CACHE_REDIS_*`. Porta 8080 exposta na rede `sail`. `.env` da app ganha `EVOLUTION_API_URL=http://evolution-api:8080` + `EVOLUTION_API_KEY`.
- **Rationale**: paridade dev/prod; permite smoke real de pareamento por QR em teste. Testes automatizados NÃO dependem do container (HTTP mockado).
- **Cuidado**: o container Evolution é para **dev/teste**; produção provisiona o mesmo serviço com segredos próprios. Documentado no quickstart.

## R9 — Segredos e segurança (Princípios I/VII)

- **Decisão**: `instance_token` (Evolution) e credenciais Twilio cifrados (cast `encrypted` / `credentials_encrypted`, padrão existente). Nunca retornados pela API após cadastro (`ChannelResource` não expõe segredos — já é o caso). QR Code é repassado ao front mas **não persistido** após pareamento. Webhook do Evolution validado por header `apikey` (segredo compartilhado via env).
- **Rationale**: Princípio I (cifragem) + VII (validação de webhook, sem vazar segredo).

## R10 — Testes e gates

- **Decisão**: HTTP do Evolution mockado via Guzzle `MockHandler` (ou um fake `EvolutionApiAdapter`), sem depender do container. Gates:
  - **G-iso**: webhook da instância do tenant A nunca entrega à inbox de B; envio nunca usa a instância de B.
  - **G-routing**: tenant com `provider=evolution` envia pelo `EvolutionApiAdapter`; com `twilio`, pelo `WhatsAppCloudAdapter`.
  - **G-VI**: notificação proativa fora da janela no Evolution → `pending_manual` (reuso Fase 13).
  - **G-secrets**: `instance_token`/credenciais não aparecem em `ChannelResource` nem em logs.
  - **G-one-active**: segundo canal WhatsApp ativo é recusado enquanto houver um ativo.
  - **G-qr**: connect cria instância e retorna QR; estado transiciona com `connection.update`.
- **Rationale**: Princípio IV (test-first) + II + VI + I.
