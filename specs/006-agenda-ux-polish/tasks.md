---
description: "Task list — Polimento UX da Agenda (pós-Fase 5)"
---

# Tasks: Polimento UX da Agenda

**Input**: Design documents from `/specs/006-agenda-ux-polish/`
**Prerequisites**: spec.md (✅), plan.md (✅) — escopo curto justificou skip de research/data-model/contracts

**Tests**: NÃO REQUERIDOS para esta phase — refinement UX é validado por audit manual no browser (vide DoD spec.md). Suite Fase 5 (1167 tests) deve continuar verde como gate de regressão.

**Organization**: 4 lotes A-D, 1 por tela/componente. Final: regression + relatório consolidado.

## Format: `[ID] [Lote] Description`

- Caminhos absolutos quando ambíguo
- Cada lote = 1 commit separado

---

## Phase 1: Setup (já feito — referência)

- [X] T001 Criar branch `006-agenda-ux-polish` a partir de `005-agendamento-consultas`
- [X] T002 Criar `specs/006-agenda-ux-polish/{spec,plan,tasks}.md`

## Phase 2: Pré-requisitos já entregues (referência)

- [X] T003 (na branch `005-...`) Refinamento `AgendaPage.vue` (commit `22f4e86`) — 18 achados, 12 corrigidos, 5 backlog, 1 deferred (AttendanceMarkButton → Lote D)
- [X] T004 (na branch `005-...`) Refinamento `WaitlistPage.vue` (commit `b0d0ea6`) — 15 achados P0+P1, todos corrigidos, 6 backlog P2

## Phase 3: Lote A — AppointmentTypesPage UX

- [ ] T005 [A] Auditar `resources/js/pages/agenda/AppointmentTypesPage.vue` — checklist 6 dimensões (estados UI, fluxo, a11y, mobile, perf, pt-BR). Documentar achados priorizados P0/P1/P2.
- [ ] T006 [A] Refinar `resources/js/pages/agenda/AppointmentTypesPage.vue`:
  - Substituir `confirm()` no destroy por modal acessível (padrão `AppointmentFormModal`)
  - Skeleton + empty state com CTA "Criar primeiro tipo" + error retry pt-BR + toast feedback
  - Validar acessibilidade do form (labels, focus trap modal, Esc fecha, Enter submete)
  - Mobile: tabela → cards em <768px, color picker funcional em mobile
  - Tooltip explicativo "Tipo: Retorno (categoria de consulta)" no item Retorno (clarify nº 4)
  - Formatação valor R$ via `Intl.NumberFormat('pt-BR', {style:'currency'})`
- [ ] T007 [A] Refinar `resources/js/components/agenda/AppointmentTypeForm.vue` — color picker acessível, validação inline (debounce 300ms), erros 422 mapeados por campo
- [ ] T008 [A] Validar manual no browser (caminho feliz + edge cases + mobile + teclado-only) + commit `feat(types-ux): refinamento UX AppointmentTypesPage`

## Phase 4: Lote B — ScheduleConfigPage UX (mais complexo)

- [ ] T009 [B] Auditar `resources/js/pages/agenda/ScheduleConfigPage.vue` — checklist + identificar fluxo "configurar 7 dias × N blocos" (alta densidade, precisa UX cuidadosa)
- [ ] T010 [B] Refinar `resources/js/pages/agenda/ScheduleConfigPage.vue`:
  - Loading skeleton + empty state ("Configure sua agenda" CTA) + error retry
  - Picker de profissional (dropdown ou autocomplete se >10 prof)
  - Editor de blocks por dia da semana com:
    - Validação inline de overlap (HH:MM)
    - Botão "Copiar de outro dia" para acelerar (Mon→Tue-Fri)
    - **Wizard "Copiar de outro profissional"** (clarify nº 5 — opção do spec)
  - Lista de exceções com timeline visual (não tabela crua)
  - Modal de criar exceção via `ScheduleExceptionForm` (refatorar form se precisar a11y)
  - Toast feedback para save/exception create/delete
  - Mobile: editor empilhado por dia (collapsible), toque ≥44px
  - **Atalho**: Ctrl+S salva (além do botão)
- [ ] T011 [B] Refinar `resources/js/components/agenda/ScheduleExceptionForm.vue` — labels, focus, validação datas (after starts_at)
- [ ] T012 [B] Validar manual + commit `feat(schedule-config-ux): refinamento UX ScheduleConfigPage`

## Phase 5: Lote C — CalendarSyncPage UX

- [ ] T013 [C] Auditar `resources/js/pages/agenda/CalendarSyncPage.vue` — checklist
- [ ] T014 [C] Refinar `resources/js/pages/agenda/CalendarSyncPage.vue`:
  - Loading skeleton para `getStatus()`
  - Estado "Não conectado" com explicação clara + benefícios + CTA "Conectar Google Calendar"
  - Estado "Conectado" com:
    - Email da conta + nome do sub-calendário
    - **`last_synced_at` em formato relativo pt-BR** ("há 5 minutos") via Luxon
    - Status do watch channel (expira em N dias) com `aria-live`
    - Botão "Desconectar" com modal de confirmação descrevendo IMPACTO ("Suas consultas no Google permanecem; novas mudanças não serão espelhadas")
  - Estado "Erro" com:
    - Mensagem específica pt-BR (não "Error: 401")
    - CTA "Reconectar" (R5 — UX revogação OAuth)
  - Placeholder Outlook claramente "Em breve — Fase 6" com ícone (clarify nº 11)
  - Toast feedback connect/disconnect
  - Mobile: cards empilhados full-width
- [ ] T015 [C] Validar manual (sem Google real — usa stub/fake) + commit `feat(calendar-sync-ux): refinamento UX CalendarSyncPage`

## Phase 6: Lote D — AttendanceMarkButton (refactor componente)

- [ ] T016 [D] Auditar `resources/js/components/agenda/AttendanceMarkButton.vue` — atualmente usa `prompt()` para motivo no-show + `confirm()` para reverter (achado A10 da auditoria AgendaPage)
- [ ] T017 [D] Refatorar para popover/inline expandable acessível:
  - Click "Não realizada" → expande inline com textarea para motivo + botões "Confirmar" / "Cancelar" (NÃO usar `prompt`)
  - Click "Reverter" → mini popover com texto explicativo + confirm
  - Estados de loading (botão disabled + spinner durante request)
  - Erros 422 inline (não toast — contexto pequeno do botão)
  - Tooltip no badge "auto_flagged_at" explicando o que significa (clarify nº 14)
  - Acessibilidade: `aria-expanded` no botão que abre popover, focus trap quando aberto, Esc fecha
  - Mobile: popover vira bottom sheet
- [ ] T018 [D] Verificar que `AgendaPage.vue` consome corretamente o componente refatorado (interface mantida)
- [ ] T019 [D] Validar manual + commit `feat(attendance-mark-ux): substitui prompt/confirm por popover inline acessível`

## Phase 7: Final regression + consolidação

- [ ] T020 Rodar suite full `vendor/bin/sail artisan test --compact` — confirmar ≥1167 tests passando (sem regressão)
- [ ] T021 Rodar `vendor/bin/sail bin pint --dirty --format agent` (não deve aplicar)
- [ ] T022 Marcar T005-T019 como `[X]` neste tasks.md
- [ ] T023 Atualizar `CLAUDE.md` adicionando 1 parágrafo em "Agendamento (Fase 5) — Key Patterns" sobre os patterns de UX (modal a11y reutilizável, useToast pattern, popover inline para confirmações curtas)
- [ ] T024 Commit final `chore(ux-polish): consolidação Lote A-D + atualização docs`
- [ ] T025 Reportar relatório consolidado ao owner (achados totais por lote, backlog P2 acumulado, recomendações de smoke visual humano)

---

## Dependencies

- Lotes A, B, C, D são **independentes** (telas/componente distintos). Agent pode fazer em qualquer ordem.
- Recomendação interna do agent: ordem por complexidade crescente — A (simples) → C (médio-pequeno) → D (refactor componente) → B (mais complexo, 7 dias × N blocos).
- T020-T025 (Phase 7) depende de A+B+C+D concluídos.

## Estratégia de Implementation

**1 agent vue-frontend-engineer** em modo UX quality auditor (mesmo modo das auditorias AgendaPage e WaitlistPage). Recebe TODOS os lotes A-D em prompt único, decide ordem internamente. Commits separados por lote.

**Não disparar agents em paralelo** — `router/index.js` e `agendaApi.js` são compartilhados; conflitos são certos.

## Critério de Aprovação

Esta feature está PRONTA quando:
1. T005-T019 todos `[X]` (4 commits de refinement)
2. T020 verde (suite full sem regressão)
3. T024 commit consolidação feito
4. T025 relatório entregue ao owner com achados + backlog P2

Smoke visual humano fica como **gate manual fora do escopo desta sessão** — owner valida no browser depois.
