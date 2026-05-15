---
name: Fase 4 entregue — Token Auth Migration
description: Cookie→Bearer Sanctum migration entregue 2026-05-13. 9 commits D-K + docs. Suite 1130/1127 verde.
type: project
originSessionId: 8ce6dc33-e69e-4924-8c99-d3e67981f57c
---
Fase 4 (`004-token-auth-migration`) entregue em 2026-05-13, branch `004-token-auth-migration` aguardando merge em `main`.

**Why:** migração de cookie-session SPA stateful para Bearer Sanctum stateless habilita deploy decoupled (SPA em `app.crm.com.br` + API em `api.crm.com.br`) e mobile/Postman/integradores externos. Constitution amendment v1.4.0 (`2791c54`) destravou implementação.

**How to apply:** futuras features que tocam auth devem seguir os 7 padrões documentados em CLAUDE.md "Token Auth (Fase 4) — Key Patterns". Em especial: `User::guardName()` pina Spatie em 'web' (não remover sob risco de quebrar `$user->can()` em prod); `tenant.slug` middleware aplicado apenas em `/auth/*` (rollout para `/inbox/*` ainda pendente — afetaria ~227 callers); `sanctum.guard = ['web']` fallback mantido até migração manual dos 16 chained `actingAs`.

**Commits (9):**
- `40af4ec` Lote A-D — Setup + UNIQUE email migration + Auth domain + US1 Login Bearer
- `88c875f` Lote E — US2 SPA Vue (Pinia + axios + Tokens page)
- `185df2e` Lote F — US3 Reverb Bearer + bug arquitetural `User::guardName()` corrigido
- `4158f88` Lote G — US4 CORS + US5 Filament + US6 Webhooks + `audit_logs_cold.executor_id` fix
- `43fc9ad` Lote H — US7 OpenAPI bearerAuth + Scribe + Postman collection
- `8fc9fa0` Lote I — Test migration actingAs→Sanctum (120 subs) + FR-005 fix (suspended tenant 403)
- `0c815fa` Lote J — Polish: purge command + AuthMetrics 4 counters + Sentry tags + CSP refinement
- `1db8e96` Lote K — Gate final: suite 1130/1127/0/0, Pint clean, OpenAPI drift 0
- `ffc6b59` Docs T097-T099 — Quickstart DoR + CLAUDE.md SPECKIT + README

**Suite progression:** 882 (pré-Fase 4) → 1130 / 1127 passed / 0 failures / 0 errors.

**Pendências operacionais pós-merge (não-bloqueantes):**
- Provisionar domínios prod app.crm.com.br + api.crm.com.br
- Smoke E2E manual (checklist 12 itens em tasks.md T104)
- Coverage CI Codecov (run --coverage leva ~12min, deferred)
- Migrar 16 chained `actingAs` para enable `sanctum.guard = []` strict Bearer-only
- Rollout middleware `tenant.slug` para `/inbox/*` e demais rotas API
