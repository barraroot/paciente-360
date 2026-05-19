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

- **Active feature**: nenhuma — aguardando próxima fase.
- **Previous features delivered (7)**:
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
