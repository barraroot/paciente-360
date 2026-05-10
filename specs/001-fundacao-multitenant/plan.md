# Implementation Plan: Fase 0 — Fundação Multi-tenant, Autenticação e Gestão de Usuários

**Branch**: `001-fundacao-multitenant` | **Date**: 2026-05-10 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-fundacao-multitenant/spec.md`

## Summary

Fase 0 entrega a **fundação operacional** do CRM Médico SaaS:
multi-tenancy, autenticação por senha forte, gestão de usuários
internos, contratação de plano via Stripe, monitoramento de cota
de IA e log de auditoria. Sem essa fase, nada das fases seguintes
(pacientes, inbox, IA, agenda) tem onde rodar — toda escrita futura
é escopada ao tenant criado aqui.

A abordagem técnica:

- **Multi-tenant single-database** com coluna `tenant_id` em toda
  tabela de domínio + global scope no Eloquent (decisão Q1 da
  clarificação, formalizada em research.md R1).
- **Resolução de tenant pelo subdomínio** em todos os ambientes —
  produção em domínio próprio, dev/CI via DNS público wildcard
  (`<slug>.lvh.me`), com **um único middleware** (decisão Q2; R6).
- **API REST versionada `/api/v1/`** consumida pela SPA Vue 3 do
  tenant; Filament 5 reservado **exclusivamente** ao Super Admin
  em `/admin`.
- **Pipeline obrigatório**
  `Form Request → Controller → Service → (Eloquent / Job /
  Integração) → Resource` — Services concentram regras de negócio
  (constituição v1.1.0).
- **Cashier + Stripe** com webhook idempotente
  (`stripe_events` table) e proration habilitada (R3).
- **Auditoria custom** (modelo `AuditLog` próprio + listener único)
  para suportar eventos não-Eloquent (login, webhook, IA), com
  retenção 2y hot + 5y cold + delete (R4 + Q3).
- **Sem 2FA no MVP**: a constituição v1.3.0 retirou o gate de 2FA do
  Princípio VII. Postura de defesa em profundidade segue garantida
  pela combinação argon2id + TLS 1.3 + rate limiting por
  tenant+endpoint + bloqueio por brute force + auditoria abrangente.
  2FA pode ser reintroduzido como opt-in voluntário em fase futura.

## Technical Context

| Item | Valor |
|---|---|
| **Linguagem** | PHP 8.5 (mínimo 8.3) |
| **Framework** | Laravel 13 |
| **Auth** | Laravel Sanctum (SPA mode, cookie HttpOnly + CSRF) |
| **Permissões** | spatie/laravel-permission (estendido com `tenant_id` em `roles` e `permissions`) |
| **Painel admin** | Filament 5 (rota `/admin` para Super Admin) |
| **Pagamentos** | laravel/cashier-stripe (latest compat com Laravel 13) |
| **Filas** | Laravel Horizon + Redis 7 |
| **WebSockets** | Laravel Reverb (instalado, **inativo nesta fase**) |
| **Banco** | PostgreSQL 18 |
| **Cache/Sessão** | Redis 7 (cache + filas + presence channels) |
| **Sessão Laravel** | Driver `database` (tabela `sessions`) para permitir kill remoto |
| **Frontend (tenant)** | Vue 3 (Composition API) + Pinia + Vue Router + Vite + Tailwind v4 + Vue I18n |
| **Containers** | Laravel Sail (Docker Compose) — php-fpm, nginx, postgres, redis, horizon worker, reverb, mailpit |
| **CI/CD** | GitHub Actions (lint, test, type-check, e2e, deploy) |
| **TLS** | Let's Encrypt (certbot) terminado em Nginx; HSTS + TLS 1.3 |
| **Monitoramento** | Sentry (sentry/sentry-laravel) + Telescope (apenas dev/staging) |
| **Testes** | PHPUnit 12 (feature + unit); Playwright para E2E |
| **Lint/Format** | Laravel Pint (`vendor/bin/sail bin pint --format agent`) |
| **Cobertura mínima** | 70% backend (princípio IV) |
| **i18n** | Vue I18n (frontend) + arquivos `lang/pt_BR/*.php` (backend); pt-BR único locale nesta fase |

**Performance Goals**: latência API p95 < 300 ms (princípio V); SC-001
a SC-013 do spec consolidam metas user-facing.

**Scale/Scope**:

- Volume alvo do MVP: até 200 tenants ativos, ~20 usuários/tenant.
- Volume de auditoria: ~50 eventos/dia/tenant em fase 0 (logins,
  CRUD admin); cresce 10× quando inbox entrar (fase 2).
- A arquitetura single-DB sustenta esse volume folgadamente em
  PostgreSQL 18 com índices propostos em `data-model.md`.

**Constraints**:

- Operação em fuso BRT/BRST.
- Idioma padrão pt-BR (constituição § Localização).
- Comandos sempre via `vendor/bin/sail` (constituição § Restrições
  Técnicas).
- Stack obrigatória — mudanças exigem amendment da constituição.

## Constitution Check

*Gate: pass before Phase 0 research. Re-check after Phase 1 design.*

### Princípios NON-NEGOTIABLE

- **I. LGPD** ✅ — consentimento de Termos/Política registrado em
  `tenants.terms_accepted_at` + versão; pseudonimização de prompts
  IA não se aplica nesta fase (sem IA); senhas em argon2id;
  auditoria de ações sensíveis em `audit_logs`; retenção 2y hot +
  5y cold + delete (FR-038); direito ao esquecimento via
  desativação (soft delete) preserva auditoria conforme exigido.
- **II. Isolamento Multi-Tenant** ✅ — global scope `BelongsToTenant`
  em **todo** Model de domínio; middleware único de resolução por
  subdomínio; jobs e broadcasts re-hidratam tenant antes de I/O;
  Redis com prefixo por tenant; testes de isolamento cobrindo 100%
  dos endpoints autenticados como gate de merge (CI).
- **III. Segurança Clínica e Auditabilidade da IA** ⚠️ — IA não está
  no escopo desta fase. Apenas a infra de auditoria (`audit_logs`)
  já está pronta para receber eventos `ai.*` quando a fase 2 entrar.
  Justificativa formal abaixo em "Verificação Constitucional".
- **VI. Conformidade Meta nos Disparos** ⚠️ — não se aplica nesta
  fase (sem canais externos). Hooks de e-mail transacional (Stripe
  notificações, recuperação de senha) usam o provedor SMTP normal,
  sem WhatsApp/IG. Justificativa formal abaixo.
- **VII. Segurança Operacional** ✅ — argon2id default
  (`config/hashing.php`); rate limiting por tenant + endpoint
  (R10); bloqueio de login após 5 falhas; TLS 1.3 em produção via
  Nginx + Let's Encrypt. **2FA fora do MVP por decisão da
  constituição v1.3.0** — substituído por: senha forte +
  brute force lock + rate limit + auditoria abrangente.

### Princípios não-negociáveis adicionais (ordem)

- **IV. Spec-Driven & Test-First** ✅ — esta fase nasce de spec
  aprovada; testes de isolamento e idempotência de webhook são
  obrigatórios; cobertura ≥ 70%; E2E nas jornadas críticas
  (cadastro → onboarding → assinatura → login → convite/aceite);
  migrações imutáveis após produção.
- **V. Observabilidade** ⚠️ **parcial** — logs estruturados com
  `tenant_id`/`user_id`/`correlation_id`, Sentry, Telescope (dev),
  eventos auditáveis para mudanças de estado de tenant/usuário
  todos cobertos. **Prometheus/Grafana adiado** para a fase 2
  (Inbox), quando métricas materiais (consumo IA por tenant,
  latência por canal) começam a existir. Justificativa abaixo.

### Resultado do gate

✅ **PASS — sem violações materiais**. Os ⚠️ marcados são adiamentos
explícitos por escopo (princípios III e VI são "ainda não aplicáveis"
nesta fase), e o V tem cobertura parcial justificada com plano de
endereçamento (fase 2). Cada adiamento está documentado em
"Verificação Constitucional" abaixo com previsão de fechamento.

## Project Structure

### Documentation (this feature)

```text
specs/001-fundacao-multitenant/
├── plan.md                          # este arquivo
├── spec.md                          # spec funcional + 5 clarifications
├── research.md                      # Phase 0 (10 decisões técnicas)
├── data-model.md                    # Phase 1 (~13 tabelas + ENUMs)
├── contracts/
│   └── openapi.yaml                 # Phase 1 — REST API v1 completa
├── quickstart.md                    # Phase 1 — onboarding do dev
├── checklists/
│   └── requirements.md              # validação de qualidade do spec
└── tasks.md                         # gerado por /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Console/
│   └── Commands/
│       ├── ApplyOverdueRestrictions.php
│       ├── ArchiveAuditLogs.php
│       └── DeleteExpiredAuditLogs.php
│
├── Events/
│   ├── Auditable.php                # interface marker
│   ├── Tenant/
│   │   ├── TenantRegistered.php
│   │   └── TenantStatusChanged.php
│   ├── User/
│   │   ├── LoginSucceeded.php
│   │   ├── LoginFailed.php
│   │   ├── LogoutSucceeded.php
│   │   ├── PasswordReset.php
│   │   └── UserInvited.php
│   ├── Billing/
│   │   ├── SubscriptionCreated.php
│   │   ├── SubscriptionUpdated.php
│   │   ├── PaymentFailed.php
│   │   └── HardCapTriggered.php
│   └── Audit/
│       └── AuditEntryRecorded.php
│
├── Filament/                        # /admin (Super Admin SOMENTE)
│   ├── Resources/
│   │   ├── TenantResource.php
│   │   └── PlanResource.php
│   ├── Pages/
│   │   └── PlatformMetrics.php
│   └── Widgets/
│       └── ActiveTenantsWidget.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── LogoutController.php
│   │   │   │   ├── MeController.php
│   │   │   │   └── PasswordController.php       # forgot/reset
│   │   │   ├── Tenant/
│   │   │   │   ├── RegisterController.php       # público
│   │   │   │   └── CurrentTenantController.php
│   │   │   ├── Onboarding/
│   │   │   │   └── OnboardingController.php
│   │   │   ├── Billing/
│   │   │   │   ├── PlansController.php
│   │   │   │   ├── CheckoutController.php
│   │   │   │   ├── SubscriptionController.php
│   │   │   │   └── AiUsageController.php
│   │   │   ├── Users/
│   │   │   │   ├── UsersController.php
│   │   │   │   └── InvitationsController.php    # listar/criar/revogar/aceitar
│   │   │   └── Audit/
│   │   │       └── AuditLogsController.php      # listar + export CSV
│   │   └── Webhooks/
│   │       └── StripeWebhookController.php
│   │
│   ├── Middleware/
│   │   ├── ResolveTenant.php
│   │   ├── EnsureTenantNotSuspended.php
│   │   ├── LogStructuredRequestData.php
│   │   └── ApplyOverdueRestrictions.php         # bloqueia rotas premium
│   │
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   ├── ForgotPasswordRequest.php
│   │   │   └── ResetPasswordRequest.php
│   │   ├── Tenant/
│   │   │   └── RegisterTenantRequest.php
│   │   ├── Billing/
│   │   │   ├── CheckoutRequest.php
│   │   │   ├── SubscriptionPatchRequest.php
│   │   │   └── HardCapRequest.php
│   │   └── Users/
│   │       ├── CreateInvitationRequest.php
│   │       ├── AcceptInvitationRequest.php
│   │       └── UserPatchRequest.php
│   │
│   └── Resources/
│       ├── TenantResource.php
│       ├── PlanResource.php
│       ├── SubscriptionResource.php
│       ├── AiUsageResource.php
│       ├── UserResource.php
│       ├── AuthenticatedUserResource.php
│       ├── InvitationResource.php
│       ├── OnboardingStateResource.php
│       └── AuditLogResource.php
│
├── Jobs/
│   ├── Audit/
│   │   ├── WriteAuditLogJob.php
│   │   ├── ArchiveAuditLogsJob.php
│   │   └── DeleteExpiredAuditLogsJob.php
│   ├── Tenant/
│   │   └── ApplyOverdueRestrictionsJob.php
│   └── Email/
│       ├── SendWelcomeEmailJob.php
│       ├── SendInvitationEmailJob.php
│       └── SendPasswordResetEmailJob.php
│
├── Listeners/
│   └── PersistAuditLogListener.php              # único listener para Auditable
│
├── Models/
│   ├── Concerns/
│   │   ├── BelongsToTenant.php                  # trait com global scope
│   │   ├── RecordsActivity.php                  # trait helper auditoria
│   │   └── HasUuidColumn.php                    # se necessário
│   ├── Scopes/
│   │   └── TenantScope.php
│   ├── Tenant.php
│   ├── Plan.php
│   ├── Subscription.php                         # estende Cashier
│   ├── User.php
│   ├── Invitation.php
│   ├── Professional.php
│   ├── AuditLog.php
│   ├── AiUsageMeter.php
│   └── StripeEvent.php
│
├── Notifications/
│   ├── WelcomeNotification.php
│   ├── InvitationNotification.php
│   ├── PasswordResetNotification.php
│   ├── PaymentFailedNotification.php
│   └── HardCapTriggeredNotification.php
│
├── Policies/
│   ├── AuditLogPolicy.php
│   ├── InvitationPolicy.php
│   ├── SubscriptionPolicy.php
│   └── UserPolicy.php
│
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php                  # registra policies + gates
│   ├── BroadcastServiceProvider.php             # canais reverb (preparação)
│   ├── EventServiceProvider.php                 # mapeia Auditable → listener
│   └── RouteServiceProvider.php                 # rate limiters
│
├── Services/                                    # ÚNICA camada de regra de negócio
│   ├── Tenant/
│   │   ├── TenantRegistrationService.php
│   │   ├── TenantStateService.php
│   │   └── TenantResolutionService.php
│   ├── Auth/
│   │   ├── AuthenticationService.php
│   │   └── PasswordResetService.php
│   ├── Onboarding/
│   │   └── OnboardingService.php
│   ├── Billing/
│   │   ├── PlanService.php
│   │   ├── CheckoutService.php
│   │   ├── SubscriptionService.php
│   │   ├── ProfessionalsQuantityService.php
│   │   ├── AiUsageService.php
│   │   ├── HardCapService.php
│   │   └── StripeWebhookService.php
│   ├── Users/
│   │   ├── UserService.php
│   │   └── InvitationService.php
│   └── Audit/
│       └── AuditService.php
│
└── Support/
    └── Csv/
        └── CsvExporter.php                       # escape contra injeção em planilha

bootstrap/
config/
├── auth.php
├── billing.php                                   # cota IA, hard cap, retries Stripe
├── cashier.php
├── hashing.php                                   # argon2id default
├── permission.php                                # spatie config
├── sanctum.php
├── tenancy.php                                   # config própria (resolution, scope)
└── audit.php                                     # tiers de retenção

database/
├── migrations/
│   ├── 2026_05_10_000001_create_tenants_table.php
│   ├── 2026_05_10_000002_create_plans_table.php
│   ├── 2026_05_10_000003_create_subscriptions_table.php   # Cashier
│   ├── 2026_05_10_000004_create_subscription_items_table.php
│   ├── 2026_05_10_000005_create_users_table.php
│   ├── 2026_05_10_000006_create_password_reset_tokens_table.php
│   ├── 2026_05_10_000007_create_personal_access_tokens_table.php
│   ├── 2026_05_10_000008_create_sessions_table.php
│   ├── 2026_05_10_000009_create_permission_tables.php     # Spatie estendido
│   ├── 2026_05_10_000010_create_invitations_table.php
│   ├── 2026_05_10_000011_create_professionals_table.php
│   ├── 2026_05_10_000012_create_audit_logs_table.php
│   ├── 2026_05_10_000013_create_audit_logs_cold_table.php
│   ├── 2026_05_10_000014_create_ai_usage_meters_table.php
│   └── 2026_05_10_000015_create_stripe_events_table.php
│
├── factories/
│   ├── TenantFactory.php
│   ├── PlanFactory.php
│   ├── UserFactory.php
│   ├── InvitationFactory.php
│   └── ...
│
└── seeders/
    ├── DatabaseSeeder.php                        # produção-safe (planos default, super admin, roles)
    ├── DevSeeder.php                             # 2 tenants + usuários por perfil
    └── DemoSeeder.php                            # esqueleto vazio nesta fase

resources/
├── js/
│   ├── app.js                                    # entrypoint Vue
│   ├── i18n/
│   │   └── pt-BR.json
│   ├── stores/                                   # Pinia
│   │   ├── auth.js
│   │   ├── tenant.js
│   │   ├── billing.js
│   │   └── onboarding.js
│   ├── router/
│   │   └── index.js
│   ├── pages/
│   │   ├── auth/
│   │   │   ├── LoginPage.vue
│   │   │   ├── ForgotPasswordPage.vue
│   │   │   └── ResetPasswordPage.vue
│   │   ├── tenant-register/
│   │   │   └── RegisterTenantPage.vue
│   │   ├── onboarding/
│   │   │   └── OnboardingWizardPage.vue
│   │   ├── billing/
│   │   │   ├── PlansPage.vue
│   │   │   ├── SubscriptionPage.vue
│   │   │   └── AiUsagePage.vue
│   │   ├── users/
│   │   │   ├── UsersListPage.vue
│   │   │   └── InviteUserPage.vue
│   │   ├── invitations/
│   │   │   └── AcceptInvitationPage.vue
│   │   └── audit/
│   │       └── AuditLogsPage.vue
│   └── components/
│       ├── layout/
│       ├── forms/
│       └── ui/
│
├── views/
│   ├── app.blade.php                             # shell mínimo do SPA
│   └── emails/
│       ├── welcome.blade.php
│       ├── invitation.blade.php
│       ├── password-reset.blade.php
│       ├── payment-failed.blade.php
│       └── hard-cap-triggered.blade.php
│
├── lang/
│   └── pt_BR/
│       ├── auth.php
│       ├── billing.php
│       ├── tenant.php
│       └── validation.php
│
└── css/
    └── app.css                                   # Tailwind v4

routes/
├── api.php                                       # /api/v1/* (com middleware ResolveTenant)
├── web.php                                       # SPA shell + cadastro público + Stripe webhook + /panel SPA
├── channels.php                                  # canais Reverb (autorização por tenant)
└── console.php

tests/
├── Feature/
│   ├── Fase0/
│   │   ├── Tenant/
│   │   │   ├── RegisterTenantTest.php
│   │   │   ├── TenantResolutionTest.php
│   │   │   └── TenantIsolationTest.php          # gate de merge — 100% endpoints
│   │   ├── Auth/
│   │   │   ├── LoginTest.php
│   │   │   ├── PasswordResetTest.php
│   │   │   └── BruteForceLockTest.php
│   │   ├── Onboarding/
│   │   │   └── OnboardingWizardTest.php
│   │   ├── Billing/
│   │   │   ├── CheckoutTest.php
│   │   │   ├── SubscriptionPatchTest.php
│   │   │   ├── HardCapTest.php
│   │   │   ├── AiUsageTest.php
│   │   │   └── StripeWebhookIdempotencyTest.php
│   │   ├── Users/
│   │   │   ├── InvitationFlowTest.php
│   │   │   └── UserPermissionsTest.php
│   │   └── Audit/
│   │       ├── AuditLogTest.php
│   │       └── AuditLogExportCsvTest.php
│   └── ...
├── Unit/
│   └── Services/
│       ├── TenantStateServiceTest.php
│       ├── HardCapServiceTest.php
│       └── ...
└── e2e/                                          # Playwright
    ├── tenant-register.spec.ts
    ├── login.spec.ts
    ├── checkout.spec.ts
    ├── invite-and-accept.spec.ts
    └── password-reset.spec.ts

docs/
├── design/                                       # mockups (referência visual)
└── ...
```

**Structure Decision**: arquitetura **single-projeto** (monorepo
Laravel com SPA Vue dentro de `resources/js`), reflete a stack
fixada na constituição. Não usamos `backend/` + `frontend/`
separados porque o Vite do Laravel já bundla a SPA dentro do mesmo
deploy. Filament Resources ficam em `app/Filament/` (caminho default
do Filament 5).

## Phase 0 / Phase 1 Reference

| Fase | Output | Localização |
|---|---|---|
| Phase 0 — Outline & Research | research.md | [./research.md](./research.md) |
| Phase 1 — Data Model | data-model.md | [./data-model.md](./data-model.md) |
| Phase 1 — Contracts | OpenAPI 3.1 da API v1 | [./contracts/openapi.yaml](./contracts/openapi.yaml) |
| Phase 1 — Quickstart | quickstart.md | [./quickstart.md](./quickstart.md) |
| Phase 2 — Tasks | tasks.md (gerado por `/speckit-tasks`) | TBD |

## Convenções de implementação (recap das regras do plano)

1. **Camadas**: `Form Request → Controller → Service → Resource`.
   Controllers finos (≤ 30 linhas), apenas roteamento. Toda regra
   em `app/Services/...`.
2. **Multi-tenancy**: trait `BelongsToTenant` em todo Model de
   domínio; middleware `ResolveTenant` resolve por host; jobs
   re-hidratam contexto via `Tenant::current()` na entrada.
3. **Versionamento da API**: prefixo `/api/v1/`. Mudanças
   incompatíveis exigem nova versão; aditivos podem entrar na v1.
4. **Validação**: `Form Request` obrigatório. Nunca
   `request()->validate()` em controller (proibido em code review).
5. **Serialização**: `Resource` ou `ResourceCollection`. Nunca
   retornar Model direto.
6. **Autorização**: `Policy` por Model; `Gate` apenas para Super
   Admin (cross-tenant) e ações fora de Models (ex.: acesso ao
   Telescope em dev).
7. **Jobs**: idempotentes. Toda integração externa (Stripe, e-mail)
   passa por job; controllers nunca chamam SDK externo direto.
8. **Migrations**: nomeadas com prefixo de domínio cronológico
   (`2026_05_10_000001_create_tenants_table.php`); imutáveis após
   produção.
9. **Seeders**: `DatabaseSeeder` (produção-safe), `DevSeeder` (dados
   de exemplo), `DemoSeeder` (rico, futuro).
10. **Configuração**: `.env` + `config/<dominio>.php`. Zero hardcode.
11. **Layout/UX**: referenciar mockups em `docs/design/*` como fonte
    visual. Login = `01 _ Login _US-2.1_ US-2.2_.png` (layout duas
    colunas + KPIs).
12. **Filament**: somente em `/admin`. Nunca para fluxos de tenant.
    Filament Resources delegam aos mesmos Services da API.

## Complexity Tracking

> Preencher apenas se o Constitution Check apontar violações que
> exigem justificativa. **Nada a registrar nesta fase** — o plano
> opera dentro dos limites da constituição. Os ⚠️ são adiamentos
> escopados, não violações.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| (none) | — | — |

## Verificação Constitucional

Mapeia, **princípio a princípio**, como a Fase 0 atende a
constituição v1.3.0. ✅ = atendido nesta fase. ⚠️ = adiamento ou
cobertura parcial com justificativa. ❌ = violação (exige correção
antes do merge — esta fase não tem nenhum).

### Princípio I — Privacidade, Consentimento e Conformidade LGPD ✅

| Item da constituição | Atendimento nesta fase |
|---|---|
| Consentimento explícito (data/canal/finalidade) antes de marketing | `tenants.terms_accepted_at` + `terms_version` capturados no cadastro (US-1.1, FR-007). Marketing-specific consent entra com campanhas (fase 5). |
| Direito ao esquecimento via anonimização | Soft delete (`deleted_at`) em `users` preserva auditoria (FR-028). Endpoint formal de "pedido de esquecimento" entra junto com o módulo de pacientes (fase 1). |
| TLS 1.3 em trânsito + criptografia em repouso para dados sensíveis | TLS 1.3 via Nginx + Let's Encrypt; PostgreSQL com encryption at rest no nível de disco (cloud); senhas em argon2id. |
| Hash de senha argon2id ou bcrypt cost ≥ 12 | argon2id default em `config/hashing.php`. |
| Pseudonimização de prompts ao LLM | ⚠️ N/A — IA não está em escopo. Trait/Service de pseudonimização entra na fase 2 quando IA for ativada. |
| Auditoria com retenção mínima 1 ano | `audit_logs` cobre 100% das ações sensíveis listadas em FR-034; retenção 2y hot + 5y cold + delete (R4 + Q3). |

### Princípio II — Isolamento Multi-Tenant ✅

| Item da constituição | Atendimento nesta fase |
|---|---|
| Global scope por `tenant_id` em todo Model de domínio | Trait `BelongsToTenant` aplicada (data-model.md). |
| API resolve tenant pelo contexto autenticado | `ResolveTenant` middleware → `app('tenant')` injetado antes dos controllers. |
| Jobs (Horizon) re-hidratam contexto de tenant | Job base abstrata `TenantAwareJob` carrega `Tenant::setCurrent($id)` antes do `handle()`. |
| Reverb autoriza canais por pertencimento ao tenant | `routes/channels.php` valida `auth()->user()->tenant_id === $tenantId` (preparado, sem eventos). |
| Caches/Redis prefixados por tenant | `RedisManager` com prefix `tenant:{id}:` aplicado via `CacheServiceProvider`. |
| Teste de isolamento cobrindo 100% dos endpoints | `TenantIsolationTest` faz fuzzing de cada rota autenticada com 2 tenants distintos e verifica `403/404` no cross-access. CI gate. |

### Princípio III — Segurança Clínica e Auditabilidade da IA ⚠️ adiado

**Justificativa**: a Fase 0 não introduz IA. Toda a infra de
auditoria (`audit_logs`, listener custom, retenção em tiers) já
está pronta para receber eventos `ai.decision_recorded`,
`ai.bypass_attempted`, `ai.escalated_to_human`. Guardrails de prompt
e testes de bypass entram **na fase 2** (Inbox + Motor IA Matricial),
quando o componente existe. Plan dessa fase (a ser gerado depois)
deverá referenciar este princípio explicitamente.

### Princípio IV — Desenvolvimento Spec-Driven e Test-First ✅

| Item da constituição | Atendimento nesta fase |
|---|---|
| Features originadas de spec aprovada | spec.md aprovado, clarifications resolvidas, plan atual. |
| Bugs com teste de regressão escrito antes do fix | Procedimento; exigido em PRs durante a fase. |
| Cobertura backend ≥ 70% | Meta de CI: `--min=70` no `phpunit --coverage`. |
| E2E nas jornadas críticas | Playwright cobre cadastro, onboarding, checkout, login, convite, recuperação de senha (`tests/e2e/`). |
| Migrations imutáveis após produção | Política em CONTRIBUTING + checks de CI bloqueando edição de migration timestamped já no main. |
| OpenAPI atualizado a cada PR que altera contrato | `contracts/openapi.yaml` é fonte da verdade; CI compara com Scribe-generated `routes:list` para detectar drift. |
| `pint` + `phpunit` antes do merge | Pipeline GitHub Actions com 4 gates: pint, phpstan (futuro), phpunit, playwright. |

### Princípio V — Observabilidade e Excelência Operacional ⚠️ parcial

| Item da constituição | Atendimento nesta fase |
|---|---|
| Logs estruturados (JSON, com `tenant_id`, `user_id`, `correlation_id`) | Middleware `LogStructuredRequestData` + Monolog processor injetam os 3 campos em toda linha de log. |
| Eventos auditáveis para envios externos | E-mails transacionais (welcome, invite, password reset, payment failed) emitem `EmailSent` auditable. |
| Eventos para mudanças de estado de tenant/usuário | `TenantStatusChanged`, `UserInvited`, `LoginSucceeded/Failed` etc. — todos via eventos PHP no listener único. |
| Métricas via Prometheus para Grafana | ⚠️ **adiado** para fase 2. Justificativa: as métricas materiais que justificam Prometheus (consumo IA por tenant, latência por canal, taxa de escalonamento) só existem quando IA + Inbox entram. Configurar agora seria infra sem dado. **Compromisso**: nenhum endpoint desta fase fica sem instrumentação que possa ser **convertida** em métrica (todos os logs estruturados têm latência + tenant_id; o exporter `spatie/laravel-prometheus` é instalável em PR única na fase 2). |
| Sentry com contexto de tenant | `Sentry::configureScope(...)` no middleware de tenant. |
| Webhooks Stripe registram payload bruto + retry | `stripe_events` table guarda payload integral; Cashier já implementa retry exponencial. |
| SLA 99.5% + backup diário 30d + DR | Definido em runbooks de infra (fora desta fase do código), com testes de restore semestrais. |

### Princípio VI — Conformidade Meta nos Disparos ⚠️ adiado

**Justificativa**: nenhum canal Meta (WhatsApp, Instagram) está
ativo na Fase 0. E-mails transacionais usam SMTP padrão e não estão
sob regras Meta. O dispatcher Meta-aware entra na fase 2 (Inbox);
o teste de runtime block + opt-in marketing + janela 24h ficará
no plano daquela fase.

### Princípio VII — Segurança Operacional ✅

| Item da constituição | Atendimento nesta fase |
|---|---|
| Hash argon2id ou bcrypt cost ≥ 12 | argon2id default. |
| TLS 1.3 em produção | Nginx + Let's Encrypt + HSTS. |
| Rate limiting por tenant + endpoint | `RouteServiceProvider::configureRateLimiting()` (R10): login 5/min/IP/tenant; cadastro 3/h/IP; API 60/min/user. |
| Bloqueio temporário após 5 tentativas falhas | Coluna `users.failed_login_attempts` + `users.locked_until`; reset em login com sucesso. |
| 2FA TOTP | ⚠️ **Removido do MVP na constituição v1.3.0**. Pode ser reintroduzido como opt-in voluntário em fase futura sem quebrar contratos (Sanctum SPA + audit log já cobrem o flow). |

### Localização e Idioma ✅

| Item | Atendimento |
|---|---|
| pt-BR padrão para UI e e-mails | Vue I18n com `pt-BR` default; `lang/pt_BR/*.php` no backend. |
| Strings em arquivos de tradução (zero hardcode) | Code review gate; lint custom (Pint não cobre, PR template tem checklist). |
| Datas/moeda/numeros formatados | `Intl.*` no front; `Carbon::setLocale('pt_BR')` global no backend. |

### Restrições Técnicas e Arquiteturais ✅

| Item | Atendimento |
|---|---|
| Stack fixa (Laravel 13, Vue 3, Sail, Filament 5, Reverb, Horizon) | Idêntica à decisão da constituição. |
| Comandos via `vendor/bin/sail` | Documentação + scripts CI. |
| API REST `/api/v1/` para tenants | Confirmado. |
| Filament 5 só para Super Admin | `/admin` exclusivo; `/panel` é a SPA. |
| Pipeline `Form Request → Controller → Service → Resource` | Estrutura de pastas reflete; PR template inclui checklist. |
| Filament reusa Services da API | `TenantResource` e `PlanResource` chamam `TenantStateService`/`PlanService` — sem duplicar lógica. |

### Itens fora do escopo do MVP — confirmação

| Item | Status |
|---|---|
| Telemedicina nativa | Fora do MVP — confirmado. |
| Multi-unidade por tenant | Fora do MVP — confirmado. |
| Prontuário eletrônico | Fora do MVP — confirmado. |
| Pré-pagamento de consulta pelo paciente | Fora do MVP — confirmado. |

---

**Resumo do gate**: ✅ **Constitution Check PASS** — sem
violações; ⚠️ adiamentos justificados (princípios III, V parcial,
VI). Pronto para Phase 2 (`/speckit-tasks`).
