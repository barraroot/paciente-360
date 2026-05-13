<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

<!-- SPECKIT START -->
For additional context about technologies to be used, project structure,
shell commands, and other important information, read the current plan:

- **Active feature**: `005-agendamento-consultas` — [plan](specs/005-agendamento-consultas/plan.md) — Fase 5 em planejamento (spec + plan + research + data-model + contracts + quickstart prontos em 2026-05-13). 14/15 clarifications resolvidos via 2 sessions de `/speckit.clarify`; NC nº 12 (UX revogação OAuth) resolvido em `research.md` R5. Próximo: `/speckit.tasks`. Highlights do design:
  - 7 user stories cobrindo Épico 6 do PRD (agenda, tipos, drag-and-drop, confirmação automática, reagendamento via chat, lista de espera FIFO sequencial, sync Google Calendar via sub-calendário tenant-scoped)
  - 14 entidades novas + 11 eventos de domínio + 6 cron jobs + ~50 endpoints REST
  - Outlook DEFERRED → Fase 6 (modelo `provider` enum preparado — clarify nº 11)
  - Constitution Check passa nos 7 princípios **sem amendment** (v1.4.0 cobre todos os gates)
- **Previous features delivered**:
  - `004-token-auth-migration` — [spec](specs/004-token-auth-migration/spec.md) — Cookie→Bearer migration entregue em 2026-05-13. 8 lotes (D-K), suite full **1130 tests / 1127 passed / 0 failures**. Commits: D `40af4ec` → K `1db8e96`. Highlights:
    - 6 endpoints Bearer (`/auth/login`, `/auth/me`, `/auth/logout[-all]`, `/auth/tokens[/{id}]`) + Reverb broadcast Bearer
    - SPA Vue migrada para Bearer + X-Tenant-Slug em `axios` + Echo authorizer
    - CSP estrita configurável (`config/csp.php`) + CORS env-driven (`CORS_ALLOWED_ORIGINS`)
    - **Bug arquitetural corrigido** (Lote F): `User::guardName()` pina Spatie no guard `web` — sem o pin, `Auth::shouldUse('sanctum')` quebraria silenciosamente `$user->can()` em produção sob Bearer
    - 4 métricas Prometheus em `AuthMetrics` + Sentry tags `auth.token_id` / `auth.token_name`
    - Comando `auth:tokens-purge-expired` schedulado diário 03:00 BRT (retention 90d)
    - Constitution amendment v1.4.0 aplicado em `2791c54` (Princípio VII — Bearer formato adicional)
  - `003-omnichannel-inbox` — [spec](specs/003-omnichannel-inbox/spec.md) — 290/290 tasks, 352 tests Fase 3, 47/47 ACs, 7/7 user stories. Mergeado em `main` 2026-05-12.
  - `002-crm-pacientes` — [plan](specs/002-crm-pacientes/plan.md) — 650 testes verdes
  - `001-fundacao-multitenant` — [plan](specs/001-fundacao-multitenant/plan.md) — 467 testes verdes
- **Constitution**: [.specify/memory/constitution.md](.specify/memory/constitution.md) (**v1.4.0** — amendment aplicado em 2026-05-12 para feature 004)
<!-- SPECKIT END -->

## CRM Pacientes (Fase 2) — Key Patterns

When working on CRM Pacientes features, remember these critical patterns:

1. **`pg_trgm + unaccent` enabled in PostgreSQL**
   - Buscas por nome/telefone usam `% similarity` com índice GIN composto `(tenant_id, campo_trgm)`.
   - `unaccent()` não é IMMUTABLE — use wrapper `immutable_unaccent(text)` em colunas GENERATED para evitar índices inválidos.

2. **Cast `AsJsonArray` padrão para JSONB multi-valor**
   - Use em colunas JSONB como `pacientes_origem_ids`, `checkpoint`, `snapshot_pre_merge`, `payload` de eventos.
   - Aplicado automaticamente em `MesclagemPaciente`, `Importacao`, `EventoTimeline`.

3. **Listener `RegistraEventoTimelineListener` projeta eventos para timeline**
   - Escuta qualquer `Auditable` cujo `auditableModel()` retorna Paciente/Anotacao/Tag.
   - Grava em `eventos_timeline` automaticamente (além de `audit_logs`).
   - Bind em `EventServiceProvider`.

4. **Abilities granulares `paciente.note.view:{tipo}` controlam visibilidade**
   - 4 tipos: `geral`, `clinica`, `comportamental`, `financeira`.
   - `AnotacaoPolicy::view()` retorna falso se o user não tem ability para o tipo da anotação.
   - Aplicado em `PacientePolicy`, `AnotacaoPolicy`, confirmado em T030.

5. **Event `ProfessionalDeactivated` dispara reatribuição de pacientes (T260)**
   - Observer no `Professional.boot()` detecta `is_active: true → false`.
   - Listener cria `TarefaReatribuicao` com lista de pacientes órfãos.
   - Job `ReassignOrphansJob` (extends `TenantAwareJob`) atualiza `profissional_responsavel_id = null`.

## Token Auth (Fase 4) — Key Patterns

When working on auth features post-Fase 4, remember:

1. **API tenant é stateless via Bearer Sanctum**
   - Endpoints autenticados exigem `Authorization: Bearer paciente360_<token>` + `X-Tenant-Slug: <slug>` (FR-011 triple-check).
   - Filament admin permanece cookie-session em domínio separado (`crm.com.br`), NÃO compartilha auth com a API tenant.
   - `users.email` é UNIQUE global (migration `2026_05_13_000001`) — permite resolver tenant via lookup direto no login.

2. **`User::guardName()` pina Spatie no guard `'web'`**
   - `Auth::shouldUse('sanctum')` (chamado pelo middleware `auth:sanctum`) muta `config('auth.defaults.guard')`. Sem o pin, Spatie buscaria permissions com `guard='sanctum'` e falharia silenciosamente (permissions seedadas com `guard='web'`).
   - Pinning resolve uma vez para todos os controllers Bearer-authenticated.

3. **Middleware `tenant.slug` em rotas `/auth/*`**
   - `EnsureTenantSlugHeader` (alias `tenant.slug`) — 400 se header ausente, 403 se mismatch com `$user->tenant_id`.
   - Allow-list: `api/v1/auth/login` apenas (não exige header — lookup por email).
   - Para `/inbox/*` e demais rotas API, ainda não aplicado (rollout adiado — afetaria ~227 callers).

4. **Testes legados usam `Sanctum::actingAs($user, ['*'])`**
   - Comando `tests:migrate-actingas-to-sanctum --apply` migrou 120 statements standalone. Chained calls (`$this->actingAs($u)->getJson(...)`) deliberadamente preservados.
   - Fallback `sanctum.guard = ['web']` mantido em `config/sanctum.php` para não quebrar chains até migração manual completa.

5. **`Sanctum::actingAs` + Spatie permissions**
   - Em setUp de testes, usar `Sanctum::actingAs($user, ['*'])` para preservar a instância com cache de roles do Spatie carregado. `$user->createToken()` força reload do DB sem o cache → channel callbacks com `$user->can(...)` podem falhar.

6. **CSP estrita configurável via `config/csp.php`**
   - `connect-src` inclui Reverb WSS + S3 media + API host. Override via env `CSP_REVERB_HOST` / `CSP_MEDIA_HOST` / `CSP_API_HOST`.
   - Production: nonce gerado por request, sem `unsafe-inline`/`unsafe-eval`. Local/test: permissivo para Vite HMR.

7. **Token retention 90d (`auth:tokens-purge-expired`)**
   - Schedule diário 03:00 BRT em `routes/console.php`. Purga `personal_access_tokens` com `expires_at < now()-90d`.
   - 4 métricas Prometheus em `AuthMetrics`: `auth_login_total{result}`, `auth_token_emitido_total`, `auth_token_revogado_total{motivo}`, `auth_active_tokens`.
