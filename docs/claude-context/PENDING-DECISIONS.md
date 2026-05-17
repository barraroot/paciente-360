# Decisões pendentes — para próxima sessão Claude

Decisões que ficaram em aberto e dependem de input do owner antes de prosseguir.

---

## D1 — `@fullcalendar/resource` (premium licença) para multi-prof view

**Contexto**: clarify nº 9 da Fase 5 previu toggle "Multi-profissional" na view semanal usando `resourceTimeGridWeek`. Implementado o toggle no UI mas sem efeito real porque `@fullcalendar/resource` é pacote premium (licença paga).

**Estado atual**: toggle aparece em AgendaPage, sem-op quando ativado.

**Opções**:
- **A) Comprar licença FullCalendar Premium** (~$480 USD/ano por dev) — desbloqueia `resource-timegrid`, `timeline`, `scheduler`
- **B) Implementar versão custom** — colunas Vue manualmente lado-a-lado, cada uma com seu `timeGridWeek`
- **C) Cortar feature** — remover toggle, documentar como "Fora de escopo"

**Recomendação minha**: **B** se a feature for realmente usada (testar em staging com 2+ profissionais primeiro); **C** se for vanity feature sem demanda real.

**Quem decide**: owner (custo vs valor de produto).

---

## D2 — Listeners de escalada inbox (Fase 5 → Fase 3)

**Contexto**: 4 listeners da Fase 5 (`DispatchConfirmationToInbox`, `EscalateCancellationOutsideWindowToInbox`, `EscalateRescheduleLimitExceededToInbox`, `DispatchWaitlistOfferToInbox`) hoje só fazem `Log::info(...)` — não criam handoff/note real na inbox da Fase 3.

**Bloqueador**: `MessagingDispatcher` da Fase 3 não expõe entry-point específico para confirmações/escaladas de agenda.

**Opções**:
- **A) Adicionar método `MessagingDispatcher::createHandoffForAgenda(AppointmentEvent $event)`** na Fase 3 — retroativo, exige PR na Fase 3
- **B) Criar conversa direta** via `Conversation::create([...])` no listener da Fase 5 — bypassa Dispatcher (acoplamento ruim)
- **C) Job intermediário** que enfileira "criar handoff inbox" e workers da Fase 3 consomem — desacoplado mas latência maior

**Recomendação minha**: **A** — adicionar 1 método público no MessagingDispatcher da Fase 3 com signature `(string $type, Appointment $appointment, array $metadata): Conversation`. Esforço S (1-2h).

**Quem decide**: owner + tech lead Fase 3.

---

## D3 — `@fullcalendar/resource` ausente bloqueia teste de auditoria multi-prof

Relacionado a D1 — sem o pacote, não dá para auditar UX da feature multi-prof. Decisão aqui depende de D1.

---

## D4 — Spec Kit retroativo nas próximas mini-features de UX

**Contexto**: Fase 6 (006-agenda-ux-polish) foi formalizada via Spec Kit retroativo (manual, sem rodar `/speckit.specify` interativo). Funcionou bem para escopo pequeno.

**Pergunta**: nas próximas mini-features de polish (ex.: refinar telas Fase 0/2/3 que também são stubs antigos), seguimos o mesmo padrão de Spec Kit retroativo manual?

**Opções**:
- **A) Sim, manter padrão** — Spec Kit retroativo manual para escopo < 30 tasks
- **B) Rodar Spec Kit completo** (`/speckit.specify` → `/speckit.plan` → `/speckit.tasks`) sempre — mais formal, mais lento
- **C) Skip Spec Kit** para refinements pequenos — usar Agent direto

**Recomendação minha**: **A** — boa-relação esforço/rastreabilidade.

**Quem decide**: owner.

---

## D5 — Escopo Fase 7

**Sugestão original**: Gestão de Retornos + Outlook sync + Receituários médicos.

**Pergunta**: cabe os 3 escopos numa fase só ou separar?

**Opções**:
- **A) 1 fase só (Fase 7) com 3 épicos** — risco: muito grande, longa
- **B) Fase 7 = Gestão de Retornos** + **Fase 8 = Outlook sync** + **Fase 9 = Receituários** — mais modular, entregas incrementais
- **C) Outlook sync vira mini-feature `008-outlook-sync` em paralelo** (similar ao 006-agenda-ux-polish) — reaproveita ~80% de código da Fase 5

**Recomendação minha**: **C** — Outlook sync é tecnicamente quase pronto (modelo `provider` enum preparado, services reutilizáveis). Faz como mini-feature paralela. Fase 7 fica focada em Gestão de Retornos. Receituários vira Fase 8 (escopo grande próprio).

**Quem decide**: owner (priorização produto).

---

## D6 — Estratégia de teste para `GoogleCalendarApiClient` real

**Contexto**: implementação real do `GoogleCalendarApiClient` (commit `3d9eb89`) substitui stubs. Tests existentes usam `FakeGoogleCalendarApiClient` (`tests/Fakes/Agenda/`) — não fazem requests reais ao Google.

**Pergunta**: como testar a implementação real?

**Opções**:
- **A) Smoke E2E manual em staging** com conta Google de teste (já no checklist QA)
- **B) Test de integração que bate em Google API real** com conta dedicada `smoke-tester@gmail.com` — caro, frágil, mas pega regressões reais
- **C) Test de integração com mock HTTP do Google** (e.g., `vcr-php` para gravar/replay) — meio termo

**Recomendação minha**: **A** + **C** futuramente. Smoke manual cobre validação inicial; VCR gravado fica como gate CI quando time crescer.

**Quem decide**: owner + QA lead.

---

## D7 — Onde mergear Fase 6 (`006-agenda-ux-polish`)

**Contexto**: Fase 6 foi forkada de `005-agendamento-consultas` (não de `main`). Tem 6 commits incluindo refs aos commits da Fase 5.

**Opções**:
- **A) Merge ordem 005 → main, depois 006 → main** — clean history, sem conflito
- **B) Merge 005 → main + cherry-pick 006 commits** — perde a structure de PR separada
- **C) Mergear 006 dentro da PR da Fase 5** (rebase + force push) — 1 PR só, mais commits

**Recomendação minha**: **A** — mantém PRs revisáveis independentes. Owner só precisa cuidar da ordem de merge.

**Quem decide**: owner.

---

## D8 — Migração para servidor remoto: validação pós-clone

**Contexto**: você está migrando para servidor de desenvolvimento remoto. Após clone + setup, primeira sessão Claude no destino vai precisar:
1. Ler `docs/claude-context/REHYDRATE-INSTRUCTIONS.md`
2. Recriar memória local em `~/.claude/projects/<NOVO-HASH>/memory/` via Write tool
3. Continuar trabalho

**Pergunta**: você quer rodar smoke validation antes de continuar trabalho novo?

**Recomendação minha**: sim — comece a primeira sessão lá com:
1. `vendor/bin/sail artisan test --compact` — confirma 1167 tests verdes
2. `vendor/bin/sail npm run dev` + abrir 6 telas refinadas no browser
3. Validar que nenhuma regressão veio da migração

Só depois disso atacar Fase 7 ou abrir PRs.
