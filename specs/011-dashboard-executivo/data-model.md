# Data Model — Dashboard Executivo (011)

**Status**: Complete | **Date**: 2026-05-23

Esta feature **não introduz nem altera entidades persistidas no banco**. Toda a infraestrutura de dados (tabela `metric_aggregations`, cron `reports:aggregate-hourly`, MetricAggregator com PARTIAL UNIQUE composto) foi entregue na Fase 8 (spec 008, Lote E). O modelo de dados relevante aqui é:

1. **View models** retornados pelo endpoint backend (consumidos pelo frontend)
2. **Esquema de persistência local** da janela escolhida no `localStorage`

---

## 1. View Models (já existentes, sem alterações)

### 1.1 Envelope do `GET /api/v1/reports/executive`

```
ExecutiveDashboardResponse := {
  period: { start: ISO8601, end: ISO8601, preset: '24h'|'7d'|'30d'|'90d' },
  aggregation_lag_seconds: number,           // 0 para preset='24h' (live)
  metrics: {
    leads_by_channel: KpiData,
    conversion_rate: KpiData,
    no_show_rate: KpiData,
    estimated_revenue: KpiData,
    response_time_first_p95: KpiData,
    ai_autonomous_resolution_rate: KpiData,
    occupancy_by_professional: { ... },      // array com profissionais
    top_procedure_types: { ... },             // top 5 procedimentos
  }
}

KpiData := {
  value: number | null,
  delta_percent: number | null,               // null = sem período anterior comparável
  json?: any                                  // payload adicional (ex.: top_procedure_types tem array)
}
```

**NOTA importante** (R2 da research): backend **não retorna `trend_points: number[]`** atualmente. Sparkline real fica DEFERRED até nova versão do backend.

### 1.2 Payload de `occupancy_by_professional`

```
{
  value: null,           // não tem valor único agregável
  delta_percent: null,
  json: [
    { professional_id: int, name: string, occupancy_percent: float },
    ...
  ]
}
```

### 1.3 Payload de `top_procedure_types`

```
{
  value: null,
  delta_percent: null,
  json: [
    { name: string, count: int, percentage: float },
    ...                  // top 5
  ]
}
```

---

## 2. Esquema de persistência local (`localStorage`)

### 2.1 Chave

```
executive_dashboard:window:v1
```

Separada das chaves `app-shell:preferences:v1` (spec 009) e `panel_home:scope:v1` (spec 010) para evitar acoplamento de schemas — R11 do spec 010 / R4 desta spec.

### 2.2 Valor (JSON)

```json
{
  "rb-clinic": {
    "1": "30d",
    "5": "7d"
  },
  "clinica-alfa": {
    "1": "24h"
  }
}
```

### 2.3 Schema (informal)

```
DashboardWindowPrefs := {
  [tenantSlug: string]: {
    [userId: string]: '24h' | '7d' | '30d' | '90d'
  }
}
```

### 2.4 Operações

| Operação | Quando | Comportamento |
|---|---|---|
| **Read** | Mount de `ExecutiveDashboardPage.vue` | Lê chave, navega `[tenantSlug][userId]`, default `'7d'` se ausente. |
| **Write** | Usuário clica em outra janela do filtro | Escreve no path correto, re-serializa o JSON completo. |

### 2.5 Invariantes

- **INV-1** (isolamento multi-tenant): chave **MUST** usar `auth.tenant.slug + auth.user.id`. Princípio II via cliente.
- **INV-2** (tolerância a corrupção): JSON malformado → default `'7d'` + sobrescrita silenciosa.
- **INV-3** (tolerância a localStorage indisponível): operações são no-ops; feature continua funcional (escolha volátil em memória).
- **INV-4** (valor inválido): se o JSON contém valor fora do enum (ex.: `"60d"`), o composable trata como ausente e usa default `'7d'`.

### 2.6 Sem PII

Apenas IDs internos + enum string. Compatível com Princípio I (LGPD minimização).

---

## 3. Estado consumido (read-only) da `useAuthStore`

| Campo | Uso |
|---|---|
| `auth.user.id` | Chave de localStorage (escopo de window) |
| `auth.tenant.slug` | Chave de localStorage (escopo de window) |
| `auth.permissions` | Verificar `report.view` antes de renderizar (router guard já cuida) |

---

## 4. Estado consumido (read/write) da `reportsStore` (Pinia, já existente)

```
reportsStore.executive := {
  data: ExecutiveDashboardResponse | null,
  loading: boolean,
  error: string | null,
  lastFetched: Date | null,
  period: { preset: string, start?: string, end?: string } | null
}
```

Métodos consumidos:
- `fetchExecutive(params)` — wrapper sobre `GET /api/v1/reports/executive`
- `drill(metric, params)` — wrapper sobre drill-down (não usado nesta spec — drill-down é DEFERRED)
- `exportPdf(params)` — wrapper sobre `POST /reports/executive/export-pdf`

Esta spec **não modifica** o store. Apenas consome.

---

## 5. State transitions

Não há transições de estado a modelar — feature read-only no backend. Mutações ocorrem apenas em:

1. **`localStorage`** (cliente) — schema da Seção 2
2. **`reportsStore.executive`** (Pinia) — gerenciado pelo store, já existente
3. **Cache Redis backend** — gerenciado pela Fase 8 (cron horário + TTL implícito via UNIQUE)

Nenhuma envolve transição de estado de entidade persistida no banco.
