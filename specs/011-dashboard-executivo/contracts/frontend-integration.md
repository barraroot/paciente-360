# Contract — Frontend Integration

**Status**: Complete | **Date**: 2026-05-23

Esta spec **não introduz endpoints novos**. Toda a integração HTTP usa contratos já entregues na Fase 8 (spec 008, Lote E). Este documento define como o frontend consome esses contratos + gates de validação.

---

## 1. Endpoints consumidos (já existentes — Fase 8)

### 1.1 Snapshot do dashboard

```
GET /api/v1/reports/executive?preset={24h|7d|30d|90d}
```

Headers obrigatórios (já implementados pelo `api.js` interceptor):
- `Authorization: Bearer <token>`
- `X-Tenant-Slug: <slug>`

Response 200: `ExecutiveDashboardResponse` (ver data-model.md § 1.1).

**Mudança backend necessária** (única): adicionar suporte ao preset `'24h'` no `ExecutiveDashboardController::resolvePeriod()` (linha 105-108). Hoje só aceita `7d/30d/90d` com default `30d`. Spec FR-001 exige 4 windows incluindo `24h`.

### 1.2 Drill-down (não usado nesta spec — DEFERRED)

```
GET /api/v1/reports/executive/drill/{metric}?start=...&end=...
```

Existe na Fase 8 mas drill-down detalhado fica fora do escopo desta spec (FR de US futura).

### 1.3 Export PDF

```
POST /api/v1/reports/executive/export-pdf
Body: { preset: '7d', start?, end? }
```

Response: PDF binary (Blob). Frontend cria download via `URL.createObjectURL`.

---

## 2. APIs dos composables frontend

### 2.1 `useDashboardWindow()`

```typescript
function useDashboardWindow(): {
  window: Ref<'24h' | '7d' | '30d' | '90d'>;
  setWindow(value: '24h' | '7d' | '30d' | '90d'): void;
}
```

**Contrato comportamental**:
- Default `'7d'` quando localStorage ausente ou corrompido.
- Escopado por `auth.tenant.slug + auth.user.id`.
- Tolerante a localStorage indisponível (modo privado/cota cheia): no-op em writes.

### 2.2 `useExecutiveDashboard()`

```typescript
function useExecutiveDashboard(): {
  data: Ref<ExecutiveDashboardResponse | null>;
  loading: Ref<boolean>;
  error: Ref<string | null>;
  window: Ref<string>;
  setWindow(value): void;
  refresh(): Promise<void>;
  exportPdf(): Promise<void>;
}
```

**Contrato comportamental**:
- Watcher em `window` → dispara `refresh()` com `AbortController` cancelando request em flight.
- `loading` true durante fetch; false após sucesso ou erro.
- `error` recebe mensagem amigável; null após sucesso.
- `exportPdf()` chama POST endpoint, recebe Blob, dispara download. Erro propaga via toast caller.

---

## 3. Contratos de componentes Vue

### 3.1 `<PeriodFilter v-model="window" />`

Props:
- `modelValue: '24h' | '7d' | '30d' | '90d'` (v-model)

Emits:
- `update:modelValue` (string)

A11y:
- Container `role="tablist"`, `aria-label="Filtro de período"`.
- Cada botão `role="tab"`, `aria-selected` true/false.
- Navegação por setas Left/Right entre tabs.

### 3.2 `<KpiCardWithSparkline />`

Props:
- `label: string` (i18n já resolvida)
- `value: number | null`
- `formatType: 'percent' | 'currency_brl' | 'seconds' | 'count'`
- `deltaPercent: number | null`
- `inversePolarity: boolean` (default false — true para no_show_rate, response_time)
- `sparklinePoints?: number[]` (DEFERRED — undefined nesta versão)
- `loading: boolean`
- `error: boolean`

Emits: `@click` (drill-down DEFERRED nesta spec — sem handler ativo).

### 3.3 `<TopProceduresCard :items />`

Props: `items: Array<{name, count, percentage}>`

Emits: `@click(item)` — drill DEFERRED.

### 3.4 `<OccupancyByProfessionalCard :items />`

Props: `items: Array<{professional_id, name, occupancy_percent}>`

Renderização:
- Ordenado decrescente por `occupancy_percent`.
- Barra de progresso CSS (width = % do max=100).
- Badge "Carga alta" quando `occupancy_percent >= 90`.

### 3.5 `<ExportMenu @export-pdf />`

Emits:
- `export-pdf` — handler caller faz `await exportPdf()`.

Estados:
- Item "PDF": ativo, com spinner durante export.
- Item "CSV": desabilitado, label "em breve".

### 3.6 `<StaleDataBanner :lag-seconds />`

Props: `lagSeconds: number`

Renderiza apenas se `lagSeconds > 7200`. Esconde se `lagSeconds === 0` (preset='24h').

### 3.7 `<Sparkline :points />` (stub funcional)

Props: `points: number[]`

Renderiza SVG inline (~50 linhas). Quando `points.length === 0`, retorna `null`.

---

## 4. Gates de validação

| Gate | Teste | Falha se |
|---|---|---|
| **G1** — Backend suporta 24h | E2E ou manual curl | `GET /reports/executive?preset=24h` retorna 422 ou interpreta como 30d |
| **G2** — Window persistida | Playwright | Logout/login não restaura última window selecionada |
| **G3** — Window isolada por tenant+user | Playwright multi-login | User A em tenant X vê window de User B em tenant X (cross-user) ou vê window de tenant Y (cross-tenant) |
| **G4** — Banner stale oculto na 24h | Playwright | Banner aparece com preset=24h mesmo com lag (live data, contradition) |
| **G5** — Polaridade invertida | Playwright | no_show_rate aumentando mostra seta verde (deveria ser vermelha) |
| **G6** — Export PDF inicia download | Playwright | Click em "Exportar PDF" não dispara download em até 10s |
| **G7** — A11y zero violations | axe automated audit | Violação séria ou crítica em `/panel/relatorios/executivo` |
| **G8** — Reuse `reportsStore` sem mudanças | Code review | PR altera `resources/js/stores/reportsStore.js` |

---

## 5. Compatibilidade

- Frontend existente (`ExecutiveDashboardPage.vue` 194 linhas) será **reescrito**. Componente `KpiCardWithTrend.vue` permanece (pode ser usado em drill-down futuro).
- `reportsStore.js` Pinia store: **sem mudanças** (gate G8).
- Backend: **1 linha** adicional no controller (R1 do research).
