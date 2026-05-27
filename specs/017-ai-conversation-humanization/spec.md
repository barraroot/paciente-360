# Feature Specification: Humanização da Conversa da IA (Contexto + Histórico por Clínica)

**Feature Branch**: `017-ai-conversation-humanization`
**Created**: 2026-05-27
**Status**: Draft
**Input**: User description: "Precisamos deixar a conversa da IA com o paciente mais humanizada, dar contexto do que está sendo falado e histórico das conversas para ela, claro que economizando tokens pensando sempre performance da aplicação + qualidade. Gostaria que a persona IA respondesse parecido como as conversas que estão em conversa1.txt e conversa2.txt. Planeje as mudanças necessárias para que cada clínica consiga atingir esse objetivo configurando seu contexto de trabalho dentro desse SAAS."

## Context: What "Humanized" Means Here

Two real example conversations (`storage/app/conversa1.txt`, `storage/app/conversa2.txt`) define the target conversational quality. Both follow the same pattern of a warm, consultative attendant who converts an inbound lead into a booked, confirmed appointment:

1. **Warm, empathetic opening** — greets, uses an emoji, asks the patient's main complaint to understand before helping ("Qual dor ou sintoma está mais te incomodando hoje?").
2. **Validation before advancing** — acknowledges feelings first ("Entendi, sinto muito por isso 💔"), then moves on.
3. **One question at a time, progressive qualification** — frequency → impact on daily life → prior treatment, building a picture of the case.
4. **Personalization** — addresses the patient by name once known ("Entendi, Maria 💛") and mirrors their specific concern (hormonal/perimenopause).
5. **Value-building before price** — explains the doctor's differentiated, individualized approach so the price feels justified, and only then quotes the value.
6. **Stays on thread across long gaps** — patients reply hours (or a day) later; the attendant continues coherently instead of restarting or repeating.
7. **Natural close toward booking** — price → location preference → available slots → reservation, including the deposit/confirmation policy.
8. **Tone** — warm, professional, short paragraphs, tasteful emojis (💛 ✨ 😊 🤍), never a clinical diagnosis.

The current AI (Fase 15 "IA Matricial") cannot reliably reproduce this because **it only sees the single latest patient message** — it has no memory of the conversation, no awareness of where it is in the funnel, and clinics have no guided way to encode "how we sell our consultation" beyond a free-form persona text. The most visible symptom: in `conversa1.txt` the patient ends up copying the bot's own question back to it, because nothing in the exchange holds the thread.

This feature gives the AI **conversation history + situational context**, lets **each clinic configure its commercial/operational "work context"**, and does so under a **strict token budget** so quality rises without runaway cost.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - IA mantém o fio da conversa (histórico + contexto) (Priority: P1)

A patient exchanges several messages with the AI over hours. Each AI reply takes into account everything already said — the complaint, the answers given, the city chosen, the price already quoted — so the conversation feels like one continuous human dialogue, never a bot that forgot the previous turn or asked something already answered.

**Why this priority**: This is the root cause of the "robotic" feeling and the single biggest quality lever. Without history, no amount of persona tuning produces the example conversations. Delivers value on its own even before clinics customize anything.

**Independent Test**: Send a multi-turn conversation to a clinic's AI (complaint → frequency → impact → "qual o valor?"). Verify the AI never re-asks an answered question, references earlier answers ("pela frequência das crises que você relatou..."), and continues coherently after a simulated multi-hour gap.

**Acceptance Scenarios**:

1. **Given** a patient who already stated their complaint and frequency, **When** they ask a new question, **Then** the AI's reply reflects the prior answers and does not re-ask the complaint or frequency.
2. **Given** a conversation where the patient already chose a city, **When** the AI proposes slots, **Then** it proposes slots for the chosen city without asking again.
3. **Given** a long conversation that exceeds the token budget, **When** the AI replies, **Then** older turns are condensed (summarized) while the most recent turns are kept verbatim, and the reply is still coherent with the early context.
4. **Given** a patient who pastes/echoes the AI's own previous message back, **When** the AI processes it, **Then** it recognizes the repetition and advances the conversation instead of looping.

---

### User Story 2 - Clínica configura seu "contexto de trabalho" (Priority: P1)

A clinic admin opens the AI configuration area and fills in the clinic's commercial and operational context — what the clinic/professional does and its differentiators, how it qualifies patients, services and pricing, locations, scheduling and deposit/confirmation policy, tone of voice and the qualification questions to ask. With this filled in, the AI's conversations resemble the example conversations for that clinic, without anyone editing prompts or code.

**Why this priority**: The user's explicit goal — "cada clínica consiga atingir esse objetivo configurando seu contexto de trabalho dentro desse SAAS." The history (US1) is the engine; this is the steering wheel each clinic uses to sound like *their* business.

**Independent Test**: As a clinic admin, fill the work-context configuration with the data implied by `conversa1.txt`/`conversa2.txt` (cefaleia/enxaqueca focus, individualized approach, R$300/R$330, Aracaju/Itabaiana, 20% deposit). Then run a fresh conversation and verify the AI naturally surfaces those facts at the right moments (value before price, correct cities, deposit policy on confirmation).

**Acceptance Scenarios**:

1. **Given** a clinic with no work context configured, **When** the admin saves a complete work-context profile, **Then** subsequent AI replies for that clinic reflect the configured services, differentiators, pricing, locations and policies.
2. **Given** two different clinics with different work contexts, **When** each handles a similar patient, **Then** each AI reflects only its own clinic's facts (no cross-tenant leakage).
3. **Given** a configured tone (e.g., "acolhedor, com emojis"), **When** the AI replies, **Then** the style matches the configured tone.
4. **Given** configured qualification questions, **When** a new lead arrives, **Then** the AI asks them progressively (one at a time), not all at once.

---

### User Story 3 - Economia de tokens e performance sob controle (Priority: P2)

The platform operator needs conversation quality to improve **without** proportional cost growth. As conversations get longer, the amount of context sent to the model stays within a configured budget through windowing + rolling summarization, and the added latency stays acceptable.

**Why this priority**: Explicit non-functional requirement ("economizando tokens pensando sempre performance"). It protects margin and latency as US1/US2 increase context size. Important but meaningless without US1 existing first.

**Independent Test**: Run a 40-message conversation and confirm the per-request input token count stays under the configured ceiling, the summary of older turns is reused/cached rather than rebuilt every turn, and end-to-end reply latency stays within target.

**Acceptance Scenarios**:

1. **Given** a conversation longer than the verbatim window, **When** a new message arrives, **Then** only the recent window is sent verbatim plus a compact running summary of everything before it.
2. **Given** the running summary already exists, **When** a new message arrives that doesn't yet require re-summarizing, **Then** the existing summary is reused without an extra model call.
3. **Given** a configured per-request input-token ceiling, **When** context is assembled, **Then** the assembled context (instructions + work context + RAG + summary + window + current message) never exceeds the ceiling.

---

### User Story 4 - Consciência de etapa do funil (Priority: P3)

The AI knows *where* it is in the funnel (e.g., greeting, qualifying, value-building, pricing, location, slot offer, reservation) and behaves accordingly — it doesn't quote price before qualifying, and it doesn't re-open qualification after the patient has asked to book.

**Why this priority**: Sharpens the human feel and conversion flow, but US1+US2 already deliver most of the perceived improvement. This is refinement.

**Independent Test**: Walk a conversation from greeting to booking and verify the AI transitions stages in order, surfacing price only after value, and slots only after the patient signals intent.

**Acceptance Scenarios**:

1. **Given** a patient who just stated a complaint, **When** the AI replies, **Then** it qualifies further rather than immediately quoting price.
2. **Given** a patient who said "quero agendar", **When** the AI replies, **Then** it moves to location/slot offering rather than re-qualifying.

---

### User Story 5 - IA consulta dados reais da clínica e cria/segura o agendamento (ferramentas/MCP) (Priority: P2)

Instead of relying only on statically configured facts, the AI can query the clinic's live data during the conversation — real available slots, professionals, procedures/prices/hours/address — and can create or look up the patient's lead record and place a *tentative* slot hold. This lets it answer "tem horário quinta?" with the true agenda and reserve it provisionally, while confirmed booking and payment are handed off.

**Why this priority**: Grounds the humanized replies in real, current data (a static slot list goes stale instantly) and turns the conversation into an actual outcome (lead + tentative hold). Sits at P2 because US1 (history) and US2 (work context) deliver the core humanization first; tools make it accurate and actionable.

**Independent Test**: With a clinic that has real availability and procedures in the system, run a conversation through to slot offering and verify: the slots offered match the live agenda, a lead record is created/looked up by phone, a tentative hold is placed, no other patient's data is ever returned, and confirmation/payment is handed off (not done by the AI).

**Acceptance Scenarios**:

1. **Given** a clinic with real availability, **When** the patient asks for times, **Then** the AI offers slots that match the live agenda (not a stale configured list).
2. **Given** a new inbound contact, **When** the AI engages, **Then** it creates or looks up the lead record by phone without exposing any other patient's data.
3. **Given** the patient picks a slot, **When** the AI proceeds, **Then** it places a *tentative* hold and hands off confirmation/payment rather than confirming or charging autonomously.
4. **Given** a tool fails, times out, or returns nothing, **When** the AI replies, **Then** it degrades gracefully (asks or hands off) and never invents a price, slot, or address.
5. **Given** a question the live data can answer, **When** the AI replies, **Then** it uses the live value over any conflicting configured/knowledge-base value.

---

### Edge Cases

- **Patient writes nothing useful / single emoji / "oi"** — AI still opens warmly and asks the qualifying question; history of one message must not break context assembly.
- **Very long single message** (patient pastes a long history) — must be counted against the token budget and truncated/handled without exceeding the ceiling.
- **Patient echoes the AI's own prior message** (seen in `conversa1.txt`) — must not cause a loop; AI advances.
- **Multi-hour / multi-day gap** between messages — history must still be available and coherent; summary must not have "expired."
- **Conversation already escalated to a human** — AI must not re-engage with context as if it owns the thread (respects existing escalation/auto-pause).
- **Work context partially filled** — AI degrades gracefully, using whatever is configured plus persona defaults, never inventing prices/locations that weren't provided.
- **Clinic configures conflicting facts** (e.g., price in work context vs. knowledge base) — there must be a defined precedence so the AI doesn't contradict itself.
- **Patient shares clinical detail** (symptoms, medication) — must continue to be handled under existing clinical-safety guardrails; humanization must not weaken the no-diagnosis / escalation rules.
- **Token budget too small to fit the current message + minimal context** — defined fallback (e.g., drop summary granularity before dropping safety instructions; never drop guardrails).
- **Tool times out or errors** — AI must degrade gracefully (ask or hand off) and never invent the missing fact; the failed tool call must be audited.
- **Patient not found by phone** — lookup returns "no record"; AI proceeds to create a lead rather than guessing an identity.
- **Slot taken between hold and confirmation** — the tentative hold must not be presented as a confirmed booking; the handoff must reflect that confirmation can still fail.
- **Clinic has no live data for a requested fact** (e.g., prices not in DB) — AI falls back to work-context config per the precedence (FR-011), not to fabrication.
- **AI tries to exceed the round-trip cap** (e.g., chained lookups) — bounded at 3; further attempts force a best-effort answer or handoff.
- **Name placeholder with unknown name** — if the contact has no known first name, the `{{primeiro_nome}}` placeholder must be stripped/neutralized before sending; the literal token must never appear in the patient-facing message.

## Requirements *(mandatory)*

### Functional Requirements

#### Conversation history & context (US1)

- **FR-001**: The system MUST include prior messages of the same conversation when composing the context sent to the AI, not only the latest inbound message.
- **FR-002**: The system MUST pass the AI the **minimum sufficient** history to respond with context — never the full message log and never today's empty single-message window. The default composition is a small fixed verbatim window (~3 most-recent turns / ≈6 messages) plus a compact rolling summary of all prior turns; window and summary sizes are configurable but default to this minimum.
- **FR-002a**: The compact rolling summary MUST carry only the key facts needed to stay coherent (complaint, qualification answers, chosen location, quoted price, expressed intent, funnel stage), and MUST be **preferred over raw history** when both could supply the same fact, so older raw messages are dropped rather than re-sent.
- **FR-002b**: The rolling summary MUST be generated/sent only once the conversation has turns *beyond* the verbatim window; for conversations that fit within the window, only the window is sent (no summary overhead).
- **FR-003**: The AI MUST NOT re-ask information the patient has already provided earlier in the same conversation.
- **FR-004**: The system MUST preserve key conversation facts across turns (complaint, qualification answers, chosen location, quoted price, expressed intent) so they remain available to the AI even after older turns are summarized.
- **FR-005**: The system MUST handle a patient message that repeats/echoes a previous AI message without entering a repetition loop.
- **FR-006**: Conversation history and any derived summary MUST be scoped to a single conversation and a single tenant; the AI MUST never receive messages from another conversation or another clinic.

#### Per-clinic work context (US2)

- **FR-007**: Each clinic MUST be able to configure a "work context" that the AI uses for all its conversations, using a **hybrid form**: structured fields for the operationally critical facts (services, pricing, locations, deposit/confirmation policy, tone of voice, and the ordered qualification questions) PLUS a free-form section for differentiators and approach narrative.
- **FR-008**: The configured work context MUST be applied to the AI's replies so that, for the same patient input, two clinics with different work contexts produce clinic-appropriate, distinct responses.
- **FR-009**: The system MUST let clinic administrators create and edit the work context through the existing AI configuration UI, without code or prompt-engineering.
- **FR-010**: The AI MUST NOT state prices, locations, slots, or policies that are not available from one of its authorized sources (live data tools, configured work context, or knowledge base). When no source can supply a requested fact, the AI MUST ask or hand off rather than fabricate it.
- **FR-011**: The system MUST define a deterministic precedence across all fact sources so the AI does not contradict itself: **live data tools are authoritative for dynamic/transactional data and any fact stored in the DB**; **work-context config is authoritative for voice/policy and for facts not in the DB**; **persona text and knowledge base (RAG) are lowest precedence** (explanatory background only).
- **FR-012**: Work context configuration MUST be tenant-isolated and MUST NOT leak across clinics.

#### Humanized behavior (US2 / US4)

- **FR-013**: The AI MUST validate/acknowledge the patient's concern before advancing the funnel (empathy-first behavior matching the examples).
- **FR-014**: The AI MUST ask qualification questions progressively (one primary question per turn) rather than presenting a form-like list.
- **FR-015**: The AI MUST build value (explain the differentiated approach) before quoting price when the patient asks about cost early, consistent with the configured work context.
- **FR-016**: The AI MUST follow the configured tone of voice (including emoji usage if configured) consistently across the conversation.
- **FR-017**: The system MUST allow personalization with the patient's name in outbound replies when the name is known, **without sending the real name to the AI provider**: the model receives a name placeholder and the real first name is substituted into the outbound message after the model responds. **When the name is unknown** (e.g., a brand-new lead with no name yet), the substitution MUST remove the placeholder / fall back to a neutral greeting — the literal placeholder (`{{primeiro_nome}}`) MUST NOT reach the patient.

#### Funnel & handoff (US4)

- **FR-018**: The AI MUST progress through funnel stages coherently (qualification → value → price → location → slot offer) and not regress to earlier stages once the patient signals booking intent. The AI MAY quote price and propose available slots autonomously and place a tentative slot hold, but the deposit/payment (PIX) collection and the financial/booking confirmation MUST be handed off to the existing reservation flow / a human — the AI MUST NOT autonomously request payment or confirm a booking.
- **FR-019**: The system MUST preserve all existing clinical-safety guardrails, intent classification, confidence scoring, escalation, and auto-pause behavior; humanization MUST NOT weaken any safety/escalation rule.
- **FR-020**: When the AI cannot safely continue (urgency, clinical question, low confidence, blocked intent), it MUST escalate to a human exactly as today, regardless of how "human" the preceding tone was.

#### Token economy & performance (US3)

- **FR-021**: The system MUST enforce a configurable ceiling on the total input context (instructions + work context + retrieved knowledge + summary + verbatim window + current message) sent per AI request.
- **FR-022**: The system MUST reuse/cache the running conversation summary so that adding a new turn does not require re-summarizing the whole history on every message.
- **FR-023**: When the assembled context would exceed the ceiling, the system MUST shed lower-priority content first (oldest detail / least-relevant knowledge) while ALWAYS retaining the safety guardrails and the current patient message.
- **FR-024**: The system MUST continue to record per-request token usage and latency for audit/billing as it does today, including the new history/summary/work-context components.

#### Live data tools (MCP) (US5)

- **FR-027**: The system MUST expose to the AI a set of live data tools, all scoped to the conversation's tenant, that let it query the clinic's own data and the current patient's record while composing a reply.
- **FR-028**: Read tools MUST cover at minimum: clinic information (services/procedures, prices, business hours, address), professionals, real-time appointment availability/slots, and the current patient's own record.
- **FR-029**: Patient-level read tools MUST resolve only the contact in the current conversation (matched by channel identifier, e.g., phone). They MUST NOT return any other patient's record and MUST NOT support name-based patient search.
- **FR-030**: Write tools MUST be limited to reversible actions: (a) create or look up a lead, which maps to the existing CRM patient/contact record (created/looked up by phone and flagged as a lead, or placed in the existing lead stage), and (b) place a *tentative* slot hold. Confirmed booking and payment MUST NOT be performed by the AI.
- **FR-031**: Every tool invocation MUST be auditable (tool name, minimized inputs, outcome) under the existing AI logging and retention rules; any PII in tool inputs/outputs MUST follow the existing pseudonimization and retention model.
- **FR-032**: The system MUST bound tool usage to at most 3 tool round-trips per AI reply; beyond the cap the AI MUST produce a best-effort answer or hand off, never loop.
- **FR-033**: When a tool fails, times out, or returns no data, the AI MUST degrade gracefully (ask the patient or hand off) and MUST NOT fabricate the missing fact (reinforces FR-010).
- **FR-034**: Tool access MUST enforce tenant isolation at the data layer (not only via the prompt); a tool MUST be incapable of returning another tenant's data even if prompted to.

#### Auditability & privacy

- **FR-025**: All AI executions MUST remain auditable (which context, summary version, work-context version, and knowledge chunks informed a reply), without storing prohibited clinical PII beyond existing retention rules.
- **FR-026**: Any patient PII included for personalization MUST respect the existing consent/finality and pseudonimization model; the resolution chosen for FR-017 MUST not broaden what raw PII is sent to the model beyond what is necessary and consented.

### Key Entities *(include if feature involves data)*

- **Work Context (per clinic)**: The clinic's commercial/operational profile the AI uses — description & differentiators, services, pricing, locations, scheduling/deposit/confirmation policy, tone, qualification questions. Tenant-scoped; versioned for auditability. Relationship: belongs to a tenant; consumed alongside the persona and knowledge base when composing AI context.
- **Conversation Summary**: A compact, running representation of the older portion of a conversation plus its key facts (complaint, answers, location, price, intent, stage). Tenant- and conversation-scoped; regenerated incrementally; reused across turns to save tokens.
- **Conversation Context Window**: The set of most-recent verbatim turns included per request, bounded by count and token budget.
- **Funnel Stage**: The conversation's current stage in the commercial flow (greeting, qualifying, value, pricing, location, slot, reservation, escalated), used to keep behavior coherent.
- **AI Persona / Knowledge Base / Guardrail (existing)**: Reused from Fase 15; the work context complements — not replaces — these, with a defined precedence (FR-011).
- **Live Data Tool (MCP)**: A tenant-scoped capability the AI can invoke mid-conversation to read clinic/patient data or perform a reversible write. Read tools (clinic info, professionals, availability, current patient record) and reversible-write tools (create/lookup lead, tentative slot hold). Each invocation is audited; access is enforced at the data layer.
- **Lead (existing CRM record)**: Reuses the Fase 2 CRM patient/contact entity in a lead state, created/looked up by phone. Becomes a regular patient once a booking is confirmed downstream. No separate parallel entity.
- **Slot Hold (tentative)**: A provisional, reversible reservation placed by the AI on a real availability slot, pending human/flow confirmation; distinct from a confirmed appointment.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a benchmark set of multi-turn conversations, the AI re-asks an already-answered question in **0%** of turns (no regression of the thread).
- **SC-002**: A clinic admin can fully configure the work context and see the AI reflect it, end-to-end, in under **15 minutes** with no developer involvement.
- **SC-003**: For conversations up to **40 messages**, the per-request input token count stays within the configured ceiling **100%** of the time, and median added latency from history/summary stays under **1.5s** versus the current single-message baseline.
- **SC-004**: Average input tokens per AI request grow **sub-linearly** with conversation length (a 40-message conversation costs no more than ~**2×** the tokens of a 5-message conversation, not ~8×).
- **SC-005**: In blind human evaluation against the two reference conversations, reviewers rate the AI's humanization (warmth, coherence, value-before-price, correct facts) at **≥4 of 5** on average across a sample of 20 conversations.
- **SC-006**: **100%** of existing clinical-safety / escalation test cases continue to pass; no safety regression is introduced by humanization.
- **SC-007**: **0** cross-tenant context leaks across the benchmark (no clinic ever sees another clinic's work context, history, summary, or tool data); **0** cross-patient leaks (a tool never returns a record other than the current conversation's contact).
- **SC-008**: With live tools enabled, **p95 end-to-end AI reply time ≤ 8s** and **≤ 3 tool round-trips per reply** across the benchmark; replies that would exceed the cap fall back to best-effort/handoff in **100%** of cases.
- **SC-009**: For facts the live data can supply (real slots, prices/address when DB-backed, professionals), the AI's stated value matches the live source in **≥ 99%** of sampled replies (no stale or fabricated commercial facts).
- **SC-010**: In **100%** of replies on conversations with more than one turn, the AI receives prior-turn context (never the empty single-message window of today); yet the history payload stays minimal — for a conversation past the verbatim window, history (window + summary) contributes no more than the equivalent of **~10 messages** worth of tokens regardless of total conversation length.

## Assumptions

- Builds on the existing Fase 15 "IA Matricial" stack (personas, RAG knowledge base, guardrails, execution logging, escalation/auto-pause). This feature extends, not replaces, that architecture.
- Conversation messages already persist per conversation/tenant (Fase 3 omnichannel inbox); history can be read from there.
- The work context is a per-clinic complement to the persona; clinics with multiple personas share one clinic-level work context unless a future need says otherwise.
- "Economize tokens" is satisfied by a verbatim recent window + rolling summary + a per-request budget; exact thresholds are configurable and tuned during implementation.
- Outbound message style/format (emojis, short paragraphs) is governed by the configured tone plus existing message sanitization; no new channel formatting rules are introduced.
- Latency and cost targets are evaluated for the default model configured for the matricial AI; switching to a heavier model is a clinic/operator choice outside this spec.
- Reference conversations are illustrative of *style and flow*; the system is not required to reproduce them verbatim, only to reach equivalent quality.
- Live data tools are implemented as MCP tools (laravel-mcp) and reuse existing domain capabilities rather than new data stores: CRM patients/leads (Fase 2), agenda availability and the tentative slot-hold / `SlotReservation` mechanism (Fase 5), professionals (Fase 12), and clinic profile data. The tentative hold maps to the existing soft reservation with TTL.
- Tool authorization is derived from the conversation's tenant context, not from an authenticated patient (the patient is an external contact); tenant scoping is enforced at the data layer via existing tenant isolation.
- Whether prices/procedures/address are DB-backed or only in work-context config varies per clinic; the precedence (FR-011) handles both, so the feature does not require migrating all clinics to DB-backed catalogs.

## Clarifications

### Session 2026-05-27

Resolved 2026-05-27 (all confirmed as the recommended option):

- **Q1 — Work-context configuration shape (FR-007/FR-009)**: **Hybrid** — structured fields for operationally critical facts (services, pricing, locations, deposit policy, tone, ordered qualification questions) + a free-form section for differentiators/approach.
- **Q2 — Commercial funnel autonomy (FR-018)**: AI **quotes price and proposes slots autonomously**, but the deposit/payment (PIX) and financial confirmation are **handed off** to the existing reservation flow / a human — the AI does not request or confirm payment.
- **Q3 — Personalization vs. pseudonimization (FR-017/FR-026)**: **Placeholder + re-injection** — the model receives a name placeholder; the real first name is substituted into the outbound message after the model responds. No additional raw PII is sent to the AI provider.

#### Live data tools (MCP) — added 2026-05-27

- Q: Should the AI's live-data tools be read-only, read + reversible writes, or full read/write autonomy? → A: **Read live data freely + writes limited to reversible actions** (create/lookup a lead and place a *tentative* slot hold); confirmed booking and payment remain handed off to the existing flow / human.
- Q: When live tools, work-context config, and the knowledge base hold the same fact, which wins? → A: **Live tools are authoritative for dynamic/transactional data** (real availability, professionals, the current patient's record) and any fact already stored in the DB; **work-context config is authoritative for voice/policy** (tone, differentiators, qualification flow, deposit policy) and for facts not in the DB; **RAG is lowest precedence** (explanatory background only).
- Q: What is the data-exposure boundary for the read tools, given the patient is unauthenticated? → A: **Clinic-level data freely** (services, procedures, prices, hours, address, professionals, aggregate availability); **patient-level data only for the contact in the current conversation**, matched by their channel identifier (phone) — never another patient's record, never name-based patient search. Clinical PII continues through existing guardrails/pseudonimization.
- Q: What does the AI's "lead" create/lookup tool map to? → A: **The existing CRM patient/contact record**, created or looked up by phone and flagged as a new lead (or placed in the existing lead stage/kanban if present). No separate parallel lead entity.
- Q: What latency / tool round-trip budget applies once tool-calling is added? → A: **p95 end-to-end ≤ 8s per AI reply and ≤ 3 tool round-trips per reply**; replies exceeding the cap fall back to a best-effort answer or handoff.

#### Minimal context (history) — added 2026-05-27

- Q: How much conversation history should be passed to the AI, given it must respond *with context* but as economically as possible (never the empty window of today)? → A: **Minimum-sufficient by default**: a small fixed verbatim window (~3 most-recent turns / ≈6 messages) + a compact rolling summary of all prior turns (key facts only); the **summary is preferred over raw history** when both could carry a fact, and the summary is only generated/sent once turns exist *beyond* the window (short conversations send just the window). Window/summary sizes are configurable but default to the minimum.
