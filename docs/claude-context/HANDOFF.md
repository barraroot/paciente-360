# HANDOFF — Estado da sessão Claude para migração

**Origem**: WSL Ubuntu (`/home/lucas/projetos/paciente-360`)
**Destino**: servidor de desenvolvimento remoto (path TBD)
**Data**: 2026-05-15
**Sessão Claude**: ~50 turnos, 39 commits, Fases 5+6 entregues

---

## Estado git ao migrar

### Branches existentes (todas pushadas para `origin`)

| Branch | Última commit | Status | Para que serve |
|---|---|---|---|
| `main` | `88cdc7e` | base — Fase 0-4 mergeadas | base estável |
| `005-agendamento-consultas` | `b0d0ea6` | ✅ pushed, **PR aberto pendente** | Fase 5 (Agendamento) — aguarda smoke E2E + Google OAuth real + merge |
| `006-agenda-ux-polish` | `25e5b79` | ✅ pushed, **PR não criado ainda** | Fase 6 (Polimento UX) — aguarda smoke visual humano + PR + merge (depende de 005 mergear primeiro) |

### Commits Fase 5 (branch `005-agendamento-consultas`, 13 commits)

```
b0d0ea6 feat(waitlist-ux): polish completo da WaitlistPage
22f4e86 feat(agenda-ux): integra FullCalendar v6 + estados completos + a11y
ad77c1d chore(agents): novo agent frontend-ux-quality
cb35229 docs(qa): smoke checklist E2E detalhado Fase 5
eb60ded docs(agenda): Postman collection (37 requests)
3d9eb89 feat(agenda): GoogleCalendarApiClient implementação real
5dc57a4 [Spec Kit] feat(implement T171-T185): Lote H — Polish
2b1dfbf [Spec Kit] feat(implement T139-T170): Lote G — US7 Google Sync
4b262cd [Spec Kit] feat(implement T116-T138): Lote F — US5 Cancel + US6 Waitlist
e718d59 [Spec Kit] feat(implement T096-T115): Lote E — US4 Confirmação + Comparecimento
8f7d23d [Spec Kit] feat(implement T060-T095): Lote D — US3 drag-and-drop + race gate
34c2855 [Spec Kit] feat(implement T032-T059): Lote C — US1 Schedule + US2 Types
6cc6e25 [Spec Kit] feat(implement T006-T031): Lote B — Foundational
a7087eb [Spec Kit] feat(implement T001-T005): Lote A — Setup
```

### Commits Fase 6 (branch `006-agenda-ux-polish`, 6 commits — fork de `005-...`)

```
25e5b79 chore(ux-polish): consolidação Lote A-D + atualização docs
c9fe8bf feat(schedule-config-ux): refinamento UX ScheduleConfigPage + exceções a11y
fc946ed feat(attendance-mark-ux): substitui prompt/confirm por popover inline acessível
0a471ad feat(calendar-sync-ux): refinamento UX CalendarSyncPage + estados rich
b12c976 feat(types-ux): refinamento UX AppointmentTypesPage + form a11y
cc11a21 [Spec Kit] docs: Polimento UX da Agenda (006) — spec + plan + tasks
```

---

## Estado da Fase 5 — entregue, aguardando smoke E2E + merge

| Item | Status |
|---|---|
| 185/185 tasks Spec Kit | ✅ commits A-H (`a7087eb` → `5dc57a4`) |
| `GoogleCalendarApiClient` real (substitui stubs Lote G) | ✅ commit `3d9eb89` (`google/apiclient v2.19.3`) |
| 37 tests Fase 5 GREEN + suite full 1167/1164/0 | ✅ |
| Postman collection (37 requests) | ✅ `docs/api/Paciente360-Agenda-Fase5.postman_collection.json` |
| Smoke checklist QA detalhado | ✅ `docs/qa/smoke-fase5-agendamento.md` (567 linhas, 9 sessões) |
| **Smoke E2E QA staging com Google real** | ⏳ **GATE HUMANO PENDENTE** |
| **PR aberto** | 🔗 https://github.com/barraroot/paciente-360/pull/new/005-agendamento-consultas (pendente criação manual ou via gh CLI) |
| Code review | ⏳ |
| Merge → main | ⏳ |

**Gate de bloqueio para merge** (do smoke checklist Apêndice B):
1. Cross-tenant Google leak (item §9.4) precisa estar OK — gate de segurança/LGPD
2. Payload Google sem PII (item §8.2) — gate Princípio I
3. Race condition (item §4.2) retorna 409 — gate FR-011a/SC-008
4. Migrations aplicam em staging
5. <5 erros 5xx durante smoke
6. Sem cron throw exception não tratada

---

## Estado da Fase 6 (UX polish) — entregue, aguardando smoke visual + PR

| Item | Status |
|---|---|
| spec.md + plan.md + tasks.md | ✅ commit `cc11a21` |
| Lote A — `AppointmentTypesPage` + form | ✅ commit `b12c976` (12 achados) |
| Lote C — `CalendarSyncPage` | ✅ commit `0a471ad` (10 achados) |
| Lote D — `AttendanceMarkButton` | ✅ commit `fc946ed` (8 achados) |
| Lote B — `ScheduleConfigPage` + form | ✅ commit `c9fe8bf` (15 achados) |
| Consolidação tasks.md + CLAUDE.md | ✅ commit `25e5b79` |
| Suite full sem regressão (1167/1164/0) | ✅ |
| **Smoke visual humano** (mobile 375px, teclado-only, rede offline) | ⏳ **GATE HUMANO PENDENTE** |
| PR aberto | ⏳ pendente (depende de 005 mergear primeiro OU pode ir em paralelo) |

Total Fase 6: **28 achados** (10 P0, 12 P1, 6 P2). 22 P0+P1 corrigidos, 6 P2 backlog.

### Pré-requisitos AgendaPage + WaitlistPage (commits na branch 005)

A Fase 6 depende dessas 2 telas refinadas que ficaram na 005:
- `AgendaPage.vue` — commit `22f4e86` (FullCalendar v6 + estados completos + a11y + Reverb sync)
- `WaitlistPage.vue` — commit `b0d0ea6` (modais a11y + status badges + countdown live + filtros + cards mobile)

**Importante**: ao mergear 005 + 006 separadamente, garante ordem (005 primeiro). Ou pode resolver merge conflict trivial se for em ordem inversa.

---

## Estado do Spec Kit

5 features no diretório `specs/`:
- `001-fundacao-multitenant/` (entregue, em `main`)
- `002-crm-pacientes/` (entregue, em `main`)
- `003-omnichannel-inbox/` (entregue, em `main`)
- `004-token-auth-migration/` (entregue, em `main`)
- `005-agendamento-consultas/` (entregue na branch, aguardando merge)
- `006-agenda-ux-polish/` (entregue na branch, aguardando merge)

`.specify/feature.json` aponta para `005-agendamento-consultas` (não atualizei para 006 — não rodou `/speckit.specify` interativo nesta feature).

---

## Próximos passos (próxima sessão Claude)

### Imediatos (gate humano)
1. **Abrir PR Fase 5** — link: https://github.com/barraroot/paciente-360/pull/new/005-agendamento-consultas
   - Título + body prontos em `docs/qa/smoke-fase5-agendamento.md` (Apêndice C tem refs)
   - Descrição completa também aparece em chat history
2. **Configurar OAuth Google** Cloud Console + setar `GOOGLE_CALENDAR_*` em staging .env (quickstart §2-3)
3. **Smoke E2E QA staging** seguindo `docs/qa/smoke-fase5-agendamento.md` (~2-3h, 9 sessões)
4. **Smoke visual** Fase 6 nas 6 telas refinadas (mobile 375px + teclado-only + rede offline)

### Próxima fase (após smoke)
- **Fase 7 — Gestão de Retornos** (Épico 7 do PRD): cadência automática "está na hora do seu retorno", **Outlook sync** (deferred da Fase 5 — modelo `provider` enum preparado), Receituários médicos.

### Débitos técnicos abertos
Ver `BACKLOG.md` neste mesmo diretório.

### Decisões pendentes
Ver `PENDING-DECISIONS.md` neste mesmo diretório.

---

## Estado de tooling local (não migra automaticamente)

- **Sail Docker**: 6 containers rodando (`paciente-360-{horizon,laravel.test,mailpit,pgsql,redis,reverb}-1`). Sobem novos no destino com `vendor/bin/sail up -d`.
- **`.env` local**: NÃO commitado. Precisa ser recriado/copiado via secret manager. Veja `.env.example` (atualizado na Fase 5 com vars Google Calendar).
- **DB local**: dados de teste podem ser reseedeados (`db:seed --class=AppointmentTypeSeeder` etc.)
- **`vendor/`** + **`node_modules/`**: composer install + npm install --legacy-peer-deps no destino
- **Claude memory**: copiada para `docs/claude-context/memory/` (este projeto). Veja `REHYDRATE-INSTRUCTIONS.md` para repopular no destino.

---

## Tarefas TaskCreate ativas no momento da migração

39 tarefas criadas durante Fases 5+6+migration. Todas marcadas `[completed]` ao final desta migração.

A próxima sessão começa com lista vazia — recriar via `TaskCreate` quando necessário.
