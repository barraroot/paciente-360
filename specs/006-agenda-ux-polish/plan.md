# Plan: Polimento UX da Agenda (pós-Fase 5)

**Branch**: `006-agenda-ux-polish`
**Date**: 2026-05-15
**Spec**: [spec.md](./spec.md)

## Summary

Refinement UX cirúrgico de 4 telas/componente da Fase 5. Sem stack mudança, sem nova dep, sem novo endpoint backend. **Reusa exatamente os patterns** já validados em `AgendaPage.vue` (commit `22f4e86`) e `WaitlistPage.vue` (commit `b0d0ea6`) — modais a11y, status badges semânticos, skeleton/empty/error states, mobile cards, atalhos teclado.

## Technical Context

**Stack**: Vue 3 Composition API + Pinia + Tailwind v4 + Heroicons + Luxon + axios via `@/lib/api`. Zero dep nova.

**Endpoints já disponíveis** (da Fase 5):
- `GET/POST/PATCH/DELETE /agenda/appointment-types` (US-6.2)
- `GET/PUT /agenda/professionals/{id}/schedules` + `GET/POST/DELETE schedule-exceptions` (US-6.1)
- `POST /agenda/calendar-sync/google/{connect,callback,disconnect}` + `GET /agenda/calendar-sync` (US-6.7)
- `POST /agenda/consultas/{id}/marcar-comparecimento` + `reverter-comparecimento` (US-6.4 / clarify nº 14)

**Patterns de modal a11y reutilizáveis** (de `AppointmentFormModal.vue`):
- `Teleport to="body"` + `role=dialog` + `aria-modal="true"` + `aria-labelledby`
- Focus trap Tab/Shift+Tab dentro do modal
- Esc fecha via `@keydown.esc.prevent`
- Bottom-sheet em mobile (`items-end sm:items-center`)
- Botão primário à direita
- Erros 422 mapeados em `fieldErrors` por campo com `role="alert"`

**Padrões de estado já validados** (de AgendaPage/WaitlistPage):
- Skeleton com `aria-busy="true"` + animação Tailwind `animate-pulse`
- Empty state: ícone Heroicons + texto + CTA primário
- Error state: mensagem em pt-BR + botão "Tentar novamente"
- Toast inline ou via composable simples (estado local com `setTimeout` 5s)
- Status badges: cores semânticas (não inventar fora do palette)

## Constitution Check

✅ **PASS** — esta phase é PURO refinement de UX. Não toca:
- Princípio I (LGPD): não muda persistência nem criptografia
- Princípio II (Multi-tenant): não toca queries (mesmos endpoints)
- Princípio III (IA): não invoca LLM
- Princípio IV (TDD): patterns já testados; refinements UX são manuais (audit visual + browser logs)
- Princípio V (Observabilidade): não muda métricas
- Princípio VI (Meta): não-impactante
- Princípio VII (Segurança): não toca auth/CSP/encrypted; pode até melhorar (focus trap previne tab-jacking)

## Project Structure

Apenas frontend:

```
resources/js/
├── components/agenda/
│   ├── AttendanceMarkButton.vue      [REFACTOR — Lote D]
│   ├── AppointmentTypeForm.vue       [provavelmente edit — Lote A]
│   ├── ScheduleExceptionForm.vue     [provavelmente edit — Lote B]
│   └── (novos componentes só se padrão repete 3+ vezes)
├── pages/agenda/
│   ├── AppointmentTypesPage.vue      [REFINE — Lote A]
│   ├── ScheduleConfigPage.vue        [REFINE — Lote B]
│   └── CalendarSyncPage.vue          [REFINE — Lote C]
└── composables/
    └── useToast.js                   [POSSIVELMENTE NOVO — se padrão de toast repete em 3+ telas]
```

## Estratégia de Implementation

**1 agent vue-frontend-engineer** invocado em modo UX quality auditor, recebendo TODOS os 4 itens em prompt único. Agent decide ordem interna baseada em complexidade (provavelmente: AppointmentTypesPage → CalendarSyncPage → ScheduleConfigPage → AttendanceMarkButton).

**Por que 1 agent só** (vs 4 paralelos):
- Conhece contexto compartilhado (`router/index.js`, `agendaApi.js` já editados anteriormente)
- Evita conflito em arquivos compartilhados
- Reusa decisões de UX (mesmo padrão de toast, badge, modal em todas)
- 1 commit por tela facilita revisão

**Commits esperados** (4):
- `feat(types-ux): refinamento UX AppointmentTypesPage`
- `feat(schedule-config-ux): refinamento UX ScheduleConfigPage`
- `feat(calendar-sync-ux): refinamento UX CalendarSyncPage`
- `feat(attendance-mark-ux): substitui prompt/confirm por popover acessível`

Mais 1 commit final consolidador atualizando `tasks.md` com status [X] para todos.

## Validação

Após cada commit:
1. `vendor/bin/sail bin pint --dirty --format agent` (não deve aplicar — só JS)
2. ESLint se projeto tem (`vendor/bin/sail npm run lint`)
3. Manual no browser via dev server + browser-logs MCP

Final:
1. `vendor/bin/sail artisan test --compact` — sem regressão (≥ 1167 tests)
2. Smoke visual humano (descrito em DoD do spec)
