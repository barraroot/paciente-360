# Research — Dashboard Executivo (011)

**Status**: Complete | **Date**: 2026-05-23

Todas as decisões técnicas para destravar Phase 1 consolidadas. Inclui 1 ajuste de escopo (sparkline real) registrado como DEFERRED consciente com fallback definido.

---

## R1 — Backend já entregue na Fase 8 — apenas extensão mínima

**Decision**: Reutilizar 100% dos endpoints `/api/v1/reports/executive*` já existentes. Backend recebe **uma única mudança**: adicionar suporte ao preset `'24h'` no `ExecutiveDashboardController::resolvePeriod()`.

**Status atual**:
- `ExecutiveDashboardController.php:105-108` tem match com `'7d', '30d', '90d'` mas **falta** `'24h'`. Default cai em 30d.
- `ExecutiveDashboardService.php` calcula valor + delta_percent (current vs previous period). Funciona para qualquer janela arbitrária (já aceita Carbon `$start, $end`).

**Mudança backend (1 linha)**:
```php
$start = $startRaw !== '' ? Carbon::parse($startRaw) : match ($preset) {
    '24h' => $end->copy()->subHours(24),    // ← adicionar
    '7d' => $end->copy()->subDays(7),
    '90d' => $end->copy()->subDays(90),
    default => $end->copy()->subDays(30),
};
```

**Rationale**: Q9 da Fase 8 estabelece que janelas ≤ 24h usam queries live (sem agregação). O service já distingue isso via flag `useAggregated`. Apenas o resolver de preset precisa do novo case.

**Alternatives considered**:
- *Sem mudança backend, mapear `24h` para `30d` no frontend*: viola a intenção da spec (FR-008 — banner stale oculto na 24h porque dados são live).
- *Mudança ampla no backend*: desnecessário; o serviço já está pronto para qualquer Carbon range.

---

## R2 — Sparkline real DEFERRED — fallback definido nesta spec

**Decision**: Esta versão **NÃO renderiza sparkline com dados históricos reais**. Em vez disso:
- KPI card exibe **delta indicator visual rico** (seta colorida + texto + porcentagem) onde o sparkline estaria.
- Acessibilidade preservada: `aria-label` descreve a tendência via delta ("alta de 12% vs período anterior").
- Sparkline real entra em spec futura quando backend tiver endpoint `/reports/executive/series?metric=...&window=...` retornando série temporal por métrica.

**Spec impacto** (transparência):
- FR-012 ("sparkline com 8 a 24 pontos") MUST ser marcado como **DEFERRED** em `DEFERRED.md` da feature.
- FR-017 (tooltip ao hover do sparkline) também DEFERRED.
- FR-016 (sem dados suficientes) e FR-013 (variação %) **PERMANECEM ATIVOS** — esses cobrem a parte essencial sem precisar de série.
- Outros 37 FRs ficam intactos.

**Rationale**:
- Backend `ExecutiveDashboardService.fetch()` retorna apenas `{value, delta_percent}` por métrica — não há séries por janela.
- Implementar séries exige: nova rota + nova query agregada por bucket (hourly para 24h, daily para 7d/30d, weekly para 90d) + extensão de `metric_aggregations` para chunks menores ou recalculation per-bucket.
- Custo de implementação dessa série supera o ganho UX da sparkline para uma primeira entrega. Polish do dashboard sem sparkline já é melhoria material.

**Alternatives considered**:
- *Sparkline fake* (linha visual sem dados reais): engana usuário; viola UX honesta.
- *Sparkline sintético de 2 pontos* (valor atual + valor anterior): inútil visualmente; só transmite o delta que já está visível em texto.
- *Implementar séries no backend nesta spec*: amplia o escopo significativamente; este spec é "polish + persistência + 24h + seções complementares" — incluir séries dobraria a complexidade.

---

## R3 — Reuso de Pinia store `reportsStore` (sem mudanças)

**Decision**: O `resources/js/stores/reportsStore.js` (entregue Fase 8) já gerencia state de `executive` (data, loading, error, period) e `drillDown`. Mantém-se intocada — apenas a Page e os componentes consomem.

**Rationale**:
- Store já tem cache leve por período + invalidation pattern.
- Adicionar nova camada (composable wrapper) seria duplicação.
- Mudanças aqui afetam código que outras partes da Fase 8 podem usar (OperationalReport, ClinicalReport).

**Alternatives considered**:
- *Criar composable `useExecutiveDashboard` que substitui o store*: refactor desnecessário, viola "se está funcionando, reaproveite".

---

## R4 — Composable `useDashboardWindow` para persistência localStorage

**Decision**: Criar `resources/js/composables/useDashboardWindow.js` espelhando o pattern do `usePanelHomeScope` (spec 010 / R11). Chave `executive_dashboard:window:v1`, aninhada por `tenant_slug + user_id`.

**Rationale**:
- Mesma estratégia de defesa-em-profundidade contra cross-tenant leak no localStorage (Princípio II).
- Schema SEPARADO do `panel_home:scope:v1` e do `app-shell:preferences:v1` para evitar acoplamento (R11 spec 010).
- Valor: `'24h' | '7d' | '30d' | '90d'`. Default: `'7d'` (FR-004).

**Alternatives considered**:
- *Persistir no backend (`users.settings`)*: sincronização cross-device seria nice mas não é requisito; adicionar migration + endpoint sem ganho material no MVP.

---

## R5 — Filtro de período: componente dedicado com `role="tablist"`

**Decision**: Criar `resources/js/components/Reports/PeriodFilter.vue` — tablist com 4 botões (`role="tab"` + `aria-selected`), navegação por setas (Left/Right) via keyboard. Emite `@update:modelValue` quando seleção muda.

**Rationale**:
- WAI-ARIA APG `tablist` pattern atende FR-038.
- Componente isolado facilita teste E2E e reuso futuro (OperationalReport pode usar mesmo padrão).
- Navegação por setas é o esperado para tablist horizontal.

**Alternatives considered**:
- *Select nativo*: menos elegante visualmente e quebra com o mockup.
- *Buttons soltos sem semantics*: fail no SC-006 (a11y violations).

---

## R6 — Sparkline component (preparado mas não usado nesta versão)

**Decision**: Mesmo deferred (R2), criar `resources/js/components/Reports/Sparkline.vue` como **stub funcional** que aceita prop `points: number[]` e renderiza SVG inline. Quando vazio (caso atual), renderiza `null` (sem warning, sem placeholder). Isto deixa o componente pronto para receber dados quando o backend implementar séries.

**Rationale**:
- Custo zero criar o stub agora (~50 linhas).
- Quando a série chegar, basta atualizar o consumer KpiCardWithSparkline para passar `points` — sem refactor visual.
- Documenta a intenção da arquitetura.

**Alternatives considered**:
- *Não criar o componente até série chegar*: força recriar tudo depois; melhor preparar.

---

## R7 — `KpiCardWithSparkline.vue` reusa visualmente o `KpiCardWithTrend.vue`

**Decision**: Criar novo componente `KpiCardWithSparkline.vue` que:
- Aceita as mesmas props de `KpiCardWithTrend` (value, deltaPercent, etc.) PLUS opcional `sparklinePoints`.
- Visualmente: number grande à esquerda, delta indicator embaixo, sparkline (quando `points` presente) à direita.
- Quando `points` está vazio/`undefined`, omite a área de sparkline e ocupa toda largura para o número + delta.

**Rationale**:
- Não DESTRUIR `KpiCardWithTrend.vue` — pode estar sendo usado em outros lugares (drill-down, etc.).
- Novo componente é o "executive dashboard variant" do existente.
- Quando sparkline real entrar, só esse componente muda.

**Alternatives considered**:
- *Editar `KpiCardWithTrend.vue` diretamente*: risco de quebrar consumers existentes na Fase 8.

---

## R8 — Top Procedures e Occupancy como componentes dedicados

**Decision**: 2 componentes novos com mesma "card shell" do KpiCard:
- `TopProceduresCard.vue`: recebe array `[{name, count, percentage}]`, renderiza barras horizontais com width proporcional ao percentage. Click → emite `@drill` com o procedimento.
- `OccupancyByProfessionalCard.vue`: recebe `[{name, occupancy_percent, professional_id}]`, ordenado decrescente, com barras + badge de "carga alta" quando ≥ 90% (FR-021).

**Rationale**:
- Backend já retorna esses campos no payload do executive endpoint (verificável em research).
- Componentes simples e auto-contidos — fácil testar e estilizar.

**Alternatives considered**:
- *Reutilizar Chart.js / D3*: overkill para barras simples. SVG inline ou flexbox + `:style="{width: pct + '%'}"` resolve em ~30 linhas.

---

## R9 — Export PDF: reuso de endpoint existente + spinner state

**Decision**: Componente `ExportMenu.vue` (dropdown) com 2 itens:
- "Exportar PDF" → chama `POST /api/v1/reports/executive/export-pdf` com payload do period atual. Aguarda Blob, cria download via `URL.createObjectURL`. Spinner no item durante request (FR-026).
- "Exportar CSV" → desabilitado (FR-028), rótulo "em breve".

**Rationale**:
- Endpoint PDF já existe (Fase 8 — `ExecutiveDashboardController::exportPdf`).
- Download via Blob é padrão SPA — funciona sem precisar de window.location.
- Spinner local é o pattern usado em outras telas do projeto (Pacientes, Receituários).

**Alternatives considered**:
- *Implementar CSV agora*: viola escopo (FR-028 explícito), exige backend endpoint novo.
- *Abrir PDF em nova aba*: complica caso o usuário queira só baixar para anexar em email; download direto é mais útil.

---

## R10 — Stale banner via `aggregation_lag_seconds` (já existente)

**Decision**: O backend já retorna `aggregation_lag_seconds` no payload. Frontend exibe banner `StaleDataBanner.vue` quando `aggregation_lag_seconds > 7200` (FR-007).

**Mudança em relação ao código atual**:
- `ExecutiveDashboardPage.vue` linha ~30 tem `isStale = computed(() => lag > 7200)`. Apenas extrair pra componente dedicado e ocultar quando `window === '24h'` (FR-008).

**Rationale**:
- Reuso direto.
- Componente dedicado facilita estilo e teste.

---

## R11 — Acessibilidade: aria-label descritivo no KPI card

**Decision**: Cada `KpiCardWithSparkline` gera `aria-label` composto: `"{label}: {valor formatado}, {trend}, variação de {delta}% vs período anterior"`. Exemplo:

> "Taxa de conversão: 42,3%, alta, variação de 12% vs período anterior"

Para `delta_percent === null`: omite a parte de variação.

**Rationale**: Atende FR-035 e SC-008 (leitor de tela). Padroniza informação para tecnologia assistiva sem depender do sparkline (que está deferred).

---

## R12 — i18n: bloco `executive_dashboard.*` em `pt-BR.json`

**Decision**: Adicionar bloco com:
- `period_filter.{24h,7d,30d,90d}` — labels dos tabs
- `metrics.{leads_by_channel,conversion_rate,no_show_rate,estimated_revenue,response_time_first_p95,ai_autonomous_resolution_rate,top_procedure_types,occupancy_by_professional}` — labels dos cards
- `sections.{top_procedures,occupancy_by_professional}` — headings das 2 seções
- `export.{title,pdf,csv,csv_disabled,exporting,failed}` — menu de export
- `stale_banner.{title,updated_ago}` — banner amarelo
- `empty_state.{no_data,explanation}` — empty state amistoso
- `errors.{global,retry}` — banner de erro
- `aria.{tablist,card_with_metric,sparkline_trend,occupancy_high}` — descritivos a11y

---

## R13 — Testes: 1 E2E Playwright cobrindo jornada combinada

**Decision**: Criar `tests/Browser/ExecutiveDashboardE2ETest.php` que cobre:
- Login como admin com `report.view`
- Acessa `/panel/relatorios/executivo`
- Vê 8 KPI cards renderizados
- Click em "30 dias" → URL e dados atualizam
- Click em "Exportar" → PDF download iniciado
- Logout + login → window "30 dias" restaurada (gate G3)

Outros gates (G1 cross-tenant, G2 N+1) já cobertos pelos testes da Fase 8 — esta spec não introduz novos endpoints.

**Rationale**: Cobertura E2E suficiente; backend imutável; complexidade primarily visual.

---

## Resumo das decisões

| ID | Decisão | Impacto |
|---|---|---|
| R1 | Backend: 1 linha (`'24h' => subHours(24)`) | Mudança mínima |
| R2 | **Sparkline real DEFERRED** — fallback visual rico (delta indicator) | Reduz escopo; FR-012/FR-017 marcados deferred |
| R3 | Reuso de `reportsStore` Pinia (sem mudanças) | Zero churn |
| R4 | `useDashboardWindow.js` + localStorage scoped | Persistência |
| R5 | `PeriodFilter.vue` com role=tablist + keyboard nav | A11y |
| R6 | `Sparkline.vue` stub funcional preparado para uso futuro | Arquitetura pronta |
| R7 | `KpiCardWithSparkline.vue` novo (não destrói existente) | Compatibilidade |
| R8 | 2 cards dedicados (TopProcedures + Occupancy) com barras CSS | Simplicidade |
| R9 | `ExportMenu.vue` reusa endpoint PDF; CSV desabilitado | Reuso |
| R10 | `StaleDataBanner.vue` extraído da página atual | Cleanup |
| R11 | `aria-label` rico nos cards | SC-008 |
| R12 | i18n bloco completo | Cobertura |
| R13 | 1 Playwright E2E para jornada combinada | Test-first com escopo enxuto |

Constitution Check 7/7 preservado em todas as decisões.
