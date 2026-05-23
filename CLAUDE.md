<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- filament/filament (FILAMENT) - v5
- laravel/cashier (CASHIER) - v16
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/pail (PAIL) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/telescope (TELESCOPE) - v5
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- vue (VUE) - v3
- laravel-echo (ECHO) - v2
- prettier (PRETTIER) - v3
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
- Follow existing application Enum naming conventions.
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

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

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

=== filament/filament rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

</laravel-boost-guidelines>

<!-- SPECKIT START -->
For additional context about technologies to be used, project structure,
shell commands, and other important information, read the current plan:

- **Active feature**: `011-dashboard-executivo` — [plan](specs/011-dashboard-executivo/plan.md) — Dashboard Executivo polish (US-10.1). Spec aprovada (39 FRs, 25 acceptance scenarios, 0 clarifications, 12/12 checklist PASS) + plan + research (13 decisões — R2 sparkline real DEFERRED) + data-model + contracts/frontend-integration.md (8 gates G1–G8) + quickstart (6 lotes A–F). **Constitution Check PASS 7/7 sem amendment**. Backend Fase 8 95% reusado — apenas 1 linha de mudança (`'24h' => subHours(24)` no `ExecutiveDashboardController::resolvePeriod()`). Frontend reescreve `ExecutiveDashboardPage.vue` + 7 novos componentes + 2 composables + persistência localStorage da window. Sparkline real fica DEFERRED (backend não retorna time-series; `Sparkline.vue` stub criado preparado para futuro). Próximo: `/speckit-tasks`.
- **Previous features delivered (10)**:
  - `010-dashboard-home` — Dashboard Home (US-1.5) entregue (commit `e688b60`). 51/73 tasks done (22 deferred — testes formais Feature/Unit/Playwright). 6/7 Constitution Re-Check PASS + 1 PARTIAL (Princípio IV — testes formais deferred). Endpoint único `GET /api/v1/panel/home` consolida 4 seções; cache Redis 30s escopado tenant+user+scope; 5 métricas Prometheus em `PanelHomeMetrics`; 4 collectors com degradação graceful; Humanizer com allow-list LGPD (14 event types). Frontend: 7 componentes panel-home + 3 composables (usePanelHome, usePanelHomeScope, useAutoRefresh). Build verde 1.38s, Pint passed.
  - `009-app-shell` — App Shell entregue. 2 commits (`c306e57` fixes env + `8ca018a` Spec Kit). 41/50 tasks done (9 deferred — testes E2E Playwright + audit a11y manual + suite full validação). 6/7 Constitution Re-Check PASS + 1 PARTIAL (Princípio IV — E2E Playwright deferred). Build verde, Pint OK, Vite 200 OK em todos os módulos novos. 16 arquivos novos frontend (composables, components/layout, navigation config) + router refactor para nested children + i18n duplo (pt-BR.json + lang/pt_BR/layout.php) + CLAUDE.md Key Patterns App Shell.
  - `008-finalizacao-mvp` — Fase 8 (Épicos 9-13) **ENTREGUE** em 2026-05-22. **5 lotes A-E** + **Phase 8 Polish**, ~280 tasks marcadas. Commits: Lote A `66bce06`/`9c5f29f`/`f7c3211` (Privacidade) → B `628fd86`/`b8b4f38` (Super Admin) → C `cea1ec4`/`e1dcf61`/`959e0a2` (Campanhas) → E `d01d276` (Relatórios) → D-1 `bc47352` (Webhooks) → D-2 `9c5fa9c` (API Pública) → Polish (pendente commit). Highlights:
    - **5 módulos**: Privacidade LGPD (Q24/26/28/29), Super Admin (Gates 5/7), Campanhas (Compliance Gate 1), Integrações Webhooks + API Pública (HMAC SSRF Q17), Relatórios (Q9/11/13).
    - **22 migrations** (1 ALTER enum + 21 CREATE), **41 eventos** (todos `Auditable` + `ContainsNoClinicalData` quando consumidos por IA).
    - **8 cron schedules**: `privacy:*` (3), `super_admin:*` (3), `campaigns:dispatch-scheduled`, `integrations:purge-expired-dlq`, `reports:aggregate-hourly`.
    - **~175 tests feature + ~45 unit + 5 E2E Playwright** (campaign-dispatch, right-to-be-forgotten, data-portability, super-admin-impersonate, webhook-delivery).
    - **Constitution Check PASS 7/7 sem amendment** (v1.4.0 — Gates 1-7 todos ATIVOS).
    - **DEFERRED**: execução real da suite + scribe:generate + smoke staging + DPO approval (todos documentados em `docs/qa/*` e `docs/lgpd/dpo-approval-fase8.md`).
  - `007-gestao-receituario` — [spec](specs/007-gestao-receituario/spec.md) — Fase 7 / Épico 8 (Gestão de Receituários) entregue em 2026-05-19. **5 lotes A-E**, **199/199 tasks**, **175/175 prescription tests verdes**, suite full **1342 tests / 1338 passed / 0 failures (1 flaky timing pré-existente)**. Commits: A `66c6c46` → B `8a9890e` → C `7780b27` → D `44d8500` → E (pendente). Highlights:
    - 4 user stories Épico 8: US-8.1 Cadastro (mascaramento controladas 5 perfis), US-8.2 Alerta D-15/D-7/D-1, US-8.3 Renovação IA (contrato pseudonimizado 7 campos), US-8.4 Relatório + CSV
    - 7 entidades + 9 eventos + 7 listeners + 5 cron jobs + ~13 endpoints REST + Filament super-admin
    - **6 Gates constitucionais** verdes: ControlledPrescriptionAccessTest (Q8), ControlledPrescriptionRegulatoryTest (Portaria 344/98), PrescriptionAlertIdempotencyTest (Redis NX + DB UNIQUE), PrescriptionEventPayloadLgpdTest (reflection allowlist 7 props), CrossTenantPrescriptionTest (404 não 403), PrescriptionAlertChannelTest (template HSM)
    - DEFERRED: InboxTask real (Inbox Fase 3 sem `createForPatient`); MessageDispatchService::send() real (mesma dep); S3 real delete em PurgeOldPdfVersions; Sentry tracing/alerting (config externa); Grafana dashboard; smoke staging E2E
    - Constitution Check PASS 7/7 **sem amendment** (v1.4.0)
  - `006-agenda-ux-polish` — [spec](specs/006-agenda-ux-polish/spec.md) — Polimento UX da Agenda (Fase 6 UX) entregue em 2026-05-15, mergeada em `main` em 2026-05-17. **25/25 tasks**, 4 lotes A-D. Highlights:
  - `006-agenda-ux-polish` — [spec](specs/006-agenda-ux-polish/spec.md) — Polimento UX da Agenda (Fase 6 UX) entregue em 2026-05-15, mergeada em `main` em 2026-05-17. **25/25 tasks**, 4 lotes A-D. Highlights:
    - Lote A: AppointmentTypesPage UX (modal a11y, color picker mobile, moeda pt-BR via `Intl.NumberFormat`)
    - Lote B: ScheduleConfigPage UX (skeleton, copiar dia, atalho Ctrl+S, accordion mobile)
    - Lote C: CalendarSyncPage UX (estados rich, watch channel `aria-live`, Outlook placeholder)
    - Lote D: AttendanceMarkButton refactor (popover inline substitui `prompt()`/`confirm()` nativos)
    - Padrões reutilizáveis consolidados em parágrafo 11 de "Agendamento (Fase 5) — Key Patterns": modal a11y (`Teleport` + focus trap + Esc), toast local, popover inline, formatação pt-BR
    - Pré-requisitos entregues na branch 005: AgendaPage + WaitlistPage refinement
    - Suite full: 1167 tests / 1164 passed / 0 failures (zero regressão vs Fase 5)
  - `005-agendamento-consultas` — [spec](specs/005-agendamento-consultas/spec.md) — Fase 5 entregue em 2026-05-14, mergeada em `main` em 2026-05-16 (PR #1). 8 lotes (A-H), **185/185 tasks**, **37 tests verdes**. Commits: A `a7087eb` → H pendente. Highlights:
    - 7 user stories Épico 6 (agenda, tipos, drag-and-drop, confirmação automática, reagendamento via chat, lista de espera FIFO sequencial K=1, sync Google Calendar)
    - 14 entidades + 16 eventos de domínio + 6 cron jobs + ~30 endpoints REST + 1 webhook
    - Gates críticos: PARTIAL UNIQUE em appointments (SC-008/FR-011a) + sub-calendário Google tenant-scoped (clarify nº 15) + payload Google sem PII (FR-038/038a)
    - Outlook DEFERRED → Fase 6 (modelo `provider` enum preparado — clarify nº 11)
    - Constitution Check PASS nos 7 princípios **sem amendment** (v1.4.0 cobriu todos os gates)
    - Stubs Google API em `GoogleCalendarApiClient` — implementação real fica para integração de produção (smoke E2E QA staging)
    - Bug arquitetural descoberto e corrigido (Lote F): Laravel 11+ Event Discovery duplica listeners se registrado manualmente em AppServiceProvider
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

## Agendamento (Fase 5) — Key Patterns

When working on agenda features post-Fase 5, remember:

1. **PARTIAL UNIQUE em `appointments` é o gate atômico de race condition**
   - `CREATE UNIQUE INDEX app_active_slot_unique ON appointments (tenant_id, professional_id, starts_at) WHERE status IN ('scheduled', 'confirmed')` (FR-011a / SC-008).
   - Status terminais (`canceled, realizada, nao_realizada, concluida_sem_registro`) ficam fora — slot consumido/passado pode ser reusado em datas futuras.
   - `reschedule` PRESERVA status (clarify nº 7) — sem 'reagendada' no enum.

2. **`SlotReservation` é reserva pessimista soft com TTL diferenciado**
   - PARTIAL UNIQUE `(tenant_id, professional_id, starts_at) WHERE released_at IS NULL` impede 2 reservas ativas no mesmo slot.
   - TTL 5min user / 2min IA (configurável em `tenant.settings.agenda.slot_reservation_ttl_*_minutes`).
   - Cleanup cron `agenda:cleanup-expired-reservations` (everyMinute) marca `release_reason='expired'`.
   - Defesa em profundidade — gate final é o UNIQUE em `appointments` (FR-011a).

3. **Sub-calendário Google tenant-scoped (clarify nº 15)**
   - `CalendarSyncAccount` UNIQUE(`tenant_id`, `professional_id`) — mesma conta Google em 2 tenants gera 2 rows com `google_calendar_id` distintos.
   - Sub-cal criado automaticamente no callback OAuth: `Paciente360 — {Tenant.nome}`.
   - TODA chamada Google API usa `calendarId={sub_cal_id}` — eventos do tenant A invisíveis ao polling do tenant B.
   - Gate: `CrossTenantGoogleSyncTest` valida ExternalCalendarBusy isolado.

4. **Payload Google sem PII clínica (FR-038/038a)**
   - `GoogleCalendarSyncService::buildEventBody()` produz APENAS:
     - `summary`: `"Consulta — {Profissional.nome}"` (fixo, sem nome paciente / CPF / convênio)
     - `description`: `"Agendamento via {Tenant.nome}"` (genérico)
     - `start.timeZone` / `end.timeZone`: IANA do profissional
   - Gate LGPD: `GoogleEventPayloadLgpdTest` (assertStringNotContainsString para Maria Souza, CPF, "Cirurgia", "dor no peito").

5. **Listeners auto-discovered Laravel 11+ — NÃO registrar manualmente**
   - Laravel 11+ scaneia `app/Listeners/` e auto-registra listeners via type-hint do método `handle($event)`.
   - Registrar via `Event::listen()` em AppServiceProvider DUPLICA execução (descoberto em Lote F: lista de espera notificava 2x — fix removeu registrações manuais).
   - Padrão: criar listener com `handle(EventClass $event)` typed → discovery cuida do resto.

6. **TZ tenant default + override profissional + UTC interno (clarify nº 13)**
   - `tenants.timezone` é fonte; `professionals.timezone` é override nullable.
   - `TimezoneResolverService::forProfessional()` retorna IANA correto.
   - DB: tudo `timestamptz UTC`. API REST: ISO 8601 com offset + envelope `timezone_display` IANA.
   - Mensagens ao paciente: `IanaTimezoneCity::format("14:00", "America/Sao_Paulo")` → `"14:00 (horário de São Paulo)"`.

7. **Cron schedule (6 commands em `routes/console.php`)**
   - `agenda:cleanup-expired-reservations` — everyMinute (TTL slot reservations)
   - `agenda:expire-waitlist-notifications` — everyMinute (clarify nº 8 — re-notifica próximo)
   - `agenda:dispatch-confirmations` — every5min (T-24h/T-2h/retry/escalation)
   - `agenda:auto-close-stale-appointments` — daily 00:30 BRT (clarify nº 14 — janela 7d)
   - `agenda:google-poll-fallback` — every5min (R3 — cobre watch channel expirado)
   - `agenda:google-renew-watch-channels` — daily 02:00 BRT (R3 — renova antes TTL ~7d)

8. **`ConfirmationDispatch.status='pending_manual'` ≠ `Appointment.status`**
   - Quando T-15min sem resposta OU paciente sem canal → `ConfirmationDispatch.status='pending_manual'` + emit `ConsultaPendenteContatoManual` (Fase 3 cria task na inbox).
   - **`Appointment.status` permanece `scheduled`** — desambiguado em FR-019b/FR-024 (analyze A1).

9. **Stubs Google API em `GoogleCalendarApiClient`**
   - Wrapper testável — métodos REAIS (createSubCalendar, insertEvent, watchChannel, etc.) marcados como TODO.
   - Em produção com `google/apiclient` instalado: implementar passando o pacote.
   - Tests (incl. CrossTenantGoogleSyncTest, GoogleEventPayloadLgpdTest) usam stubs — não fazem requests reais.

10. **`Appointment.notes` é encrypted via cast** (Princípio I)
    - Cast `'notes' => 'encrypted'` aplica `Crypt::encryptString` antes de persistir.
    - Mesmo padrão para `CalendarSyncAccount.encrypted_access_token` / `encrypted_refresh_token`.

11. **UX polish Fase 6 (006-agenda-ux-polish) — Padrões reutilizáveis**
    - **Modal a11y padrão**: `Teleport to="body"` + `role=dialog` + `aria-modal="true"` + `aria-labelledby` + focus trap Tab/Shift+Tab + `@keydown.esc.prevent="close"` + overlay click fecha + bottom-sheet `items-end sm:items-center` em mobile. Ref: `AppointmentFormModal.vue` / `RescheduleConfirmModal.vue`.
    - **Toast pattern local** (sem lib): `const toast = ref(null)` + `showToast(msg, type)` + `setTimeout 5000` + `role=alert aria-live=assertive`. Replicado em AppointmentTypesPage, ScheduleConfigPage, CalendarSyncPage.
    - **Popover inline para confirmações curtas** (substitui `confirm()` / `prompt()`): `ref(false)` para controle + `aria-expanded` no trigger + `aria-controls` no painel + Esc fecha via keydown local. Ex.: `AttendanceMarkButton.vue` — painel "Não realizada" com textarea + popover de reversão.
    - **Proibido**: `confirm()`, `prompt()`, `alert()` nativos em qualquer componente novo — todos inacessíveis por leitores de tela e bloqueiam tab-order.
    - **Confirmação destrutiva**: sempre modal descritivo com nome/impacto do que será deletado — nunca só "Tem certeza?".
    - **Formatação moeda**: `new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)`. Data relativa: `DateTime.fromISO(iso).toRelative({ locale: 'pt-BR' })` via Luxon (já no bundle).

## Receituários (Fase 7) — Key Patterns

When working on prescription features post-Fase 7, remember:

1. **`PrescriptionType` define a regra regulatória (Portaria 344/98)**
   - `controlled` (Listas A) → validade fixa 30d + EXATAMENTE 1 item (trigger DB `enforce_controlled_single_item`) + mascaramento + audit em cada visualização.
   - `special` (Listas B) → validade fixa 30d.
   - `common` (Lista C ou sem controle) → `duration_days ∈ {30, 60, 90, 180}` (CHECK constraint server-side).
   - CHECK `chk_prescription_validity_by_type` enforça regra no DB; `StorePrescriptionRequest` é defesa em profundidade.

2. **Mascaramento de controladas via `ControlledPrescriptionMaskingService`**
   - Receita `type=controlled` retorna `PrescriptionMasked` (omite `items`/`notes`) para qualquer user sem ability `prescription.view_controlled`.
   - Emissor + Admin Clínica veem completo + emitem evento `PrescricaoControladaVisualizada` (audit em `audit_logs`).
   - **Ponto único de emissão**: `PrescriptionResource::toArray()` — evita duplicação em coleções.

3. **Global scope `withControlledIfAble` em `Prescription::booted()`**
   - Quando user não tem `prescription.view_controlled`: filtra receitas `controlled` cujo `professional_id != $user->id` antes do query DB.
   - Isto + mascaramento no Resource = duas camadas de defesa (Princípio I + defense in depth).

4. **Cadência de alertas D-15/D-7/D-1 — Idempotência dual layer**
   - DB UNIQUE `(prescription_id, alert_type)` + Redis lock `prescription_alert:{pid}:{type}:{date}` TTL 25h (defense in depth).
   - `PrescriptionAlertIdempotencyKey::for($pid, $alertType, $date)` gera chave Redis (padrão Fase 5 commands).
   - Cron `prescriptions:process-alerts` daily 06:00 BRT + `prescriptions:expire-active` daily 00:30 BRT (`withoutOverlapping()`).
   - Checkpoint passado na criação → `status=skipped` com `skip_reason='checkpoint_past_at_creation'` (não tenta disparar retroativo).

5. **`ContainsNoClinicalData` marker interface — Gate LGPD por reflection**
   - Qualquer evento consumido pela IA Matricial implementa `App\Support\Lgpd\ContainsNoClinicalData` (marker sem métodos).
   - `PrescriptionEventPayloadLgpdTest` valida via reflection que `ReceitaProximaDoVencimento` tem EXATAMENTE 7 props: `prescriptionId, patientId, professionalId, professionalName, daysUntilExpiry, prescriptionType, defaultAppointmentTypeId`.
   - **Qualquer field clínico (medication_name/posology/notes) quebra o gate** — adicionar nova prop exige revisão LGPD obrigatória.
   - `PrescriptionForAiResource` projeta os mesmos 7 campos no endpoint `GET /ai/prescriptions/{id}/context`.

6. **Opt-out paciente via `PatientProfessionalPreference`**
   - `suppress_renewal_notifications` boolean por `(patient_id, professional_id)` — UNIQUE composto.
   - `DispatchPrescriptionAlertViaMessaging` lê a preferência; se `suppress=true` → alert vira `skipped` com `skip_reason='recipient_opted_out'`. Evento `ReceitaProximaDoVencimento` ainda é emitido — apenas envio externo é suprimido.

7. **Debounce 4h por destinatário via Redis** (FR-016 / Q4d)
   - Cache key `messaging_debounce:prescription_alert:{patient_id}:{alert_type}` TTL 14400s.
   - `Redis::set($key, 1, 'EX', 14400, 'NX')` → se já existe → `skip_reason='debounced'`.

8. **Renovação via `prescription_renewals` (junção explícita)**
   - UNIQUE parcial `original_prescription_id WHERE renewed_prescription_id IS NOT NULL` impede duas renovações concluídas da mesma origem.
   - `RenewPrescriptionService::complete()` transita original → `superseded` + emite `ReceitaRenovada` → listener `CancelAlertScheduleOnRenewal` cancela alerts pending.
   - `StorePrescriptionRequest` aceita `renewed_from_id` nullable; `PrescriptionService::create()` chama `complete()` na mesma transação.
   - Política: `canRenew = status=active AND expires_at <= today+30d AND não já renovada`. Inelegível → 422 `prescription_not_eligible_for_renewal` com `reason` específico.

9. **Versionamento de PDF path-based** (research §2)
   - Path `prescriptions/{tenant_id}/{prescription_id}/v{n}.pdf` — versão atual em `pdf_version` na DB.
   - Substituição preserva `v0.pdf` no S3 (não usa S3 native versioning — portabilidade entre disks).
   - URL assinada TTL 15min via `PrescriptionSignedUrlService::sign()` + audit log de emissão.
   - Job semanal `prescriptions:purge-old-pdfs` mantém últimas 5 versões — controladas preservadas TODAS dentro da janela de retenção.

10. **Filament super-admin read-only para suporte** (research §7.4)
    - `app/Filament/Resources/Prescriptions/PrescriptionResource.php` — `withoutGlobalScopes()` para enxergar cross-tenant.
    - Apenas `ViewAction` (sem create/edit/delete). Audit log `super_admin.prescription.viewed` no boot do componente.
    - Acessível em `crm.com.br/admin` (cookie session Fase 4).

11. **Métricas Prometheus em `PrescriptionMetrics`**
    - `prescription_alerts_dispatched_total{tenant, alert_step, status}`
    - `prescription_alerts_blocked_total{reason, tenant}` (`no_template`, `no_channel`, `no_conversation`)
    - `prescription_alerts_idempotency_hits_total`
    - `prescription_alerts_processed_total`
    - `prescription_renewals_initiated_total{initiated_by, tenant}`
    - `prescription_pdfs_uploaded_total{status}`
    - `prescription_signed_urls_emitted_total{tenant}`
    - `prescription_csv_exports_total{tenant, has_controlled}`
    - `prescription_controlled_access_denied_total{tenant, perfil}` (alerta Sentry > 10 em 5min = scan).

12. **DEFERRED ao final da Fase 7** (documentados nos commits dos lotes C e D)
    - **InboxTask real**: `EnqueueInboxTaskOnAiRenewal` e fallback em `DispatchPrescriptionAlertViaMessaging` usam `Log::warning` + métrica. Integração com `ConversationService::createForPatient()` da Fase 3 ainda não disponível (Conversation precisa `channel_id` + `external_thread_id`, modelo de inbox interna ainda não desenhado).
    - **`MessageDispatchService::send()` real**: lookup de Conversation por paciente não existe — dispatcher atualiza `alert.status='dispatched'` diretamente.
    - **S3 real delete** em `PurgeOldPrescriptionPdfVersionsJob`: stub `Log::info` por enquanto.
    - **Smoke staging E2E**: 5 cenários do quickstart documentados em `docs/qa/smoke-fase7-prescriptions.md` — aguardando infra staging com módulo habilitado.
    - **Sentry alerts**: contadores Prometheus prontos; rules de alerting precisam ser configuradas em prod.


## Finalização (Fase 8) — Key Patterns

When working on features across Privacy/SuperAdmin/Campaigns/Integrations/Reports modules post-Fase 8, remember:

1. **`ConsentFinalidade::Integracoes` é o gate de PII em payload externo (Q17)**
   - Adicionado em migration `2026_05_25_000000_add_integracoes_to_consent_finalidade_enum.php` (ALTER TYPE).
   - `WebhookDispatcher::applyMasking()` chama `ConsentService::hasGranted($pacienteId, ConsentFinalidade::Integracoes)`. Sem granted → `paciente.id = '<consent_withheld>'` + outros campos removidos.
   - `PatientPublicResource` (API pública) aplica o mesmo gate.
   - PARTIAL UNIQUE `(patient_id, finalidade) WHERE state='granted'` em `consent_records` enforce 1 consentimento ativo por finalidade.

2. **Catálogo Q17 = EXATAMENTE 13 eventos no `BroadcastDomainEventToWebhooksListener::EVENT_CATALOG`**
   - Agenda 4 (Criada/Confirmada/Cancelada/Reagendada) + Pacientes 2 + Messaging 2 + Prescrições 2 (controladas mascaradas) + Campanhas 1 + Privacidade 2.
   - Subscriber registrado via `Event::subscribe()` em `EventServiceProvider::boot()` — NÃO usa auto-discovery (evita duplicação Fase 5 bug).
   - Gate test `WebhookCatalogCoverageTest` valida `count === 13` + dot-notation `<recurso>.<acao>` + classes existem via reflection. Adicionar evento ao catálogo exige atualizar este gate.

3. **HMAC SHA-256 + SSRF defense via `UrlGuard`**
   - `HmacSigner::sign($payload, $secret)` → `sha256=<hex>`. Verify usa `hash_equals` (timing-safe).
   - Header outbound: `X-Paciente360-Signature: sha256=...` + `X-Paciente360-Event` + `X-Paciente360-Event-Id` + `X-Paciente360-Correlation-Id`.
   - `UrlGuard::assertSafeOutboundUrl()` bloqueia RFC 1918 (10/8, 172.16/12, 192.168/16), loopback (127/8, ::1), link-local (169.254/16), CGN (100.64/10), `.local/.internal/.test/.invalid`, HTTP em produção (permite em local/test para Stripe simulator).
   - **NÃO faz DNS resolution** — defesa em profundidade adicional fica no Guzzle client (verify TLS + protocols estritos).

4. **Retry policy Q16: 30s, 2min, 10min, 1h, 6h (5 tentativas) → DLQ**
   - `DispatchWebhookJob::tries = 1` (controle manual via `next_attempt_at`, NÃO via Laravel automatic retries).
   - Após esgotar → `MoveToDeadLetterJob` move para `webhook_dead_letter` com `expires_at=now()+30d`.
   - DB UNIQUE `(webhook_endpoint_id, event_id)` em `webhook_deliveries` enforça idempotência.
   - Retention DLQ via cron `integrations:purge-expired-dlq` (daily 03:00 BRT).

5. **API Pública resolve tenant pelo TOKEN, NUNCA por URL/header**
   - Trait `ResolvesApiPublicTenant` em `app/Http/Controllers/Api/V1/Public/Concerns/` — `tenantId(Request)` lê de `$user->tenant_id` (Sanctum) ou `oauth.tenant_id` (OauthAuthenticator attribute).
   - Defesa contra cross-tenant attacks (Princípio II).
   - Recursos fora do escopo Q14 retornam **404** (não 401 — não revela existência) — `PublicApiScopeRestrictionTest` valida.

6. **Controladas (Portaria 344/98) SEMPRE mascaradas via API pública e webhooks**
   - `PrescriptionPublicResource`: se `type='controlled'`, omite `items` e `notes`, adiciona `masked=true` e nota explicativa.
   - `WebhookDispatcher::applyMasking()`: idem para `event_type` que começa com `prescricao.`.
   - **Defesa em profundidade** — não confia em scope do token: independente de `prescriptions.read_controlled`, o resource mascara.
   - `R-8-4` gate: `PublicApiControlledMaskingTest`.

7. **Idempotency-Key NFR-9 (24h dedup)**
   - POST `/api/public/v1/patients` e `/appointments` aceitam header `Idempotency-Key`.
   - Cache key: `api_public:idempotency:{tenant_id}:{resource}:store:{key}` TTL 24h.
   - Replay retorna 201 + header `Idempotency-Replayed: true` com mesmo body original.

8. **OAuth 2.0 Client Credentials gated por `finalization.oauth_enabled` (Q18)**
   - Default `false` — `OauthClientService::createClient()` lança `RuntimeException('oauth_disabled')`.
   - Quando habilitado, `tenant_oauth_clients` table armazena `client_secret_hash` (SHA-256). Plaintext retornado APENAS no create.
   - **Stub JWT-like** em formato `stub.<base64-payload>.stub` — produção exige Passport real (composer require lazy).
   - `OauthAuthenticator` middleware decodifica payload + injeta `oauth.tenant_id` no request attributes.

9. **Rate limit por token + cap IP em `ApiPublicRateLimiter`**
   - Token: `plan.api_rate_limit_per_minute` (default 60, varia por plano via PlanVersion snapshot).
   - IP: `finalization.api_public_ip_hard_cap_per_minute` (default 10000) — cap anti-DDoS global.
   - Headers RFC 6585: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` em 429.

10. **`tenant_suspended` retorna 503 na API pública (não 403)**
    - `EnsureApiPublicTenantNotSuspended` middleware ≠ `EnsureTenantNotSuspended` interno (que devolve 403).
    - 503 sinaliza ao integrador que o problema é temporário do tenant, não da API — ele pode retentar quando regularizado.
    - Resolve tenant via: `app('tenant')` → `$user->tenant` → `oauth.tenant_id` attribute.

11. **Pseudonimização dual layer para IA (Q29)**
    - **Layer 1 (design-time)**: marker interface `App\Support\Lgpd\ContainsNoClinicalData` — todos eventos consumidos pela IA devem implementar. CI gate `EventsForAiPseudonymizationTest` valida via reflection.
    - **Layer 2 (runtime)**: `PseudonymizationAuditor` audita semanalmente prompts via cron `privacy:audit-pseudonymization`. Resultado em `pseudonymization_audits` table.
    - `PiiScrubber` (T291) aplicado ao Sentry `before_send` global — mascarara CPF/email/telefone/RG/SUS em mensagens de exceção e breadcrumbs.

12. **Q26 — Mapa de anonimização explícito**
    - Cada coluna `anonymizable` está documentada em `docs/lgpd/privacy-operations.md` § 2.
    - `ForgettingExecutor` aplica updates atômicos em uma transação.
    - **Preservadas por obrigação legal**: receitas `controlled` (Portaria 344/98 — 5y), `audit_logs` (Princípio VII — 5y), `appointments.starts_at/ends_at` (registro contábil).
    - Gate test `MapaAnonimizacaoTest` valida que cada coluna marcada foi realmente zerada/redigida.

13. **Super Admin Impersonate — Gate 7 (audit obrigatório)**
    - Banner sticky `ImpersonateBanner.vue` em `<App>.vue` polling 60s para validar sessão ativa.
    - Cada tela visitada durante impersonate gera `super_admin.screen.visited` audit_log via `ImpersonateScreenAuditTrigger` middleware.
    - Sessão sem ≥1 audit é flagged como anomalia (provavelmente bot scrape).
    - 4 tipos de anomalias monitoradas: `mass_data_export`, `unusual_impersonate`, `controlled_prescription_scan`, `webhook_delivery_failure_spike`.

14. **Estratégia Q9: agregações ≥24h, queries live ≤24h (Relatórios)**
    - `ExecutiveDashboardService::shouldUseAggregations()` decide por janela.
    - `metric_aggregations` table com PARTIAL UNIQUE composto `(tenant_id, metric_name, period, period_start, COALESCE(dimensions, '{}'::jsonb))` para upsert idempotente.
    - Cron `reports:aggregate-hourly` (hourlyAt :05) chama `MetricAggregator::aggregateDailyForTenant()` para 8 métricas (leads_by_channel, conversion_rate, no_show_rate, estimated_revenue, response_time_first_p95, ai_autonomous_resolution_rate, occupancy_by_professional, top_procedure_types).
    - `aggregation_lag_seconds` no envelope > 7200 → banner stale na SPA (R-8-5).

15. **Q13: Escopo por perfil em Relatório Clínico**
    - `ClinicalReportService::professionalScopeFor(User)` retorna `$user->id` se `hasRole('medico') AND ! hasRole('admin-clinica')`, caso contrário `null`.
    - Médico vê apenas própria agenda; Admin Clínica vê tenant inteiro.
    - Resource expõe `scoped_professional_id` no envelope para o front renderizar título correto.

16. **`finalization.php` config como single source of truth**
    - Lote D: `oauth_enabled`, `webhook_max_retries`, `webhook_dlq_retention_days`, `webhook_retry_backoff_seconds`, `webhook_http_timeout_seconds`, `api_public_ip_hard_cap_per_minute`, `webhook_max_endpoints_default`.
    - Lote E: `report_aggregation_threshold_hours`, `report_max_window_months`.
    - Overrides via env (FINALIZATION_*) — defaults conservadores.

17. **DEFERRED ao final da Fase 8** (Phase 8 Polish documentado em `docs/qa/*` e `docs/lgpd/*`)
    - **Constitution Gates execução real (T287)**: testes codificados, requer Sail rodando → `docs/qa/gates-fase8-final.md`.
    - **Suite full execution (T288)**: validar ~1517 tests verdes → requer Sail.
    - **OpenAPI Scribe (T292)**: `scribe:generate` requer Sail + tags @apiResource manuais nos 6 public controllers.
    - **Sentry tags validation (T290)**: tags codificadas em `configureSentryScope()` — validação visual no painel Sentry.
    - **Smoke staging E2E (T296-T297)**: 10 cenários documentados em `docs/qa/smoke-fase8-staging.md`.
    - **DPO approval formal (T298)**: template em `docs/lgpd/dpo-approval-fase8.md` aguardando revisão jurídica.
    - **Passport instalação concreta**: gated por `FINALIZATION_OAUTH_ENABLED=true` em produção enterprise.
    - **InboxTask real** (herdado da Fase 7): `EnqueueInboxTaskOnAiRenewal` ainda usa `Log::warning` — aguardando `ConversationService::createForPatient()`.
    - **S3 real delete** (herdado): stub `Log::info` em jobs de purga.

## App Shell (Fase 9) — Key Patterns

When working on `/panel/*` routes post-Fase 9, remember:

1. **Rota pai `/panel` com nested children renderiza `AppShell` uma única vez**
   - `routes/index.js`: `/panel` tem `component: AppShell` e `children: panelChildren` (38 rotas).
   - Sidebar/Topbar montam apenas no primeiro acesso ao painel; navegação interna só troca `<router-view>`.
   - **Não declarar `/panel/*` como rota raiz** — quebra o reuso do chrome e força remount.
   - `/panel/onboarding` é IRMÃ (não filha) — fullscreen sem chrome por design.

2. **Navegação por permissões: única source of truth em `config/navigation.js`**
   - Árvore canônica estática (10 grupos + items). Cada entry com `routeName` + `ability` (ou `anyOf`).
   - `useNavigation()` faz filter em runtime contra `auth.permissions`. Grupos com 0 children visíveis somem inteiros.
   - **Para adicionar item novo na sidebar**: 1) entry em `navigation.js` com `routeName` + `ability`; 2) i18n key em `pt-BR.json` (`layout.sidebar.*`); 3) `meta.title` na rota filha em `router/index.js`.

3. **Preferências de UI: `localStorage` escopado por `tenant_slug + user_id`**
   - Chave única: `app-shell:preferences:v1` com JSON aninhado `{ [tenantSlug]: { [userId]: { sidebarMode, expandedGroups } } }`.
   - **Gate Princípio II**: NUNCA usar chave plana — multi-tenant cross-leak. `useShellPreferences` lê de `auth.tenant.slug + auth.user.id` reativamente.
   - Fallback robusto: localStorage indisponível ou JSON corrompido → defaults silenciosos. Operações nunca lançam.

4. **Breakpoints reativos via `useBreakpoint`**
   - 3 refs: `isMobile` (< 768px), `isTablet` (768–1023px), `isDesktop` (≥ 1024px).
   - Implementação via `useMediaQuery` do `@vueuse/core` (já dep).
   - AppShell tem watcher `isMobile` que fecha drawer ao cruzar para desktop (FR-022).

5. **Drawer mobile: `<Teleport to="body">` + focus trap próprio + Esc/click-outside**
   - `MobileDrawer.vue` reusa `Sidebar mode="expanded"` internamente (DRY).
   - `useShellFocusTrap` — implementação manual ~80 linhas; alternativa para `@vueuse/integrations` que requer 2 deps a mais.
   - Q1 clarification: drawer fecha **imediatamente** ao clicar item; navegação ocorre em paralelo.

6. **Document.title via `router.afterEach` + fallback estático**
   - Lê `to.meta.title` (i18n key, string literal, ou função); formata `{tenantName} — {pageTitle}`.
   - Fallback: `findLabelKeyForRoute(name)` faz lookup estático em `NAVIGATION` (sem usar `useNavigation()` fora de setup context).
   - Topbar exibe o mesmo título contextual entre tenant name e ícones à direita.

7. **UserMenu: logout fail-safe via `auth.logout() → router.push('auth.login')`**
   - Em erro de rede, ainda chama `auth.reset()` e redireciona — token Bearer pode estar inválido de qualquer forma (princípio VII).
   - Dropdown em `<Teleport to="body">` para escapar overflow/transform do parent.

8. **Heroicons SVG inline em componente único `HeroIcon.vue`**
   - Switch por `name` prop — 15 ícones (~15 KB bundle). Sem dep nova de pacote (research R10).
   - Adicionar ícone novo = adicionar bloco `v-else-if="name === 'foo'"` no componente.

9. **i18n: `pt-BR.json` (SPA) ≠ `lang/pt_BR/*.php` (backend)**
   - SPA usa JSON único em `resources/js/i18n/pt-BR.json`.
   - Bloco `layout.*` adicionado com `sidebar`, `topbar`, `user_menu`, `drawer`, `empty_state`, `panel_home`.
   - Backend `lang/pt_BR/layout.php` espelha apenas para mensagens de response (não usado pelo Vue I18n).

10. **Empty state quando user não tem nenhuma permission de módulo**
    - `useNavigation().isEmpty` true → AppShell substitui `<router-view>` por mensagem + botão "Sair".
    - Sidebar e topbar continuam visíveis com chrome mínimo (tenant name + user menu).

11. **DEFERRED ao final da Fase 9**
    - **Testes E2E Playwright** (T011/T020/T024/T027/T040): especificados no `quickstart.md § Lote E` — requer Sail + browser headless. Cenários documentados; pendentes de implementação concreta.
    - **Audit a11y** (T041): roda manualmente via Chrome DevTools Lighthouse; meta SC-007 = 0 violations sérias/críticas.
    - **Suite full PHP** (T047): rodar `vendor/bin/sail artisan test --compact` para confirmar zero regressão pós router refactor.
    - **Smoke checklist** (T044): validar 6 maiores rotas (Agenda, Pacientes, Inbox, Receituários, Campanhas, Relatórios Executivo) sem regressão visual.
    - **`pacientes.show` route precedence**: ordem das rotas dinâmicas (`:id`) vs estáticas (`/novo`, `/mesclagem`, `/funil`, `/importar`) ajustada para evitar shadow — verificar manualmente.

## Dashboard Home (Fase 10) — Key Patterns

When working on the Dashboard Home (`/panel`) features post-Fase 10, remember:

1. **Endpoint único consolidado em `GET /api/v1/panel/home?scope=user|clinic`**
   - Retorna 4 seções (kpis + upcoming_appointments + attention_items + recent_activity) em UMA response — atende SC-008 (1 request por carga).
   - Cache Redis 30s escopado por `panel_home:{tenant_id}:{user_id}:{scope}` (R2/R4).
   - **NÃO criar endpoints separados por seção** — viola scope-of-1 e fragmenta cache.

2. **4 collectors com degradação graceful em `PanelHomeService::safeRun()`**
   - `KpiCollector`, `UpcomingAppointmentsCollector`, `AttentionItemsCollector`, `RecentActivityCollector`.
   - Falha em 1 collector → `section = null + error=true`, demais seções permanecem normais (R13). Sentry tag `panel_home.section_failed=<section>` + métrica `panel_home_section_failures_total`.
   - **Nunca lançar 500 por falha parcial** — usuário perde valor de todas as outras seções.

3. **`PanelHomePolicy` é o ÚNICO ponto de auth dentro do dashboard**
   - `canSeeClinicScope`: força `scope_applied='user'` se user não tem `admin-clinica` (Q1 da clarification — sem 403, downgrade silencioso).
   - `canSeeWebhookDlqAlerts`: filtra alertas `webhook_dlq` da lista.
   - `canSeeConfirmationAlerts`: filtra alertas `confirmation_pending`.
   - **Defesa em profundidade**: gates dentro do payload (não no middleware) para retornar 200 com lista filtrada.

4. **Q1 — "Minha visão" para admin+medico escopa como profissional**
   - `scope_applied='user'` SEMPRE filtra por `professional_id = Professional.where(user_id=current).pluck('id')` em appointments e pacientes; por `professional_id = current.user_id` em prescriptions (modelo divergente — Prescription.professional_id → User direto).
   - Toggle continua disponível para alternar para "clinic".

5. **Q3 — Alerta `paciente_funil_stale` filtra por `funilColuna.is_terminal=false`**
   - Implementação simplificou Q3: usa o flag `is_terminal` da `FunilColuna` (model) ao invés de hardcoded slugs.
   - Estágios terminais (`agendado`, `concluído`, `perdido`) ficam fora automaticamente.
   - Config `panel.funil_alert_stages` mantida para uso futuro como restrição adicional opcional.

6. **`AttentionItemDto` heterogêneo com `severityRank()` para sort determinístico**
   - 5 tipos: `conversation_escalated`, `prescription_expiring`, `paciente_funil_stale`, `confirmation_pending`, `webhook_dlq`.
   - Severity ranking: `danger=3 > warn=2 > info=1`. Ordenação: severity DESC → occurredAt DESC.
   - Sort no PHP (Collection::sortBy com 2 callbacks) — não em DB porque a coleção é heterogênea.

7. **Humanizer da timeline com allow-list de event types (LGPD)**
   - `App\Support\AuditLog\Humanizer::humanize($event): { description, link }`.
   - Allow-list em `config/panel.recent_activity_allowlist` — 14 event types curados.
   - **Nunca incluir CPF/email/telefone/conteúdo clínico nas descrições** — gate G6 obrigatório.
   - `paciente.viewed` (visualização de prontuário) NÃO entra na allow-list por design.

8. **Frontend: `usePanelHome` é a única source of truth da página**
   - Encapsula: fetch, loading, error, scope (via `usePanelHomeScope`), refresh manual e auto-refresh.
   - Cancela request anterior via `AbortController` quando scope muda mid-flight (evita dados misturados).
   - Reconcilia scope local quando backend faz downgrade (`data.scope_applied !== local scope` → `setScope(applied)`).

9. **`useAutoRefresh` com Page Visibility API + trigger no return-to-focus**
   - `setInterval` rodando só quando `visibilityState='visible'`.
   - Pausa automática em background (SC-009: 0 requests com aba oculta).
   - Retorno ao foco após mais de `intervalMs/2` em background → refresh imediato.

10. **localStorage `panel_home:scope:v1` separado do `app-shell:preferences:v1`**
    - Aninhado por `tenant_slug → user_id`. Princípio II: chave escopada.
    - **NÃO compartilhar chave com app-shell** — schemas independentes evita acoplamento.
    - Default: `'user'`. Fallback se localStorage indisponível: memória volátil.

11. **DEFERRED ao final da Fase 10** (documentado em `specs/010-dashboard-home/DEFERRED.md`)
    - **11 arquivos de teste** (T013–T017, T023–T025, T030–T033, T039–T041) — gates G1–G10 codificados no contract mas Feature/Unit tests não criados nesta sessão; cenários documentados em quickstart.md
    - **Audit a11y Lighthouse/axe** (T065): manual via Chrome DevTools
    - **E2E Playwright** (T062): jornada US-1+US-2+US-3 deferred
    - **Suite full validation** (T068, T069): `vendor/bin/sail artisan test --compact` 1300+ tests

## Dashboard Executivo (Fase 11) — Key Patterns

When working on Executive Dashboard features post-Fase 11, remember:

1. **Backend Fase 8 95% reusado — apenas 1 linha de mudança**
   - `ExecutiveDashboardController::resolvePeriod()` recebeu case `'24h' => $end->copy()->subHours(24)`.
   - Endpoints + Pinia store + ExecutiveDashboardService permanecem intactos. Gate G8 valida que `reportsStore.js` não é modificado pelo spec 011.

2. **`useExecutiveDashboard` é o ÚNICO consumer recomendado do store**
   - Wrapper sobre `reportsStore` que combina state + window persistente + auto-refresh on window change + abort handling implícito (via store).
   - Page consome o composable; componentes consomem props.
   - **NÃO chamar `useReportsStore().loadExecutive()` direto da page** — sempre via composable para garantir window sync.

3. **localStorage `executive_dashboard:window:v1` — chave SEPARADA**
   - 3 chaves de localStorage hoje: `app-shell:preferences:v1` (spec 009), `panel_home:scope:v1` (spec 010), `executive_dashboard:window:v1` (spec 011).
   - Schemas independentes deliberadamente (R11 do spec 010 / R4 desta spec) para evitar acoplamento e versionar separadamente.
   - Validação `sanitize(value)` no composable força default `'7d'` se localStorage trouxer valor fora do enum `{24h, 7d, 30d, 90d}`.

4. **Polaridade invertida explícita em `KpiCardWithSparkline.vue`**
   - Prop `inversePolarity: boolean` (default false).
   - Métricas com polaridade invertida (menos é melhor): `no_show_rate`, `response_time_first_p95`. Aumentar é vermelho; diminuir é verde.
   - Definição no consumer (page) — `inversePolarityMetrics` Set.
   - Comunicação visual sempre acompanhada de ícone (↑/↓) + texto explícito além da cor (FR-039 a11y).

5. **`Sparkline.vue` stub funcional — preparado para futuro**
   - Aceita `points: number[]`; renderiza `null` se vazio.
   - Quando backend implementar `/reports/executive/series?metric=...&window=...`, basta o consumer passar `sparklinePoints` real.
   - **Backend atual NÃO retorna time-series por métrica** — R2 do research; FR-012/FR-017 DEFERRED.

6. **`PeriodFilter.vue` com `role="tablist"` + keyboard nav**
   - Setas Left/Right deslocam entre os 4 tabs em loop circular.
   - Home/End para primeiro/último.
   - `aria-selected` e `tabindex` reativos.

7. **`StaleDataBanner.vue` — visibilidade condicional**
   - Aparece SOMENTE quando `lagSeconds > 7200` AND `window !== '24h'` (FR-008 — janela 24h é live data, banner não se aplica).
   - Timestamp relativo via Luxon (`pt-BR` locale).

8. **`ExportMenu.vue` — PDF ativo, CSV deferred**
   - Item PDF emite `@export-pdf`; page chama `useExecutiveDashboard.exportPdf()` que delega ao store (Blob download).
   - Item CSV sempre `aria-disabled="true"` com label "em breve" — placeholder consciente (FR-028).
   - Spinner via prop `loading` durante export (`exporting` do composable).

9. **Re-uso de patterns de `KpiCardWithTrend.vue` (Fase 8) preservado**
   - `KpiCardWithSparkline.vue` é VARIANT do existente — não substitui.
   - Drill-down futuro pode continuar usando `KpiCardWithTrend` em outros contextos.

10. **DEFERRED ao final da Fase 11** (`specs/011-dashboard-executivo/DEFERRED.md`)
    - **Sparkline real** (FR-012, FR-017): depende de extensão backend retornando time-series por métrica.
    - **CSV export**: backend endpoint não existe; UI mostra placeholder "em breve".
    - **Drill-down detalhado** dentro do dashboard: rota dedicada.
    - **Comparativo arbitrário** entre 2 períodos custom.
    - **Auto-refresh**: intencionalmente desligado (dashboard analítico).
    - **Filtros adicionais** por profissional/tipo: escopo OperationalReport.
    - **E2E Playwright completo** (T018): cenários documentados em quickstart.
    - **A11y audit Lighthouse** (T019): manual via Chrome DevTools.
