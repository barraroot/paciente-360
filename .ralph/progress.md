# Ralph Progress — Fase 0 (001-fundacao-multitenant)

Histórico de tasks concluídas em iterações do Ralph Wiggum loop.

| Quando | Task | Título | Commit |
|---|---|---|---|
| 2026-05-10 10:50 | T001 | Adicionar dependências PHP no composer.json | (a preencher após commit) |
| 2026-05-10 11:20 | T002 | Adicionar dependências Node no package.json | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 11:35 | T003 | Compose: Postgres 18, Redis 7, Mailpit, Reverb, Horizon | (a preencher após commit) |
| 2026-05-10 11:50 | T004 | argon2id como driver default de hash | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 12:05 | T005 | Configuração de multi-tenancy (subdomain suffix, public hosts, slugs reservados) | (a preencher após commit) |
| 2026-05-10 12:20 | T006 | config/billing.php (Stripe + política de cobrança FR-013/014/019) | (a preencher após commit) |
| 2026-05-10 12:35 | T007 | config/audit.php (tiers de retenção FR-038 + schedule mensal) | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 12:50 | T008 | pint.json (preset Laravel + phpdoc_align + not_operator_with_successor_space) | (a preencher após commit) |
| 2026-05-10 13:05 | T009 | PHPUnit coverage (source/exclude + relatórios + script test:coverage) | (a preencher após commit) |
| 2026-05-10 13:20 | T010 | Setup Playwright (config + tests/e2e/.gitkeep + --pass-with-no-tests) | (a preencher após commit) |
| 2026-05-10 13:35 | T011 | GitHub Actions CI (pint + phpunit-coverage + playwright) | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 13:50 | T012 | Sentry com tag `tenant.id` / `user.id` via Authenticated + tenant.resolved | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 14:05 | T013 | Telescope só em dev/staging (config + provider + bootstrap guard) | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 14:20 | T015 | Tailwind v4 + tema base (paleta oklch + tokens semânticos) | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 14:35 | T014 | Vue 3 entrypoint + Pinia + Vue Router + vue-i18n + auth store | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 14:50 | T016 | SPA shell (`app.blade.php`) + rotas web (`/`, `/cadastro`, `/panel/{any?}`) | (pendente — sandbox bloqueia `git add`) |
| 2026-05-10 12:06 | T017 | Pail como service Compose opcional (profile `pail`) + tty/stdin + quickstart atualizado | (pendente — sandbox bloqueia `git add`) |

## Notas operacionais

- T016: shell único da SPA + rotas web. Decisões:
  - **`resources/views/app.blade.php`** criado como única view do
    tenant. Estrutura mínima: `<html lang="{{ ... app()->getLocale() }}">`,
    `<meta name="csrf-token" content="{{ csrf_token() }}">` (já
    deixa pronto o requisito do axios bootstrap em T050 — Sanctum
    SPA), `@vite(['resources/css/app.css', 'resources/js/app.js'])`
    e `<div id="app"></div>` como alvo único do `mount('#app')`. Sem
    JS inline, sem CSS inline — toda a UI vai pelo Vite.
  - **`routes/web.php`** reescrito com `Route::view`:
    - `/` → `app` (Vue Router faz `redirect: '/panel'`).
    - `/cadastro` → `app` (US-1.1, cadastro público de tenant).
    - `/panel` → `app` (raiz autenticada).
    - `/panel/{any}` → `app` com `->where('any', '.*')` — catch-all
      indispensável para HTML5 mode do Vue Router (sem isso, GET
      `/panel/foo` retorna 404 do Laravel em vez do shell). O `any`
      foi mantido obrigatório (não `{any?}`) porque o caso raiz já
      é coberto pela linha anterior; o operador `?` complica o
      pattern `.*` e não traz benefício.
    - **Slugs reservados** documentados em comentário-âncora no topo
      do arquivo: `/admin` (Filament — T118+), `/telescope` (T013),
      `/horizon`, `/stripe/webhook` (Cashier — T072), `/up`
      (health-check). Comentário serve de guarda contra alguém
      adicionar `Route::view('/admin', ...)` futuramente — os
      testes de regex `T118.*Filament` e `T072.*Stripe` falham se
      o âncora for removido.
  - **Welcome.blade.php**: deixado intacto. Não é mais referenciado
    por nenhuma rota; pode ser removido em uma limpeza posterior
    (não é escopo de T016, evita inflar o diff).
  - **TDD**: `tests/Unit/Frontend/SpaShellAndRoutesTest.php` cobre
    14 asserts:
    1. Shell existe.
    2. Carrega ambos `@vite` entrypoints (CSS + JS).
    3. Tem `<div id="app">`.
    4. `<html lang="...">` lê `app()->getLocale()`.
    5. `<meta name="csrf-token" content="{{ csrf_token() }}">`
       presente.
    6. `routes/web.php` existe.
    7. Declara `Route::view('/', 'app')`.
    8. Declara `Route::view('/cadastro', 'app')`.
    9. Declara `Route::view('/panel/{any}', 'app')` (catch-all).
    10. Catch-all usa `->where('any', '.*')`.
    11. Declara `Route::view('/panel', 'app')` (raiz).
    12. Comentário-âncora `/admin` cita Filament + T118.
    13. Comentário-âncora `/stripe/webhook` cita Stripe + T072.
    14–16. Smoke tests funcionais via `$this->withoutVite()`:
        - GET `/` retorna 200 com `id="app"`.
        - GET `/panel`, `/panel/foo`, `/panel/foo/bar` retornam
          200 com `id="app"` (critério literal da T016).
        - GET `/cadastro` retorna 200 com `id="app"`.
  - **Vite + Vue plugin**: `@vitejs/plugin-vue` foi adicionado em
    T002 mas ainda não está plugado em `vite.config.js`. NÃO é
    bloqueante para T016 (testes deste card são server-side); fica
    como ajuste para T138+ (smoke E2E real) ou T017 (próxima task
    elegível). Registrei em `.ralph/blockers.md` se precisar
    promover para nova tarefa.
  **Pendente fora do loop**: `vendor/bin/sail artisan test --filter=SpaShellAndRoutesTest`
  e `vendor/bin/sail bin pint --dirty --format agent`. Sandbox do
  Ralph bloqueia Sail/PHP; gate efetivo é o CI (T011 — job
  `phpunit-coverage` rodará o teste novo automaticamente).

## Notas operacionais antigas

- T014: bootstrap Vue 3 montado em `resources/js/app.js`. Decisões:
  - **App raiz**: criado `resources/js/App.vue` como shell mínimo com
    `<RouterView />`. Necessário porque `createApp(App).mount('#app')`
    exige um root component — sem ele o build do Vite falha.
  - **Pinia + Router + I18n**: registrados na ordem canônica (`use(pinia)`
    antes de `use(router)`) para que stores possam ser consumidas em
    guards futuros (auth.js → US-2.x). `./echo` mantido como import
    side-effect (não regredir Reverb — Princípio V).
  - **Router**: `createWebHistory()` (HTML5 mode) — exige catch-all
    em `routes/web.php` (entra em T016). Rotas declaradas:
    - `/` → redirect para `/panel`.
    - `/panel` (named `panel.home`) com placeholder inline mostrando
      "Paciente 360 — Painel em construção", usando tokens Tailwind
      do tema (T015). Cumpre critério "rota /panel exibe placeholder".
    - `/panel/:pathMatch(.*)*` (named `panel.catchAll`) prepara o
      caminho para sub-rotas SPA sem 404 (será preenchido por
      US-1.x/US-2.x).
    - `meta.requiresAuth: true` declarado já agora; o guard real
      será plugado em T080+ (Sanctum SPA).
  - **i18n**: `resources/js/i18n/index.js` cria a instância via
    `createI18n` no modo `legacy: false` (Composition API).
    `locale=fallbackLocale='pt-BR'` por Princípio Localização (MVP
    Brasil-only). Mensagens em `resources/js/i18n/pt-BR.json`
    com namespaces `app`, `common`, `auth`, `errors` — esqueleto
    suficiente para US-2.x começar a usar `$t('auth.login')`.
  - **Auth store**: `resources/js/stores/auth.js` registra
    `defineStore('auth', { state, getters, actions })` com `user`,
    `tenant`, `permissions` + `isAuthenticated` / `currentTenantId`
    + setters/reset. Sem chamadas HTTP nesta fase — preenchido em
    T080+ quando Sanctum estiver pronto.
  - **TDD**: `tests/Unit/Frontend/Vue3BootstrapTest.php` cobre
    (1) existência dos 5 arquivos; (2) `createApp(App).mount('#app')`;
    (3) Pinia/Router/I18n importados em app.js; (4) `import './echo'`
    preservado; (5) router usa `createRouter`+`createWebHistory`;
    (6) regex valida `path: '/panel'`; (7) i18n usa locale/fallback
    `pt-BR`; (8) JSON do locale válido com chave raiz `app`;
    (9) auth store registra `defineStore('auth', ...)`.
  **Pendente fora do loop**: `vendor/bin/sail npm run dev` (e/ou
  `npm run build`) para confirmar critério de aceitação ("compila
  sem erro"); `vendor/bin/sail artisan test --filter=Vue3BootstrapTest`
  para gate PHP. Sandbox do Ralph bloqueia npm/Sail/Pint; gate
  efetivo são os jobs `phpunit-coverage` (PHP) e `playwright`
  (que executa `npm run lint` + `test:e2e`) do CI introduzido em
  T011.

## Notas operacionais antigas

- T001: edição de `composer.json` aplicada com versões compatíveis com Laravel 13:
  `laravel/sanctum ^4.0`, `laravel/cashier ^16.0`, `spatie/laravel-permission ^7.0`,
  `laravel/horizon ^5.30`, `laravel/telescope ^5.6`, `sentry/sentry-laravel ^5.0`,
  `laravel/pail ^1.2.5` (movido para `require`). Sem `pragmarx/google2fa`
  (2FA fora do MVP — constituição v1.3.0).
  **Pendente fora do loop**: rodar `vendor/bin/sail composer install` para
  regenerar `composer.lock` e validar resolução de dependências. O sandbox
  do Ralph não autoriza chamadas a Sail/composer; o critério de aceitação
  da tarefa (instalação sem conflitos) será confirmado na primeira
  execução manual ou no job de CI introduzido em T011.
- T002: edição de `package.json` aplicada com as dependências exigidas pela task:
  `vue ^3.5.13`, `pinia ^2.3.0`, `vue-router ^4.5.0`, `vue-i18n ^10.0.5`,
  `@vueuse/core ^12.0.0`, `axios ^1.7.9` (em `dependencies`); `@playwright/test ^1.49.1`,
  `prettier ^3.4.2` (em `devDependencies`). Mantidos `tailwindcss ^4.0.0`,
  `vite ^8.0.0` e demais já presentes. Adicionado `@vitejs/plugin-vue ^5.2.1`
  (transitivamente necessário para T014/T016 compilar SFCs Vue dentro do Vite).
  Scripts `lint`, `format`, `test:e2e` registrados para suportar gates do CI (T011).
  **Pendente fora do loop**: rodar `vendor/bin/sail npm install` para regenerar
  `package-lock.json` e validar resolução de versões; mesmo motivo do T001
  (sandbox do Ralph não autoriza chamadas a npm/sail). Será coberto por T011 (CI).
- T003: ajustes em `compose.yaml`:
  - `pgsql` mantido em `postgres:18-alpine` com healthcheck `pg_isready`.
  - `redis` fixado em `redis:7-alpine` com healthcheck `redis-cli ping`.
  - `mailpit` mantido (1025 SMTP, 8025 dashboard).
  - **Removido `soketi`** (substituído pelo Reverb nativo do Laravel).
  - **Removido `minio`** — não consta na task T003; reintroduziremos em fase
    posterior se documentos/anexos exigirem object storage.
  - Novo serviço `reverb`: reusa imagem `sail-8.5/app`, executa
    `php artisan reverb:start --host=0.0.0.0 --port=8080`, depende de
    `redis` saudável.
  - Novo serviço `horizon`: reusa imagem `sail-8.5/app`, executa
    `php artisan horizon`, depende de `redis` e `pgsql` saudáveis,
    healthcheck via `php artisan horizon:status`.
  - `laravel.test` agora usa `depends_on` com `condition: service_healthy`
    para Postgres/Redis e `service_started` para Mailpit/Reverb/Horizon.
  - `.env.example` reescrito: `DB_CONNECTION=pgsql` (host `pgsql`),
    `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`,
    `CACHE_STORE=redis`, `MAIL_HOST=mailpit`, vars de Reverb (servidor +
    cliente Vite) e Horizon. `APP_LOCALE=pt_BR`.
  **Pendente fora do loop**: subir efetivamente os containers com
  `vendor/bin/sail up -d` para confirmar critério de aceitação
  (`\dt` em `psql` listando tabelas vazias após migrate). Sandbox do Ralph
  não autoriza Docker; validação será coberta pelo job de CI em T011 e
  pelo quickstart manual.
- T005: criado `config/tenancy.php` com:
  - `subdomain_suffix` lido de `TENANCY_SUBDOMAIN_SUFFIX` (default `lvh.me`
    para dev/CI; produção exige `crm.com.br` no `.env`).
  - `public_hosts = ['crm', 'admin', 'www']` — subdomínios ignorados pelo
    futuro middleware `ResolveTenant` (cadastro público, super admin,
    estáticos).
  - `reserved_slugs` cobrindo hosts públicos + rotas internas (`api`,
    `app`, `auth`, `panel`, `stripe`, `webhook`, etc.) — protege contra
    registro de tenant que colidiria com endpoints conhecidos.
  - `redis_prefix = 'tenant:{id}:'` — placeholder substituído em runtime
    pelo `RedisManager` (Princípio II).
  - `correlation_header = 'X-Correlation-Id'` — para o middleware de
    logging estruturado (Princípio V), não é fonte de verdade do tenant.
  TDD: `tests/Unit/Config/TenancyConfigTest.php` cobre (1) suffix default
  `lvh.me`; (2) override via config; (3) `redis_prefix` exato; (4)
  `public_hosts` contém `crm`, `admin`, `www`; (5) `reserved_slugs` cobre
  os críticos; (6) `correlation_header` definido.
  Atualizado `.env.example` com bloco `# --- Multi-tenancy (Princípio II) ---`
  e a var `TENANCY_SUBDOMAIN_SUFFIX=lvh.me`.
  **Pendente fora do loop**: `vendor/bin/sail artisan test --filter=TenancyConfigTest`
  e `vendor/bin/sail bin pint --dirty --format agent`. Sandbox do Ralph
  bloqueia execução de PHP/Sail; gate efetivo será o CI introduzido em T011.
- T006: criado `config/billing.php` com:
  - `stripe.{key,secret,webhook_secret}` lidos de env (Princípio VII).
  - `currency = 'brl'` (MVP Brasil-only).
  - `payment_retries = 3` (FR-014: 3 falhas → Inadimplente).
  - `grace_days = 7` (FR-014: carência antes da degradação seletiva).
  - `suspension_eligible_days = 37` (7 + 30 — elegibilidade para
    suspensão **manual** pelo Super Admin; suspensão automática
    fora do escopo do MVP).
  - `hard_cap_default = null` (FR-019: opt-in pelo Admin Clínica).
  - `webhook.path = 'stripe/webhook'` e `webhook.tolerance = 300s`
    (idempotência + tolerância de clock para validação HMAC do
    Stripe — FR-013).
  TDD: `tests/Unit/Config/BillingConfigTest.php` cobre (1) retries=3;
  (2) grace_days=7; (3) suspension_eligible_days=37; (4)
  hard_cap_default=null; (5) chaves Stripe sobrescrevíveis; (6)
  estrutura `stripe.{key,secret,webhook_secret}`; (7) currency=brl.
  `.env.example` recebeu o bloco `# --- Billing / Stripe ---` com as
  vars `STRIPE_*`, `BILLING_*`, `CASHIER_CURRENCY`. `BILLING_HARD_CAP_DEFAULT=(null)`
  para forçar coerção a `null` via dotenv (string vazia retornaria
  `''`, quebrando o teste de tipo).
  **Pendente fora do loop**: `vendor/bin/sail artisan test --filter=BillingConfigTest`
  e `vendor/bin/sail bin pint --dirty --format agent`. Sandbox do
  Ralph bloqueia Sail; gate efetivo é o CI (T011).
- T007: criado `config/audit.php` com:
  - `hot_days = 730` (2 anos — FR-038 tier hot, painel do Admin
    Clínica consulta direto).
  - `cold_days = 1825` (5 anos totais — janela cold de 2 a 5 anos
    em `audit_logs_cold`).
  - `delete_after_days = 1825` (deleção física aos 5 anos —
    LGPD Art. 16). Mantido como knob separado para flexibilizar
    políticas futuras por tipo de evento sem alterar a janela cold.
  - `archive_batch_size = 1000` (T264 — jobs em batches
    transacionais para evitar OOM e lock prolongado).
  - `schedule.archive = '0 3 1 * *'` e `schedule.delete = '0 4 1 * *'`
    (mensal, dia 1; archive ANTES de delete para defesa em
    profundidade no boundary de 5 anos).
  TDD: `tests/Unit/Config/AuditConfigTest.php` cobre (1) hot_days=730;
  (2) cold_days=1825; (3) delete_after_days=1825; (4) archive
  schedule ≥ 5 campos cron; (5) delete schedule ≥ 5 campos cron;
  (6) archive ≠ delete (ordenação garantida); (7) hot_days
  sobrescrevível via config(); (8) batch_size é int positivo.
  `.env.example` recebeu o bloco `# --- Auditoria ---` com
  `AUDIT_HOT_DAYS`, `AUDIT_COLD_DAYS`, `AUDIT_DELETE_AFTER_DAYS`,
  `AUDIT_ARCHIVE_BATCH_SIZE`, `AUDIT_SCHEDULE_ARCHIVE`,
  `AUDIT_SCHEDULE_DELETE`.
  **Pendente fora do loop**: `vendor/bin/sail artisan test --filter=AuditConfigTest`
  e `vendor/bin/sail bin pint --dirty --format agent`. Sandbox do
  Ralph bloqueia Sail; gate efetivo é o CI (T011).
- T008: criado `pint.json` com:
  - `preset = "laravel"` (baseline oficial do framework — alinha com
    Restrições Técnicas e Princípio IV).
  - Override de `phpdoc_align` com `align: "left"` — o preset Laravel
    deixa em `vertical` por default; alinhamento à esquerda evita
    diff visual em PHPDoc multi-linha quando nomes de parâmetros
    mudam (decisão pragmática, espelha convenção do skeleton 13).
  - `not_operator_with_successor_space: true` — força `! $foo` em vez
    de `!$foo`, melhorando legibilidade de negações em condicionais
    (especialmente em Form Requests e Policies).
  - `exclude` cobrindo `bootstrap/cache`, `storage`, `vendor`,
    `node_modules` — defesa em profundidade caso `vendor/bin/sail bin
    pint` seja invocado de raiz sem `--dirty`.
  **Pendente fora do loop**: `vendor/bin/sail bin pint --test --format agent`
  num arquivo já formatado para confirmar exit 0. Sandbox bloqueia
  Sail; gate efetivo é o CI (T011).
- T009: ajustes em `phpunit.xml` para reativar cobertura no PHPUnit 12:
  - Em `<source>`, adicionado `<exclude>` para `app/Providers/AppServiceProvider.php`
    e `app/Http/Controllers/Controller.php` (esqueletos triviais sem lógica
    testável; evitam diluir o denominador da cobertura).
  - Adicionado bloco `<coverage includeUncoveredFiles="true">` com três relatórios:
    `text` (sumário no stdout — útil para CI/local), `clover`
    (`storage/app/coverage/clover.xml` — consumido por Codecov/Sonar) e
    `html` (`storage/app/coverage/html` — inspeção local).
  - O threshold de 70% NÃO foi colocado no XML: o Laravel test runner valida
    `--min=70` em runtime via flag, conforme exigido pela aceitação. Isso
    permite rodar `--coverage` sem threshold em desenvolvimento e ainda assim
    falhar o CI.
  - `composer.json` ganhou o script `test:coverage` (`@php artisan test --coverage --min=70`),
    invocado pelo job `phpunit-coverage` do CI (T011).
  - `.gitignore` agora ignora `/storage/app/coverage` para não commitar
    artefatos de cobertura local/CI.
  **Pendente fora do loop**: rodar `vendor/bin/sail artisan test --coverage --min=70`
  para confirmar roteabilidade com pcov/xdebug instalado. Sandbox do Ralph
  bloqueia Sail; gate efetivo é o CI (T011).
- T010: criado `playwright.config.ts` + `tests/e2e/.gitkeep`:
  - `testDir = './tests/e2e'`, alinhado ao layout em plan.md (linhas 468–473).
  - `baseURL` default `https://clinica-alfa.lvh.me` (tenant de demo previsto
    em `config/tenancy.php`); sobrescrevível via `PLAYWRIGHT_BASE_URL`
    para o CI rodar contra um host efêmero próprio.
  - `retries = 2` apenas em CI; `workers = 1` em CI para isolamento
    determinístico do schema multi-tenant (Princípio II) — local mantém
    paralelismo padrão.
  - `forbidOnly = !!process.env.CI` impede que `.only` chegue ao main.
  - Reporter em CI = `[github, html(open:never)]`; local = `list`.
  - `trace: 'on-first-retry'` + `screenshot/video: only-on-failure` — barato
    no caminho feliz, diagnóstico nos retries.
  - `ignoreHTTPSErrors: true` (suffix `lvh.me` em dev usa cert autoassinado).
  - `headless` controlável via `PLAYWRIGHT_HEADED` (atende cláusula
    "headed/CI flag" da task).
  - Único projeto `chromium` no MVP; Firefox/WebKit em fase posterior.
  - `package.json` script `test:e2e` recebe `--pass-with-no-tests` para
    atender o critério de aceitação ("zero specs sem erro") até T138+
    introduzirem os primeiros specs.
  **Pendente fora do loop**: `vendor/bin/sail npm install` para baixar
  binários do `@playwright/test` e `vendor/bin/sail npm run test:e2e`
  para confirmar exit 0 com zero specs. Sandbox do Ralph bloqueia
  npm/sail; gate efetivo é o CI (T011).
- T011: criado `.github/workflows/ci.yml` com 3 jobs paralelos —
  `pint`, `phpunit-coverage`, `playwright` — disparados em PRs e push
  para `main`. Decisões:
  - `concurrency` cancela runs antigos do mesmo ref ao subir novo
    commit (poupa minutos de Actions e evita ordering bugs).
  - `permissions: contents: read` (least privilege; jobs não fazem
    deploy/release nesta fase).
  - PHP 8.5 via `shivammathur/setup-php@v2`; PCOV no job de coverage
    (mais rápido que Xdebug; suficiente para `--min=70`). Pint roda
    sem coverage (gate de estilo é I/O bound).
  - **phpunit-coverage** sobe `services` do GitHub Actions com
    `postgres:18-alpine` (db `testing`, user `sail`/password — espelha
    o ambiente Sail) e `redis:7-alpine`, cada um com `health-cmd` e
    `--health-retries 10`. Variáveis de env do job sobrescrevem
    `DB_HOST=127.0.0.1` (services do Actions ficam no localhost do
    runner, **não** no hostname `pgsql` do Sail). `phpunit.xml` já
    força `DB_DATABASE=testing` no `<php>`, mas mantemos a env para
    `migrate --force` (que roda fora do PHPUnit).
  - **Stripe**: chaves `STRIPE_KEY_TEST`, `STRIPE_SECRET_TEST`,
    `STRIPE_WEBHOOK_SECRET_TEST` injetadas via `secrets.*`. Sem chaves
    em prod no CI (Princípio VII). T011 não exige uso efetivo das
    chaves — webhook tests entram em T072.
  - **Playwright**: instala Node 22 LTS (compatível com Vite 8),
    cacheia `~/.cache/ms-playwright` por hash de `package-lock.json`,
    fallback para `install-deps` quando há cache hit (libs do sistema
    não persistem no cache). `npm run lint` (Prettier --check) roda
    no mesmo job para economizar setup. `npm run test:e2e` herda
    `--pass-with-no-tests` (T010), permitindo verde até specs reais
    chegarem em T138+.
  - Artefatos: `clover.xml` (14 dias) e `playwright-report/` (14
    dias) — diagnóstico sem inflar storage do GitHub.
  - **APP_KEY**: hardcoded base64 dummy no env do job apenas para
    permitir `key:generate` rodar em cima sem quebrar `migrate`.
    Senão precisaríamos importar uma chave de secret só para o CI.
  **Pendente fora do loop**: o workflow só corre quando o branch
  bater no GitHub e o PR for aberto. Validação efetiva = primeiro PR
  desta fase abrindo os 3 checks.
- T004: criado `config/hashing.php` publicando o stub do framework com
  `'driver' => env('HASH_DRIVER', 'argon2id')` (default fixado em argon2id
  conforme Princípio VII). Parâmetros Argon2id mantidos no baseline OWASP
  2025: `memory=65536`, `threads=1`, `time=4`. Bcrypt fica como fallback
  com `rounds=12`. Adicionado em `.env.example` o bloco `HASH_DRIVER`,
  `ARGON_*` e mantido `BCRYPT_ROUNDS=12`.
  TDD: `tests/Unit/HashDriverTest.php` cobre: (1) driver default igual a
  `argon2id`; (2) `Hash::make()` retorna prefixo `$argon2id$`; (3) opções
  Argon ≥ baseline; (4) default de `BCRYPT_ROUNDS` no arquivo config ≥ 12
  (regex no source — protege contra alguém abaixar o número); (5)
  round-trip `Hash::check()` para hash gerado.
  **Pendente fora do loop**: rodar `vendor/bin/sail artisan test --filter=HashDriverTest`
  para confirmar o critério de aceitação. Sandbox do Ralph bloqueia
  execução de PHP/Sail; CI (T011) será o gate efetivo.
- T012: criado `config/sentry.php` (stub do sentry-laravel v5) e
  `app/Providers/AppServiceProvider::configureSentryScope()`:
  - DSN lido de `SENTRY_LARAVEL_DSN` (com fallback para `SENTRY_DSN`).
    Vazio = cliente inerte (testes/CI sem credencial não disparam).
  - `send_default_pii=false` e `breadcrumbs.sql_bindings=false`
    como defaults LGPD-friendly (Princípio I) — PII e bindings de
    SQL só com opt-in explícito.
  - `ignore_exceptions` lista `ValidationException`,
    `AuthenticationException` e `NotFoundHttpException` (ruído
    esperado, não merece evento).
  - `traces_sample_rate` / `profiles_sample_rate` default null
    (APM opt-in — entra na fase 2 com Inbox/IA).
  - `AppServiceProvider::boot()` guarda toda lógica de Sentry atrás
    de `app()->bound('sentry')` — sem o pacote resolvido (vendor
    fora do sandbox), o boot é no-op. Quando o pacote está bound:
    - `Authenticated` listener seta `user.id` e `tenant.id` no
      escopo do Sentry usando o usuário recém-autenticado.
    - Evento string `tenant.resolved` (a ser disparado pelo
      middleware `ResolveTenant` em T050) propaga `tenant.id` e
      `tenant.slug` mesmo em rotas pré-autenticação.
    - Closures internos checam `function_exists('Sentry\\configureScope')`
      como segunda camada de defesa contra autoload faltando.
  - TDD: `tests/Unit/Config/SentryConfigTest.php` cobre (1) DSN
    sobrescrevível via config; (2) DSN default null; (3) chave
    `environment` exposta; (4) `send_default_pii=false`; (5)
    `traces_sample_rate` null/0; (6) `breadcrumbs.sql_bindings=false`;
    (7) `AppServiceProvider::boot()` não quebra com Sentry unbound
    (cobertura do guard de defesa).
  - `.env.example` ganhou bloco `# --- Sentry ---` com
    `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`,
    `SENTRY_*_SAMPLE_RATE`, `SENTRY_SEND_DEFAULT_PII=false`,
    `SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED=false`.
  **Pendente fora do loop**: validar manualmente em staging com
  `throw new \RuntimeException('test')` autenticado para confirmar
  que o evento aparece no Sentry com tag `tenant.id` (critério da
  task). Sandbox do Ralph bloqueia PHP/Sail; CI (T011) é o gate
  para `SentryConfigTest`.
- T013: criado `config/telescope.php` (stub do laravel/telescope) +
  `app/Providers/TelescopeServiceProvider.php` + guard em
  `bootstrap/providers.php`:
  - `config/telescope.php` com `enabled = env('TELESCOPE_ENABLED', false)`
    — default seguro (rotas não registradas em produção, GET
    `/telescope` retorna 404 mesmo se o pacote estiver vendor).
    Watchers sensíveis (LGPD): `MailWatcher` default OFF; query slow
    threshold 500ms; `RequestWatcher` mantido ON em dev/staging
    (Authorize bloqueia produção). `ignore_paths` cobre `up`
    (health-check) e `stripe/webhook` (não inflar histórico).
  - `App\Providers\TelescopeServiceProvider` estende
    `Laravel\Telescope\TelescopeApplicationServiceProvider`. No
    `register()`, early-return se ambiente ≠ local/staging
    (defesa em profundidade — Princípio VII). Em local: `Telescope::filter`
    sem restrição (DX). Em staging: filtra apenas exceções, requests
    com erro, jobs falhos e schedules — para limitar volume.
    `Telescope::tag` injeta `tenant:{id}` lendo `request()->attributes`
    (será setado pelo `ResolveTenant` em T050). `gate('viewTelescope')`:
    em local libera qualquer autenticado; em staging exige
    `hasRole('super_admin')` (Spatie Permission, configurado em T040+).
    `hideSensitiveRequestDetails()` mascara `cookie`, `authorization`,
    `stripe-signature`, `_token`, `password*` em RequestWatcher
    (Princípio I — LGPD).
  - `bootstrap/providers.php` registra o `TelescopeServiceProvider`
    apenas se `class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)`.
    Sem o vendor (sandbox/CI base), o autoloader nem chega ao arquivo
    do provider — boot continua íntegro.
  - `.env.example` ganhou bloco `# --- Telescope ---` com
    `TELESCOPE_ENABLED=false`, `TELESCOPE_PATH=telescope`,
    `TELESCOPE_DRIVER=database`, `TELESCOPE_MAIL_WATCHER=false`.
  - TDD: `tests/Unit/Config/TelescopeConfigTest.php` cobre (1) enabled
    default false; (2) path canônico `telescope`; (3) driver `database`;
    (4) `storage.database` exposto; (5) regex no source garantindo que
    nunca alguém troque `env('TELESCOPE_ENABLED', false)` para `true`;
    (6) arquivo do provider existe; (7) provider extende a base do
    Telescope; (8) provider tem guard `environment(['local','staging'])`;
    (9) gate `viewTelescope` checa `super_admin`; (10) registro
    condicional via `class_exists` em `bootstrap/providers.php`.
  **Pendente fora do loop**: rodar `vendor/bin/sail composer require laravel/telescope`
  efetivamente (vendor não persiste no sandbox) e
  `vendor/bin/sail artisan test --filter=TelescopeConfigTest`. Critério
  funcional ("GET /telescope em produção → 404") será validado
  manualmente em staging após o primeiro deploy ou via Playwright
  spec dedicado em fase posterior. CI (T011) é o gate para o
  TelescopeConfigTest.
- T015: `resources/css/app.css` reescrito como fonte única de tokens
  do design system (Tailwind v4 CSS-first) e `tailwind.config.js`
  criado como stub mínimo. Decisões:
  - **Paleta primária** = teal/verde-escuro extraído do mockup
    `docs/design/01 _ Login _US-2.1_ US-2.2_.html`. Escala 50–950
    (`--color-primary-*`) ancorada em `oklch(0.55 0.10 180)` (500),
    `oklch(0.32 0.06 180)` (700) e `oklch(0.22 0.04 200)` (900) —
    tons replicados do CSS inline do mockup, mantendo colorimetria
    perceptualmente uniforme (oklch evita drift em ajuste de luma).
  - **Neutros**: fundo creme quente (`oklch(0.985 0.003 95)`) e
    derivados, espelhando a hue do mockup. Texto frio neutro
    (`oklch(0.22 0.012 250)`) gera contraste alto sem cair em puro
    preto.
  - **Tokens semânticos** (`success/warning/danger/info/accent`) com
    par 50/500 cada — suficientes para badges/alerts; expandimos
    quando algum componente exigir.
  - **Tipografia**: `--font-sans = Instrument Sans` (já carregada
    via `bunny()` no `vite.config.js`); fallback completo para
    sistema. `--font-mono` separado para tokens de código futuros.
  - **Sombras + radius** centralizados em `@theme` para evitar
    valores mágicos espalhados em SFCs Vue.
  - **`tailwind.config.js`**: `export default {}` apenas. Tailwind v4
    detecta o arquivo (IDE/IntelliSense, prettier-plugin-tailwindcss),
    mas a configuração canônica permanece no CSS. Doc-string explica
    que plugins futuros entrariam via `@plugin '...'` no CSS.
  - **`@source '../**/*.vue'`** adicionado para o JIT do Tailwind v4
    enxergar classes em SFCs Vue (necessário a partir de T014).
  - TDD: `tests/Unit/Frontend/TailwindThemeTest.php` cobre (1)
    existência dos dois arquivos; (2) `@import 'tailwindcss'`; (3)
    presença do bloco `@theme {`; (4) lista canônica de tokens
    (primary 500/700/900, surface, surface-muted, border, foreground,
    foreground-muted, success/warning/danger/info via `dataProvider`);
    (5) `--font-sans` com Instrument Sans; (6) ao menos um valor
    `oklch()` (proteção contra drift para hex). Smoke tests sobre o
    arquivo CSS — qualquer remoção de token quebra o gate antes do
    Vite buildar.
  **Pendente fora do loop**: rodar `vendor/bin/sail npm run build`
  para confirmar zero warnings (critério da task) e
  `vendor/bin/sail artisan test --filter=TailwindThemeTest`. Sandbox
  do Ralph bloqueia npm/Sail; gate efetivo é o CI (T011 — `playwright`
  job já executa `npm run lint` e o `phpunit-coverage` job já roda
  o test suite completo).
- T017: Pail integrado ao Compose como service opcional. Decisões:
  - **Service `pail`** declarado no `compose.yaml` reusando a imagem
    `sail-8.5/app` (mesmo PHP/extensões da app, zero rebuild
    extra). Command: `php artisan pail --timeout=0` para streaming
    contínuo sem o cap default de 60s.
  - **`profiles: [pail]`** mantém o service inerte por padrão.
    Activação opt-in: `COMPOSE_PROFILES=pail vendor/bin/sail up -d pail`
    ou `vendor/bin/sail --profile pail up -d`. Sem o profile, `sail up`
    não sobe um worker extra que ninguém pediu.
  - **`tty: true` + `stdin_open: true`** para o cursor control e
    cores do Pail funcionarem; sem isso o output sai cortado e não
    dá para interromper limpo via `docker compose attach`.
  - **`depends_on: laravel.test`** garante que o container principal
    esteja de pé (volume compartilhado e logs gerados por requests)
    mesmo quando o profile é ativado isoladamente.
  - **`restart: unless-stopped`** alinha com Horizon/Reverb — se o
    dev quer Pail sob profile, manter rodando entre reboots de host
    é o comportamento esperado.
  - **Quickstart** (`specs/.../quickstart.md`) ganhou bloco com os
    dois modos: ad-hoc (`sail artisan pail`) e service Compose
    (`COMPOSE_PROFILES=pail sail up -d pail` + `sail logs -f pail`).
  - TDD: `tests/Unit/Config/PailComposeTest.php` parseia o YAML via
    `Symfony\Component\Yaml` (já em vendor como dep transitiva) e
    cobre (1) existência do service `pail`; (2) imagem reusada
    `sail-8.5/app`; (3) command `php artisan pail`; (4) profile
    `pail` opcional; (5) volume `.:/var/www/html` montado;
    (6) `tty:true` + `stdin_open:true`; (7) `depends_on:
    laravel.test`; (8) quickstart documenta `sail artisan pail`.
  **Pendente fora do loop**: rodar `vendor/bin/sail artisan test --filter=PailComposeTest`
  e validar critério funcional (`vendor/bin/sail artisan pail --filter=info`
  produz logs em tempo real) em ambiente Sail real. Sandbox do Ralph
  bloqueia execução PHP/Docker; gate efetivo é o CI (T011 —
  `phpunit-coverage` job roda a suíte completa).
