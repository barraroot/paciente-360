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

- **Active feature**: `018-ai-multimodal-mcp` — **PLANNED 2026-05-30** — Conversa Reativa, Multimodal e Auto-Curadoria do Kanban via IA. Plano em [`specs/018-ai-multimodal-mcp/plan.md`](specs/018-ai-multimodal-mcp/plan.md) (spec + research + data-model + 5 contratos + quickstart). Endereça 4 gaps da Fase 17: **(1) Coalescência híbrida** (Q1=C) — debounce passivo 3-4s + cancel-and-reprocess via versão Redis (`ai:turn:{conv}:v` INCR atômico); **(2) Multimodalidade** — STT Whisper inbound (WA+IG Direct, widget fora) e TTS ElevenLabs outbound **só sob gatilho explícito** (Q3=A, matcher de frases PT-BR); **(3) Auto-curadoria do CRM** — lead auto-criado na coluna inicial do `funil_colunas` no 1º contato (Q-clarify-3=B: paciente regular NÃO entra no kanban, anexa ao prontuário); abastecimento de nome/observações via capability `update-lead-profile`; status transita automático por listeners com mapping configurável por tenant (`kanban_pipeline_mappings`); FR-020 trava regressão sob manual override; **(4) Chat de teste de Persona** (US6) com `sandbox=true` propagado pela credencial MCP. **Decisão Q2=B (substituição)**: servidor MCP `laravel/mcp` v0 **substitui** as tools `laravel/ai` em produção sob flag `AI_TOOLS_VIA_MCP`, com **circuit breaker** Redis (Q-clarify-1=B) que auto-reverte para tools nativas após N falhas consecutivas (nativas **mantidas no código** como fallback runtime — FR-052 ajustado). **7 tabelas novas** + alterações + novo enum `ConsentFinalidade::Transcricao` (Q-clarify-2=B). **Voz** como atributo da Persona (Q-clarify-4=B). **Rate limit** 2 camadas (Q-clarify-5=C) reusando `RateLimiter::for(...)` da Fase 8; excedido → cooldown auditável. **Constitution 7/7 PASS** com 1 desvio (latência fim-a-fim p95 ≤12s — target, sem amendment; mesmo precedente da 017). 8 clarificações (3 specify + 5 clarify). 73 FRs, 11 SCs, 7 user stories (4×P1).
- **Previous active**: `017-ai-conversation-humanization` — **DELIVERED 2026-05-27** — Humanização da conversa da IA (Constitution 7/7 PASS com 1 desvio). Detalhe em "Humanização da Conversa da IA (Fase 17)" abaixo.
- **Previous features delivered (15)** — sumário enxuto; detalhe técnico de cada fase vive nos blocos "Key Patterns" abaixo e em `specs/<feature>/`:
  - `016-frontend-ux-audit` — Auditoria/correção de UI/UX do frontend (SPA Vue): 0 overflow + 0 axe serious/critical nas 39 rotas, gate `npm run ux:gate`, tokens + 5 primitivos `components/ui/`. DELIVERED 2026-05-27. Constitution 9/9 PASS sem amendment.
  - `015-ai-matricial` — IA Matricial: camada plugável de IA agêntica sobre o omnichannel. MERGED main 2026-05-26. Catálogo `ai_models` (Filament super-admin) + `PersonaAgent` (`laravel/ai`) + RAG via pgvector/embeddings + `ProcessAiResponseJob` (fila `ai`) + guardrails/intenção/confiança/escalonamento/auto-pause + log auditável ≥6m. Constitution PASS 7/7.
  - `014-channel-provider-integration` — Integração de Canal WhatsApp: Twilio (oficial) | Evolution API v2 (não oficial). DELIVERED 2026-05-25. `ChannelAdapterResolver` provider-aware + `EvolutionApiAdapter`/`EvolutionInstanceService` + webhooks/cron de reconciliação + UI QR. Constitution v1.5.0 (amendment MINOR) admite Evolution opcional; Princípio VI por reuso do gate da Fase 13.
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
- **Constitution**: [.specify/memory/constitution.md](.specify/memory/constitution.md) (**v1.5.0** — amendment MINOR em 2026-05-25 para feature 014; Evolution como canal não oficial opcional)
<!-- SPECKIT END -->

## Key Patterns — Fases 2–11 (resumo)

Detalhe técnico completo arquivado em [`docs/key-patterns-archive.md`](docs/key-patterns-archive.md). **Leia o arquivo antes de mexer em código dessas fases.** Abaixo só os gotchas mais críticos:

- **Fase 2 (CRM Pacientes)**: `pg_trgm + unaccent` (use wrapper `immutable_unaccent()` em colunas GENERATED). Cast `AsJsonArray` em JSONB multi-valor. Listener `RegistraEventoTimelineListener` projeta `Auditable` → `eventos_timeline`. Abilities granulares `paciente.note.view:{tipo}` (geral/clinica/comportamental/financeira) controlam visibilidade.
- **Fase 4 (Token Auth)**: API tenant stateless via Bearer Sanctum + `X-Tenant-Slug` (triple-check). `User::guardName()` pina Spatie no guard `'web'` (senão permissions falham silenciosamente sob `auth:sanctum`). Em testes use `Sanctum::actingAs($user, ['*'])` (preserva cache de roles). CSP via `config/csp.php`. Token retention 90d.
- **Fase 5 (Agendamento)**: PARTIAL UNIQUE `app_active_slot_unique` é o gate atômico de race (status terminais fora). `SlotReservation` = reserva pessimista soft com TTL. Sub-calendário Google tenant-scoped (`CalendarSyncAccount` UNIQUE tenant+professional). Payload Google SEM PII clínica (gate `GoogleEventPayloadLgpdTest`). **Listeners auto-discovered Laravel 11+ — NÃO registrar manualmente** (duplica execução). TZ: DB UTC, API ISO 8601 + offset. `ConfirmationDispatch.status='pending_manual'` ≠ `Appointment.status`. `notes` encrypted via cast.
- **Fase 6 (Agenda UX)**: Padrão modal a11y (`Teleport`+`role=dialog`+focus trap+Esc+overlay). Toast local sem lib. **Proibido `confirm()`/`prompt()`/`alert()` nativos** em componente novo. Moeda via `Intl.NumberFormat('pt-BR')`, datas via Luxon.
- **Fase 7 (Receituários)**: `PrescriptionType` define regra Portaria 344/98 (controlled=30d+1 item+mascarado; CHECK no DB). Mascaramento via `ControlledPrescriptionMaskingService` (ponto único `PrescriptionResource::toArray()`). Global scope `withControlledIfAble`. Alertas D-15/7/1 idempotentes (DB UNIQUE + Redis lock). Marker `ContainsNoClinicalData` + gate por reflection (7 props exatas). Renovação via `prescription_renewals`. PDF versionado path-based, URL assinada 15min.
- **Fase 8 (Finalização MVP)**: `ConsentFinalidade::Integracoes` é o gate de PII em payload externo. Catálogo = EXATAMENTE 13 eventos em `BroadcastDomainEventToWebhooksListener::EVENT_CATALOG` (gate `WebhookCatalogCoverageTest`). HMAC SHA-256 + SSRF defense via `UrlGuard`. Retry 30s/2m/10m/1h/6h → DLQ. API Pública resolve tenant pelo TOKEN (nunca URL/header); fora de escopo → 404. Controladas SEMPRE mascaradas. Idempotency-Key 24h. OAuth gated por `finalization.oauth_enabled`. `tenant_suspended` → 503 na API pública (403 interno). Pseudonimização dual layer p/ IA. `finalization.php` é single source of truth.
- **Fase 9 (App Shell)**: Rota pai `/panel` com nested children renderiza `AppShell` uma vez (NÃO declarar `/panel/*` como raiz). Navegação por permissões: source of truth única em `config/navigation.js`. Preferências em `localStorage` escopadas por `tenant_slug+user_id` (NUNCA chave plana — cross-leak). Drawer mobile via `Teleport`+focus trap. Heroicons inline em `HeroIcon.vue`.
- **Fase 10 (Dashboard Home)**: Endpoint único `GET /api/v1/panel/home?scope=user|clinic` (4 seções, cache Redis 30s). 4 collectors com degradação graceful (`safeRun()` — nunca 500 por falha parcial). `PanelHomePolicy` é o único ponto de auth (downgrade silencioso de scope, não 403). `AttentionItemDto` heterogêneo com `severityRank()`. Humanizer com allow-list LGPD (sem PII/clínico). localStorage `panel_home:scope:v1` separado.
- **Fase 11 (Dashboard Executivo)**: Backend Fase 8 reusado (1 linha — case `'24h'`). `useExecutiveDashboard` é o único consumer do store. localStorage `executive_dashboard:window:v1` separado. Polaridade invertida explícita (`no_show_rate`, `response_time_first_p95`). Sparkline/CSV são stubs DEFERRED (backend não retorna time-series).


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


## Humanização da Conversa da IA (Fase 17) — Key Patterns

When working on AI conversation/context/tools post-Fase 17, remember:

1. **A IA NUNCA mais recebe "janela vazia"** — antes o `AiMessageProcessor` só lia a última inbound. Agora `PersonaAgent implements Conversational` e `messages()` devolve a **janela verbatim mínima** (`ConversationHistoryAssembler`, default `ai.matricial.history.window_messages=6`, **pseudonimizada por mensagem** via `PiiScrubber`). A mensagem atual vai em `prompt()`, NUNCA no histórico (excluída por `beforeMessageId`).

2. **Resumo rolante incremental** (`ai_conversation_summaries`, 1/conversa, `ConversationSummarizerService`): só roda quando há turnos **além** da janela ainda não cobertos (`covered_up_to_message_id`); caso contrário **reusa sem chamar o modelo** (FR-022). Lock Redis `ai:summary:{conversation}`. Guarda `funnel_stage` (US4). Disparado pelo `AiMessageProcessor` ANTES de montar o contexto.

3. **Orçamento de tokens** (`AiContextBudget`): teto `ai.matricial.history.input_token_ceiling`; shedding por prioridade **RAG → resumo → mensagens mais antigas**; NUNCA descarta guardrails mínimos nem a mensagem atual (FR-021/023). Estimativa ~4 chars/token. O builder agora compõe o bloco FIXO sem RAG e re-anexa RAG/resumo já orçados.

4. **Contexto de Trabalho por clínica** (`ai_work_contexts`, 1/tenant, `AiWorkContextService`): híbrido (campos estruturados + texto livre). `renderForPrompt` injeta o bloco via `AiGuardrailEnforcer::composeInstructions($persona, $rag, $workContext)`. **Precedência FR-011 declarada no prompt**: ferramentas (dado vivo) > work context > persona/RAG. CRUD `GET/PUT /api/v1/ai/work-context` (singleton, gated `ai.work-context.view|manage`). UI `pages/Ia/WorkContextPage.vue` + store `ia.js`.

5. **Nome via placeholder `{{primeiro_nome}}`** — o modelo recebe o marcador; `OutboundNameInjector` substitui pelo 1º nome real **só na mensagem de saída** (FR-017). Nome real NUNCA vai ao provedor; nome desconhecido → placeholder neutralizado (nunca o token literal ao paciente).

6. **Ferramentas de dados ao vivo = laravel/ai `HasTools`** (NÃO laravel/mcp — este fica opcional p/ exposição externa). `PersonaAgent` tem `#[MaxSteps(3)]` (cap de round-trips, FR-032). Tools em `app/Domain/Ai/Tools/` estendem `ConversationTool` (base: `ToolContext` + auditoria `ai_tool_invocations` + degradação graciosa FR-033 — em falha NUNCA inventa). Montadas por `ConversationToolFactory` (gate `AI_TOOLS_ENABLED`).
   - `get-clinic-info` → serviços/preços de `appointment_types` (DB vivo); horário/endereço caem no work context (não há tabela). `list-professionals`, `get-availability` (slots reais Fase 5). `get-current-patient`: **só o contato da conversa** (patient_id/telefone), **NÃO devolve nome** ao modelo, consent-aware (FR-029). `create-or-find-lead`: `Paciente status='lead'` por `telefone_primario_normalizado` (FR-030). `hold-slot`: `SlotReservation holder_type='ia'` (TTL); **NÃO confirma nem cobra** — handoff (FR-018).
   - **Isolamento no data layer (FR-034)**: toda tool filtra `tenant_id` explicitamente, além do global scope.

7. **Caching Anthropic** — `PersonaAgent implements HasProviderOptions` → `cache_control: ephemeral` no bloco estático quando `AI_PROMPT_CACHING=true` (corta tokens repetidos turno a turno).

8. **Auditoria estendida** — `ai_execution_logs` ganhou `work_context_version`/`summary_version`/`tools_used`/`tool_round_trips`. `ai_tool_invocations` registra cada chamada (input/result pseudonimizados, FR-031). Métrica `ai_tool_round_trips` no Prometheus.

9. **Guardrails/escala/auto-pause da Fase 15 INTACTOS** — todo o contexto novo é ADITIVO ao system prompt; a saída continua estruturada e passa pelo mesmo `evaluate()` determinístico. 148 testes de IA verdes (sem regressão de segurança — SC-006).

10. **Constituição: desvio de TARGET de métrica documentado** (sem amendment) — §V cita "resposta IA ≤5s"; com tool-calling o alvo é p95 ≤8s (decisão Q5, canal assíncrono). O MUST de §V é *expor* a métrica (cumprido); o ≤5s é target não-vinculante. Single-pass mantém ≤5s.

11. **DEFERRED ao final da Fase 17** (desvio consciente D2, padrão das fases anteriores)
    - E2E Playwright `ai-scheduling-conversation.spec.ts` (T062): a jornada via IA exige provedor real/mockado no browser — fica para staging.
    - Validação manual do `quickstart.md` (T066) em ambiente com provedor real.
    - Adoção de `model_settings` (temperature/max_tokens) por persona: `providerOptions` cobre caching; aplicação de temperatura por persona não é injetável de forma limpa no SDK atual (atributos são estáticos) — deferred.
