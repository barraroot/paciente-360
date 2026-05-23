# Contract — GET /api/v1/panel/home

**Status**: Complete | **Date**: 2026-05-23

Contrato canônico do único endpoint introduzido por esta feature. Serve como referência para implementação, testes e (futuramente) documentação Scribe/OpenAPI.

---

## 1. Endpoint

```
GET /api/v1/panel/home
```

### Authentication

- `Authorization: Bearer <token>` (Sanctum Personal Access Token — Fase 4)
- `X-Tenant-Slug: <slug>` (triple-check Fase 4)

### Middleware stack

```
['auth:sanctum', 'tenant.slug', 'tenant.not-suspended', 'throttle:api']
```

### Query parameters

| Param | Tipo | Default | Descrição |
|---|---|---|---|
| `scope` | `'user' \| 'clinic'` | `'user'` | Escopo solicitado. Backend aplica `scope_applied = 'user'` automaticamente se user não tem permissão para `'clinic'` (sem retornar 403). |

### Response 200 (success)

```json
{
  "scope_requested": "user",
  "scope_applied": "user",
  "can_toggle_scope": false,
  "generated_at": "2026-05-23T14:30:00Z",
  "cache_hit": true,
  "sections": {
    "kpis": {
      "data": {
        "appointments_today": { "total": 12, "confirmed": 8, "pending": 4 },
        "conversations_pending": { "total": 3, "unassigned": 1 },
        "leads_new_7d": { "total": 7 },
        "prescriptions_expiring_30d": { "total": 2 }
      },
      "error": false
    },
    "upcoming_appointments": {
      "data": [
        {
          "id": 412,
          "starts_at": "2026-05-23T15:00:00-03:00",
          "starts_at_local": "15:00",
          "patient_name": "João Silva",
          "appointment_type": "Consulta",
          "professional_name": "Dr. Carlos Santos",
          "status": "confirmed"
        }
      ],
      "error": false
    },
    "attention_items": {
      "data": [
        {
          "type": "conversation_escalated",
          "severity": "danger",
          "title": "Conversa aguardando atendimento humano",
          "description": "Maria foi escalada há 15 minutos.",
          "link": "/panel/inbox/conversa/89",
          "occurred_at": "2026-05-23T14:15:00Z"
        }
      ],
      "error": false
    },
    "recent_activity": {
      "data": [
        {
          "id": 9012,
          "actor": { "name": "Maria Souza", "initials": "MS" },
          "description": "Maria Souza criou paciente João Silva",
          "occurred_at": "2026-05-23T14:28:00Z",
          "link": "/panel/pacientes/47"
        }
      ],
      "error": false
    }
  }
}
```

### Response 200 (degraded section)

Quando um collector falha individualmente, a seção fica `null` + `error: true`. Outras seções permanecem normais. Endpoint NÃO retorna 5xx por falha parcial.

```json
{
  "sections": {
    "kpis": { "data": null, "error": true },
    "upcoming_appointments": { ... },
    "attention_items": { ... },
    "recent_activity": { ... }
  }
}
```

Sentry tag `panel_home.section_failed=kpis` registrada.

### Response 401 (não autenticado)

```json
{ "message": "Unauthenticated." }
```

### Response 403 (X-Tenant-Slug mismatch)

Tratado pelo middleware `tenant.slug` — não passa pelo controller.

### Response 503 (tenant suspenso)

Tratado pelo middleware `tenant.not-suspended` — convencional.

### Response 422 (scope inválido)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "scope": ["The selected scope is invalid."]
  }
}
```

---

## 2. Comportamento de cache

### Chave

```
panel_home:{tenant_id}:{user_id}:{scope_applied}
```

### TTL

`config('panel.cache_ttl_seconds')` — default **30 segundos**, override via env `PANEL_HOME_CACHE_TTL`.

### Storage

Redis (cache driver default do projeto).

### Hit/miss

- **Miss**: Service executa os 4 collectors, monta envelope, escreve no cache, retorna. `cache_hit: false`.
- **Hit**: Service deserializa do cache, retorna sem invocar collectors. `cache_hit: true`.

### Invalidação

Passiva (TTL). Sem listener ativo neste MVP — frescor de 30s é aceitável para a natureza do dashboard.

---

## 3. Filtros aplicados por scope

### `scope_applied='user'`

| Section | Filtro adicional |
|---|---|
| KPI `appointments_today` | `professional_id = user.id` |
| KPI `conversations_pending` | `assignee_id = user.id OR assignee_id IS NULL` |
| KPI `leads_new_7d` | `responsavel_id = user.id` (se policy do paciente exige; caso contrário, sem filtro) |
| KPI `prescriptions_expiring_30d` | `professional_id = user.id` |
| Upcoming appointments | `professional_id = user.id` |
| Attention items | mesmos filtros por tipo |
| Recent activity | sem filtro adicional (atividade do tenant visível para qualquer membro) |

### `scope_applied='clinic'`

Nenhum filtro adicional além do `tenant_id` (já aplicado por global scopes).

### Caso especial — role dupla (Q1 da clarification)

Usuário com `admin-clinica + medico`:
- Solicita `scope=user` → `scope_applied='user'` → filtros como profissional.
- Solicita `scope=clinic` → `scope_applied='clinic'` → sem filtros de usuário (visão da clínica inteira).
- `can_toggle_scope=true` (tem permissão admin para alternar).

---

## 4. Permission gates

| Recurso | Gate |
|---|---|
| Toggle de scope para `'clinic'` | `User has role 'admin-clinica'` OR equivalent (`PanelHomePolicy::canSeeClinicScope`) |
| Alerta tipo `webhook_dlq` aparece na lista | `User can 'webhook.manage'` (`PanelHomePolicy::canSeeWebhookDlqAlerts`) |
| Alerta tipo `confirmation_pending` | `User can 'agenda.view'` |

Sem permissão → recurso simplesmente omitido (sem 403; defesa em profundidade dentro do response, não no middleware).

---

## 5. Performance contract

| Métrica | Alvo |
|---|---|
| p50 latência (cache hit) | < 50 ms |
| p50 latência (cache miss) | < 250 ms |
| p95 latência (overall) | **< 500 ms** (SC-003) |
| Queries DB por request (cache miss) | ≤ 12 (gate test) |
| Queries DB por request (cache hit) | 0 |

---

## 6. Métricas Prometheus

| Métrica | Tipo | Labels | Quando |
|---|---|---|---|
| `panel_home_requests_total` | counter | `tenant`, `scope`, `cache_hit` | Cada request |
| `panel_home_duration_seconds` | histogram | `section` (ou empty para overall) | Cada request + cada collector |
| `panel_home_cache_hits_total` | counter | `tenant` | Apenas em hit |
| `panel_home_section_failures_total` | counter | `section` | Falha de collector |

Buckets do histogram: `[0.05, 0.1, 0.25, 0.5, 1.0, 2.5]` (alvo p95 < 500ms).

---

## 7. Gates de validação (testes obrigatórios)

| Gate | Teste | Falha se |
|---|---|---|
| **G1** — Cross-tenant | `PanelHomeCrossTenantTest::test_user_of_tenant_a_cannot_see_data_of_tenant_b` | Endpoint retorna QUALQUER row de outro tenant. |
| **G2** — N+1 | `PanelHomeNplusOneTest::test_endpoint_uses_at_most_12_queries` | `DB::getQueryLog()` ultrapassa 12 queries em chamada cache-miss. |
| **G3** — Cache TTL | `PanelHomeCacheTest::test_second_call_within_ttl_is_served_from_cache` | Segunda call dentro de 30s NÃO retorna `cache_hit: true`. |
| **G4** — Scope override | `PanelHomeScopeTest::test_user_without_admin_role_forced_to_user_scope` | User sem role admin consegue `scope_applied='clinic'`. |
| **G5** — Role dupla | `PanelHomeScopeTest::test_admin_medico_minha_visao_filters_by_professional` | Admin+médico pedindo scope=user ainda vê tenant inteiro (deveria ver só dele). |
| **G6** — LGPD timeline | `PanelHomeRecentActivityLgpdTest::test_descriptions_never_contain_cpf_email_phone_clinical` | Regex pattern de CPF/email/telefone/conteúdo clínico casa em alguma descrição. |
| **G7** — Allow-list audit events | `PanelHomeRecentActivityLgpdTest::test_only_allowlisted_event_types_appear` | Event type fora da allow-list aparece na timeline. |
| **G8** — Webhook DLQ permission | `PanelHomeEndpointTest::test_user_without_webhook_manage_does_not_see_dlq_alerts` | Alerta `webhook_dlq` aparece para user sem permissão. |
| **G9** — Section degradação | `PanelHomeEndpointTest::test_kpi_collector_failure_returns_other_sections_normally` | Falha em um collector quebra response inteira em vez de retornar `error: true` apenas naquela seção. |
| **G10** — Limite recortes | `PanelHomeEndpointTest::test_upcoming_appointments_limited_to_5_in_6h_window` | Retorna mais de 5 itens OU itens fora da janela 6h. |

---

## 8. Versionamento

Endpoint vive sob `/api/v1/` — qualquer breaking change exige `/api/v2/panel/home` (sem precedente para isso ainda). Adição de campos não-breaking pode entrar em `/api/v1/` direto desde que clientes ignorem campos desconhecidos (frontend do mesmo monorepo controla isso).
