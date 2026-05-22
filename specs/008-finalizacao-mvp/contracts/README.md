# Contracts — Finalização do MVP (Fase 8)

**Branch**: `008-finalizacao-mvp` | **Date**: 2026-05-21 | **Phase**: 1

> Esta pasta contém os contratos de interface externos da Fase 8: (i) OpenAPI v1 da **API Pública** (Q14), (ii) schemas dos **eventos de webhook** (Q17), (iii) schema da **portabilidade de dados** (Q28). Endpoints internos da SPA Vue (`/api/v1/...` tenant) NÃO são parte deste contrato — vivem em `routes/api.php` documentados via Scribe.

---

## 0. Estrutura

```text
contracts/
├── README.md                            # Este arquivo — índice e convenções
├── api-public-v1.yaml                   # OpenAPI 3.0 consolidado da API Pública (Q14)
├── webhook-events.yaml                  # Schema dos 13 eventos do catálogo (Q17)
└── portability-schema-v1.json           # JSON Schema do arquivo de portabilidade (Q28)
```

> **Decisão**: contratos de API Pública v1 ficam **consolidados em 1 único arquivo OpenAPI** (`api-public-v1.yaml`) em vez de split por recurso. Razão: API Pública v1 é pequena (~30 endpoints), parceiros externos preferem um único `download → import no Postman/Insomnia` → não há ROI em fragmentar. Endpoints internos (que são ~60) continuam documentados pelo Scribe automaticamente a partir das anotações de Controller.

---

## 1. API Pública v1 — Escopo (Q14)

**6 recursos expostos**:

| Recurso | Verbos | Mascaramento aplicado |
|---|---|---|
| `/v1/patients` | GET (list, show), POST, PATCH | Sem PII compartilhamento se `share_with_integrations_consent=false` |
| `/v1/appointments` | GET (list, show), POST, PATCH, DELETE | — |
| `/v1/messages` | GET (list, show) | Read-only para evitar bypass de guardrails Fase 3 |
| `/v1/prescriptions` | GET (list, show) | Read-only; controladas sempre mascaradas (items.medication = `<protected>`) |
| `/v1/appointment-types` | GET (list, show) | Read-only |
| `/v1/professionals` | GET (list, show) | Read-only |

**Recursos NÃO expostos** (retornam 404 — não 401, para não vazar existência):

- `/v1/campaigns` — Módulo 1 é interno do tenant
- `/v1/reports/*` — métricas internas
- `/v1/billing` / `/v1/plans` — financeiro
- `/v1/audit-logs` — auditoria interna
- `/v1/ai-decisions` — segredo operacional
- `/v1/webhooks` / `/v1/api-tokens` — meta-configuração
- `/v1/privacy/*` — fluxos LGPD são processo humano

### Autenticação (Q18)

- **Default**: Sanctum hashado SHA-256 via header `Authorization: Bearer paciente360_<token>` (formato Fase 4 reaproveitado).
- **Opt-in enterprise**: OAuth 2.0 Client Credentials via `laravel/passport`.
  - `POST /oauth/token` (grant_type=`client_credentials`, scope=`api:public:*`) → retorna JWT de 1h.
  - JWT em header `Authorization: Bearer <jwt>`.

### Rate limiting (Q15)

| Plano | Req/min por token | Hard cap por IP |
|---|---|---|
| básico | 100 | 10.000 |
| pro | 1.000 | 10.000 |
| enterprise | 5.000 | 10.000 |

Headers em **toda** resposta:
- `X-RateLimit-Limit: <plan_limit>`
- `X-RateLimit-Remaining: <count>`
- `X-RateLimit-Reset: <unix_timestamp>`
- `Retry-After: <seconds>` (apenas em 429)

### Versionamento

- Path-based: `/api/public/v1/...` na app + alias `/v1/...` no subdomínio se configurado.
- Breaking change → introduzir `/v2/`. Versão obsoleta retorna headers `Deprecation: true` e `Sunset: <ISO8601>`.
- Política de deprecation: mínimo 6 meses entre `Sunset` e shutdown.

### Idempotência (NFR-9)

- Toda operação **write** (POST, PATCH, DELETE) aceita header opcional `Idempotency-Key: <uuid>`.
- Requests com mesma chave em janela de 24h retornam o **mesmo response** (cached). Implementação reusa pattern Stripe.

### Códigos de erro padronizados

| Código | Significado | Body |
|---|---|---|
| 400 | Validation error | `{error: "validation_failed", errors: {field: ["msg"]}}` |
| 401 | Token inválido/ausente | `{error: "unauthenticated"}` |
| 403 | Token válido sem permissão para o recurso | `{error: "forbidden"}` |
| 404 | Recurso não existe **ou** fora do escopo v1 | `{error: "not_found"}` (não distingue) |
| 422 | Regra de negócio violada | `{error: "unprocessable", reason: "<code>"}` |
| 429 | Rate limit excedido | `{error: "rate_limited", retry_after: <seconds>}` |
| 503 | Tenant suspenso | `{error: "tenant_suspended"}` |

---

## 2. Webhook Events — Catálogo (Q17)

**13 eventos materiais expostos a integradores externos**:

1. `PacienteCriado` (Fase 2)
2. `PacienteAtualizado` (Fase 2)
3. `AgendamentoCriado` (Fase 5)
4. `AgendamentoConfirmado` (Fase 5)
5. `AgendamentoCancelado` (Fase 5)
6. `AgendamentoReagendado` (Fase 5)
7. `ConsultaRealizada` (Fase 5)
8. `PrescricaoCriada` (Fase 7)
9. `PrescricaoCancelada` (Fase 7)
10. `ReceitaRenovada` (Fase 7)
11. `ReceitaProximaDoVencimento` (Fase 7)
12. `RetornoDisparado` (Fase 6 — gated por feature flag)
13. `EscalonamentoParaHumano` (Fase 4 IA — gated por feature flag)

**Excluídos** do catálogo público (mesmo se assinados):
- Eventos de campanha (Módulo 1) — internos
- Webhooks (auto-referência) — loop
- Audit logs e decisões IA com prompt — vazamento

### Envelope universal

Todo payload de webhook tem estrutura:

```json
{
  "event_type": "AgendamentoCriado",
  "event_id": "<uuid>",
  "tenant_id": 123,
  "correlation_id": "<uuid>",
  "occurred_at": "2026-05-21T14:00:00Z",
  "delivery_attempt": 1,
  "data": {
    "...evento-específico..."
  }
}
```

### Headers HTTP do POST

| Header | Valor |
|---|---|
| `Content-Type` | `application/json` |
| `User-Agent` | `Paciente360-Webhook/1.0` |
| `X-CRM-Event-Type` | `AgendamentoCriado` |
| `X-CRM-Event-Id` | `<uuid>` |
| `X-CRM-Correlation-Id` | `<uuid>` |
| `X-CRM-Tenant-Id` | `123` |
| `X-CRM-Signature` | `sha256=<hex>` — HMAC SHA-256 do body raw |
| `X-CRM-Delivery-Attempt` | `1` (1-5) |

### Validação HMAC pelo receptor

```php
$expected = hash_hmac('sha256', $rawBody, $secret);
$signature = explode('=', $request->header('X-CRM-Signature'))[1];
hash_equals($expected, $signature); // true se válido
```

### Retry policy (Q16)

| Tentativa | Delay desde a anterior | Total transcorrido |
|---|---|---|
| 1 | imediato | 0 |
| 2 | 30s | 30s |
| 3 | 2min | 2,5min |
| 4 | 10min | 12,5min |
| 5 | 1h | 1h12,5min |
| (esgotado) | 6h até desistir | ~7,5h |

Após 5 falhas → entrada em DLQ com 30 dias de retenção.

### Mascaramento condicional

- Receitas controladas: `data.items[].medication = "<protected>"` independente do scope.
- Pacientes com `share_with_integrations_consent=false`: `data.patient.name = "<consent_withheld>"`, demais PII mascarados.

---

## 3. Portabilidade — Schema JSON v1 (Q28)

Arquivo gerado em S3 quando paciente solicita Portabilidade (LGPD Art. 18º V).

### Estrutura de alto nível

```json
{
  "schema_version": "1.0",
  "exported_at": "2026-05-21T14:00:00Z",
  "tenant": {
    "id": 123,
    "name": "Clínica QA",
    "slug": "qa-clinic"
  },
  "patient": {
    "id": 456,
    "nome": "Maria Silva",
    "cpf": "123.456.789-00",
    "rg": null,
    "data_nascimento": "1985-03-15",
    "telefone": "+5511999999999",
    "email": "maria@example.com",
    "endereco": { ... },
    "convenio": { ... },
    "tags": ["vacinação", "preventivo"],
    "communication_preferences": { ... },
    "created_at": "2024-01-15T10:00:00Z"
  },
  "consents": [
    {
      "channel": "whatsapp",
      "finalidade": "marketing",
      "state": "granted",
      "granted_at": "2024-01-15T10:05:00Z",
      "terms_version": "2.1"
    }
  ],
  "timeline": [
    { "type": "ConsultaRealizada", "occurred_at": "2024-02-10T...", "professional": {...}, "appointment_type": {...} }
  ],
  "appointments": [
    {
      "id": 789,
      "starts_at": "2024-02-10T14:00:00Z",
      "professional": { "id": 12, "nome": "Dr. João" },
      "appointment_type": { "id": 5, "nome": "Consulta Cardiológica" },
      "status": "realizada"
    }
  ],
  "prescriptions": [
    {
      "id": 321,
      "type": "comum",
      "issued_at": "2024-02-10T15:00:00Z",
      "expires_at": "2024-05-10T...",
      "professional": { ... },
      "items": [
        { "medication": "Losartana 50mg", "posology": "1 cp ao dia", "...": "..." }
      ]
    },
    {
      "id": 322,
      "type": "controlada",
      "issued_at": "2024-03-15T...",
      "expires_at": "2024-04-15T...",
      "items": [
        { "medication": "<protected>", "posology": "<protected>" }
      ]
    }
  ],
  "messages_metadata": [
    {
      "conversation_id": 999,
      "channel": "whatsapp",
      "direction": "inbound",
      "occurred_at": "2024-02-09T...",
      "body": "Olá, quero agendar..."
    }
  ]
}
```

> **Nota sobre mensagens**: corpo (`body`) **só é incluído** se paciente não fez opt-out de inclusão de mensagens. Caso contrário, fica `"body": "<opted_out>"`. Decision em research.md §3.4.

### Versionamento

- `schema_version` semântico (`MAJOR.MINOR`).
- v1.0 é o initial. Breaking changes → v2.0.
- Receptor (paciente) deve ler `schema_version` antes de processar.

### Entrega

- Arquivo S3 em `privacy/portability/{patient_id}/{request_id}.json`
- URL assinada via `Storage::disk('s3')->temporaryUrl($path, now()->addDays(7))`
- E-mail ao paciente com link + instruções
- Audit log de cada download (primeira vez popula `downloaded_at`)
- Expiração: 7 dias após geração; novo link sob solicitação (deadline LGPD não reinicia)

---

## 4. Diretrizes para implementação

### Convenção de nomes (camelCase vs snake_case)

- **API Pública v1**: usa `snake_case` em todos os campos JSON (consistência com Laravel default + alinha com APIs brasileiras como Stripe BR, Mercado Pago).
- **Webhook payloads**: usa `snake_case` por consistência com API.
- **Headers**: usa `kebab-case` com prefixo `X-CRM-`.
- **OpenAPI**: descreve em inglês os campos no `description` mas mantém o nome real em `snake_case` no schema. Razão: parceiros internacionais conseguem ler; nome real é o que aparece no payload.

### Schemas detalhados

Por concisão, este `README.md` lista apenas a estrutura macro. Schemas completos com `properties`, `required`, `format`, `pattern`, `example` ficam em:

- `api-public-v1.yaml` — OpenAPI 3.0 (gerado parcialmente pelo Scribe + ajustes manuais)
- `webhook-events.yaml` — schemas por event_type com JSON Schema Draft 7
- `portability-schema-v1.json` — JSON Schema do arquivo

Esses arquivos serão **gerados na fase de implementação (Lote D e Lote A)** a partir do código fonte das Resources/Controllers/PortabilityExporter. Não há valor em mantê-los manualmente sincronizados — o Scribe + decorators OpenAPI cuidam.

### Versionamento dos contratos

- `api-public-v1.yaml`: versionado no repositório com a versão da app. Breaking changes geram nova v2.yaml.
- `webhook-events.yaml`: versionado junto. Eventos novos são **aditivos** (não rompem clientes existentes).
- `portability-schema-v1.json`: imutável após release. v2 substitui em caso de mudança incompatível.

---

## 5. Próximos passos da Phase 1

✅ Contratos macro definidos (este README)
⏭️ Geração automática de `api-public-v1.yaml` via Scribe ao final do Lote D (após implementação dos 11 endpoints da API pública)
⏭️ Geração de `webhook-events.yaml` ao final do Lote D (a partir dos schemas dos eventos consumidos pelo Webhook Dispatcher)
⏭️ Geração de `portability-schema-v1.json` ao final do Lote A (a partir do `PortabilityExporter::buildArchive()`)

Esta organização **garante que os arquivos serão sempre consistentes com o código** — não há contrato escrito à mão que possa divergir do que a app retorna. O README aqui é o **norte arquitetural** que orienta a implementação.
