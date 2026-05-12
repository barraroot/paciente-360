# Quickstart: Fase 3 — Omnichannel Inbox

**Branch**: `003-omnichannel-inbox` | **Data**: 2026-05-11

Guia para provisionar credenciais externas, subir o ambiente local e validar manualmente as 7 user stories da Fase 3 (WhatsApp via Twilio, Instagram via Meta Graph direta, Widget web embutível).

Pré-requisitos: **Fase 0** e **Fase 2** entregues e funcionais (`clinica-alfa.lvh.me` navegável, paciente seedado).

---

## 0. Pré-requisitos do projeto (já existentes — apenas confirme)

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan migrate:fresh --seed --class=DevSeeder
# Deve abrir: http://clinica-alfa.lvh.me (login: admin@clinica-alfa.com.br / password123)
# Reverb container deve estar UP — confirmar:
docker compose ps reverb
```

**Confirmação Reverb**: a Fase 0 já entregou `reverb` container no `docker-compose.yml`; a Fase 3 é a **primeira fase a exercitar broadcast real**. Se o container não estiver subindo, validar:

```bash
vendor/bin/sail logs reverb --tail=50
# Esperado: "Starting server on 0.0.0.0:8080..."
```

---

## 1. Provisionamento externo — **leia antes da implementação dos lotes de integração**

Quando os lotes de integração externa (US-4.1 / US-4.2 / US-4.3) começarem, você precisa **pré-provisionar** as 3 contas externas abaixo e gerar as credenciais. Te aviso explicitamente em cada lote, mas adianta as criações para evitar bloqueio.

### 1.1 Conta Twilio (WhatsApp via NC-1)

1. Criar conta em https://www.twilio.com/try-twilio (gratuita; ganha ~$15 de crédito sandbox).
2. Após login, no Console → **Account → API keys & tokens**:
   - Copiar `Account SID` → `TWILIO_ACCOUNT_SID`.
   - Copiar `Auth Token` (clicar "show") → `TWILIO_AUTH_TOKEN`.
3. Habilitar **WhatsApp Sandbox** em **Messaging → Try it out → Send a WhatsApp message**:
   - Mensagem inicial `join <palavra>` para o número Twilio (instruções na própria página) — pareia seu celular ao sandbox.
   - Anotar **número Twilio sandbox** (`+1 415 523 8886` em geral) → `TWILIO_WHATSAPP_FROM_DEFAULT=whatsapp:+14155238886`.
4. Em **Messaging → Services**, criar um **Messaging Service** dedicado para dev:
   - Nome: `Paciente360 Dev`.
   - Anotar `MGxxxxx...` do serviço (será gravado em `messaging_channels.provider_metadata.messaging_service_sid` quando conectar canal pelo painel).
5. **Sandbox webhook**: na página do WhatsApp Sandbox → seção **Sandbox Settings**:
   - `When a message comes in` → será preenchido após você subir o ngrok (passo 2 abaixo).
   - `Status callback URL` → idem.
6. Templates aprovados: para teste, o sandbox já oferece templates fixos (`Your appointment is coming up on {{1}} at {{2}}`). Para produção, criar templates em **Content Editor** (precisa pré-aprovação Meta — leva ~24h).

### 1.2 App Meta for Developers (Instagram via Graph API direta)

1. Acessar https://developers.facebook.com/ → criar app **Tipo: Business**.
2. Anotar:
   - **App ID** → `META_APP_ID`.
   - **App Secret** (em Settings → Basic → Show) → `META_APP_SECRET`.
3. Em **Add Products**, adicionar **Instagram → Instagram Graph API**.
4. Criar **Instagram test account** ou usar conta de Instagram Profissional vinculada a uma Página Facebook que você administre:
   - Conta IG **precisa ser Business ou Creator** (não pessoal).
   - **Conta IG precisa estar vinculada à Página Facebook** (vínculo via app Instagram do celular → Settings → Account → Linked Accounts).
5. Em **Tools → Graph API Explorer**:
   - User Access Token com escopo: `instagram_basic`, `instagram_manage_messages`, `pages_messaging`, `pages_show_list`, `business_management`.
   - Trocar por **Page Access Token de longa duração** (60d) via endpoint `/oauth/access_token?grant_type=fb_exchange_token`. Salvar o token para o `messaging_channels.credentials_encrypted` quando conectar canal.
6. Anotar:
   - `page_id` (da Facebook Page vinculada).
   - `ig_business_account_id` (em `GET /{page_id}?fields=instagram_business_account`).
7. Gerar **verify_token** aleatório (você inventa qualquer string forte) → `META_WEBHOOK_VERIFY_TOKEN`. Sugestão:
   ```bash
   openssl rand -hex 32
   ```
8. Em **Webhooks → Instagram**:
   - Callback URL: será preenchido após o ngrok subir (passo 2).
   - Verify Token: mesmo valor de `META_WEBHOOK_VERIFY_TOKEN`.
   - **Subscription Fields**: `messages`, `messaging_postbacks`, `message_reactions`.

### 1.3 Storage S3 (NOVO disk `media`)

**Em dev**: usar **MinIO** já incluído no compose da Fase 0 (se estiver) ou subir um:

```yaml
# docker-compose.yml — confirme se já existe o serviço minio; se não, adicione:
minio:
  image: 'minio/minio:latest'
  ports: ['9000:9000', '9001:9001']
  environment:
    MINIO_ROOT_USER: paciente360
    MINIO_ROOT_PASSWORD: paciente360-secret
  command: server /data --console-address ":9001"
  volumes: ['minio_data:/data']
```

Criar bucket `paciente360-media` em http://localhost:9001 → Buckets → Create.

**Em produção**: AWS S3 ou Cloudflare R2. Bucket dedicado **separado** dos imports da Fase 2 (escopo blast radius). SSE-S3 habilitado obrigatoriamente.

### 1.4 ngrok com domínio fixo

```bash
# Instalar ngrok (https://ngrok.com/download)
ngrok config add-authtoken <seu-token-grátis>

# Reservar um domínio gratuito permanente em https://dashboard.ngrok.com/cloud-edge/domains
# Anotar: paciente360-dev.ngrok-free.app  (exemplo — seu vai variar)
```

Por que **domínio fixo**? Webhooks Twilio + Meta exigem URL estável. Sem domínio fixo, cada `ngrok http 80` gera URL nova → você precisa reconfigurar no painel a cada sessão.

---

## 2. Subir ngrok e configurar webhooks externos

```bash
# 1. Sail rodando (Sail nginx escuta na porta 80 do host)
vendor/bin/sail up -d

# 2. Túnel ngrok aponta para 80
ngrok http --domain=paciente360-dev.ngrok-free.app 80

# Resultado:
#   Forwarding: https://paciente360-dev.ngrok-free.app -> http://localhost:80
```

**Agora configure as URLs nos painéis externos:**

### Twilio WhatsApp Sandbox

Em **Messaging → Try it out → Send a WhatsApp message → Sandbox Settings**:

```
When a message comes in:
  POST https://paciente360-dev.ngrok-free.app/api/v1/webhooks/twilio/whatsapp

Status callback URL:
  POST https://paciente360-dev.ngrok-free.app/api/v1/webhooks/twilio/status
```

### Meta Instagram

Em **App → Webhooks → Instagram → Edit Subscription**:

```
Callback URL:    https://paciente360-dev.ngrok-free.app/api/v1/webhooks/instagram
Verify Token:    <valor de META_WEBHOOK_VERIFY_TOKEN gerado no passo 1.2.7>
Subscription Fields: messages, messaging_postbacks, message_reactions
```

Ao clicar **Verify and Save**, Meta dispara `GET .../webhooks/instagram?hub.mode=subscribe&hub.challenge=...` → backend valida o `verify_token` e responde com o `challenge`. Se falhar, conferir:

- O backend está rodando (`vendor/bin/sail logs laravel.test --tail=100`).
- `META_WEBHOOK_VERIFY_TOKEN` no `.env` casa exatamente com o que você digitou no Meta.
- A rota `/api/v1/webhooks/instagram` está em `routes/api.php` com o controller `MetaInstagramWebhookController@verify`.

### Reverb (WebSocket) — **NÃO precisa ngrok local**

Cliente JS no painel conecta direto no container Reverb exposto na porta 8080 do host (`http://localhost:8080`). O ngrok é apenas para receber webhooks **externos** dos providers.

### Widget JS — só precisa ngrok se testar embed em site externo real

Para testar local em HTML estático servindo do mesmo host (`http://clinica-alfa.lvh.me/widget/v1/{key}.js`), basta o Sail.

---

## 3. Variáveis de ambiente novas (acrescentar ao `.env`)

```env
# ──────────────────────────────────────────────────────────────
# Twilio (WhatsApp) — NC-1 Q1.a decidiu Twilio como provider
# ──────────────────────────────────────────────────────────────
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_WHATSAPP_FROM_DEFAULT=whatsapp:+14155238886    # Sandbox padrão; troque por produção
TWILIO_WEBHOOK_SIGNING_KEY="${TWILIO_AUTH_TOKEN}"     # mesmo valor; documenta intenção
TWILIO_CONTENT_API_VERSION=2010-04-01

# ──────────────────────────────────────────────────────────────
# Meta/Facebook (Instagram via Graph API direta)
# ──────────────────────────────────────────────────────────────
META_APP_ID=xxxxxxxxxxxxxxxx
META_APP_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
META_GRAPH_API_VERSION=v21.0
META_WEBHOOK_VERIFY_TOKEN=<token-aleatório-256-bits>  # gerado via `openssl rand -hex 32`

# ──────────────────────────────────────────────────────────────
# Widget público
# ──────────────────────────────────────────────────────────────
WIDGET_PUBLIC_DOMAIN=widget.crm.com.br                # prod
WIDGET_PUBLIC_DOMAIN_DEV=widget.lvh.me                # dev
WIDGET_PUBLIC_PROTOCOL=https

# ──────────────────────────────────────────────────────────────
# Storage S3 (NOVO disk `media`)
# ──────────────────────────────────────────────────────────────
FILESYSTEM_DISK_MEDIA=s3
AWS_BUCKET_MEDIA=paciente360-media
AWS_REGION_MEDIA=us-east-1
AWS_ACCESS_KEY_ID_MEDIA=paciente360                   # MinIO dev (passo 1.3)
AWS_SECRET_ACCESS_KEY_MEDIA=paciente360-secret
AWS_USE_PATH_STYLE_ENDPOINT=true                      # MinIO dev
AWS_ENDPOINT_MEDIA=http://minio:9000                  # MinIO dev

# ──────────────────────────────────────────────────────────────
# Reverb — confirme valores já existentes no .env (Fase 0 já provisionou)
# ──────────────────────────────────────────────────────────────
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=paciente360
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=reverb                                    # nome do container
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# ──────────────────────────────────────────────────────────────
# Configuração de domínio do projeto (já existente Fase 0)
# ──────────────────────────────────────────────────────────────
# Importante: APP_URL precisa bater com o ngrok para callbacks
APP_URL=https://paciente360-dev.ngrok-free.app        # quando ngrok ativo
# OU
APP_URL=http://clinica-alfa.lvh.me                    # quando dev local sem ngrok
```

**Atenção**: ao alternar entre dev local puro e dev com ngrok, troque `APP_URL`. Sanctum stateful e cookies dependem dele.

---

## 4. Migrations + seed da Fase 3

```bash
vendor/bin/sail artisan migrate

# Confirma 12 tabelas messaging_* criadas
vendor/bin/sail artisan db:show

# Habilita extensões PG já carregadas na Fase 2 (idempotente)
vendor/bin/sail artisan tinker --execute 'echo DB::selectOne("SELECT * FROM pg_extension WHERE extname IN ('"'"'pg_trgm'"'"','"'"'unaccent'"'"','"'"'btree_gin'"'"')")->extname;'

# Seed cria 2 canais sandbox em clinica-alfa para testes (NOVO seeder)
vendor/bin/sail artisan db:seed --class=DevSeeder
```

---

## 5. Fluxo manual — Conectar WhatsApp (US-4.1)

1. Login em `http://clinica-alfa.lvh.me/login` como admin-clinica.
2. Navegar para `/panel/canais` → botão **"Conectar WhatsApp"**.
3. Form pede:
   - Nome: `Agendamento WhatsApp Sandbox`
   - Account SID: `<TWILIO_ACCOUNT_SID>`
   - Auth Token: `<TWILIO_AUTH_TOKEN>`
   - Messaging Service SID: `<MGxxxxx do passo 1.1.4>`
   - WhatsApp Sender: `whatsapp:+14155238886`
4. Submeter → backend chama Twilio `GET /Messages?limit=1` para validar credenciais → grava em `messaging_channels` com `status=ativo`.
5. **Validar**:
   - Linha em `messaging_channels` com `type=whatsapp`, `credentials_encrypted` cifrado.
   - Evento `channel.connected` em `audit_logs`.
6. **Teste real**: do seu celular pareado com o sandbox, envie qualquer texto para o número Twilio:
   - Backend recebe webhook em `/api/v1/webhooks/twilio/whatsapp`.
   - Validation HMAC `X-Twilio-Signature` passa.
   - `messaging_webhook_events` ganha linha com `status=processed`.
   - `messaging_conversations` cria nova conversa (paciente NULL — não identificado).
   - `messaging_messages` ganha 1 mensagem com `direction=in`.
   - Inbox em `/panel/inbox` mostra a nova conversa **em tempo real** (Reverb broadcast).
7. **Respondendo**: atendente clica na conversa → digita texto → envia → backend chama Twilio API → mensagem chega no seu celular. Status `sent → delivered → read` aparece via webhook de status.

---

## 6. Fluxo manual — Conectar Instagram (US-4.2)

1. Em `/panel/canais` → botão **"Conectar Instagram"**.
2. Form pede:
   - Nome: `Atendimento Instagram`
   - Page ID: `<page_id do passo 1.2.6>`
   - Page Access Token: `<token de longa duração do passo 1.2.5>`
   - IG Business Account ID: `<ig_business_account_id do passo 1.2.6>`
3. Backend chama Meta Graph API para validar token + obter `ig_username`.
4. **Teste real**: envie DM para a conta IG profissional pareada → Meta dispara webhook → mesma cadeia do passo 5 acima.

---

## 7. Fluxo manual — Widget Web (US-4.3)

1. Em `/panel/canais` → botão **"Criar Widget Web"**.
2. Form pede:
   - Nome: `Site Principal`
   - Domínios autorizados: `http://localhost:8000`, `https://clinica-alfa.com.br`
   - Aparência: cor primária, posição (bottom-right), button label "Fale conosco"
   - Horário: 08:00-18:00 seg-sex
   - Comportamento fora do horário: `fila` (recebe mas avisa)
   - Pre-chat form: `opcional`
3. Backend gera `public_key` (64 chars hex aleatório) e config.
4. **Snippet embed**: clicar no botão "Copiar snippet" → cola em qualquer HTML:
   ```html
   <script async src="https://widget.lvh.me/widget/v1/abc123def456.../widget.js"></script>
   ```
5. **Teste**: criar `/tmp/test-widget.html`:
   ```html
   <!doctype html><html><head><meta charset="utf-8"><title>Test</title></head>
   <body><h1>Teste widget</h1>
   <script async src="http://widget.lvh.me/widget/v1/<sua-public-key>.js"></script>
   </body></html>
   ```
6. Abrir o HTML em `http://localhost:8000/test-widget.html` (servidor estático local).
7. Botão flutuante aparece → clicar → digitar mensagem → enviar.
8. **Validar**: inbox do tenant em `/panel/inbox` recebe conversa em tempo real com `channel.type=web`.

---

## 8. Fluxo manual — Inbox + Atribuição + Transferência (US-4.4 + US-4.5)

1. Em `/panel/inbox`, ver lista de conversas (filtros: status, canal, atendente, busca trigram).
2. Selecionar conversa "Sem atendente" → botão **"Pegar para mim"** → atribui.
3. **Auto-atribuição**: configurar regra em `/panel/inbox/regras-atribuicao` → estratégia `round_robin` → nova conversa entra → auto-atribuído ao próximo atendente online com vaga.
4. **Transferência**: na conversa atribuída → botão "Transferir" → escolher usuário-alvo ou role + nota mínima 10 chars → submeter.
5. **Validar**:
   - `messaging_conversation_assignments` ganha 2 linhas (anterior com `unassigned_at` preenchido + nova).
   - Timeline do paciente mostra `conversa.transferida` com nota.
   - Atendente alvo recebe notificação browser push (se `notification_preferences.browser_push=true`).

---

## 9. Fluxo manual — Modo Humano Assume (US-4.6)

Esta fase **não** tem IA ativa. Endpoint serve como **contrato** para Fase 4.

1. Numa conversa, clicar **"Assumir manualmente"** → escolher duração (1–24h, default 4h).
2. **Validar**:
   - `conversations.ai_paused_until` = agora + 4h.
   - `conversations.ai_pause_set_by` = user atual.
   - Evento `ConversaAssumidaPorHumano` em `audit_logs` + `eventos_timeline`.
3. **Retomar**: botão "Liberar para IA" → `ai_paused_until=NULL`, evento `ConversaRetomadaPelaIA`.
4. **Confirmação contratual**:
   ```bash
   vendor/bin/sail artisan test --filter=ConversaIATogglingContractTest --compact
   # Deve passar — congela API para Fase 4 plugar subscriber sem retrofit.
   ```

---

## 10. Fluxo manual — Respostas Rápidas (US-4.7)

1. Em `/panel/inbox/respostas-rapidas` → criar:
   - Atalho: `/preço`
   - Escopo: `tenant` (compartilhada — exige ability `quick_reply.manage`)
   - Conteúdo: `Olá {primeiro_nome}, nossa consulta custa R$ 350. Posso agendar?`
2. Outra resposta como `private` (escopo do user): `/oi` → `Oi {primeiro_nome}, sou {atendente}.`
3. **Teste no inbox**: numa conversa com paciente identificado, digitar `/preço` → autocomplete sugere → ENTER → mensagem é renderizada server-side via `POST /quick-replies/{id}/render` → preview substitui `{primeiro_nome}` por nome real → atendente confirma → envia.
4. **Validar**: variáveis substituídas; `quick_reply.usage_count` incrementa.

---

## 11. Princípio VI — Janela 24h (NÃO-NEGOCIÁVEL)

1. Numa conversa WhatsApp **sem mensagem inbound recente** (>24h):
2. Atendente tenta enviar texto livre → UI mostra aviso "Janela expirada".
3. Atendente força envio via API direta:
   ```bash
   curl -X POST https://clinica-alfa.lvh.me/api/v1/inbox/conversations/<id>/messages \
        -H "Idempotency-Key: $(uuidgen)" \
        -H "Content-Type: application/json" \
        -d '{"content_type":"text","body":"força bruta"}'
   ```
4. **Resposta esperada**: HTTP 422
   ```json
   {
     "message": "Janela de 24h expirada; selecione um template aprovado para retomar contato.",
     "code": "mensagem.bloqueada_fora_janela",
     "details": {
       "last_inbound_message_at": "2026-05-09T14:30:00Z",
       "hours_since_last_inbound": 48.2,
       "available_templates_count": 3
     }
   }
   ```
5. **Validar audit**: `audit_logs.action = mensagem.bloqueada_fora_janela`.
6. **Caminho aprovado**: enviar via template:
   ```bash
   curl ... -d '{"content_type":"template","template":{"provider_template_id":"HX...","variables":{"1":"João"}}}'
   ```
   → passa (HTTP 201).

---

## 12. Stress test (RNF-003)

```bash
# 4 cenários Artillery (research R8)
vendor/bin/sail artisan load:run --scenario=inbox-load
vendor/bin/sail artisan load:run --scenario=webhook-flood
vendor/bin/sail artisan load:run --scenario=outbound-burst
vendor/bin/sail artisan load:run --scenario=reverb-broadcast

# Relatórios em storage/load-reports/
```

Métricas-alvo (recap R8):
- p95 mensagem→recipient < 2s
- 0 mensagens perdidas (correlação webhook count vs. messages count)
- Fila `webhooks-meta` nunca > 100 jobs pendentes
- CPU Reverb < 70% sustained

---

## 13. Testes automatizados

```bash
# Suite Fase 3
vendor/bin/sail artisan test --compact tests/Feature/Fase3/
vendor/bin/sail artisan test --compact tests/Unit/Messaging/

# Regressão Fases 0 + 2
vendor/bin/sail artisan test --compact

# Pint
vendor/bin/sail bin pint --dirty --format agent

# OpenAPI drift
vendor/bin/sail artisan openapi:check
```

---

## 14. E2E (Playwright)

```bash
# Jornada nova:
#   1. Paciente envia WhatsApp simulado (webhook Twilio fixture)
#   2. Atendente vê na inbox em tempo real (Reverb assertion)
#   3. Atendente responde
#   4. Status sent → delivered → read aparece via webhook fixture
vendor/bin/sail npx playwright test tests/e2e/inbox-whatsapp-roundtrip.spec.ts
```

---

## 15. Troubleshooting

| Sintoma | Causa provável | Resolução |
|---|---|---|
| Webhook Twilio retorna 403 | `X-Twilio-Signature` inválido — geralmente `APP_URL` divergente do ngrok | Confirmar `APP_URL` no `.env` bate com URL do ngrok (sem `/` final) |
| Webhook Meta nunca chega | Verify token errado ou Page não vinculada a IG Business | Re-verificar token em **App → Webhooks → Instagram → Edit Subscription**; confirmar IG profissional vinculada |
| Reverb não conecta no painel | CSP do navegador / cookies cross-port | Confirmar `VITE_REVERB_HOST=localhost` no client + `REVERB_HOST=reverb` no server |
| Mídia upload falha (S3 PUT) | MinIO sem bucket | Criar bucket `paciente360-media` em http://localhost:9001 |
| Mensagem stuck em `queued` | Circuit breaker aberto para provider | `vendor/bin/sail artisan tinker --execute 'app(\App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService::class)->for("twilio")->reset();'` |
| Atendente não recebe notificação | Token Sanctum stateful + Reverb auth falham por subdomínio | Confirmar Sanctum SPA stateful para subdomínio do tenant |
| Janela 24h sempre bloqueia | `last_inbound_message_at` não atualiza | Verificar listener `MensagemRecebidaUpdatesConversationListener` |
| Templates Twilio nunca aparecem | `ChannelTemplateSyncJob` não rodou | `vendor/bin/sail artisan messaging:sync-templates --channel-id=<id>` |
| Widget não carrega no site externo | `Origin` não está em `allowed_origins` | Editar widget config em `/panel/canais/widget/{id}/editar` |

---

## 16. URLs locais úteis

- `http://clinica-alfa.lvh.me/panel/canais` — gestão de canais
- `http://clinica-alfa.lvh.me/panel/inbox` — inbox unificada
- `http://clinica-alfa.lvh.me/panel/inbox/respostas-rapidas` — quick replies
- `http://clinica-alfa.lvh.me/panel/inbox/regras-atribuicao` — auto-assign rules
- `http://widget.lvh.me/widget/v1/{public_key}.js` — bundle widget JS
- `http://localhost:9001` — MinIO console (S3 dev)
- `http://localhost:8025` — Mailpit
- `http://paciente360-dev.ngrok-free.app/api/v1/webhooks/twilio/whatsapp` — webhook URL (configurar no Twilio)
- `http://paciente360-dev.ngrok-free.app/api/v1/webhooks/instagram` — webhook URL (configurar no Meta)

---

## 17. Definição de Pronto (cross-ref do spec § 9)

Verificar **antes** de mergear:

- [ ] `migrate:fresh --seed --class=DevSeeder` cria 12 tabelas `messaging_*` novas + 2 canais sandbox seedados em clinica-alfa.
- [ ] Suite Fase 3: ≥ 150 testes novos verdes.
- [ ] Coverage Fase 3 ≥ 75%, global ≥ 70%.
- [ ] `openapi:check` exit 0 (drift entre Fase 0 + Fase 2 + Fase 3).
- [ ] Pint clean.
- [ ] 1 E2E Playwright verde (`inbox-whatsapp-roundtrip.spec.ts`).
- [ ] `InboxTenantIsolationTest` cobre 100% dos endpoints autenticados novos.
- [ ] Webhook signature validation funcional (Twilio + Meta + Widget).
- [ ] **Princípio VI**: teste de bloqueio fora janela 24h passa (`MessageDispatchServicePrincipioVITest`).
- [ ] **Princípio III**: contrato `ConversaIATogglingContractTest` passa.
- [ ] Stress test Artillery: 4 cenários executados; relatórios anexados ao PR.
- [ ] Documentação `quickstart.md` atualizada com observações reais (este arquivo).

---

## 18. Checklist enxuto de provisioning (cópia rápida)

Para você marcar enquanto provisiona — **levanto isso de novo no início do lote de integração externa**:

- [ ] Conta Twilio criada + WhatsApp Sandbox pareado com seu celular
- [ ] `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `MGxxxxx` (Messaging Service) anotados
- [ ] App Meta for Developers criado + Instagram Graph API adicionado
- [ ] `META_APP_ID`, `META_APP_SECRET` anotados
- [ ] Conta IG profissional vinculada a Page FB + `page_id` + `page_access_token` (60d) + `ig_business_account_id` anotados
- [ ] `META_WEBHOOK_VERIFY_TOKEN` gerado (`openssl rand -hex 32`)
- [ ] ngrok domínio fixo reservado (`paciente360-dev.ngrok-free.app` ou similar)
- [ ] Bucket S3/MinIO `paciente360-media` criado + chaves de acesso anotadas
- [ ] Reverb container UP no compose
- [ ] `.env` atualizado com todas as variáveis acima
- [ ] Webhooks Twilio + Meta configurados nos painéis com URLs do ngrok
