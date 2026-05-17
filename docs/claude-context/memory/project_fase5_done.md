---
name: Fase 5 entregue
description: Agendamento de Consultas (Épico 6) — 7 user stories, 14 entidades, 16 eventos, 6 cron jobs, sub-cal Google tenant-scoped, 37 tests verdes em 8 lotes A-H entregues 2026-05-14
type: project
originSessionId: dd5761cd-3e18-4ab8-b693-246eb024324c
---
**Fato**: Fase 5 (Agendamento de Consultas) entregue em 2026-05-14 — 185/185 tasks via 8 lotes A-H. Branch `005-agendamento-consultas` aguardando merge em `main`.

**Why**: Outcome de negócio = converter leads qualificados (Fase 2) em receita previsível + reduzir no-show via confirmação automática. Primeira fase que fecha o loop com Inbox Omnichannel (Fase 3) e expõe contratos para futura IA Matricial.

**How to apply**:
- Toda nova feature de agenda usa os patterns da seção "Agendamento (Fase 5) — Key Patterns" em `CLAUDE.md`
- Stubs `GoogleCalendarApiClient` precisam de implementação real antes de smoke E2E em staging com OAuth real (quickstart §8.4)
- 4 dívidas técnicas explícitas para próximas iterações: cobertura tests por US (3-4 happy paths cada vs 6-8 ACs declarados), AgendaPage.vue precisa integração real FullCalendar v6 (atualmente lista simples), SlotGenerator sem cache Redis 60s ainda, listeners de escalada (Cancel/Reschedule/Waitlist) são stubs log para integração com MessagingDispatcher (Fase 3) quando expor entry-point

**Highlights de design**:
- 7 user stories: agenda, tipos, drag-and-drop, confirmação T-24h/T-2h/retry, cancelamento via chat, lista de espera FIFO sequencial K=1, sync Google Calendar via sub-cal tenant-scoped
- Outlook DEFERRED → Fase 6 (modelo `provider` enum preparado — clarify nº 11)
- Constitution Check PASS nos 7 princípios **sem amendment** (v1.4.0 cobre)
- Bug arquitetural descoberto: Laravel 11+ Event Discovery duplica se registrado manualmente em AppServiceProvider — fix removeu registrações manuais (Lote F)

**Tests verdes** (37):
- Gates críticos: SlotConflictRaceTest (FR-011a/SC-008), GoogleEventPayloadLgpdTest (Princípio I/FR-038), CrossTenantGoogleSyncTest (clarify nº 15/AC-6.7.11)
- US coverage: 3 schedule + 3 type + 4 creation + 4 confirmation + 4 attendance + 4 cancellation + 4 waitlist
- Princípio II: 5 cross-tenant + 1 perf

**Commits Fase 5**:
- A `a7087eb` — Setup
- B `6cc6e25` — Foundational (14 migrations + abilities + 5 factories)
- C `34c2855` — US1+US2
- D `8f7d23d` — US3 drag-and-drop
- E `e718d59` — US4 confirmação
- F `4b262cd` — US5+US6 cancel + waitlist
- G `2b1dfbf` — US7 Google sync
- H — Polish (este lote)
