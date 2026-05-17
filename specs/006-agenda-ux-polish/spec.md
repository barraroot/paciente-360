# Feature Specification: Polimento UX da Agenda (pós-Fase 5)

**Feature Branch**: `006-agenda-ux-polish`
**Created**: 2026-05-15
**Status**: ✅ **Ready for /speckit.implement** — escopo curto, patterns já validados nas 2 telas refinadas (AgendaPage, WaitlistPage), sem nova dep, sem novo endpoint backend.

---

## Visão Geral

Refinamento UX das **4 telas + 1 componente** da Fase 5 que ficaram como "stubs funcionais MVP" (operacionais mas crus). Operação em produção exige polish significativo — atendente faz 50-100 ops/turno e cada clique mal desenhado é fricção que se acumula.

**Pré-requisitos já entregues** (na branch `005-agendamento-consultas`):
- `AgendaPage.vue` refinada — commit `22f4e86` (FullCalendar v6 + estados completos + a11y + Reverb sync)
- `WaitlistPage.vue` refinada — commit `b0d0ea6` (modal acessível + status badges + countdown live + filtros + cards mobile)

**Esta fase NÃO**:
- Adiciona endpoint novo no backend (todos já existem da Fase 5)
- Instala dep npm nova (FullCalendar v6 + Luxon + Heroicons cobrem)
- Inventa design system (reusa tokens `text-foreground`, `bg-surface-elevated`, etc.)
- Refaz testes da Fase 5 (37/37 ainda verdes)

---

## Itens em escopo

| # | Item | Tela/componente | Esforço estimado |
|---|---|---|---|
| 1 | `AppointmentTypesPage.vue` | CRUD admin de tipos de atendimento (US-6.2) | M |
| 2 | `ScheduleConfigPage.vue` | Config horários × dia da semana (US-6.1) | L (mais complexo — 7 dias × N blocos) |
| 3 | `CalendarSyncPage.vue` | UI Google OAuth + status sync (US-6.7) | S |
| 4 | `AttendanceMarkButton.vue` | Componente — substituir `prompt()`/`confirm()` por popover acessível (clarify nº 14 / achado A10 da auditoria AgendaPage) | M |

---

## Acceptance Criteria — Critério de "Pronto" por item

Cada item deve passar **todos os 6 dimensões** da heurística de auditoria UX (vide `.claude/agents/frontend-ux-quality.md`):

### AC-A — Estados UI
- [ ] **Loading**: skeleton/spinner com `aria-busy` (não texto cru "Carregando...")
- [ ] **Empty state**: ilustração + texto explicativo + CTA primário em pt-BR (não só "Nenhum item")
- [ ] **Error state**: mensagem acionável em pt-BR + botão "Tentar de novo" (não stack trace)
- [ ] **Success feedback**: toast `role=alert aria-live=assertive` para create/update/delete

### AC-B — Fluxo de operação
- [ ] **Steps mín**: tarefas frequentes ≤ 4 cliques
- [ ] **Atalhos teclado**: Esc fecha modal, Enter submete form, Tab ordem lógica
- [ ] **Defaults inteligentes**: campos pré-populados quando contexto sabe
- [ ] **Confirmação destrutiva**: descreve O QUE está sendo deletado (não só "Tem certeza?")
- [ ] **Sem `prompt()`/`confirm()`/`alert()` nativos** — substitutos acessíveis

### AC-C — Acessibilidade WCAG 2.1 AA
- [ ] Semantic HTML correto (`<button>` ação, `<a>` nav, headings hierárquicos)
- [ ] Labels em forms com `for`/`id` ou `aria-label`
- [ ] `:focus-visible` com ring destacado
- [ ] Contraste texto/fundo ≥ 4.5:1 (cores tokens)
- [ ] `aria-live` em alertas dinâmicos
- [ ] Modal com `role=dialog`, `aria-modal=true`, `aria-labelledby`, focus trap, Esc fecha

### AC-D — Mobile responsivo (até 375px)
- [ ] Tabelas → cards/lista em < 768px
- [ ] Modais full-screen / bottom-sheet em < 640px
- [ ] Toques ≥ 44x44px
- [ ] Sem scroll horizontal

### AC-E — Performance percebida
- [ ] Skeletons aparecem em < 100ms
- [ ] Operações > 1s mostram progresso
- [ ] Sem freeze de UI

### AC-F — Localização pt-BR (RNF-018)
- [ ] **Zero strings em inglês** voltadas ao usuário
- [ ] Datas/horários com `useTimezoneRenderer` (Luxon pt-BR)
- [ ] Moeda `R$ 1.234,56` via `Intl.NumberFormat('pt-BR')`
- [ ] Status enum traduzidos (waiting → "Aguardando" etc.)

---

## Princípios UX (não-negociáveis)

- **Tom de voz**: profissional + empático em pt-BR
- **Densidade informacional**: hierarquia tipográfica 12/14/16/20, whitespace generoso, sem cores berrantes
- **Cores semânticas**: vermelho destrutivo/emergência, verde sucesso, amber atenção, azul (cor produto) CTA primário. **Proibido roxo/rosa/gradiente**.
- **Reuso de componentes**: padrões já validados em `AppointmentFormModal` (a11y completo) e `RescheduleConfirmModal` — replicar em vez de reinventar
- **Reuso de composables**: `useTimezoneRenderer` para qualquer data/hora; criar `useToast()` se padrão repete 3+ vezes (já está implícito em AgendaPage e WaitlistPage)

---

## Fora de escopo

- ❌ Animações elaboradas (Vue `<Transition>` em status changes — fica P2 backlog)
- ❌ Multi-prof view com `@fullcalendar/resource` (premium — bloqueado por dep ausente)
- ❌ AC-6.6.6 relatório waitlist agregado (P3 do spec original)
- ❌ Heartbeat `useSlotReservation` integrado (item separado — backlog técnico Fase 5)
- ❌ Nova feature funcional (esta fase é POLISH, não construção)

---

## Definição de Pronto (DoD)

Antes de mergear esta branch para `005-agendamento-consultas` (ou `main` se `005` já mergeou):

- [ ] **Os 4 itens** com critério AC-A → AC-F verde
- [ ] Suite full `vendor/bin/sail artisan test --compact` — sem regressão (≥ 1167 tests passando como na Fase 5)
- [ ] Pint clean
- [ ] Sem nova dep npm
- [ ] **Manual review pelo owner** (smoke visual no browser via `vendor/bin/sail npm run dev`):
  - Caminho feliz de cada tela
  - Mobile 375px (Chrome DevTools)
  - Só com teclado (Esc/Enter/Tab)
  - Estados error com rede desconectada (DevTools offline)

---

## Referências

- Heurística completa: `.claude/agents/frontend-ux-quality.md`
- Patterns validados: `resources/js/pages/agenda/AgendaPage.vue` (commit `22f4e86`) + `WaitlistPage.vue` (commit `b0d0ea6`)
- Patterns de modal a11y: `resources/js/components/agenda/AppointmentFormModal.vue` + `RescheduleConfirmModal.vue` (refatorados)
- Tokens design system: `resources/css/tokens.css` + Tailwind defaults
