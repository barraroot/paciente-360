# Implementation Plan: Fase 4 — Migração Auth Cookie → Bearer Token

**Branch**: `004-token-auth-migration` | **Date**: 2026-05-12 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/004-token-auth-migration/spec.md` (Status: Clarified — 5/5 perguntas resolvidas)

## Summary

Migra autenticação da API tenant de **Sanctum SPA stateful (cookie session)** para **Sanctum Personal Access Tokens (Bearer)**. Filament super admin **preservado em cookie** (guard `web` próprio, isolado). Habilita deploy decoupled `api.crm.com.br` (Laravel) + `app.crm.com.br` (Vue 3 estático em CDN) + clientes externos (mobile, Postman, integrações). Toca constituição (Princípio VII — amendment v1.4.0 obrigatório), ~30 arquivos backend, ~650 testes, todas as policies, Reverb broadcast auth e CORS config.

Decisões consolidadas via `/speckit.clarify` (2026-05-12):
- **Tenant resolution**: header `X-Tenant-Slug` obrigatório (não cookie/subdomain)
- **Token storage**: `localStorage` com mitigações XSS obrigatórias (CSP + DOMPurify + 30d expiration + ESLint no-unsanitized)
- **Refresh strategy**: sliding expiration 30d (toda request renova; throttle interno renova só quando < 5d)
- **Login tenant lookup**: email globalmente único (UNIQUE cross-tenant; migration pré-flight de dedup)
- **Logout scope**: apenas token corrente; `/logout-all` separado para revogar todos

## Technical Context

**Language/Version**: PHP 8.5 (backend), JavaScript ES2023 (frontend)

**Primary Dependencies (NOVAS nesta fase)**:
- Backend: **Sanctum Personal Access Tokens** (já instalado em Fase 0 com `composer require laravel/sanctum`; tabela `personal_access_tokens` existe via Sanctum migration — apenas mudamos o **fluxo** de uso, sem dep nova). Laravel 13 já inclui middleware `HandleCors` nativo (sem `fruitcake/laravel-cors`).
- Frontend:
  - **DOMPurify** (npm — sanitize HTML user-provided antes de render, mitigação XSS R1)
  - **ESLint plugin `no-unsanitized`** (devDep — gate CI bloqueia DOM sinks diretos)
- DevOps:
  - CSP header config via middleware `SetSecurityHeaders` (server-side Laravel ou nginx)

**Primary Dependencies (REUSADAS)**:
- Laravel 13 + PHP 8.5 + PostgreSQL 18 + Redis 7
- **Spatie Permission** team mode preservado — apenas como `team_id` é descoberto muda
- **Filament 5** super admin — cookie session preservado, guard `web` próprio
- **Reverb** broadcasting — `/broadcasting/auth` passa a aceitar Bearer via guard sanctum
- **Echo + pusher-js** — `authorizer` reconfigurado para Bearer (Lote H Fase 3 já tem `authorizer` customizado — só muda header)
- **PHPUnit 12** — `Sanctum::actingAs($user, ['*'])` já compatível
- **`api` instance axios** (`resources/js/lib/api.js`) — reconfigurada com Authorization Bearer interceptor

**Storage**:
- **PostgreSQL 18**: `personal_access_tokens` (Sanctum padrão, já existe); **migration nova**: UNIQUE constraint em `users.email` global (cross-tenant) + comando pré-flight dedup
- **Redis 7**: cache + queue inalterados; session ainda usado por Filament em `crm.com.br`
- **Sem mudança** em schema messaging/pacientes (Fase 3 intocada)

**Testing**: PHPUnit 12. Migração mecânica `$this->actingAs($user)` → `Sanctum::actingAs($user, ['*'])` em ~650 testes via script `tests:migrate-actingas-to-sanctum`. Stress de regressão obrigatório.

**Target Platform**: Linux server (Sail Docker dev; staging/prod Laravel Cloud ou self-hosted). SPA navegador moderno ES2023. **Pós-migração: API e SPA podem viver em domínios distintos.**

**Project Type**: Web application multi-tenant SaaS B2B decoupled (API REST + SPA Vue 3 + Filament super admin).

**Performance Goals**:
- Login p95 < 500ms (SC-003) — bcrypt cost 10 + 1 DB lookup + 1 token insert + audit log
- Authenticated endpoints p95 < 300ms (Princípio V) — preservado
- Sliding expiration UPDATE < 5ms (apenas quando `expires_at - now() < 5d`)
- CORS preflight OPTIONS < 50ms
- Reverb auth via Bearer < 100ms

**Constraints**:
- **Princípio VII NÃO-NEGOCIÁVEL com amendment v1.4.0**: aceitar Bearer como formato adicional (não substitui argon2id + TLS 1.3 + rate limit + brute force lock)
- **Princípio I LGPD**: token opaque (sem PII embarcada); audit log de uso suspeito (mesma key, IPs distintos <5min)
- **Princípio II Multi-tenant não-negociável**: triple-check token + header + user.tenant_id — anti-token-roubo
- **XSS hardening como RELEASE GATES**: CSP estrita + DOMPurify + ESLint no-unsanitized
- **Filament cookie isolation**: cookies de `crm.com.br` NÃO podem cruzar com `app.crm.com.br`
- **Backward compat zero**: breaking change deliberado — flip switch via deploy
- Cobertura ≥ 70% global preservada; OpenAPI drift 0

**Scale/Scope**:
- ~30 arquivos backend (controllers, middleware, providers)
- ~15 arquivos frontend (axios, echo, auth store, login flow)
- ~650 testes migrados (script)
- 5 commands novos (dedup, token purge, tests migrate, etc.)
- 3 migrations (UNIQUE email + housekeeping)
- ~60 tasks totais em 9 lotes

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Princípio I — LGPD (NON-NEGOTIABLE)

- ✅ **Token opaque**: Sanctum tokens são strings random sem PII embarcada (vs JWT). DB armazena hash SHA-256.
- ✅ **Pseudonimização em logs**: middleware `LogStructuredRequestData` (Fase 0/3) estendido para mascarar `Authorization: Bearer <token>` (sempre `Bearer SCRUBBED`).
- ✅ **Audit log de uso suspeito**: novo `TokenUsoSuspeito` event (Auditable) quando mesma token aparece em IPs/UAs distintos em janela <5min — mitiga R1.
- ✅ **Cascateamento**: tokens de user anonimizado são revogados via listener em `PacienteAnonimizado` / `UserAnonimizado` event.
- ✅ **Retenção**: job `auth:tokens-purge-expired` deleta tokens revoked/expired > 90d.

### Princípio II — Isolamento Multi-Tenant (NON-NEGOTIABLE)

- ✅ **Triple-check anti-token-roubo cross-tenant**:
  1. Token Sanctum pertence a `user_id` X
  2. Request envia `X-Tenant-Slug` = Y
  3. Middleware valida `user(X).tenant_id === tenant(Y).id`; mismatch → 403
- ✅ **ResolveTenant** middleware lê header `X-Tenant-Slug` em rotas autenticadas. Subdomínio mantido como fallback (legado/dev local).
- ✅ **Spatie team mode preservado**: `setPermissionsTeamId($tenant->id)` executado por `ResolveTenant` como antes.
- ✅ **Reverb broadcast auth** via Bearer: callback continua validando ability + tenant ownership.
- ✅ **`InboxTenantIsolationTest` Fase 3 deve continuar passando** após migração (gate de regressão).

### Princípio III — Segurança Clínica e Auditabilidade da IA

- ✅ **N/A para esta fase**: migração de auth não afeta camada IA. Contratos `ConversaIATogglingContract` (Fase 3) preservados.

### Princípio IV — Spec-Driven Test-First

- ✅ **Spec Clarified**: 5/5 perguntas resolvidas.
- ✅ **TDD respeitado**: novos endpoints terão testes falhando antes da implementação.
- ⚠️ **Migração de ~650 testes** é o maior risco operacional. Mitigação: comando idempotente + rodar suite após cada batch.
- ✅ **Cobertura ≥ 70% global** preservada.
- ✅ **OpenAPI Scribe**: `bearerAuth` security scheme adicionado; `openapi:check` exit 0.
- ✅ **Pint + test** gate inalterado.

### Princípio V — Observabilidade

- ✅ **Logs estruturados** com `tenant_id, user_id, request_id, token_id (prefix)`.
- ✅ **Eventos auditáveis**: `TokenEmitido`, `TokenRevogado`, `LoginFalhouViaToken`, `TokenUsoSuspeito` — todos `Auditable`.
- ✅ **Métricas Prometheus novas**:
  - `paciente360_auth_login_total{result}`
  - `paciente360_auth_token_emitido_total`
  - `paciente360_auth_token_revogado_total{motivo}`
  - `paciente360_auth_active_tokens` (gauge)
- ✅ **Sentry**: contexto com `auth.user_id, auth.tenant_id, auth.token_id_prefix`.
- ✅ **SLA**: 99.5% uptime preservado.

### Princípio VI — Conformidade Meta nos Disparos (NON-NEGOTIABLE)

- ✅ **N/A para esta fase**: webhooks Twilio/Meta/Widget continuam validando HMAC signature.

### Princípio VII — Segurança Operacional (NON-NEGOTIABLE) ⚠️ EXIGE AMENDMENT v1.4.0

**Hoje (v1.3.0)**: sem menção a Bearer; foca em hash de senha + TLS + rate limit + brute force lock.

**Amendment v1.4.0 proposto (MINOR bump — não reduz gates existentes)**:
- Adiciona reconhecimento de **Bearer Sanctum Personal Access Tokens** como formato de autenticação **adicional ao cookie session** (não substituto)
- Filament super admin permanece com session cookie (guard `web`)
- API tenant migra para Bearer
- Mantém **TODOS** os gates existentes (argon2id, TLS 1.3, rate limit, brute force lock)
- Adiciona 5 novos gates específicos:
  - Tokens armazenados via SHA-256 hash no DB
  - CORS configurável via `CORS_ALLOWED_ORIGINS` env
  - CSP estrita obrigatória em produção (sem `unsafe-inline`/`unsafe-eval`)
  - DOMPurify obrigatório em HTML user-provided
  - Audit log de uso suspeito de token (mesmo token, IPs distintos <5min)

**Aprovação requerida ANTES de implementação (Lote A T001)**. Sem amendment, gate falha.

### Resultado do gate

⚠️ **CONDICIONADO** ao constitution amendment v1.4.0. **Lote A T001 = amendment**. Demais lotes bloqueados até T001 done.

## Project Structure

### Documentation (this feature)

```text
specs/004-token-auth-migration/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — 6 decisões técnicas
├── data-model.md        # Phase 1 — entidades + UNIQUE email migration
├── quickstart.md        # Phase 1 — deploy split + CORS + CSP + Postman
├── contracts/
│   └── openapi.yaml     # Phase 1 — endpoints auth + bearerAuth scheme
├── checklists/
│   └── requirements.md  # Já existe
└── tasks.md             # Phase 2 — gerado pelo /speckit.tasks
```

### Source Code (repository root)

```text
app/Domain/Auth/                 # NOVO bounded context
├── Events/
│   ├── TokenEmitido.php
│   ├── TokenRevogado.php
│   ├── LoginFalhouViaToken.php
│   └── TokenUsoSuspeito.php
├── Services/
│   ├── TokenIssuerService.php
│   ├── TokenRevocationService.php
│   ├── SlidingExpirationService.php
│   ├── SuspiciousTokenUsageDetector.php
│   └── EmailDedupService.php
└── Contracts/
    └── BearerAuthContract.php

app/Http/Controllers/Api/V1/Auth/
├── LoginController.php          # POST /auth/login
├── LogoutController.php         # POST /auth/logout (token corrente)
├── LogoutAllController.php      # POST /auth/logout-all
├── MeController.php             # GET /auth/me
└── TokensController.php         # GET /tokens, DELETE /tokens/{id}

app/Http/Middleware/
├── EnsureTenantSlugHeader.php   # NOVO — exige X-Tenant-Slug + cross-check
├── SlideTokenExpiration.php     # NOVO — renova expires_at se < 5d
├── MaskAuthorizationInLogs.php  # Estensão de LogStructuredRequestData
└── SetSecurityHeaders.php       # CSP + HSTS + X-Frame-Options

app/Http/Requests/Auth/
└── LoginRequest.php

app/Http/Resources/V1/
└── TokenResource.php

config/
├── auth.php                     # guard 'sanctum' default em API; 'web' em Filament
├── sanctum.php                  # expiration sliding 30d
└── cors.php                     # CORS_ALLOWED_ORIGINS env

database/migrations/
└── 2026_05_12_HHMMSS_add_unique_email_global_constraint.php

app/Console/Commands/
├── UsersDedupeEmailsCrossTenantCommand.php  # Pré-flight migration
├── AuthTokensPurgeExpiredCommand.php         # Schedule diário
└── TestsMigrateActingAsCommand.php           # Helper massa

resources/js/
├── lib/api.js                   # Authorization Bearer interceptor + X-Tenant-Slug auto-inject
├── stores/auth.js               # localStorage token + auto-load boot
├── pages/Auth/LoginPage.vue     # Flow novo (email + password)
├── pages/Auth/TokensPage.vue    # NOVO — gestão sessions
└── echo.js                      # Bearer authorizer

tests/Feature/Fase4/Auth/        # NOVO suite (~10 arquivos)
tests/Feature/Fase4/Migration/   # Migration safety
tests/Unit/Auth/                 # Services unit tests

# Bulk
tests/{Feature,Unit}/Fase{0,2,3}/**/*Test.php  # ~650 — migração via script
```

**Structure Decision**: Bounded context `app/Domain/Auth/` segue padrão da Fase 3 (`app/Domain/Messaging/`). Migração ampla de testes via script automatizado — gate de safety: suite full passar 882+ verdes (baseline pós-Fase 3).

## Phase 0 / Phase 1 Reference

- **Phase 0 — Research**: [research.md](./research.md) — 6 decisões técnicas (Sanctum tokens deep-dive, sliding mechanism, CORS strategy, CSP policy, ESLint config, script migração testes).
- **Phase 1 — Data Model**: [data-model.md](./data-model.md) — `personal_access_tokens` Sanctum padrão + UNIQUE constraint `users.email` + 4 events Auditable.
- **Phase 1 — Contracts**: [contracts/openapi.yaml](./contracts/openapi.yaml) — endpoints auth + `bearerAuth` security scheme.
- **Phase 1 — Quickstart**: [quickstart.md](./quickstart.md) — deploy split (api + app) + CORS env + CSP + Postman + curl examples.

## Convenções de implementação

Recap Fases 0–3 + 8 convenções desta fase:

1. **Sail obrigatório**.
2. **Pint clean** antes de PR.
3. **TDD**: cada AC tem teste antes da implementação.
4. **Multi-tenant isolation**: extensão crítica do `InboxTenantIsolationTest` para `X-Tenant-Slug` header.
5. **i18n pt-BR**: nada hardcoded.
6. **OpenAPI Scribe + drift**: gate CI; `bearerAuth` scheme.
7. **Eventos `Auditable`**: cada token op dispara → audit_logs automático.
8. **Migrations imutáveis**: prefixadas `2026_05_12_*`.

**Adições Fase 4**:

9. **Constituição amendment v1.4.0 ANTES de qualquer código** (Lote A T001).
10. **Bounded context `app/Domain/Auth/`**.
11. **Triple-check tenant**: token.user.tenant_id === header tenant.id.
12. **Sliding expiration throttled**: só UPDATE se `expires_at - now() < 5d` (~6x menos UPDATEs vs renovar sempre).
13. **Migração de testes via script idempotente**: `tests:migrate-actingas-to-sanctum`.
14. **CSP estrita obrigatória em prod**; staging mais relaxado.
15. **DOMPurify enforced** via ESLint `no-unsanitized`.
16. **Filament guard isolation**: `web` (Filament) e `sanctum` (API tenant) lado a lado em `config/auth.php`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| **Bounded context `app/Domain/Auth/`** | Segue padrão Fase 3; 4 events + 5 services justificam isolamento. | `app/Services/Auth/`: misturado com legacy auth Fase 0. |
| **`SuspiciousTokenUsageDetector` com cache Redis** | Mitigação R1 ativa (XSS exfiltration). Sem detecção, R1 passive-only. | Apenas log: ninguém revisa em real-time; cache Redis TTL 5min é detecção barata. |
| **Script de migração de testes** | ~650 testes — manual leva dias e risca regressões. | Manual: tempo + risco; sed inline cobre 95% dos casos. |
| **Sliding expiration throttled (5d buffer)** | Sem throttle: 1 UPDATE/request → carga DB. Buffer 5d → ~6x menos UPDATEs. | Renovar sempre: simples mas custoso. Não renovar: NC-2 escolheu sliding. |

## Verificação Constitucional pós-design

| Princípio | Status | Como atendido |
|---|---|---|
| **I — LGPD** | ✅ | Token opaque + audit suspeito + retenção 90d + cascateamento user anonimização. |
| **II — Multi-tenant** | ✅ | Triple-check; ResolveTenant header; InboxTenantIsolationTest estendido. |
| **III — IA** | ✅ | N/A. |
| **IV — Spec-Driven** | ✅ | 5 NCs resolvidos; TDD; suite 882+ preservada. |
| **V — Observabilidade** | ✅ | 4 métricas + 4 events Auditable + Sentry context. |
| **VI — Meta** | ✅ | N/A — HMAC inalterado. |
| **VII — Segurança Operacional** | ⚠️ **CONDICIONADO** | Aprovado sob amendment v1.4.0. Sem amendment, gate bloqueado. |

**Resultado**: ✅ APROVADO condicionado a amendment v1.4.0. Pronto para `/speckit.tasks` após amendment.

---

## Plano de execução

### Fase amendment (PRE-LOTE A — OBRIGATÓRIO)

1. Atualizar `.specify/memory/constitution.md` para v1.4.0
2. Sync impact report no header
3. Commit `constitution: amend v1.4.0 — accept Bearer tokens for API tenant`

### Fase de implementação (lotes A-I após amendment)

| Lote | Escopo | Tasks estimadas |
|---|---|---|
| A | Amendment + Sanctum config + login endpoint | ~10 |
| B | Middleware pipeline (remove stateful from API, keep Filament) | ~5 |
| C | SPA axios + auth store + interceptors | ~8 |
| D | Echo + Reverb broadcast auth | ~5 |
| E | CORS + Filament isolation | ~5 |
| F | Migrar ~650 testes (script + verificação) | ~10 |
| G | Novo suite token lifecycle | ~8 |
| H | OpenAPI bearerAuth + quickstart deploy split | ~5 |
| I | Verificação final + housekeeping | ~5 |

**Total estimado**: ~60 tasks (a serem detalhadas em `tasks.md` via `/speckit.tasks`).
