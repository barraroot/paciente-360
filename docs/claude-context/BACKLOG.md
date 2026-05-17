# Backlog acumulado (Fases 5 + 6) — pós-migração

**Status**: trabalho técnico das Fases 5+6 entregue. Itens abaixo são **débitos técnicos não-bloqueantes** + **P2 UX** + **smoke pendente**.

---

## Débitos técnicos Fase 5

Documentados nos commits da Fase 5. Não bloqueiam merge — são melhorias incrementais.

### Backend / arquitetura

1. **`SlotGeneratorService` cache Redis 60s** (R7 do plan)
   - State: algoritmo determinístico funciona; sem cache ainda
   - Impacto: SC-009 (slot list ≤300ms p95) passa em test atual mas vai apertar em escala
   - Onde: `app/Services/Agenda/SlotGeneratorService.php`
   - Esforço: M (1-2h — adicionar cache wrapper + invalidação granular nos listeners)

2. **Listeners de escalada inbox são log-stubs**
   - `DispatchConfirmationToInbox`, `EscalateCancellationOutsideWindowToInbox`, `EscalateRescheduleLimitExceededToInbox`, `DispatchWaitlistOfferToInbox` — todos hoje só fazem `Log::info(...)` ao invés de chamar `MessagingDispatcher` da Fase 3
   - Bloqueador: `MessagingDispatcher` da Fase 3 não expõe entry-point para confirmações de agenda ainda
   - Esforço: M (depende de aceitar adicionar método no MessagingDispatcher Fase 3)

3. **Detecção de "paciente sem canal"** usa `telefone_primario` como proxy
   - Em `ConfirmationDispatcherService::dispatchOne()`: `! empty($appointment->paciente?->telefone_primario)`
   - Refinar para verificar se há `Conversation` ativa em messaging_conversations — quando integração com Fase 3 evoluir
   - Esforço: S

4. **`DetectGoogleSyncFailureJob` não testado**
   - Cache Redis behavior (2 falhas em 1min) não tem test isolado
   - Lógica está implementada mas só validada por inspeção
   - Esforço: S (escrever test com Cache::fake)

5. **Listener `SyncAppointmentToGoogleCalendar` não testado em isolamento**
   - Triggered via auto-discovery Laravel 11+, mas sem assertion de que dispatch acontece
   - Esforço: S

### Tests da Fase 5

6. **Cobertura por US: 3-4 happy paths cada** (vs 6-8 ACs declarados no spec)
   - 37 tests cobrem caminhos críticos + gates obrigatórios (race, LGPD, cross-tenant Google)
   - Faltam: cenários retry T-30min, idempotência reverse AC-6.4.6, via_ia integração, drag client-side com snap-back, multi-prof toggle, cadastro rápido paciente, override_block com push notification, cascade FR-028c via ScheduleException, médico cancela própria consulta, múltiplas listas waitlist simultâneas (AC-6.6.5), AC-6.6.6 relatório agregado
   - Esforço: L (4-6h — uma sessão de "expand tests")

7. **CalendarSyncOAuthTest e TimezoneRenderTest** previstos no plan mas não criados
   - Apenas T140 (CrossTenantGoogleSync) e T141 (GoogleEventPayloadLgpd) foram entregues como gates obrigatórios
   - Esforço: M

### Frontend Fase 5 (parcialmente refinado em Fase 6)

8. **AgendaPage — multi-prof view requer `@fullcalendar/resource` (premium)**
   - Toggle existe mas sem efeito (clarify nº 9 prevê)
   - Bloqueado por dep paga
   - Decisão: comprar licença OU implementar versão custom OU descartar feature

9. **AgendaPage — heartbeat `useSlotReservation`** não integrado ao AppointmentFormModal
   - Composable existe; modal não chama
   - Risco: enquanto form aberto, reserva expira em 5min sem aviso ao user
   - Esforço: S

10. **AgendaPage — listener `datesSet` do FullCalendar**
    - Quando user navega prev/next semana, store.range não atualiza → consultas mostradas ficam desatualizadas
    - Esforço: S

11. **AgendaPage — indicação visual de slots disponíveis vs ocupados**
    - Hoje só mostra eventos. Spec diz para sugerir slots livres no calendário
    - Esforço: M

---

## Débitos técnicos Fase 6 (UX polish)

P2 acumulados pelos lotes — backlog ordenado por valor:

| # | Item | Origem | Esforço |
|---|---|---|---|
| 12 | Wizard "Copiar de outro profissional" (clarify nº 5 avançado) | Lote B (B-B13) | M — requer GET `/agenda/professionals/{id}/schedules` de outro prof |
| 13 | Popover de reversão como bottom sheet no mobile (`AttendanceMarkButton`) | Lote D (D-D8) | S — posicionamento viewport-aware |
| 14 | Animação Vue `<Transition>` nos modais (cosmético — entrada/saída) | Lote A + todos | S |
| 15 | Ícones SVG oficiais Google/Outlook | Lote C (C-C10) | S — depende aprovação marketing/legal |
| 16 | Preview de slots gerados pós-config | Lote B (B-B14) | L — chama GET `/agenda/slots-disponiveis` em tempo real |
| 17 | Drag-and-drop reordering blocos intra-dia | Lote B (B-B15) | M — `@vueuse/core useSortable` já disponível |
| 18 | Busca/filtro por nome em Tipos | Lote A (A-A12) | S |
| 19 | Watch channel renewal automático antes de expirar | Lote C | L — job schedulado + endpoint |

---

## Smoke pendente (gates humanos)

### Fase 5 — Smoke E2E QA staging (bloqueia merge)
- Doc: `docs/qa/smoke-fase5-agendamento.md` (567 linhas, 9 sessões, ~2-3h)
- Pré-requisitos: OAuth Client GCP + env staging + migrations + seeders + cron
- Gate: cross-tenant Google leak deve estar OK (Apêndice B do checklist)

### Fase 6 — Smoke visual humano (bloqueia merge)
6 telas para validar manualmente no browser via `vendor/bin/sail npm run dev`:

1. **AgendaPage** — drag-create + drag-to-move com confirm + edge case slot ocupado snap-back
2. **WaitlistPage** — empty state CTA + modal inscrição + countdown row notified + cards mobile
3. **AppointmentTypesPage** — modal inativar + R$ formatado pt-BR + tooltip Retorno + cards mobile
4. **ScheduleConfigPage** — accordion 7 dias + validação overlap + Ctrl+S + timeline exceções
5. **CalendarSyncPage** — 3 estados (não conectado / conectado / erro) + Outlook disabled
6. **AttendanceMarkButton** — clique "Não realizada" expande inline (sem `prompt()`)

Para cada: testar caminho feliz + mobile 375px (Chrome DevTools) + teclado-only + rede offline.

---

## Próxima Fase (Fase 7 — Épico 7 do PRD)

**Sugestão de escopo**:
- **Gestão de Retornos** — cadência automática "está na hora do seu retorno X dias após consulta Y"
- **Outlook sync** — deferred da Fase 5 (clarify nº 11). Modelo `provider` enum aceita 'outlook' desde Lote B Fase 5. ~80% do código `GoogleCalendarSyncService` reusa.
- **Receituários médicos** — entidade nova (Receita) + emissão PDF + assinatura digital (?)

Quando iniciar: `/speckit.specify Fase 7 — Gestão de Retornos + Outlook sync + Receituários`.

---

## Decisões abertas

Ver `PENDING-DECISIONS.md` neste mesmo diretório.
