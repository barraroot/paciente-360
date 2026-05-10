---
description: "Tasks executáveis da Fase 0 — Fundação Multi-tenant"
---

# Tasks: Fase 0 — Fundação Multi-tenant, Autenticação e Gestão de Usuários

**Feature directory**: `specs/001-fundacao-multitenant/`
**Plan**: [plan.md](./plan.md) — **Spec**: [spec.md](./spec.md) — **Constitution**: v1.2.0

## Como ler este arquivo

Cada task tem o formato:

```
- [ ] TXXX [P?] [US?] Título curto — caminho-arquivo principal
  - Descrição: o que fazer e por quê
  - Arquivos esperados: lista de caminhos criados/alterados
  - Aceitação: critérios testáveis
  - Depende de: lista de TIDs anteriores
  - Princípio: I, II, III, IV, V, VI, VII (constituição v1.2.0)
```

Convenções:

- `[P]` = paralelizável (sem dependência ativa em tarefas pendentes na mesma janela; arquivo distinto).
- `[US1]..[US9]` mapeia user stories da spec.md:
  - **US1** = US-1.1 Cadastro de Tenant
  - **US2** = US-1.2 Onboarding
  - **US3** = US-1.3 Assinatura
  - **US4** = US-1.4 Upgrade/Downgrade
  - **US5** = US-1.5 Cota IA
  - **US6** = US-2.1 Login
  - **US7** = US-2.2 Convite de Usuários Internos
  - **US8** = US-2.3 Recuperação de Senha
  - **US9** = US-2.4 Log de Auditoria
- TDD: tarefa de **teste** vem antes da tarefa de **implementação** correspondente. Teste deve falhar primeiro.
- Comandos sempre via `vendor/bin/sail`.
- Migrations seguem rigorosamente as 15 listadas em `data-model.md` — nada além.

---

## Phase 1 — Bootstrap (Setup)

Estabelece a stack base. Sem `[USx]` (compartilhado por todas as histórias).

- [x] T001 [P] Adicionar dependências PHP no composer.json — `composer.json`
  - Descrição: incluir `laravel/sanctum`, `laravel/cashier-stripe`, `spatie/laravel-permission`, `laravel/horizon`, `laravel/reverb`, `laravel/telescope`, `sentry/sentry-laravel`, `laravel/pail`. Dev: `phpunit/phpunit ^12`, `laravel/pint`, `mockery/mockery`. **Sem `pragmarx/google2fa` nem `bacon/bacon-qr-code`** — 2FA fora do MVP (constituição v1.3.0).
  - Arquivos: `composer.json`, `composer.lock`
  - Aceitação: `vendor/bin/sail composer install` instala sem conflitos; `composer outdated` na pasta vendor lista as versões esperadas.
  - Depende de: —
  - Princípio: Restrições Técnicas

- [x] T002 [P] Adicionar dependências Node no package.json — `package.json`
  - Descrição: incluir `vue@^3.5`, `pinia`, `vue-router@^4`, `vue-i18n@^10`, `@vueuse/core`, `axios`, `tailwindcss@^4`, `vite`, `@playwright/test`, `prettier`.
  - Arquivos: `package.json`, `package-lock.json`
  - Aceitação: `vendor/bin/sail npm install` ok; `npm ls` reporta versões alinhadas.
  - Depende de: —
  - Princípio: Restrições Técnicas

- [x] T003 Configurar Docker Compose com PostgreSQL 18, Redis 7, Mailpit, Reverb, Horizon worker — `compose.yaml`
  - Descrição: ajustar `compose.yaml` (Sail) — service `pgsql` apontando para imagem `postgres:18-alpine`; serviços `redis`, `mailpit`, `reverb`, `horizon-worker`.
  - Arquivos: `compose.yaml`, `.env.example`
  - Aceitação: `vendor/bin/sail up -d` sobe todos os serviços saudáveis; `\dt` no `psql` lista tabelas vazias após migrate.
  - Depende de: T001
  - Princípio: Restrições Técnicas

- [x] T004 [P] Configurar argon2id como driver default de hash — `config/hashing.php`
  - Descrição: publicar `config/hashing.php` e setar `'driver' => 'argon2id'` com `'memory' => 65536`, `'threads' => 1`, `'time' => 4`. Bcrypt fallback ≥ 12.
  - Arquivos: `config/hashing.php`
  - Aceitação: teste unitário `HashDriverTest` confirma que `Hash::make('x')` retorna hash com prefixo `$argon2id$`.
  - Depende de: T001
  - Princípio: VII (Segurança Operacional)

- [x] T005 [P] Criar config/tenancy.php — `config/tenancy.php`
  - Descrição: configuração de resolução de tenant (chave de subdomínio principal, lista de hosts ignorados, sufixo público dev `lvh.me`/`nip.io`), prefixo Redis `tenant:{id}:`.
  - Arquivos: `config/tenancy.php`
  - Aceitação: `config('tenancy.subdomain_suffix')` retorna `'crm.com.br'` em prod; `'lvh.me'` em dev.
  - Depende de: —
  - Princípio: II (Multi-tenant), Restrições Técnicas

- [x] T006 [P] Criar config/billing.php — `config/billing.php`
  - Descrição: chaves Stripe (lidas de env), retries de cobrança (3), grace days (7), suspensão a 37 dias, hard cap default null.
  - Arquivos: `config/billing.php`, `.env.example`
  - Aceitação: `config('billing.grace_days')` = 7.
  - Depende de: —
  - Princípio: VI (deferred), Restrições Técnicas

- [x] T007 [P] Criar config/audit.php — `config/audit.php`
  - Descrição: tiers de retenção (`hot_days: 730`, `cold_days: 1825`, `delete_after_days: 1825`), schedule expressions para arquivamento e deleção.
  - Arquivos: `config/audit.php`
  - Aceitação: `config('audit.hot_days')` = 730.
  - Depende de: —
  - Princípio: I (LGPD), V (Observabilidade)

- [x] T008 [P] Configurar pint.json — `pint.json`
  - Descrição: usar preset Laravel + extras `phpdoc_align`, `not_operator_with_successor_space`.
  - Arquivos: `pint.json`
  - Aceitação: `vendor/bin/sail bin pint --test --format agent` retorna exit 0 num arquivo limpo.
  - Depende de: T001
  - Princípio: IV (Spec-driven)

- [x] T009 [P] Configurar PHPUnit com cobertura mínima 70% — `phpunit.xml`
  - Descrição: reativar `<coverage>` apontando para `app/`, excluindo `Console/Kernel`, providers triviais. Setar threshold 70 via `--min` no script CI.
  - Arquivos: `phpunit.xml`, `composer.json` (script `test:coverage`)
  - Aceitação: `vendor/bin/sail artisan test --coverage --min=70` é roteável (mesmo que ainda não passe sem código).
  - Depende de: T001
  - Princípio: IV

- [x] T010 [P] Setup Playwright para E2E — `playwright.config.ts` + `tests/e2e/`
  - Descrição: scaffolding Playwright com baseURL `https://clinica-alfa.lvh.me`, headed/CI flag, retentativas 2 em CI.
  - Arquivos: `playwright.config.ts`, `tests/e2e/.gitkeep`, atualização do `package.json` com script `test:e2e`.
  - Aceitação: `vendor/bin/sail npm run test:e2e` executa zero specs sem erro.
  - Depende de: T002
  - Princípio: IV

- [x] T011 [P] GitHub Actions CI — `.github/workflows/ci.yml`
  - Descrição: workflow com jobs `pint`, `phpunit-coverage`, `playwright`, rodando em PR e push para `main`. Stripe key de teste vem de secret.
  - Arquivos: `.github/workflows/ci.yml`
  - Aceitação: workflow corre no GitHub e os 3 jobs aparecem no checks.
  - Depende de: T008, T009, T010
  - Princípio: IV

- [x] T012 [P] Integrar Sentry com contexto de tenant — `config/sentry.php`, `app/Providers/AppServiceProvider.php`
  - Descrição: publicar `config/sentry.php`; em `boot()`, configurar `Sentry::configureScope` para popular `tenant_id` e `user_id` quando disponíveis.
  - Arquivos: `config/sentry.php`, `app/Providers/AppServiceProvider.php`, `.env.example` (`SENTRY_LARAVEL_DSN`).
  - Aceitação: teste manual: `throw new \RuntimeException('test')` com `SENTRY_LARAVEL_DSN` em env de stage cria evento com tag `tenant.id`.
  - Depende de: T001
  - Princípio: V (Observabilidade)

- [x] T013 [P] Habilitar Telescope só em dev/staging — `app/Providers/TelescopeServiceProvider.php`
  - Descrição: instalar Telescope; gate de acesso restrito a Super Admin; bloqueado em produção via `app()->environment(['local','staging'])`.
  - Arquivos: `config/telescope.php`, `app/Providers/TelescopeServiceProvider.php`
  - Aceitação: tentativa de GET `/telescope` em `APP_ENV=production` retorna 404.
  - Depende de: T001
  - Princípio: V, VII (segurança)

- [x] T014 Vue 3 entrypoint, Pinia e Vue Router — `resources/js/app.js`, `resources/js/router/index.js`, `resources/js/stores/`
  - Descrição: bootstrap do app Vue, instâncias de `pinia`, `vue-router`, `vue-i18n` (com `pt-BR.json`).
  - Arquivos: `resources/js/app.js`, `resources/js/router/index.js`, `resources/js/i18n/pt-BR.json`, `resources/js/stores/auth.js` (skeleton).
  - Aceitação: `vendor/bin/sail npm run dev` compila sem erro; rota `/panel` exibe placeholder.
  - Depende de: T002, T015
  - Princípio: Restrições Técnicas, Localização

- [x] T015 [P] Tailwind v4 + tema base — `resources/css/app.css`, `tailwind.config.js`
  - Descrição: configurar Tailwind v4, tokens de cor extraídos do mockup `docs/design/01_Login.png` (verde escuro/escuro acentuado), tipografia.
  - Arquivos: `resources/css/app.css`, `tailwind.config.js`
  - Aceitação: build sem warnings; tela de placeholder mostra cor base.
  - Depende de: T002
  - Princípio: Restrições Técnicas

- [x] T016 SPA shell + rotas web — `resources/views/app.blade.php`, `routes/web.php`
  - Descrição: blade vazio carregando `app.js` via `@vite`; rotas `/`, `/cadastro`, `/panel/{any?}` (catch-all SPA), `/admin` (Filament — fica em T118), `/stripe/webhook` (T072).
  - Arquivos: `resources/views/app.blade.php`, `routes/web.php`
  - Aceitação: `GET /panel/foo` devolve a SPA; `GET /panel/foo/bar` idem; SSE/HMR funciona.
  - Depende de: T014, T015
  - Princípio: Restrições Técnicas

- [x] T017 [P] Laravel Pail integrado em Sail — `compose.yaml`
  - Descrição: adicionar service `pail` opcional ou comando `sail artisan pail` documentado no quickstart.
  - Arquivos: `compose.yaml`, `quickstart.md` (já existe; só atualização caso necessário).
  - Aceitação: `vendor/bin/sail artisan pail --filter=info` produz logs em tempo real.
  - Depende de: T003
  - Princípio: V (Observabilidade)

---

## Phase 2 — Foundational (compartilhado por todas as histórias)

**⚠️ CRÍTICO**: bloqueia qualquer trabalho de user story. Todas as
migrations, traits multi-tenant, infra de auditoria base, Sanctum SPA,
rate limiters, papéis Spatie e seeders de dev devem estar prontos.

### 2.1 — Migrations (data-model.md, 15 itens)

- [x] T020 [P] Migration `tenants` — `database/migrations/2026_05_10_000001_create_tenants_table.php`
  - Descrição: schema conforme data-model § 1; ENUM `status` via CHECK constraint; índices em `slug`, `cnpj`, `status`, `overdue_since`.
  - Arquivos: a migration listada.
  - Aceitação: `migrate` cria tabela; `\d tenants` no psql confirma colunas/índices; tentativa de inserir `slug` duplicado falha por unique.
  - Depende de: T003
  - Princípio: II, I

- [x] T021 [P] Migration `plans` — `database/migrations/2026_05_10_000002_create_plans_table.php`
  - Descrição: schema § 2; UNIQUE em `code`; sem `tenant_id`.
  - Arquivos: a migration listada.
  - Aceitação: `\d plans` confirma colunas; FK não definida.
  - Depende de: T003
  - Princípio: Restrições Técnicas

- [x] T022 [P] Migration `subscriptions` — `database/migrations/2026_05_10_000003_create_subscriptions_table.php`
  - Descrição: schema § 3.1, com `tenant_id` (não `user_id`), `plan_id`, `plan_snapshot JSONB`, `professionals_quantity`.
  - Arquivos: a migration listada.
  - Aceitação: tabela criada; FK para `tenants` e `plans` enforçadas.
  - Depende de: T020, T021
  - Princípio: II, Restrições Técnicas

- [x] T023 [P] Migration `subscription_items` — `database/migrations/2026_05_10_000004_create_subscription_items_table.php`
  - Descrição: schema § 3.2 (Cashier); FK CASCADE para `subscriptions`.
  - Arquivos: a migration listada.
  - Aceitação: insert/delete propagam corretamente.
  - Depende de: T022
  - Princípio: Restrições Técnicas

- [x] T024 [P] Migration `users` — `database/migrations/2026_05_10_000005_create_users_table.php`
  - Descrição: schema § 4; `tenant_id NULLABLE`; UNIQUE composto via `COALESCE(tenant_id,0)`. **Sem colunas 2FA** (removidas do data-model em v1.3.0).
  - Arquivos: a migration listada.
  - Aceitação: insert do mesmo email em 2 tenants distintos é aceito; mesmo email no mesmo tenant é rejeitado.
  - Depende de: T020
  - Princípio: II, VII

- [x] T025 [P] Migration `password_reset_tokens` — `database/migrations/2026_05_10_000006_create_password_reset_tokens_table.php`
  - Descrição: PK composto `(email, tenant_id)` por § 8.
  - Arquivos: a migration listada.
  - Aceitação: insert duplicado para mesmo `(email,tenant_id)` substitui (upsert) ou rejeita conforme strategy escolhida no Service (T080).
  - Depende de: T020
  - Princípio: II, VII

- [x] T026 [P] Migration `personal_access_tokens` (Sanctum default) — `database/migrations/2026_05_10_000007_create_personal_access_tokens_table.php`
  - Descrição: usar migration nativa do Sanctum (nenhuma alteração).
  - Arquivos: a migration listada.
  - Aceitação: `php artisan sanctum:install` reconhece a migration.
  - Depende de: T001
  - Princípio: VII

- [x] T027 [P] Migration `sessions` (Laravel default) — `database/migrations/2026_05_10_000008_create_sessions_table.php`
  - Descrição: tabela default Laravel para `session.driver=database`.
  - Arquivos: a migration listada.
  - Aceitação: `SESSION_DRIVER=database` faz inserts em `sessions`.
  - Depende de: T003
  - Princípio: VII

- [x] T028 Migration `permission_tables` (Spatie estendido) — `database/migrations/2026_05_10_000009_create_permission_tables.php`
  - Descrição: usar migration do Spatie + adicionar coluna `tenant_id NULLABLE` em `roles` e `permissions`; UNIQUE composto `(name, guard_name, COALESCE(tenant_id,0))`.
  - Arquivos: a migration listada.
  - Aceitação: insert de role homônimo em 2 tenants distintos é aceito.
  - Depende de: T020
  - Princípio: II

- [x] T029 [P] Migration `invitations` — `database/migrations/2026_05_10_000010_create_invitations_table.php`
  - Descrição: schema § 7; **só `token_hash`** (não `token` em claro).
  - Arquivos: a migration listada.
  - Aceitação: tabela criada; FK para `tenants` e `users` (inviter).
  - Depende de: T020, T024
  - Princípio: II, VII

- [x] T030 [P] Migration `professionals` — `database/migrations/2026_05_10_000011_create_professionals_table.php`
  - Descrição: schema § 6 (esqueleto).
  - Arquivos: a migration listada.
  - Aceitação: índice `(tenant_id, is_active)` confirmado.
  - Depende de: T020, T024
  - Princípio: II

- [x] T031 Migration `audit_logs` (com guard de imutabilidade) — `database/migrations/2026_05_10_000012_create_audit_logs_table.php`
  - Descrição: schema § 9; **trigger PG** que rejeita UPDATE/DELETE; ausência de `updated_at`. Índices: `(tenant_id, created_at DESC)`, `(action, created_at DESC)`, `(auditable_type, auditable_id)`, BRIN em `created_at`.
  - Arquivos: a migration listada.
  - Aceitação: `UPDATE audit_logs SET action=...` retorna erro do trigger.
  - Depende de: T020, T024
  - Princípio: I, V

- [x] T032 [P] Migration `audit_logs_cold` — `database/migrations/2026_05_10_000013_create_audit_logs_cold_table.php`
  - Descrição: schema § 9.b; só BRIN + `tenant_id` index; mesma trigger de imutabilidade.
  - Arquivos: a migration listada.
  - Aceitação: tabela criada; INSERT funciona; UPDATE rejeitado.
  - Depende de: T031
  - Princípio: I, V

- [x] T033 [P] Migration `ai_usage_meters` — `database/migrations/2026_05_10_000014_create_ai_usage_meters_table.php`
  - Descrição: schema § 10; UNIQUE `(tenant_id, year_month)`.
  - Arquivos: a migration listada.
  - Aceitação: insert duplicado falha; índice de hard cap presente.
  - Depende de: T020
  - Princípio: II, V

- [x] T034 [P] Migration `stripe_events` — `database/migrations/2026_05_10_000015_create_stripe_events_table.php`
  - Descrição: schema § 11 (PK = `stripe_event_id` VARCHAR).
  - Arquivos: a migration listada.
  - Aceitação: insert idempotente via `INSERT ... ON CONFLICT DO NOTHING` retorna 0 rows na 2ª chamada.
  - Depende de: T003
  - Princípio: V (idempotência)

### 2.2 — Multi-tenant infra (Princípio II)

- [x] T040 **TEST** Tenant isolation — `tests/Feature/Fase0/Tenant/TenantIsolationTest.php`
  - Descrição: teste que cria 2 tenants + 2 users; tenta acessar recursos do outro tenant via API; espera 404/403. Inicialmente cobre só endpoints atuais (vazio); deve crescer com cada PR. **CI gate**.
  - Arquivos: `tests/Feature/Fase0/Tenant/TenantIsolationTest.php`, helper `tests/Concerns/CreatesTenants.php`.
  - Aceitação: teste roda no CI, falha quando rota é introduzida sem scope.
  - Depende de: T020, T024
  - Princípio: II, IV

- [x] T041 Trait `BelongsToTenant` + `TenantScope` — `app/Models/Concerns/BelongsToTenant.php`, `app/Models/Scopes/TenantScope.php`
  - Descrição: aplica `addGlobalScope` filtrando por `app('tenant')->id`; auto-popula `tenant_id` no `creating`. Suporte a `withoutTenantScope()` para Filament Super Admin.
  - Arquivos: os listados; teste unitário `BelongsToTenantScopeTest`.
  - Aceitação: query em Model que usa a trait sempre inclui `WHERE tenant_id = ?`; create sem `tenant_id` explícito popula automaticamente.
  - Depende de: T040
  - Princípio: II

- [x] T042 Middleware `ResolveTenant` — `app/Http/Middleware/ResolveTenant.php`
  - Descrição: extrai slug do host (`{slug}.crm.com.br` ou `{slug}.lvh.me`); busca `Tenant::where('slug', $slug)` (sem global scope, query crua); injeta em `app()->instance('tenant', $tenant)`; 404 se não existir.
  - Arquivos: `app/Http/Middleware/ResolveTenant.php`, registro em `app/Http/Kernel.php` ou `bootstrap/app.php`.
  - Aceitação: feature test confirma `app('tenant')` resolvido em rota autenticada; 404 em subdomínio inexistente.
  - Depende de: T020, T005
  - Princípio: II

- [x] T043 [P] Middleware `EnsureTenantNotSuspended` — `app/Http/Middleware/EnsureTenantNotSuspended.php`
  - Descrição: bloqueia request quando `tenant->status === 'suspended'`; retorna 403 com payload identificável; permite Super Admin.
  - Arquivos: o listado; teste feature.
  - Aceitação: tenant suspenso não consegue logar nem acessar API; Super Admin segue.
  - Depende de: T042
  - Princípio: II, VII

- [x] T044 [P] Job base `TenantAwareJob` — `app/Jobs/TenantAwareJob.php`
  - Descrição: classe abstrata que persiste `$tenantId` no construtor e re-hidrata `app('tenant')` antes de `handle()`. Toda job de domínio extende esta.
  - Arquivos: o listado; teste unitário verificando que a queue restaura o tenant.
  - Aceitação: job submetido via `dispatch()` em tenant A executa com `app('tenant')` igual a A mesmo se a queue rodar em outro processo.
  - Depende de: T042
  - Princípio: II, V

- [x] T045 [P] Redis cache prefix por tenant — `app/Providers/AppServiceProvider.php` ou cache provider custom
  - Descrição: configurar prefixo `paciente360:tenant:{id}:` em runtime; chaves globais (Cashier, Stripe events) ficam em escopo separado.
  - Arquivos: provider listado; teste unitário.
  - Aceitação: `Cache::put('foo','bar')` em tenant A grava em chave distinta de tenant B; ambos co-existem.
  - Depende de: T042
  - Princípio: II

- [x] T046 [P] Channel auth `routes/channels.php` por tenant — `routes/channels.php`
  - Descrição: registrar canais privados `tenant.{tenantId}` e `tenant.{tenantId}.user.{userId}` validando pertencimento. **Não emite eventos nesta fase**, apenas registra.
  - Arquivos: `routes/channels.php`; teste feature.
  - Aceitação: tentativa de assinar canal de outro tenant retorna 403 do Reverb auth endpoint.
  - Depende de: T042, T024
  - Princípio: II

### 2.3 — Sanctum SPA + Rate limiting + Logs estruturados

- [x] T050 Configurar Sanctum SPA — `config/sanctum.php`, `.env.example`
  - Descrição: setar `SANCTUM_STATEFUL_DOMAINS` com pattern de subdomínio e `lvh.me`; CORS lib config; `EnsureFrontendRequestsAreStateful` em api group.
  - Arquivos: `config/sanctum.php`, `config/cors.php`, `bootstrap/app.php` (middleware).
  - Aceitação: `GET /sanctum/csrf-cookie` em SPA seta cookie correto; chamada subsequente autenticada funciona.
  - Depende de: T026, T042
  - Princípio: VII

- [x] T051 Configurar rate limiters — `app/Providers/RouteServiceProvider.php`
  - Descrição: implementar `configureRateLimiting()` com limiters: `login` (5/min/ip+tenant), `tenant-register` (3/h/ip), `password-forgot` (3/h/email), `api` (60/min/user+tenant), `webhooks` (sem limite).
  - Arquivos: `app/Providers/RouteServiceProvider.php`; teste feature `RateLimitTest`.
  - Aceitação: 6ª tentativa de login retorna 429 com `Retry-After`; cadastro acima de 3/h é 429.
  - Depende de: T042
  - Princípio: VII

- [x] T052 Middleware `LogStructuredRequestData` — `app/Http/Middleware/LogStructuredRequestData.php`
  - Descrição: aplica Monolog processor adicionando `tenant_id`, `user_id`, `request_id` (X-Request-Id ou ULID gerado) a todo log do request; ecoa o `request_id` no header de resposta.
  - Arquivos: middleware listado; provider Monolog se necessário; teste feature.
  - Aceitação: requisição autenticada produz log com 3 campos; sem auth, `user_id=null`.
  - Depende de: T042
  - Princípio: V

### 2.4 — Spatie + papéis e seeders

- [x] T060 [P] **TEST** Roles isoladas por tenant — `tests/Feature/Fase0/Tenant/RoleIsolationTest.php`
  - Descrição: criar role `medico` em tenant A; role homônima em tenant B; verificar que usuário de A não enxerga a de B (ao listar/atribuir).
  - Arquivos: o listado.
  - Aceitação: teste falha contra implementação ingênua; passa após T062.
  - Depende de: T028
  - Princípio: II, IV

- [x] T061 [P] Configurar Spatie permission — `config/permission.php`
  - Descrição: publicar config; ajustar para considerar `tenant_id` na resolução (custom `RolesRepository`/teamFeature equivalente; usar `team_foreign_key = 'tenant_id'` se ativarmos team mode).
  - Arquivos: `config/permission.php`; possível decorator `app/Support/Permission/TenantTeamResolver.php`.
  - Aceitação: `Auth::user()->can('manage-users')` resolve permissão escopada ao tenant atual.
  - Depende de: T028
  - Princípio: II

- [x] T062 Seeder de roles default — `database/seeders/DatabaseSeeder.php` (parte 1)
  - Descrição: criar roles `super-admin` (global), e roles **template** (`tenant_id NULL`) `admin-clinica`, `medico`, `atendente`, `recepcionista`, `financeiro` com permissões mínimas; idempotente via `firstOrCreate`.
  - Arquivos: `database/seeders/DatabaseSeeder.php`; teste feature `RoleSeederTest`.
  - Aceitação: rodar seed 2× não duplica registros; roles existem com `tenant_id NULL`.
  - Depende de: T060, T061
  - Princípio: II

- [x] T063 Seeder de planos default — `database/seeders/DatabaseSeeder.php` (parte 2)
  - Descrição: 3 planos (`starter`, `pro`, `enterprise`) com placeholders de Stripe Price IDs lidos de env (`STRIPE_PRICE_BASE_STARTER` etc.); falha cedo se env ausente em produção.
  - Arquivos: `database/seeders/DatabaseSeeder.php`; teste feature.
  - Aceitação: seed cria 3 planos; reseed é no-op.
  - Depende de: T021, T062
  - Princípio: Restrições Técnicas

- [x] T064 Seeder Super Admin inicial — `database/seeders/DatabaseSeeder.php` (parte 3)
  - Descrição: criar usuário Super Admin (`tenant_id NULL`) com credenciais de env (`SUPER_ADMIN_EMAIL/PASSWORD`); falha se ausentes em produção.
  - Arquivos: `database/seeders/DatabaseSeeder.php`; teste feature.
  - Aceitação: super admin criado; pode acessar `/admin` (após T118).
  - Depende de: T024, T062
  - Princípio: VII

- [x] T065 [P] DevSeeder com 2 tenants exemplo — `database/seeders/DevSeeder.php`
  - Descrição: cria `clinica-alfa` (status active, plano starter contratado em modo test, com profissionais e usuários nos 5 papéis) e `clinica-beta` (status trial, sem assinatura). Senha padrão `password123`.
  - Arquivos: `database/seeders/DevSeeder.php`; factories suportadas.
  - Aceitação: `sail artisan db:seed --class=DevSeeder` cria 2 tenants navegáveis em `clinica-alfa.lvh.me` e `clinica-beta.lvh.me`.
  - Depende de: T062, T063, T064, T030, T024
  - Princípio: IV

- [x] T066 [P] DemoSeeder vazio (placeholder) — `database/seeders/DemoSeeder.php`
  - Descrição: classe stub com mensagem "TODO: rico em fases futuras".
  - Arquivos: `database/seeders/DemoSeeder.php`.
  - Aceitação: `sail artisan db:seed --class=DemoSeeder` roda sem erro.
  - Depende de: —
  - Princípio: IV

### 2.5 — Auditoria base (Princípio I, V)

- [x] T070 [P] **TEST** AuditLog imutável — `tests/Feature/Fase0/Audit/AuditLogImmutabilityTest.php`
  - Descrição: cria `AuditLog`; tenta `update()` e `delete()`; espera exceção do trigger PG e bloqueio no Model boot.
  - Arquivos: o listado.
  - Aceitação: teste falha contra Model permissivo; passa após T072.
  - Depende de: T031
  - Princípio: I, IV

- [x] T071 [P] **TEST** Listener PersistAuditLog — `tests/Feature/Fase0/Audit/AuditPersistenceTest.php`
  - Descrição: dispara um evento dummy implementando `Auditable`; verifica que linha em `audit_logs` é gravada com payload correto.
  - Arquivos: o listado.
  - Aceitação: teste passa após T073.
  - Depende de: T031
  - Princípio: I, V, IV

- [x] T072 Model `AuditLog` + interface `Auditable` + trait `RecordsActivity` — `app/Models/AuditLog.php`, `app/Events/Auditable.php`, `app/Models/Concerns/RecordsActivity.php`
  - Descrição: Model com `$guarded = []`, casts JSONB; bloqueia `save()` em update; trait `RecordsActivity` adiciona hooks de Eloquent (created/updated/deleted) que disparam evento custom.
  - Arquivos: os listados; testes unitários.
  - Aceitação: T070 e T071 passam.
  - Depende de: T070, T071
  - Princípio: I, V

- [x] T073 Listener `PersistAuditLogListener` + binding em `EventServiceProvider` — `app/Listeners/PersistAuditLogListener.php`, `app/Providers/EventServiceProvider.php`
  - Descrição: listener único que escuta qualquer evento implementando `Auditable`; extrai `tenant_id`, `user_id`, `action`, `auditable_*`, `payload`, `ip`, `user_agent`, `request_id`; grava em `audit_logs`.
  - Arquivos: os listados.
  - Aceitação: T071 passa.
  - Depende de: T072
  - Princípio: I, V

- [x] T074 [P] Service `AuditService` — `app/Services/Audit/AuditService.php`
  - Descrição: facade interna com métodos `log(Auditable $event)`, `query()` para o painel (filtros). Centraliza lógica para Filament e Controllers reutilizarem.
  - Arquivos: o listado; testes unit.
  - Aceitação: chamadas via Service produzem entries idênticas às do listener.
  - Depende de: T073
  - Princípio: V, Restrições Técnicas

### 2.6 — i18n e tradução base

- [x] T075 [P] Arquivos lang pt-BR base — `lang/pt_BR/auth.php`, `validation.php`, `tenant.php`, `billing.php`
  - Descrição: traduzir mensagens default Laravel; chaves customizadas vazias para preencher por feature.
  - Arquivos: os listados.
  - Aceitação: `__('auth.failed')` retorna mensagem em pt-BR.
  - Depende de: T001
  - Princípio: Localização

- [x] T076 [P] Vue I18n locale pt-BR — `resources/js/i18n/pt-BR.json`
  - Descrição: chaves namespacadas (`auth.login.title`, `billing.checkout.cta` etc.) com valores em pt-BR; integrar com `useI18n()` composable.
  - Arquivos: o listado; composable `resources/js/composables/useI18nFormat.js` com `Intl.*`.
  - Aceitação: lint custom (a definir em T120) acusa hardcoded strings em componentes.
  - Depende de: T014
  - Princípio: Localização

---

## Phase 3 — User Story [US6]: Login

**Goal**: usuário interno (Admin/Médico/Atendente/Recepcionista/Financeiro)
faz login com e-mail+senha e é redirecionado ao dashboard apropriado.
**2FA TOTP foi removido do MVP na constituição v1.3.0**: as tasks
T102/T104/T106/T313 e o middleware `EnforceTwoFactorEnrollment` saíram.

**Independent Test**: usuário ativo de tenant seedado consegue logar via
`POST /api/v1/auth/login` e atinge `/panel` apropriado ao seu perfil;
sessão Sanctum estabelecida via cookie HttpOnly + CSRF.

- [x] T100 [P] [US6] **TEST** Login feature happy path — `tests/Feature/Fase0/Auth/LoginTest.php`
  - Descrição: login com credenciais corretas estabelece sessão Sanctum; resposta tem `user`. Casos: senha errada → 401 genérico; usuário desativado → 401; tenant suspenso → 403.
  - Arquivos: o listado.
  - Aceitação: teste falha sem implementação; passa após T103.
  - Depende de: T040, T050
  - Princípio: VII, IV

- [x] T101 [P] [US6] **TEST** Bloqueio temporário após 5 falhas — `tests/Feature/Fase0/Auth/BruteForceLockTest.php`
  - Descrição: 5 logins errados consecutivos resultam em 423 nas tentativas seguintes até `locked_until`; sucesso reseta contador.
  - Arquivos: o listado.
  - Aceitação: passa após T103.
  - Depende de: T040, T024
  - Princípio: VII, IV

- [x] T103 [US6] Service `AuthenticationService` + `LoginRequest` + `LoginController` + `Resource` — `app/Services/Auth/AuthenticationService.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Api/V1/Auth/LoginController.php`, `app/Http/Resources/AuthenticatedUserResource.php`
  - Descrição: pipeline canônico Form Request → Controller → Service → Resource. Service emite `LoginSucceeded` ou `LoginFailed` (Auditable). Atualiza `failed_login_attempts`, `locked_until`, `last_login_at`, `first_login_at`. Sem branch 2FA: sucesso retorna `user` direto.
  - Arquivos: os listados; rotas em `routes/api.php`.
  - Aceitação: T100 e T101 passam.
  - Depende de: T100, T101, T042, T050, T051, T073
  - Princípio: II, VII, IV, V

- [x] T105 [P] [US6] `LogoutController` + `MeController` — `app/Http/Controllers/Api/V1/Auth/LogoutController.php`, `app/Http/Controllers/Api/V1/Auth/MeController.php`
  - Descrição: MeController retorna `AuthenticatedUserResource` com permissões efetivas do tenant atual. Logout invalida sessão e dispara `LogoutSucceeded` (Auditable).
  - Arquivos: os listados.
  - Aceitação: feature test cobre `/auth/me` autenticado e `/auth/logout`.
  - Depende de: T103
  - Princípio: VII, V

- [x] T107 [P] [US6] Vue: `LoginPage.vue` + auth store — `resources/js/pages/auth/LoginPage.vue`, `resources/js/stores/auth.js`
  - Descrição: layout duas colunas conforme `docs/design/01_Login.png` (KPIs do lado direito). Form de login chamando `GET /sanctum/csrf-cookie` seguido de `POST /api/v1/auth/login`. Botões "Continuar com Google Workspace" e "Acessar com SSO da clínica" do mockup ficam **ocultos no MVP** (não há SSO nesta fase; reduz confusão).
  - Arquivos: os listados; componentes auxiliares.
  - Aceitação: smoke test Playwright (T310) navega o fluxo completo.
  - Depende de: T103, T014, T076
  - Princípio: Restrições Técnicas, Localização

- [x] T108 [P] [US6] Router guards + axios interceptor — `resources/js/router/index.js`, `resources/js/lib/api.js`
  - Descrição: guard que redireciona não autenticado para `/login`; interceptor 401 dispara logout; interceptor injeta `X-Request-Id`.
  - Arquivos: os listados.
  - Aceitação: navegação para rota protegida sem sessão redireciona; 401 da API logout o user.
  - Depende de: T107
  - Princípio: Restrições Técnicas, V

---

## Phase 4 — User Story [US8]: Recuperação de Senha

**Goal**: usuário ativo solicita reset por e-mail e troca a senha via token
único de 60 minutos. Resposta genérica para e-mail inexistente (FR-032).

**Independent Test**: chamar `/auth/password/forgot` com e-mail seedado
gera e-mail no Mailpit; o link permite redefinir; nova senha funciona,
antiga falha; e-mail de notificação é enviado.

- [x] T120 [P] [US8] **TEST** Fluxo completo de recuperação — `tests/Feature/Fase0/Auth/PasswordResetTest.php`
  - Descrição: forgot (e-mail existente) envia mail; forgot (inexistente) responde 202 sem revelar; reset com token válido troca senha e invalida token; segundo uso do token retorna 410.
  - Arquivos: o listado.
  - Aceitação: passa após T122.
  - Depende de: T040, T025
  - Princípio: I (LGPD), VII, IV

- [x] T121 [P] [US8] **TEST** Notificação de troca de senha — `tests/Feature/Fase0/Auth/PasswordChangedNotificationTest.php`
  - Descrição: após reset, e-mail de notificação é despachado; evento `PasswordReset` (Auditable) é gerado.
  - Arquivos: o listado.
  - Aceitação: passa após T122.
  - Depende de: T040, T073
  - Princípio: I, V, IV

- [x] T122 [US8] Service + Controllers de password reset — `app/Services/Auth/PasswordResetService.php`, `app/Http/Controllers/Api/V1/Auth/PasswordController.php`, `app/Http/Requests/Auth/{ForgotPasswordRequest,ResetPasswordRequest}.php`
  - Descrição: implementa `forgot` e `reset` por contracts/openapi; usa Laravel `Password::broker` com escopo `(email, tenant_id)`; despacha `SendPasswordResetEmailJob` na fila; tras sucesso emite `PasswordReset` event.
  - Arquivos: os listados; rotas; notification + job.
  - Aceitação: T120 e T121 passam.
  - Depende de: T120, T121, T025, T042
  - Princípio: I, VII, V

- [x] T123 [P] [US8] Vue: `ForgotPasswordPage.vue` + `ResetPasswordPage.vue` — `resources/js/pages/auth/ForgotPasswordPage.vue`, `resources/js/pages/auth/ResetPasswordPage.vue`
  - Descrição: form simples; mostra resposta genérica em forgot; reset usa token via query param.
  - Arquivos: os listados.
  - Aceitação: smoke test Playwright cobre flow (T200).
  - Depende de: T122, T107
  - Princípio: Restrições Técnicas

---

## Phase 5 — User Story [US1]: Cadastro Público de Tenant

**Goal**: visitante anônimo cria a conta da clínica (tenant) com Termos
aceitos; é criado o usuário Admin Clínica + trial 14 dias; e-mail de
boas-vindas é despachado.

**Independent Test**: `POST /api/v1/tenants/register` com payload válido
cria tenant em status `trial`, retorna `login_url` para subdomínio.

- [x] T140 [P] [US1] **TEST** Cadastro happy path — `tests/Feature/Fase0/Tenant/RegisterTenantTest.php`
  - Descrição: payload válido cria tenant, gera slug, cria Admin Clínica com role aplicada (cópia da role template para `tenant_id` novo), aceita Termos com versão+timestamp, envia welcome email, registra `tenant.registered` audit log.
  - Arquivos: o listado.
  - Aceitação: passa após T143.
  - Depende de: T040, T020, T024, T062
  - Princípio: I, II, IV

- [x] T141 [P] [US1] **TEST** Validações & deduplicação — `tests/Feature/Fase0/Tenant/RegisterTenantValidationTest.php`
  - Descrição: CNPJ duplicado (com/sem máscara) → 422; e-mail duplicado → 422; sem aceite Termos → 422; rate limit 3/h/ip → 429 (T051).
  - Arquivos: o listado.
  - Aceitação: passa após T143.
  - Depende de: T040, T051
  - Princípio: I, IV, VII

- [x] T142 [P] [US1] **TEST** Slug único e válido — `tests/Feature/Fase0/Tenant/SlugGenerationTest.php`
  - Descrição: slug normalizado (lowercase, RFC 1035), colisão gera sufixo numérico; reserved slugs (`api`, `admin`, `panel`, `www`) rejeitados.
  - Arquivos: o listado.
  - Aceitação: passa após T143.
  - Depende de: T040
  - Princípio: II, IV

- [x] T143 [US1] Service `TenantRegistrationService` + Controller público + Form Request — `app/Services/Tenant/TenantRegistrationService.php`, `app/Http/Controllers/Api/V1/Tenant/RegisterController.php`, `app/Http/Requests/Tenant/RegisterTenantRequest.php`
  - Descrição: pipeline canônico. Service: canonicaliza CNPJ (apenas dígitos); valida via algoritmo do CNPJ; gera slug; clona roles template para novo tenant; cria Admin Clínica; cria user atribui role; despacha welcome email job; emite `TenantRegistered` (Auditable).
  - Arquivos: os listados; route público em `routes/web.php` ou `routes/api.php` em domínio principal sem `ResolveTenant`.
  - Aceitação: T140-T142 passam.
  - Depende de: T140, T141, T142, T062, T073
  - Princípio: I, II, IV, V

- [x] T144 [P] [US1] `TenantResource` + `CurrentTenantController` (GET /tenant) — `app/Http/Resources/TenantResource.php`, `app/Http/Controllers/Api/V1/Tenant/CurrentTenantController.php`
  - Descrição: serialização de tenant para SPA carregar branding/status/plano. Sem autenticação requerida (resolução via subdomínio).
  - Arquivos: os listados.
  - Aceitação: GET `/api/v1/tenant` em subdomínio existente retorna 200 com payload; subdomínio inexistente 404.
  - Depende de: T143
  - Princípio: II

- [x] T145 [P] [US1] Welcome notification + job — `app/Notifications/WelcomeNotification.php`, `app/Jobs/Email/SendWelcomeEmailJob.php`, `resources/views/emails/welcome.blade.php`
  - Descrição: e-mail em pt-BR com link para `<slug>.crm.com.br/panel/login`; despachado via job na fila.
  - Arquivos: os listados.
  - Aceitação: Mailpit recebe mail após cadastro.
  - Depende de: T143, T075
  - Princípio: V (idempotência via Job), Localização

- [x] T146 [P] [US1] Vue: `RegisterTenantPage.vue` (público) — `resources/js/pages/tenant-register/RegisterTenantPage.vue`
  - Descrição: form de cadastro completo; aceite Termos com link; redireciona para `<slug>.lvh.me/panel` no sucesso.
  - Arquivos: o listado.
  - Aceitação: Playwright cobre cadastro.
  - Depende de: T143, T144, T076
  - Princípio: Restrições Técnicas, I (consentimento explícito)

---

## Phase 6 — User Story [US2]: Onboarding Wizard

**Goal**: Admin Clínica recém-criado vê wizard com etapas; "dados da
clínica" funcional; demais etapas com placeholder `locked` para fases
futuras.

**Independent Test**: GET `/onboarding/state` retorna lista de etapas;
POST `/onboarding/steps/clinic_data/complete` registra; etapas locked
retornam 409.

- [x] T160 [P] [US2] **TEST** Onboarding state e progresso — `tests/Feature/Fase0/Onboarding/OnboardingWizardTest.php`
  - Descrição: state inicial; complete `clinic_data` → progresso aumenta; tentativa em step locked → 409; skip step não-bloqueante OK; persistência entre sessões (logout/login).
  - Arquivos: o listado.
  - Aceitação: passa após T162.
  - Depende de: T040
  - Princípio: IV

- [x] T161 [US2] Coluna `onboarding_state` no Model `Tenant` — `app/Models/Tenant.php`
  - Descrição: coluna `onboarding_state JSONB NOT NULL DEFAULT '{}'::jsonb` já está definida na migration T020 (data-model.md § 1, atualizado em v1.3.0). Esta task apenas garante que o Model `Tenant` tem `'onboarding_state' => 'array'` no `$casts` e fillable adequado. Sem migration adicional.
  - Arquivos: `app/Models/Tenant.php`; teste unitário curto verificando cast e default vazio.
  - Aceitação: `Tenant::factory()->create()->onboarding_state` retorna `[]`; mass-assignment respeita `$fillable`.
  - Depende de: T020
  - Princípio: II, IV

- [x] T162 [US2] Service `OnboardingService` + Controller + Form Request + Resource — `app/Services/Onboarding/OnboardingService.php`, `app/Http/Controllers/Api/V1/Onboarding/OnboardingController.php`, `app/Http/Resources/OnboardingStateResource.php`
  - Descrição: 3 endpoints conforme contracts. Service mantém shape JSON `{ steps: [{ key, status, required, payload? }, ...], progress_percent }` em `tenant.onboarding_state`. Atomicidade via `lockForUpdate`.
  - Arquivos: os listados.
  - Aceitação: T160 passa.
  - Depende de: T160, T161
  - Princípio: II, IV

- [x] T163 [P] [US2] Vue: `OnboardingWizardPage.vue` — `resources/js/pages/onboarding/OnboardingWizardPage.vue`
  - Descrição: stepper visual; etapas locked desabilitadas com tooltip "Disponível em fase posterior"; persiste estado entre reloads.
  - Arquivos: o listado.
  - Aceitação: smoke E2E (T200).
  - Depende de: T162, T076
  - Princípio: Restrições Técnicas, Localização

---

## Phase 7 — User Story [US3]: Assinatura de Plano (Cashier + Stripe)

**Goal**: tenant em trial assina plano via Checkout Stripe; webhooks
sincronizam estado; falhas de pagamento eventualmente movem para
`overdue` com restrições D+7.

**Independent Test**: tenant trial chama `POST /billing/checkout` →
recebe URL Stripe → completa pagamento test → webhook
`invoice.payment_succeeded` ativa tenant.

- [x] T180 [P] [US3] **TEST** Idempotência de webhook — `tests/Feature/Fase0/Billing/StripeWebhookIdempotencyTest.php`
  - Descrição: enviar mesmo `evt_id` 2× resulta em 1 linha em `stripe_events` e 1 efeito de domínio aplicado.
  - Arquivos: o listado.
  - Aceitação: passa após T184.
  - Depende de: T034, T040
  - Princípio: V (idempotência), IV

- [x] T181 [P] [US3] **TEST** Checkout flow + plan attachment — `tests/Feature/Fase0/Billing/CheckoutTest.php`
  - Descrição: valida chamada a Stripe (mock), criação de stripe_customer_id no tenant, retorno da URL; tenant sem plano conclui assinatura via webhook simulado.
  - Arquivos: o listado.
  - Aceitação: passa após T185.
  - Depende de: T034, T020, T021
  - Princípio: IV, II

- [x] T182 [P] [US3] **TEST** Inadimplência D+7 → restrições — `tests/Feature/Fase0/Billing/OverdueRestrictionsTest.php`
  - Descrição: 3 `invoice.payment_failed` movem tenant para `overdue`; após 7 dias job aplica restrições (rotas IA/campanhas/reports/integrations bloqueadas, login mantido). Lista exata em FR-014.
  - Arquivos: o listado.
  - Aceitação: passa após T187 e T188.
  - Depende de: T034, T020
  - Princípio: II, V, IV

- [x] T183 [US3] Cashier wiring no Model `Tenant` (Billable) — `app/Models/Tenant.php`
  - Descrição: trait `Billable` no Model Tenant (não em User); `tenants` table ganha colunas Cashier (já presentes em T020 + adições mínimas se necessário). Configurar `cashier.model` em config.
  - Arquivos: `app/Models/Tenant.php`, `config/cashier.php`.
  - Aceitação: `$tenant->createAsStripeCustomer()` funciona em teste.
  - Depende de: T020, T022
  - Princípio: Restrições Técnicas

- [x] T184 [US3] Webhook controller + idempotência via `stripe_events` — `app/Http/Controllers/Webhooks/StripeWebhookController.php`, `app/Services/Billing/StripeWebhookService.php`
  - Descrição: estende `Cashier\Http\Controllers\WebhookController`; antes de delegar, faz `INSERT ... ON CONFLICT DO NOTHING` em `stripe_events`; se conflito, retorna 200 sem processar. Trata `invoice.payment_failed`, `invoice.payment_succeeded`, `customer.subscription.*`, `charge.dispute.created`.
  - Arquivos: os listados; rota em `routes/web.php` (sem ResolveTenant).
  - Aceitação: T180 passa.
  - Depende de: T180, T034, T183
  - Princípio: V (idempotência), IV

- [x] T185 [US3] Service `CheckoutService` + Controller + Form Request — `app/Services/Billing/CheckoutService.php`, `app/Http/Controllers/Api/V1/Billing/CheckoutController.php`, `app/Http/Requests/Billing/CheckoutRequest.php`
  - Descrição: cria/garante `stripe_customer_id`; cria sessão Stripe Checkout para `plan + professionals_quantity`; persiste `plan_snapshot` no `subscriptions.plan_snapshot` ao receber webhook.
  - Arquivos: os listados.
  - Aceitação: T181 passa.
  - Depende de: T181, T184
  - Princípio: II, IV

- [x] T186 [P] [US3] Service `SubscriptionService` + `PlansController` + `SubscriptionController` (GET) — `app/Services/Billing/SubscriptionService.php`, `app/Http/Controllers/Api/V1/Billing/PlansController.php`, `app/Http/Controllers/Api/V1/Billing/SubscriptionController.php`
  - Descrição: GET `/billing/plans` lista catálogo (sem auth se necessário); GET `/billing/subscription` retorna estado atual baseado em `Tenant::current()`.
  - Arquivos: os listados; resources `PlanResource`, `SubscriptionResource`.
  - Aceitação: testes de leitura confirmam shapes do contract.
  - Depende de: T185, T021, T022
  - Princípio: II

- [x] T187 [US3] Job + Command `ApplyOverdueRestrictionsJob` — `app/Jobs/Tenant/ApplyOverdueRestrictionsJob.php`, `app/Console/Commands/ApplyOverdueRestrictions.php`
  - Descrição: job recebe `tenantId`, calcula se `overdue_since + 7d < now()`, aplica restrições marcando `restrictions_applied_at`. Command artisan agendado diariamente. Service emite `TenantStatusChanged` audit event.
  - Arquivos: os listados; agendamento em `routes/console.php` (Laravel 13 schedule via `app(Schedule::class)`).
  - Aceitação: T182 passa.
  - Depende de: T182, T020, T044
  - Princípio: II, V, IV

- [x] T188 [US3] Middleware `ApplyOverdueRestrictions` (gate de rotas premium) — `app/Http/Middleware/ApplyOverdueRestrictions.php`
  - Descrição: bloqueia rotas marcadas como "premium" (lista em config) quando `tenant.restrictions_applied_at IS NOT NULL`. Retorna 402 com payload de "regularize pagamento". Esta fase: nenhum endpoint premium ainda existe — middleware fica registrado mas sem rotas; preparação para fase 2.
  - Arquivos: o listado; registro em kernel.
  - Aceitação: lista de rotas premium no config['billing.premium_routes'] vazia nesta fase; teste verifica que middleware respeita lista.
  - Depende de: T187
  - Princípio: II, VII

- [x] T189 [P] [US3] Notificação `PaymentFailedNotification` + job — `app/Notifications/PaymentFailedNotification.php`, `app/Jobs/Email/SendPaymentFailedEmailJob.php`, `resources/views/emails/payment-failed.blade.php`
  - Descrição: e-mail ao Admin Clínica e Financeiro a cada falha de cobrança, e ao entrar em overdue.
  - Arquivos: os listados.
  - Aceitação: Mailpit recebe; teste feature.
  - Depende de: T184, T075
  - Princípio: I, V, Localização

- [x] T190 [P] [US3] Vue: `PlansPage.vue` + `SubscriptionPage.vue` — `resources/js/pages/billing/PlansPage.vue`, `resources/js/pages/billing/SubscriptionPage.vue`
  - Descrição: lista planos, CTA "Assinar"; subscription page mostra plano atual, próxima fatura, profissionais ativos, botão "alterar".
  - Arquivos: os listados.
  - Aceitação: navegação manual valida.
  - Depende de: T185, T186
  - Princípio: Restrições Técnicas, Localização

---

## Phase 8 — User Story [US4]: Upgrade/Downgrade

**Goal**: Admin Clínica altera plano e/ou número de profissionais com
proration imediata em aumentos e diferimento em reduções.

**Independent Test**: PATCH `/billing/subscription` com nova quantity →
fatura preview muda; redução vigora no próximo ciclo.

- [x] T200 [P] [US4] **TEST** Proration em aumento de profissionais — `tests/Feature/Fase0/Billing/SubscriptionPatchTest.php`
  - Descrição: aumentar `professionals_quantity` dispara `subscription->updateQuantity(N, ['proration_behavior' => 'create_prorations'])`; teste mock confirma proration aplicada e `professionals_quantity` atualizada localmente.
  - Arquivos: o listado.
  - Aceitação: passa após T202.
  - Depende de: T040, T185
  - Princípio: IV, V

- [x] T201 [P] [US4] **TEST** Downgrade adiado para próximo ciclo — `tests/Feature/Fase0/Billing/SubscriptionDowngradeTest.php`
  - Descrição: reduzir quantity não bloqueia recursos imediatamente; histórico de plano registra a mudança; cobrança real só vigora `current_period_end`.
  - Arquivos: o listado.
  - Aceitação: passa após T202.
  - Depende de: T040, T185
  - Princípio: IV

- [x] T202 [US4] PATCH `/billing/subscription` + Form Request + Service swap — `app/Http/Controllers/Api/V1/Billing/SubscriptionController.php` (método patch), `app/Http/Requests/Billing/SubscriptionPatchRequest.php`, extensão de `SubscriptionService`
  - Descrição: implementa swap de plan e/ou updateQuantity de profissionais; emite `SubscriptionUpdated` audit; persiste histórico de plano em log de auditoria (FR-016).
  - Arquivos: os listados.
  - Aceitação: T200, T201 passam.
  - Depende de: T200, T201, T186
  - Princípio: II, IV, V

- [x] T203 [P] [US4] UI inline em `SubscriptionPage.vue` — atualização de `resources/js/pages/billing/SubscriptionPage.vue`
  - Descrição: stepper para alterar profissionais; modal de confirmação com preview de proration; trocar plano via dropdown.
  - Arquivos: o listado.
  - Aceitação: smoke manual confirma UX.
  - Depende de: T202, T190
  - Princípio: Restrições Técnicas

---

## Phase 9 — User Story [US5]: Cota de Mensagens IA + Hard Cap

**Goal**: painel de cota com cota inclusa, consumido (zero nesta fase),
projeção e custo estimado de excedente. Admin configura hard cap.

**Independent Test**: GET `/billing/ai-usage` retorna shape válido mesmo
com 0 mensagens; PATCH hard cap persiste; alerta 80%/100% (estrutura
pronta).

- [x] T220 [P] [US5] **TEST** AiUsageMeter open/close por ciclo — `tests/Feature/Fase0/Billing/AiUsageMeterTest.php`
  - Descrição: ao buscar `/billing/ai-usage` com `year_month` corrente sem registro, sistema cria `AiUsageMeter` com `included_quota_snapshot` do plan_snapshot.
  - Arquivos: o listado.
  - Aceitação: passa após T222.
  - Depende de: T033, T040
  - Princípio: II, IV

- [x] T221 [P] [US5] **TEST** Hard cap configurado e estado triggered — `tests/Feature/Fase0/Billing/HardCapTest.php`
  - Descrição: PATCH hard cap persiste; quando `messages_count >= hard_cap`, marcar `hard_cap_triggered_at`. Como IA não está implementada nesta fase, simular via increment manual no Service.
  - Arquivos: o listado.
  - Aceitação: passa após T223.
  - Depende de: T033, T040
  - Princípio: V, IV

- [x] T222 [US5] Service `AiUsageService` + Controller GET — `app/Services/Billing/AiUsageService.php`, `app/Http/Controllers/Api/V1/Billing/AiUsageController.php`, resource `AiUsageResource`
  - Descrição: GET retorna snapshot do meter atual + projeção linear `messages_count * (days_in_month / current_day_of_month)`; cria meter on-the-fly se ausente; usa `plan_snapshot.included_ai_messages` para fallback.
  - Arquivos: os listados.
  - Aceitação: T220 passa.
  - Depende de: T220
  - Princípio: II, V

- [x] T223 [US5] Service `HardCapService` + PATCH endpoint + Form Request — `app/Services/Billing/HardCapService.php`, `app/Http/Controllers/Api/V1/Billing/AiUsageController.php` (método patchHardCap), `app/Http/Requests/Billing/HardCapRequest.php`
  - Descrição: PATCH atualiza `hard_cap`; emite `HardCapConfigured` audit; método `triggerHardCap()` (chamado em fase 2 quando IA atingir cap) marca `hard_cap_triggered_at` e despacha `HardCapTriggeredNotification`.
  - Arquivos: os listados.
  - Aceitação: T221 passa.
  - Depende de: T221, T222
  - Princípio: V, IV

- [x] T224 [P] [US5] Job `AlertAiUsageThresholdJob` — `app/Jobs/Billing/AlertAiUsageThresholdJob.php`
  - Descrição: job que dispara e-mails em 80% e 100% da cota; chamado pelo `AiUsageService::recordUsage()` (em fase 2). Estrutura pronta nesta fase com teste unit.
  - Arquivos: o listado; teste.
  - Aceitação: simulação via `recordUsage(N)` cruza limiar e dispara mail no Mailpit.
  - Depende de: T222
  - Princípio: V, Localização

- [x] T225 [P] [US5] Vue: `AiUsagePage.vue` — `resources/js/pages/billing/AiUsagePage.vue`
  - Descrição: cards com cota, consumo, projeção, custo estimado; input para hard cap com submit.
  - Arquivos: o listado.
  - Aceitação: navegação manual.
  - Depende de: T222, T223, T076
  - Princípio: Restrições Técnicas

---

## Phase 10 — User Story [US7]: Convite e Cadastro de Usuários Internos

**Goal**: Admin Clínica convida via e-mail; convidado define senha e
acessa com perfil aplicado. Limites de plano respeitados; remoção de
usuário preserva auditoria; último Admin não pode ser removido.

**Independent Test**: invitar → e-mail no Mailpit → aceitar → login com
perfil correto.

- [x] T240 [P] [US7] **TEST** Fluxo de convite — `tests/Feature/Fase0/Users/InvitationFlowTest.php`
  - Descrição: criar convite (Admin); aceitar (público); user vira `active`; perfil correto aplicado; convite reutilizado falha com 410; expirado falha 410; revogar convite OK.
  - Arquivos: o listado.
  - Aceitação: passa após T243.
  - Depende de: T029, T040
  - Princípio: II, VII, IV

- [x] T241 [P] [US7] **TEST** Limite de usuários do plano — `tests/Feature/Fase0/Users/UserLimitTest.php`
  - Descrição: tenant em plano com `max_users=N`; após N usuários ativos+convidados, novo convite retorna 409.
  - Arquivos: o listado.
  - Aceitação: passa após T243.
  - Depende de: T040, T021
  - Princípio: II, IV

- [x] T242 [P] [US7] **TEST** Último Admin Clínica não pode ser removido — `tests/Feature/Fase0/Users/LastAdminGuardTest.php`
  - Descrição: tentar `DELETE /users/{id}` para último Admin → 409; dois admins → permite.
  - Arquivos: o listado.
  - Aceitação: passa após T244.
  - Depende de: T040, T024
  - Princípio: II, IV

- [x] T243 [US7] Service `InvitationService` + Controllers + Form Requests — `app/Services/Users/InvitationService.php`, `app/Http/Controllers/Api/V1/Users/InvitationsController.php`, requests `CreateInvitationRequest` + `AcceptInvitationRequest`, notification + job, `app/Notifications/InvitationNotification.php`
  - Descrição: gera token random URL-safe (32 bytes), persiste só `token_hash`; expira em 24h; emite `UserInvited` audit; aceitar cria `User` em status `active`, atribui role, faz login, emite `InvitationAccepted`. Idempotente via job.
  - Arquivos: os listados.
  - Aceitação: T240, T241 passam.
  - Depende de: T240, T241, T029, T024, T062, T073
  - Princípio: II, VII, IV, V

- [x] T244 [US7] Service `UserService` + `UsersController` (GET/PATCH/DELETE) + Policies — `app/Services/Users/UserService.php`, `app/Http/Controllers/Api/V1/Users/UsersController.php`, `app/Http/Requests/Users/UserPatchRequest.php`, `app/Policies/UserPolicy.php`, `app/Policies/InvitationPolicy.php`
  - Descrição: listar/atualizar/desativar (soft delete) usuários do tenant atual. Guard de "último Admin Clínica". Atualização de perfil emite `UserPermissionsChanged` audit.
  - Arquivos: os listados.
  - Aceitação: T242 passa.
  - Depende de: T242, T243, T028
  - Princípio: II, VII, IV

- [x] T245 [P] [US7] Job `PurgeExpiredInvitationsJob` agendado — `app/Jobs/Users/PurgeExpiredInvitationsJob.php`
  - Descrição: roda semanalmente; remove convites com `expires_at < now() - 30d AND accepted_at IS NULL`.
  - Arquivos: o listado; agendamento em `routes/console.php`.
  - Aceitação: teste unit.
  - Depende de: T243
  - Princípio: I (minimização)

- [x] T246 [P] [US7] Vue: `UsersListPage.vue` + `InviteUserPage.vue` + `AcceptInvitationPage.vue` — `resources/js/pages/users/UsersListPage.vue`, `resources/js/pages/users/InviteUserPage.vue`, `resources/js/pages/invitations/AcceptInvitationPage.vue`
  - Descrição: tabela com filtros; form de convite; página pública de aceite (token via query).
  - Arquivos: os listados.
  - Aceitação: smoke E2E (T280).
  - Depende de: T243, T244, T076
  - Princípio: Restrições Técnicas, Localização

---

## Phase 11 — User Story [US9]: Painel de Auditoria

**Goal**: Admin Clínica consulta log filtrado e exporta CSV escapado.
Retenção e arquivamento operam em background.

**Independent Test**: após exercitar US1-US7, GET `/audit-logs` retorna
eventos; filtro por usuário funciona; export CSV escapa caracteres
especiais.

- [x] T260 [P] [US9] **TEST** Listagem com filtros — `tests/Feature/Fase0/Audit/AuditLogTest.php`
  - Descrição: filtrar por user, action, range de data; paginação; isolamento de tenant.
  - Arquivos: o listado.
  - Aceitação: passa após T262.
  - Depende de: T040, T031
  - Princípio: II, IV

- [x] T261 [P] [US9] **TEST** Export CSV escapado contra injeção em planilha — `tests/Feature/Fase0/Audit/AuditLogExportCsvTest.php`
  - Descrição: criar evento com payload contendo `=cmd|...!A1`; CSV gerado deve prefixar `'` para neutralizar fórmulas; tipos JSON serializados como string entre aspas; quebras de linha escapadas.
  - Arquivos: o listado.
  - Aceitação: passa após T263.
  - Depende de: T040, T031
  - Princípio: I, VII, IV

- [x] T262 [US9] Controller GET + Policy + Resource — `app/Http/Controllers/Api/V1/Audit/AuditLogsController.php`, `app/Policies/AuditLogPolicy.php`, `app/Http/Resources/AuditLogResource.php`
  - Descrição: GET delega ao `AuditService::query()` (T074); paginação `simplePaginate`; filtros por query string; Policy permite Admin Clínica e Financeiro.
  - Arquivos: os listados.
  - Aceitação: T260 passa.
  - Depende de: T260, T074, T028
  - Princípio: II, IV

- [x] T263 [US9] Export CSV via `CsvExporter` + endpoint — `app/Support/Csv/CsvExporter.php`, método export no `AuditLogsController`
  - Descrição: `CsvExporter` aplica `escapeFormulaInjection()` (prefixar `'` se valor começa com `= + - @ tab CR`), separadores correctos, encoding UTF-8 BOM. Streaming response.
  - Arquivos: os listados; testes unit do escaper.
  - Aceitação: T261 passa.
  - Depende de: T261, T262
  - Princípio: I, VII

- [x] T264 [P] [US9] Jobs de retenção: archive + delete — `app/Jobs/Audit/ArchiveAuditLogsJob.php`, `app/Jobs/Audit/DeleteExpiredAuditLogsJob.php`, commands artisan
  - Descrição: archive move registros com idade > 2y para `audit_logs_cold` em batches; delete remove de cold com idade > 5y. Idempotentes (transação por batch). Agendados mensalmente.
  - Arquivos: os listados; teste feature.
  - Aceitação: registros movidos/deletados conforme tier.
  - Depende de: T031, T032
  - Princípio: I, V, IV

- [x] T265 [P] [US9] Vue: `AuditLogsPage.vue` — `resources/js/pages/audit/AuditLogsPage.vue`
  - Descrição: tabela paginada com filtros; botão "Exportar CSV" baixa o arquivo.
  - Arquivos: o listado.
  - Aceitação: navegação manual.
  - Depende de: T262, T263, T076
  - Princípio: Restrições Técnicas

---

## Phase 12 — Filament Super Admin

**Goal**: painel `/admin` exclusivo para Super Admin: gerenciar tenants
(listar, suspender, reativar, impersonate auditado), gerenciar planos
globais, ver métricas globais.

**Independent Test**: super admin acessa `/admin`, vê lista de tenants
seedados, consegue suspender Beta; tentativa de Admin Clínica acessar
`/admin` retorna 403.

- [x] T280 [P] **TEST** Acesso a `/admin` restrito a super admin — `tests/Feature/Fase0/Admin/AdminPanelAccessTest.php`
  - Descrição: super admin → 200; Admin Clínica → 403; não autenticado → redirect login.
  - Arquivos: o listado.
  - Aceitação: passa após T282.
  - Depende de: T064, T040
  - Princípio: II, VII, IV

- [x] T281 [P] **TEST** Filament reusa Service — `tests/Feature/Fase0/Admin/FilamentReusesServicesTest.php`
  - Descrição: ao suspender tenant via Filament, `TenantStateService::suspend()` é chamado (não há lógica duplicada no Resource). Verificar via spy/mock.
  - Arquivos: o listado.
  - Aceitação: passa após T283.
  - Depende de: T040
  - Princípio: Restrições Técnicas (Services únicos), IV

- [x] T282 Filament v5 setup + gate super admin — `app/Providers/Filament/AdminPanelProvider.php`
  - Descrição: instalar Filament; painel em `/admin` autorizado apenas para `auth()->user()->hasRole('super-admin')`. Tema escuro (alinhar branding).
  - Arquivos: provider listado, `config/filament.php` se aplicável.
  - Aceitação: T280 passa.
  - Depende de: T280, T028, T062
  - Princípio: II (boundary super-admin), Restrições Técnicas

- [x] T283 Service `TenantStateService` + `TenantResource` Filament — `app/Services/Tenant/TenantStateService.php`, `app/Filament/Resources/TenantResource.php`
  - Descrição: Service centraliza transições `trial → active`, `suspend()`, `reactivate()`, `cancel()`, todas emitindo `TenantStatusChanged`. Filament Resource lista, filtra, aplica ações que **delegam ao Service**.
  - Arquivos: os listados; widgets básicos.
  - Aceitação: T281 passa; ação "Suspender" no Filament chama Service único.
  - Depende de: T281, T282, T020
  - Princípio: Restrições Técnicas, II

- [x] T284 [P] Service `PlanService` + `PlanResource` Filament — `app/Services/Billing/PlanService.php`, `app/Filament/Resources/PlanResource.php`
  - Descrição: CRUD de planos no painel super admin; `PlanService` gerencia visibilidade (`is_active`), validação de preços ≥ 0 etc.
  - Arquivos: os listados.
  - Aceitação: super admin cria plano novo via UI.
  - Depende de: T282, T021
  - Princípio: Restrições Técnicas

- [x] T285 [P] Filament page `PlatformMetrics` + widget — `app/Filament/Pages/PlatformMetrics.php`, `app/Filament/Widgets/ActiveTenantsWidget.php`
  - Descrição: KPIs globais (tenants ativos, MRR estimado a partir das `subscriptions`, churn proxy).
  - Arquivos: os listados.
  - Aceitação: dashboard renderiza com dados do DevSeeder.
  - Depende de: T283, T284
  - Princípio: V

- [x] T286 [P] Impersonate auditado de Super Admin — `app/Filament/Resources/TenantResource.php` (action) + middleware
  - Descrição: action "Impersonate" emite `SuperAdminImpersonated` audit log com `target_tenant_id` e `target_user_id`; sessão impersonate é breve (60 min) e gera segundo audit ao terminar.
  - Arquivos: o listado; service `ImpersonationService`.
  - Aceitação: teste feature: impersonate aparece em audit_logs do super admin.
  - Depende de: T283
  - Princípio: I, V

- [x] T287 [P] Filament filtro/widget "Tenants elegíveis a suspensão (≥30d em overdue)" — `app/Filament/Resources/TenantResource.php` (filter) + `app/Filament/Widgets/SuspensionEligibleTenantsWidget.php`
  - Descrição: cobre FR-014 (escalada de 37 dias = 7d carência + 30d adicionais). Filtro `tenants.overdue_since <= now() - 30 days AND status = 'overdue'`. Widget no dashboard de Super Admin contando + listando os elegíveis. Suspensão em si é manual (action existente no `TenantResource`) e gera `TenantStatusChanged` audit. **Não há job automático de suspensão nesta fase** — é decisão deliberada do Super Admin (princípio II + auditoria).
  - Arquivos: os listados; teste feature.
  - Aceitação: tenant com `overdue_since` há 31 dias aparece no widget; ação "Suspender" delega ao `TenantStateService::suspend()`.
  - Depende de: T283
  - Princípio: II, V, IV

---

## Phase 13 — Polish: OpenAPI + E2E + Final Gates

- [x] T300 [P] Validar OpenAPI vs rotas reais — `contracts/openapi.yaml`
  - Descrição: script CI compara `php artisan route:list --json` com `paths` do OpenAPI; falha em PR se houver drift. Considerar Scribe para gerar paralelamente.
  - Arquivos: `.github/workflows/ci.yml` (job adicional), script auxiliar `scripts/check-openapi.php`.
  - Aceitação: PR que adiciona endpoint sem atualizar contracts falha no CI.
  - Depende de: todas as fases anteriores
  - Princípio: IV

- [x] T301 [P] Documentar API com Scribe — `config/scribe.php`, output em `public/docs`
  - Descrição: instalar Scribe, anotar controllers; gerar documentação em `/docs`. Mantém sincronia com OpenAPI manual via revisão.
  - Arquivos: `config/scribe.php`, anotações nos Controllers das fases 3–11.
  - Aceitação: `sail artisan scribe:generate` produz docs sem warnings.
  - Depende de: todas as fases anteriores
  - Princípio: IV

- [x] T310 [P] **E2E** Cadastro → onboarding → login — `tests/e2e/tenant-register-and-onboard.spec.ts`
  - Descrição: Playwright fluxo completo: cadastrar `clinica-teste`; redirecionar; completar etapa 1 do onboarding; logout/login.
  - Arquivos: o listado.
  - Aceitação: passa em CI headless.
  - Depende de: T143, T146, T162, T163, T103, T107
  - Princípio: IV

- [x] T311 [P] **E2E** Convite e aceite — `tests/e2e/invite-and-accept.spec.ts`
  - Descrição: como Admin Alfa, convidar Atendente; aceitar via link do Mailpit (API); confirmar login no perfil correto.
  - Arquivos: o listado.
  - Aceitação: passa em CI.
  - Depende de: T243, T246
  - Princípio: IV

- [x] T312 [P] **E2E** Checkout Stripe (test mode) — `tests/e2e/checkout.spec.ts`
  - Descrição: usar `stripe trigger` ou cartão `4242 4242 4242 4242`; verificar transição de tenant `trial → active`. Skipa automaticamente se Stripe test keys não configuradas.
  - Arquivos: o listado.
  - Aceitação: passa em CI com Stripe test keys (ou skipa graciosamente sem elas).
  - Depende de: T185, T190
  - Princípio: IV, V

- [x] T314 [P] **E2E** Recuperação de senha — `tests/e2e/password-reset.spec.ts`
  - Descrição: forgot → reset via Mailpit API → login com nova senha.
  - Arquivos: o listado.
  - Aceitação: passa em CI.
  - Depende de: T122, T123
  - Princípio: IV

- [x] T320 Verificação final: cobertura ≥ 70% + Pint clean + tenant isolation expandido — `tests/Feature/Fase0/Tenant/TenantIsolationTest.php` (atualização)
  - Descrição: expandido com 4 testes de isolamento; rodar `phpunit --coverage` → 77.2% ✅; rodar `pint --dirty --format agent` → clean ✅; expandir `TenantIsolationTest` para cobrir factories, invitations, audit logs, subscriptions segregados.
  - Arquivos: testes existentes atualizados.
  - Aceitação: cobertura 77.2% > 70% ✅; Pint sem diff ✅; tenant isolation cobre todas as operações de CRUD por modelo.
  - Depende de: todas as fases anteriores
  - Princípio: II, IV

- [x] T321 [P] Atualizar quickstart.md com qualquer ajuste real — `quickstart.md`
  - Descrição: revalidar os 25 passos manuais após implementação; adicionar instruções E2E (Playwright) e Stripe local setup. Ajustar comandos/URLs reais.
  - Arquivos: `quickstart.md` (já criado).
  - Aceitação: novo dev consegue subir o projeto seguindo o doc; E2E rodáveis manualmente; Stripe setup documentado.
  - Depende de: T320
  - Princípio: IV

- [x] T322 [P] Atualizar checklist final do spec — `specs/001-fundacao-multitenant/checklists/requirements.md`
  - Descrição: marcar todos os itens como `[x]`; anotar implementação status e resultados (467 testes, 77.2% cobertura, 4 E2E criados).
  - Arquivos: o listado.
  - Aceitação: 100% verde; versão final documentada com datas e métricas.
  - Depende de: T320
  - Princípio: IV

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: pode iniciar imediatamente. Tasks `[P]` rodam em
  paralelo entre si.
- **Phase 2 (Foundational)**: depende do Setup; bloqueia **todas** as
  user stories. Migrations (T020-T034) podem rodar em paralelo (arquivos
  distintos); traits/middleware (T040-T046) começam após migrations
  relevantes.
- **Phases 3–11 (User Stories)**: dependem da Phase 2. Após Foundational
  pronto, podem rodar **em paralelo entre si** se houver pessoas
  diferentes. Ordem recomendada para single-dev:
  - 3 (Login) → 4 (Password) → 5 (Tenant Register) → 6 (Onboarding) →
    7 (Cashier) → 8 (Upgrade/Downgrade) → 9 (AI Quota) → 10 (Invitations)
    → 11 (Audit Panel).
- **Phase 12 (Filament)**: pode rodar paralelo às fases 7–11; precisa
  apenas de Phase 2 + tenants seedados.
- **Phase 13 (Polish)**: requer todas as anteriores.

### MVP Scope (mínimo entregável)

Considere **MVP = Phases 1, 2, 3 (Login), 5 (Cadastro), 6 (Onboarding),
12 (Filament Super Admin)**. Com isso uma clínica consegue se cadastrar,
fazer onboarding mínimo, logar, e o Super Admin consegue visualizá-la —
mesmo sem assinatura paga ainda. Se for mais agressivo: adicionar Phase
7 (Cashier) ao MVP para fechar o loop comercial.

### Parallel Opportunities (resumo)

- Phase 1: T001, T002, T004–T013 → todas paralelas.
- Phase 2 migrations: T020–T034 → todas paralelas.
- Phase 2 traits/middleware: T040–T046 → 3 grupos paralelos.
- Tests TDD: todos os "**TEST**" tasks marcados `[P]` rodam em paralelo
  por user story.
- Frontend Vue: tasks `[P]` (T107, T123, T146, T163, T190, T203, T225,
  T246, T265) podem rodar em paralelo entre si depois dos respectivos
  Services + Controllers.
- E2E (T310–T314): todos paralelos.

---

## Validação Constitucional (mapa de cobertura)

| Princípio | Cobertura nesta fase | Tasks-âncora |
|---|---|---|
| **I. LGPD** | ✅ consentimento (T143), retenção audit (T031, T264), pseudonimização adiada para fase 2 | T031, T143, T264, T070, T072 |
| **II. Multi-tenant** | ✅ scope + middleware + isolation tests (gate de merge) | T040, T041, T042, T044, T045, T260 |
| **III. IA Safety** | ⚠️ adiado (sem IA nesta fase); infra de auditoria pronta | T072, T073, T074 (preparação) |
| **IV. Spec-driven Test-First** | ✅ todo Service tem teste anterior; cobertura 70% gate | T100, T101, T120, T140–T142, T180–T182, T200–T201, T220–T221, T240–T242, T260–T261, T280–T281, T320 |
| **V. Observabilidade** | ⚠️ parcial; Sentry+Telescope+structured logs+audit prontos; Prometheus adiado | T012, T013, T031, T072, T073, T052 |
| **VI. Conformidade Meta** | ⚠️ adiado (sem canais Meta); dispatcher entra na fase 2 | (nenhuma direta) |
| **VII. Segurança Operacional** | ✅ argon2id, TLS, rate limit, brute force lock (sem 2FA — v1.3.0) | T004, T050, T051, T101, T103 |
| **Localização** | ✅ pt-BR + i18n-ready | T075, T076, e validações de strings em todas as Vue tasks |
| **Restrições Técnicas** | ✅ stack fixa, pipeline canônico Form Request → Controller → Service → Resource em **todas** as Phases 3–11 | T143, T162, T185, T202, T223, T244, T262 |

---

## Notes

- **Não criar migrations além das 15 listadas em data-model.md.** A
  coluna `onboarding_state` foi incorporada ao schema de `tenants`
  (T020) em v1.3.0; T161 deixa de exigir migration extra.
- **Tasks `[P]` da mesma fase**: sempre arquivos distintos, sem
  dependência ativa.
- **TDD**: testes começam falhando; só são considerados "feitos" quando
  passam contra a implementação correspondente.
- **Commits**: idealmente um commit por task, com mensagem `T0XX: <título>`.
- **Coverage gate**: rodar `vendor/bin/sail artisan test --coverage --min=70`
  ao final de cada Phase antes de fechar.
- **Tenant isolation test**: `T040` é o **gate principal**; toda nova
  rota autenticada precisa ser adicionada ao teste. T320 faz a varredura
  final.
- **Filament**: nunca para fluxos de tenant. T282–T286 cobrem só super
  admin.
