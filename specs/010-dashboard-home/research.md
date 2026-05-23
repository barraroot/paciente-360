# Research — Dashboard Home (010)

**Status**: Complete | **Date**: 2026-05-23

Todas as decisões técnicas necessárias para destravar Phase 1 (data-model, contracts, quickstart) consolidadas. Não há `NEEDS CLARIFICATION` pendentes vindos do plan.

---

## R1 — Endpoint consolidado único vs múltiplos endpoints por seção

**Decision**: **Endpoint único** `GET /api/v1/panel/home?scope=user|clinic` retorna todas as 4 seções em uma única response (KPIs + upcoming + attention + activity).

**Rationale**:
- SC-008 exige "exatamente 1 chamada à API por carga inicial". Atende diretamente.
- Reduz latency total no cliente: 1× TLS handshake + 1× roundtrip de auth, vs 4×.
- Cache Redis fica simples: 1 chave por (tenant, user, scope), invalidação atômica.
- Frontend gerencia loading com 1 promise; estados de cada seção derivam do payload (não há "kpis ok, mas activity ainda carregando").
- Endpoint individual por seção só faz sentido se houver re-uso (ex.: outra tela só com KPIs). Não é o caso aqui.

**Alternatives considered**:
- *4 endpoints* (`/panel/home/kpis`, `/panel/home/upcoming`, ...): permitiria carregamento progressivo (KPIs aparecem antes da timeline). Mas custo de N requests + cache mais fragmentado + complexidade do frontend não compensam para uma tela cuja meta é 1s total.
- *GraphQL*: nenhum precedente no projeto; over-engineering para 4 chunks fixos.

---

## R2 — Cache Redis 30s escopado por tenant + user + scope

**Decision**: Cache key `panel_home:{tenant_id}:{user_id}:{scope}` com TTL 30s. Implementação via `Cache::remember()` do Laravel. Invalidação **passiva** (expira sozinho) — sem invalidação ativa por eventos.

**Rationale**:
- 30s é o equilíbrio entre frescor percebido (auto-refresh do front é 2min, cache não interfere com isso significativamente) e redução de carga em DB (até 4 requests/min/user agregadas num único hit no cache window).
- Invalidação passiva é simples e correta para dashboard read-only: usuário pode ver dado "30s velho" sem prejuízo material. A próxima janela já pega dado fresco.
- Cache key inclui `scope` porque "Minha visão" e "Visão da clínica" têm payloads diferentes — chave separada por scope evita servir errado.
- Princípio II (multi-tenant): tenant_id na chave garante isolamento mesmo se houver colisão hipotética de user_id entre tenants.

**Alternatives considered**:
- *Invalidação ativa por eventos* (ex.: `AppointmentCreated` invalida cache de todos os users daquele tenant): adiciona complexidade significativa (listener fan-out) sem ganho perceptível para o usuário. 30s é frescor mais que suficiente em UX.
- *Sem cache, queries diretas*: mata o p95 < 500ms target. Tenants grandes com 10k appointments/dia gerariam scan pesado em cada call.
- *Cache mais longo (5min)*: usuário veria "consultas hoje: 11" enquanto a 12ª já foi marcada — frustração. 30s ainda dentro de tolerância.

---

## R3 — Cache TTL configurável via `config/panel.php`

**Decision**: Criar `config/panel.php` com 3 chaves: `cache_ttl_seconds` (default 30), `autorefresh_seconds` (default 120), `upcoming_window_minutes` (default 360 = 6h). Cada chave ledora de env (`PANEL_HOME_CACHE_TTL`, `PANEL_HOME_AUTOREFRESH`, `PANEL_HOME_UPCOMING_WINDOW`).

**Rationale**:
- Defaults conservadores que cobrem o caso médio.
- Per Q2 da clarification: janela de 6h é fixa no MVP, mas armazenar em config (vs hardcoded) custa nada e abre porta para configurabilidade futura sem refactor.
- Env override permite ajuste em ambiente sem deploy (ex.: staging com TTL=5s para testes).
- Pattern já presente em outros configs do projeto (`config/finalization.php` da Fase 8).

**Alternatives considered**:
- *Hardcoded constants no service*: bloqueia configurabilidade — vai contra o princípio "deploy-safe configuration".
- *Tenant settings* (`tenant.settings.panel_home.*`): overkill para versão 1; pode entrar depois se algum tenant demandar.

---

## R4 — Collectors por seção: isolamento de responsabilidade e teste

**Decision**: 4 classes separadas em `app/Services/Panel/Collectors/`:
- `KpiCollector::collect(Tenant, User, scope): array` — 4 `count()` queries agregadas
- `UpcomingAppointmentsCollector::collect(...)`: lista até 5 appointments com eager loading
- `AttentionItemsCollector::collect(...)`: heterogêneo, retorna `Collection<AttentionItemDto>` ordenado por urgência
- `RecentActivityCollector::collect(...)`: query em `audit_logs` com filtros + humanização

Cada collector tem interface implícita (mesmo método `collect()` com signature padronizada). `PanelHomeService` instancia os 4 e compõe o response.

**Rationale**:
- Cada collector testável isoladamente (Unit tests dedicados).
- Falha em um collector pode degradar gracefully (response com placeholder para aquela seção) sem matar o endpoint inteiro — atende Sentry tag `panel_home.section_failed` previsto na Constitution Check.
- Separation of concerns: o leitor de uma classe não precisa entender as 3 outras.

**Alternatives considered**:
- *Tudo dentro do `PanelHomeService`*: arquivo de 500+ linhas, queries de 4 domínios diferentes misturadas, difícil de manter e testar.
- *1 collector por entidade* (AppointmentCollector, PrescriptionCollector...): perderia coesão de "seção visual" — KPIs precisam de 4 entidades diferentes, juntando elas em 1 collector é mais coerente com o que aparece na tela.

---

## R5 — Eager loading rigoroso + assertQueryCount como gate

**Decision**: Para cada query de lista (`UpcomingAppointmentsCollector`, `AttentionItemsCollector`, `RecentActivityCollector`), aplicar `with([...])` para todos os relacionamentos acessados na renderização. Gate test `PanelHomeNplusOneTest` valida via `DB::enableQueryLog()` + `assertCount()` (alvo: ≤ 12 queries totais por call, incluindo cache lookup).

**Rationale**:
- N+1 é o killer típico de p95 em dashboards. Sem gate, regressão silenciosa é provável.
- Pattern já usado em outras features (Inbox e Pacientes têm `assertQueryCount` similar nos seus tests).
- 12 queries é um teto razoável: 4 counts (KPIs) + 1 lista appointments + 1 lista alertas + 1 lista activity + 5 buffer = teto pragmatic.

**Alternatives considered**:
- *Confiar no eager loading manual sem teste*: prática frágil; depende de revisão humana em PR.
- *Telescope query log em prod*: feedback tardio + custo de armazenamento.

---

## R6 — Definição de "atenção" e ordenação por severidade

**Decision**: `AttentionItemsCollector` produz uma lista heterogênea de `AttentionItemDto` com campos:
- `type`: `'conversation_escalated' | 'prescription_expiring' | 'paciente_funil_stale' | 'confirmation_pending' | 'webhook_dlq'`
- `severity`: `'danger' | 'warn' | 'info'`
- `title_key`: i18n key
- `description`: string truncada com nome do recurso (sem PII sensível)
- `link`: rota relativa do front (`/panel/inbox/conversa/123`, etc.)
- `occurred_at`: ISO 8601 para ordenação secundária

Ordenação: `severity DESC` (danger primeiro) → `occurred_at DESC` (mais recente primeiro). Top 5 retornados.

Mapeamento de severidade:
| Tipo | Severity |
|---|---|
| `conversation_escalated > 10min` | danger |
| `prescription_expiring ≤ 7d` | danger |
| `paciente_funil_stale > 48h` | warn |
| `confirmation_pending` | warn |
| `webhook_dlq` | info |

**Rationale**:
- Lista heterogênea com tipo discriminador é o padrão idiomático para "mixed alerts" UI.
- Ordenação determinística (severity → occurred_at) — testável sem precisar mockar `now()`.
- Q3 da clarification: estágios do funil `lead, qualificando, interessado, agendamento` são os únicos que produzem `paciente_funil_stale`.

**Alternatives considered**:
- *Scoring numérico de urgência (0-100)*: mais flexível mas adiciona complexidade. Trade-off não vale para 5 tipos fixos.
- *Cada tipo em endpoint separado*: viola SC-008 (1 request).

---

## R7 — Timeline humanização: helper backend + gate test LGPD

**Decision**: `RecentActivityCollector` produz strings já humanizadas no backend (PT-BR), via helper `humanizeAuditEvent(AuditLog $event): string` que mapeia `event_type` para uma frase template (ex.: `paciente.created` → `"{actor.name} criou paciente {target.name}"`). Helper NUNCA inclui CPF, telefone completo, email completo ou conteúdo clínico. Gate test `PanelHomeRecentActivityLgpdTest` valida via regex que descrições não casam patterns sensíveis.

**Rationale**:
- Humanização no backend evita lógica de string-format espalhada no front em N idiomas (atual: só pt-BR; futuro: en, es).
- Centralização permite gate test único cobrindo TODAS as descrições.
- Regex assertions específicos:
  - CPF: `/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/`
  - Telefone completo: `/\b(?:\+?55)?\s?\(?\d{2}\)?\s?9?\d{4}-?\d{4}\b/`
  - Email completo: `/\b[\w.-]+@[\w.-]+\.\w+\b/`
  - CRM/sigilo médico: heurística simples (palavras-chave em allowlist)

**Alternatives considered**:
- *Humanização no frontend* (i18n templates): replica lógica + dificulta gate LGPD único.
- *Sem humanização — payload bruto*: pior UX e mesmo problema de validação.

---

## R8 — Permission gates via Policy

**Decision**: `PanelHomePolicy` em `app/Policies/Panel/` com métodos:
- `canSeeClinicScope(User $user): bool` — retorna `$user->hasRole('admin-clinica')` (ou role mapeada equivalente)
- `canSeeWebhookDlqAlerts(User $user): bool` — retorna `$user->can('webhook.manage')`
- `canSeePaymentAlerts(User $user): bool` — (placeholder para spec futura, retorna sempre false hoje)

Service consulta a policy ANTES de invocar collectors específicos. Toggle de scope no payload é restringido a quem tem `canSeeClinicScope`.

**Rationale**:
- Policy é o ponto canônico de auth no Laravel; mantém pattern do projeto.
- Frontend recebe campo `effective_scope` na response indicando o scope efetivamente aplicado — se o frontend pedir `?scope=clinic` mas o user não tem permissão, backend força `user` e devolve `effective_scope: 'user'` (Q1: usuário sem permissão de admin SEMPRE em "Minha visão").
- FR-013 (alertas filtrados por permissão): policy é o gate.

**Alternatives considered**:
- *Middleware*: middleware é para "permitir/negar request inteira"; aqui o gate é por seção, não por endpoint inteiro.
- *Inline checks no service*: poluem service com lógica de auth; viola separation of concerns.

---

## R9 — Frontend: estado em composable `usePanelHome`, sem store global

**Decision**: Toda a lógica de fetch + estado + auto-refresh + retry vive em `usePanelHome.js` (composable). O `PanelHome.vue` consome o composable e renderiza os 4 componentes filhos. Sem Pinia store dedicada para o dashboard.

**Rationale**:
- Dashboard é tela única — não há outro componente que precise compartilhar o estado.
- Composable é mais leve e o Vue 3 idiomático para state local de tela.
- Pinia store seria over-engineering: nenhum outro lugar lê os dados do panel home.

**Alternatives considered**:
- *Store Pinia*: complexo demais para um caso de uso fechado.
- *Estado direto no `PanelHome.vue`*: dificulta extração de auto-refresh + cache + retry (que o composable encapsula).

---

## R10 — Auto-refresh com Page Visibility API

**Decision**: `useAutoRefresh.js` recebe um callback e um intervalo (ms). Internamente:
- `setInterval` quando `document.visibilityState === 'visible'`
- Cleanup do interval em `visibilitychange` quando aba some
- Re-criação do interval quando aba volta
- Trigger imediato de refresh quando aba volta após mais de `interval/2` em background (caso comum: usuário voltou e quer ver atualizado)

**Rationale**:
- `Page Visibility API` é nativa, suporte universal em browsers modernos.
- Pausar em background economiza requests (SC-009: 0 requests com aba oculta).
- Trigger imediato no return-to-focus melhora UX percebida.

**Alternatives considered**:
- *Always-on interval*: viola SC-009.
- *`requestIdleCallback`*: bom para tarefas opcionais; não é o caso aqui (atualizar dashboard é trabalho explícito).

---

## R11 — Persistência local do scope: chave separada do app-shell

**Decision**: Composable `usePanelHomeScope.js` lê/escreve `localStorage` chave `panel_home:scope:v1` aninhada por `tenant_slug + user_id` (mesmo padrão de R4 do spec 009). Valor: `'user' | 'clinic'`. Default: `'user'`.

**Rationale**:
- Princípio II (multi-tenant): chave escopada por tenant+user — mesmo padrão de defesa em profundidade do spec 009.
- Chave SEPARADA da `app-shell:preferences:v1` para evitar conflito de schema/versionamento; sidebar prefs e dashboard scope são preocupações independentes.
- Fallback robusto: localStorage indisponível → memória local volátil; user vê "Minha visão" por padrão e a escolha não persiste mas tudo continua funcionando.

**Alternatives considered**:
- *Mesmo localStorage do app-shell*: acopla schemas — mudança em um impactaria o outro.
- *Persistir no backend (em `users.settings`)*: sincronização cross-device seria nice mas não é requisito; adiciona migration + endpoint sem ganho material no MVP.

---

## R12 — Métricas Prometheus + Sentry tags

**Decision**: `PanelHomeMetrics extends AbstractModuleMetrics` expõe:
- `panel_home_requests_total{tenant, scope, cache_hit}` (counter)
- `panel_home_duration_seconds{section}` (histogram com buckets `[0.05, 0.1, 0.25, 0.5, 1.0, 2.5]`)
- `panel_home_cache_hits_total{tenant}` (counter)
- `panel_home_section_failures_total{section}` (counter, incrementado quando collector falha)

Sentry tags injetadas em scope local da request:
- `panel_home.tenant_id`
- `panel_home.scope`
- `panel_home.cache_hit`

**Rationale**:
- Pattern já estabelecido (`AgendaMetrics`, `AuthMetrics`).
- Histogram com buckets focados em p95 < 500ms (alvo no SC).
- Section_failures permite alerting granular (ex.: alerta se >5% das requests têm section_failed em janela 5min).

**Alternatives considered**:
- *Single histogram para o endpoint inteiro*: perderia visibilidade de qual seção é gargalo.

---

## R13 — Falha graceful por seção (degraded response)

**Decision**: Se um collector lança exceção, `PanelHomeService` captura, registra no Sentry (`panel_home.section_failed`), incrementa métrica, e retorna a seção como `null` com `error: true` no envelope. Frontend renderiza placeholder específico ("Não foi possível carregar esta seção. Recarregue para tentar novamente.") na seção afetada — outras seções permanecem funcionais.

**Rationale**:
- Dashboard tem 4 seções independentes; falha de uma não deveria matar as outras 3.
- Atende FR-037 (banner de erro não-bloqueante).
- Sentinela explícito (`error: true`) permite ao front diferenciar "seção vazia naturalmente" de "falhou".

**Alternatives considered**:
- *Resposta 500 inteira*: usuário perde tudo por causa de bug isolado em audit log query.
- *Silenciar a falha (retornar empty)*: esconde bug em produção; viola observabilidade.

---

## Resumo das decisões

| ID | Decisão | Impacto |
|---|---|---|
| R1 | Endpoint único consolidado | 1 request inicial (SC-008) |
| R2 | Cache Redis 30s escopado por tenant+user+scope | p95 < 500ms; isolamento Princípio II |
| R3 | `config/panel.php` com TTL/window/autorefresh configuráveis | Deploy-safe tuning |
| R4 | 4 collectors separados em `app/Services/Panel/Collectors/` | Testabilidade + degradação graceful |
| R5 | Eager loading + gate test N+1 (assertQueryCount ≤ 12) | Sem regressão silenciosa |
| R6 | `AttentionItemDto` heterogêneo com `type` + severity → ordenação determinística | UX claro + testável |
| R7 | Humanização da timeline no backend + gate LGPD por regex | FR-019 enforced |
| R8 | `PanelHomePolicy` com `canSeeClinicScope` e `canSeeWebhookDlqAlerts` | Permission filtering + Q1 |
| R9 | Composable `usePanelHome.js` (sem Pinia store) | Estado local de tela |
| R10 | `useAutoRefresh` com Page Visibility API | SC-009 (0 req em background) |
| R11 | localStorage `panel_home:scope:v1` escopado por tenant+user | Persistência + isolamento |
| R12 | Métricas Prometheus + Sentry tags em `PanelHomeMetrics` | Princípio V |
| R13 | Falha graceful por seção (`error: true` + Sentry tag) | Resiliência sem quebrar UX |

Todas as decisões honram o Constitution Check (zero violações).
