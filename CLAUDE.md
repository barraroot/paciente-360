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

- **Active feature**: `014-channel-provider-integration` — [plan](specs/014-channel-provider-integration/plan.md) — Integração de Canal WhatsApp: Twilio (oficial) ou Evolution API v2 (não oficial). Spec (4 US, 21 FRs, 7 SC, checklist 16/16 PASS) + 3 clarifications (Session 2026-05-25: via não oficial bloqueia proativos fora da janela 24h → pendente manual; um provedor ativo por clínica por vez; paridade completa inbound+outbound) + plan + research (R1–R10) + data-model (coluna `provider` em `messaging_channels` + UNIQUE parcial "um WhatsApp ativo por tenant") + contracts (8 gates G1–G8) + quickstart (8 lotes A–H). **Constitution Check PASS 7/7 sem amendment** (Princípio VI: via oficial inalterada; não oficial reusa o gate da Fase 13 — sem `ChannelTemplate` aprovado, proativo fora da janela cai em `pending_manual`). Evolution **auto-hospedado** (Docker, env-config), uma instância por canal de tenant, conexão por QR Code; novo `EvolutionApiAdapter` + `ChannelAdapterResolver` provider-aware + `EvolutionWebhookController`; tela Vue de config. Próximo: `/speckit-tasks`.
- **Previous features delivered (12)** — sumário enxuto; detalhe técnico de cada fase vive nos blocos "Key Patterns" abaixo e em `specs/<feature>/`:
  - `012-professionals-management` — Gestão de Profissionais + Unlock Onboarding Step 2. DELIVERED 2026-05-24 (smoke browser + a11y 0 violations). Pós-merge: fixes de navegação (drift de abilities nav/router vs catálogo), `/me` voltou a enviar permissions (AuthenticatedUserResource), Horizon staging, baseline de testes (phpunit.xml env).
  - `011-dashboard-executivo` — Dashboard Executivo (US-10.1), merge main 2026-05-23. Backend Fase 8 reusado (1 linha). Sparkline/CSV DEFERRED.
  - `010-dashboard-home` — Dashboard Home (US-1.5). Endpoint único `GET /api/v1/panel/home` (4 seções, cache Redis 30s, collectors graceful).
  - `009-app-shell` — App Shell `/panel` (sidebar/topbar/drawer, navegação por permissões, router nested children).
  - `008-finalizacao-mvp` — Fase 8: Privacidade LGPD + Super Admin + Campanhas + Webhooks/API Pública (HMAC/SSRF) + Relatórios. 22 migrations, 41 eventos, 8 cron.
  - `007-gestao-receituario` — Receituários (Épico 8): cadastro/mascaramento controladas, alertas D-15/7/1, renovação IA, relatório. 6 gates constitucionais.
  - `006-agenda-ux-polish` — Polimento UX da Agenda (modal a11y, popover inline, formatação pt-BR).
  - `005-agendamento-consultas` — Agenda completa (Épico 6): tipos, drag-and-drop, confirmação automática, lista de espera FIFO, sync Google Calendar. Outlook DEFERRED.
  - `004-token-auth-migration` — Cookie→Bearer Sanctum. CSP/CORS env-driven. Constitution amendment v1.4.0.
  - `003-omnichannel-inbox` — Inbox omnichannel (Fase 3). 7/7 user stories.
  - `002-crm-pacientes` — CRM Pacientes (Fase 2).
  - `001-fundacao-multitenant` — Fundação multi-tenant (Fase 1).
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

## Gestão de Profissionais (Fase 12) — Key Patterns

When working on Professional features post-Fase 12, remember:

1. **`Gate::define('professional.manage', ...)` (ability-based) — NÃO usar policy de model**
   - Conflito: `Gate::policy(Professional::class, ProfessionalSchedulePolicy::class)` JÁ existe da Fase 5 (escopo schedule).
   - Solução: gate ability-based em `AppServiceProvider::registerPolicies()`.
   - Controllers chamam `Gate::authorize('professional.manage')` (não `('manage', Professional::class)`).
   - **CRÍTICO — recursão/SIGSEGV**: a closure DEVE usar `$user->hasPermissionTo('professional.manage')`, NUNCA `$user->can(...)`. O `Gate::before` do Spatie retorna `null` em negação → `can()` volta à própria ability → recursão infinita → stack overflow → segfault em todo path negado. Aplicado também a `report.view`/`report.export` (mesmo bug latente da Fase 8, corrigido na 012).

2. **Middleware stack obrigatório: `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`**
   - `tenant.slug` é CRÍTICO — sem ele, `TenantResolved` não dispara, Spatie team_id fica null, e gate sempre retorna false (mesmo com permission atribuída).
   - Pattern já usado em todas as rotas autenticadas; spec 012 inicialmente esqueceu e gerou 403 generalizado.

3. **UNIQUE composto PARCIAL `WHERE deleted_at IS NULL`**
   - `(tenant_id, council_type, council_number, council_state) WHERE deleted_at IS NULL`
   - Permite reuso do número após soft-delete (médico saiu e voltou anos depois; correção de cadastro errado).
   - **NÃO inclui `council_type_other`** — quando type=OUTRO, colisão entre dois "Outros" diferentes é altamente improvável; admin lida caso a caso.

4. **Fluxo de convite via `pending_invitation_email` column + listener**
   - Coluna nova `professionals.pending_invitation_email` (indexed por `tenant_id` para lookup rápido).
   - `Invitation` model NÃO tem `payload` JSON — usar lookup por email é o canal de junção.
   - Listener `ActivatePendingProfessionalOnInvitationAccepted` consome `InvitationAccepted` (Fase 4): busca Professional pendente match pelo email + tenant; vincula `user_id`, ativa, limpa `pending_invitation_email`.
   - Auto-discovery Laravel 11+ — não registrar manualmente em EventServiceProvider.

5. **Q2 — Endpoint dedicado `/professionals/check-email`**
   - Retorna `{exists_in_current_tenant, existing_user: {id, name}, exists_in_other_tenant}`.
   - **NUNCA retorna o email do user existente** (Princípio I — minimização PII).
   - Frontend usa onblur do campo email pra preview; backend trata 409 ao POST sem `confirmed_existing_user=true`.

6. **`ProfessionalInvitationService::createWithInvite()` retorna `JsonResponse` direto**
   - Por que: precisa retornar 409 com payload custom (Q2) em alguns caminhos sem precisar lançar exceção.
   - Controller delega: `if (! $data['user_id']) return $this->inviteService->createWithInvite(...)`.

7. **`ProfessionalResource` NÃO inclui email do user vinculado**
   - Disponível em `/users/{id}` se precisar — repetir aqui amplia surface PII sem ganho.
   - Apenas `{id, name}` do user (via `whenLoaded('user')`).

8. **Update PROIBIDO em `user_id` e `is_active`** (FR-010)
   - `UpdateProfessionalRequest` usa rule `prohibited` em ambos.
   - `is_active` muda apenas via `POST /activate` ou `DELETE` — endpoints dedicados com side effects (reatribuição de pacientes).

9. **Reatribuição automática em desativação reusa Fase 2 + Fase 5**
   - Observer `Professional.boot()` (Fase 5) dispara `ProfessionalDeactivated` quando `is_active: true → false`.
   - Listener (Fase 2) enfileira `ReassignOrphansJob` → atualiza `paciente.profissional_responsavel_id = NULL`.
   - Spec 012 NÃO implementa novamente — apenas garante que `Service::deactivate()` muta o campo corretamente.

10. **Onboarding `unlockStep` + triggers — pattern aditivo**
    - `OnboardingService::unlockStep($tenant, $stepKey)` muta status `locked → pending` (idempotente).
    - Triggers em `completeStep`: `clinic_data → first_professional`; `first_professional → schedule_setup`.
    - Skip de `first_professional` NÃO unlocka `schedule_setup` (faz sentido — sem profissional, não há agenda para configurar).
    - Steps 3 (`channel_connection`) e 5 (`ai_knowledge_base`) permanecem locked até specs futuras.

11. **Confirmações destrutivas/sensíveis via modal a11y — NUNCA `window.confirm()`**
    - `DeactivateConfirmModal.vue` (desativação — FR-015/FR-032) e `EmailAlreadyUserModal.vue` (Q2 confirmação de email já-é-user — FR-005a) substituíram os `window.confirm()` que existiam em `ProfessionalsListPage.vue` e `ProfessionalFormModal.vue`.
    - Padrão: `role="alertdialog"` + `aria-modal` + `aria-labelledby`/`aria-describedby` + `useShellFocusTrap(modalEl, openRef)` + Esc/overlay fecham + foco retorna ao trigger. Mesmo padrão do `ProfessionalFormModal`.
    - `EmailAlreadyUserModal` fica aninhado dentro do `ProfessionalFormModal` (z-[60] > z-50) mas Teleporta para body; os dois focus-traps NÃO brigam porque o trap do form vira no-op quando o `document.activeElement` não está na sua lista de focáveis.
    - Reforça a regra global da Fase 6: proibido `confirm()`/`prompt()`/`alert()` nativos em componente novo.

12. **DEFERRED ao final da Fase 12** (commit `d2a3d99` fechou os gaps de teste + UX)
    - ~~9 Feature tests + 1 Unit (G1–G9)~~ **FEITO**: 7 arquivos escritos nesta sessão (os 4 que já existiam + CRUD/Deactivation/EmailAlreadyUser/Especialidades/OnboardingUnlock/unit). Suíte full 1577/1572/0 failures.
    - ~~Onboarding wizard step 2 UI inline~~ **FEITO**: `ProfessionalFormModal` embarcado no `OnboardingWizardPage` (step `first_professional`).
    - Audit a11y Lighthouse/axe na página + modais (manual)
    - Smoke browser real nas 3 personas
    - Backfill onboarding (`onboarding:backfill-unlocks` command) para tenants existentes
    - Constitution Re-Check formal + `.specify/feature.json` → DELIVERED

## Notificações Outbound (Fase 13) — Key Patterns

When working on outbound notification delivery post-Fase 13, remember:

1. **`OutboundNotificationDispatcher` é o ÚNICO ponto que chama `MessageDispatchService::send`**
   - Listeners (Agenda/Prescription) ficam finos: montam um `NotificationRequest` (DTO imutável em `app/Domain/Messaging/Notification/DataTransfer/`) e chamam `dispatch()`.
   - Ordem determinística R5 (curto-circuita no 1º bloqueio): **opt_out → debounce 4h → idempotência → resolver canal → janela/template → envio**.
   - **NUNCA lança** ao listener — toda falha vira `OutboundNotification` terminal (`pending_manual`/`skipped`). Garante SC-003 (nada "some").

2. **Gate de aprovação Princípio VI = consulta runtime ao `ChannelTemplate` (decisão D1)**
   - `NotificationTemplateResolver::resolve(tenant, type, Channel)` só retorna o `NotificationTemplate` se existir um `ChannelTemplate` (`messaging_channel_templates`) com `meta_template_status='approved'` para `(channel, provider_template_id)`.
   - Sem template aprovado fora da janela → `pending_manual/no_template`. Isso satisfaz LITERALMENTE "o dispatcher MUST consultar status do template antes do disparo" sem denormalizar o status (evita drift). `notification_templates` é o catálogo por tenant (tipo→provider_template_id); `ChannelTemplate` é a fonte de verdade da aprovação.

3. **Resolução de canal é WhatsApp-only para proativo (R1)**
   - `OutboundChannelResolver`: `Channel` WhatsApp `status='ativo'` do tenant + `paciente.telefone_primario_normalizado` como `external_thread_id`. Sem WhatsApp ativo OU sem telefone → `null` → `pending_manual/no_channel`.
   - `withinWindow` = existe `Conversation` com `last_inbound_message_at` < 24h → texto livre permitido (`freeFormBody`); fora da janela exige template.
   - `ConversationService::findOrCreateForPatientChannel()` abre conversa para envio proativo SEM marcar sinais de inbound (`last_inbound_message_at`/`received_outside_hours`).

4. **Fallback `pending_manual` = mensagem de sistema na conversa + `priority='alta'` (R10, clarify Q1)**
   - Não há modelo de tarefa novo. `routeToManual()` posta `Message` `sender_type='system'` descrevendo motivo+contexto (sem PII clínica) e eleva `Conversation.priority='alta'` (NÃO há coluna de tag — sinalização é só priority, decisão U1).
   - Para `no_channel` (sem canal ativo), usa a conversa existente do paciente se houver; senão o próprio `OutboundNotification` é o artefato rastreável.

5. **Reconciliação de status via `MessageObserver::updated()` (R7/U2)**
   - O `TwilioStatusCallbackController` faz `Message->update(['status'=>...])`. O `MessageObserver::updated()` detecta `wasChanged('status')` para `delivered`/`failed`, acha a `OutboundNotification` por `message_id` e chama `dispatcher->reconcileDelivered()`/`reconcileFailed()`.
   - `delivered` → `sent→delivered` + latência. `failed` (definitivo) → `failed→pending_manual/send_failed`. **"lido" NÃO é rastreado.**

6. **Idempotência dual-layer + debounce por (paciente, tipo)**
   - UNIQUE parcial `(tenant, patient, type, milestone, created_at::date) WHERE status <> 'skipped'` em `outbound_notifications` + `Message.idempotency_key` (`notif:{tenant}:{type}:{patient}:{milestone}:{date}`).
   - Debounce 4h: Redis `messaging_debounce:notification:{type}:{patient}` NX. Marcos distintos (`t_minus_24h` vs `t_minus_2h`) só não colidem na idempotência; o debounce é por tipo (em produção os marcos estão a >4h). Testes isolam o gate de idempotência via `Redis::flushdb()` entre dispatches.

7. **Eventos auditáveis sem PII clínica**
   - `NotificacaoEnviada` / `NotificacaoSuprimida` / `NotificacaoRoteadaParaManual` implementam `Auditable` + `ContainsNoClinicalData`; payload = `notification_id, patient_id, type, milestone, reason`. Persistidos em `audit_logs` via `PersistAuditLogListener`.

8. **Listener de prescrição é ADITIVO (não quebra a Fase 7)**
   - `DispatchPrescriptionAlertViaMessaging` mantém sua máquina de estados do `PrescriptionAlert` (opt-out/debounce/canal/template-por-nome/status) e, no passo de sucesso, chama o dispatcher para entrega REAL + rastreio. Os ~8 testes existentes de alerta continuam verdes.
   - `EnqueueInboxTaskOnAiRenewal` agora despacha `ai_renewal_task` (substituiu o stub `Log::info`; teste atualizado para asserir a `OutboundNotification`).

9. **US5 — CRUD de `notification-templates` permission-gated por `channel.connect`**
   - `GET/POST/PUT/DELETE /api/v1/notification-templates` (middleware `auth:sanctum`+`tenant.slug`+`tenant.not-suspended`). `StoreNotificationTemplateRequest` valida a allow-list não-clínica de `variables_map` (`patient_name, appointment_datetime, professional_name, clinic_name, days_until_expiry, offer_expires_at`).
   - Cross-tenant → 404 (route model binding via global scope). `notification_type`/`channel_type` são `prohibited` no update (imutáveis).

10. **DEFERRED ao final da Fase 13**
    - UI Vue `NotificationTemplatesPage.vue` + store (T039) — backend + seed operam; provisionável por seed.
    - Seeder de templates default (T040).
    - E2E Playwright da confirmação (D2 — desvio consciente do Princípio IV, padrão das fases anteriores) + smoke staging (T045).
    - Instagram-within-window free-text: resolver foca WhatsApp; Instagram dentro da janela é caminho futuro (nenhum AC depende dele).

## Integração de Canal — Twilio | Evolution (Fase 14) — Key Patterns

When working on channel/provider features post-Fase 14, remember:

1. **Dimensão `provider` no `Channel` (não no `type`)**
   - `messaging_channels.provider` (`twilio`|`evolution`, default `twilio`); `type` continua `whatsapp` para ambos. Enum `App\Domain\Messaging\Channel\Enums\ChannelProvider`.
   - WhatsApp oficial = Twilio; não oficial = Evolution API v2 (Baileys, QR Code). Migração aditiva/retrocompatível.

2. **`ChannelAdapterResolver::for(Channel)` é o ponto ÚNICO de seleção de adapter**
   - Resolve por `(type, provider)`: whatsapp+twilio→`WhatsAppCloudAdapter`, whatsapp+evolution→`EvolutionApiAdapter`, instagram→`InstagramGraphAdapter`, web→`WebWidgetAdapter`.
   - `SendOutboundMessageJob` e `ProcessInboundMessageJob` usam o resolver (substituiu o `match($type)` hardcoded). Adicionar provedor = adicionar caso no resolver, nada mais.

3. **`EvolutionApiAdapter implements ChannelAdapter, SupportsQrConnection`**
   - `SupportsQrConnection` (createInstance/getQrCode/connectionState/disconnect) é o contrato extra para provedores com pareamento por sessão. Twilio NÃO implementa.
   - HTTP via Laravel `Http` (fakeável com `Http::fake()`). `send` suporta texto + mídia (`/message/sendText` e `/message/sendMedia`). `parseInboundWebhook` mapeia `messages.upsert`.

4. **Evolution é AUTO-HOSPEDADO (config, nunca input do tenant)**
   - `config('messaging.providers.evolution')`: `api_url`, `api_key` (global), `webhook_secret`, `webhook_base_url`. Uma instância por canal de tenant (`instance_name = tenant_{id}_ch_{id}`).
   - `EvolutionInstanceService` orquestra create/qr/state/terminate e persiste `instance_token` **cifrado** em `provider_metadata` (nunca exposto por `ChannelResource` — `array_diff_key` remove `instance_token`/`auth_token`/`page_access_token`).
   - Serviço Docker `evolution-api` no `compose.yaml` (profile `evolution`, porta host 8085→8080, reusa pgsql schema `evolution` + redis DB 2).

5. **Um WhatsApp ativo por tenant por vez (R7)**
   - UNIQUE parcial `one_active_whatsapp_per_tenant` em `(tenant_id) WHERE type='whatsapp' AND status IN ('ativo','conectando')` + checagem `ChannelService::assertNoActiveWhatsapp` (erro amigável 409). Trocar provedor = desconectar antes.

6. **Webhook do Evolution resolve tenant pela INSTÂNCIA (Princípio II)**
   - `EvolutionWebhookController` (`POST /api/v1/webhooks/evolution/{instance}`): valida header `apikey` (hash_equals contra `webhook_secret`), resolve `Channel` por `provider_metadata->instance_name`. `connection.update`→status; `messages.upsert`→`WebhookEventRecorder`+`ProcessInboundMessageJob` (ignora `fromMe`).
   - Fallback de estado: cron `channels:reconcile-evolution-state` (everyMinute) consulta `connectionState` (cobre webhook perdido — SC-005).

7. **Conformidade Princípio VI no não oficial = REUSO do gate da Fase 13**
   - Evolution não tem `ChannelTemplate` HSM aprovado → o `OutboundNotificationDispatcher` bloqueia proativos fora da janela 24h → `pending_manual/no_template`. Dentro da janela, texto livre é permitido. NÃO criar bypass.
   - `OutboundChannelResolver` (Fase 13) é provider-agnóstico (filtra `type=whatsapp, status=ativo`), já reconhece o canal Evolution ativo.

8. **Constituição v1.5.0 (amendment) admite Evolution como canal não oficial OPCIONAL**
   - A stack fixa enumerava só Twilio/Instagram/widget; adicionar Evolution exigiu amendment MINOR (precedente: v1.4.0 do Bearer). Via oficial permanece padrão; aviso de risco obrigatório na UI (FR-003).

9. **CHECKs do schema da Fase 3 precisaram de ALTER** (lição de integração real)
   - `messaging_channels.status` ganhou `conectando`; `messaging_webhook_events.provider` ganhou `evolution`. Webhook URL do Evolution DEVE incluir `/api/v1` (rota nomeada). Esses 3 bugs só apareceram no smoke real com WhatsApp.

10. **Frontend: estende a tela Canais existente (Fase 3), não cria nova**
    - `pages/Canais/Index.vue` ganhou item de dropdown "WhatsApp (Não Oficial)" → `components/Canais/EvolutionQrModal.vue` (nome + aviso de risco → QR base64 + polling de `connection-state` até `ativo`). Store `canais.js` ganhou `connectEvolution`/`regenerateQr`/`fetchConnectionState`.

11. **DEFERRED / não feito ao final da Fase 14**
    - Mídia outbound real no Evolution usa `MediaPayload.storagePath` (resolução de URL pública S3 ainda é o placeholder herdado da Fase 3).
    - Smoke browser da TELA (clicar no painel) — validação foi via QR real + API; a navegação visual no `/panel` fica como verificação manual.
