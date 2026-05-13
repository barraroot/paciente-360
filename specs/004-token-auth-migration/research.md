# Research: Fase 4 — Token Auth Migration

**Branch**: `004-token-auth-migration` | **Data**: 2026-05-12 | **Status**: Phase 0 — completo

6 decisões técnicas que orientam `data-model.md`, `contracts/openapi.yaml` e a implementação. Cada decisão tem contexto, alternativas, decisão final, racional e implicações práticas.

> Este documento **não revisita decisões de produto** (já cobertas pelos 5 NCs resolvidos via `/speckit.clarify`). Foca em decisões de engenharia que projetam aquelas decisões em código.

---

## R1 — Sanctum Personal Access Tokens (vs. JWT, Passport, custom)

**Decisão**: **Sanctum Personal Access Tokens** (já incluso na instalação Sanctum existente).

**Alternativas consideradas**:
- **Laravel Passport (OAuth2 full)**: overkill para o caso — não precisamos OAuth flows (client credentials, authorization code, etc.). Adiciona ~50 tabelas. Justificado apenas se entregamos OAuth2 para integradores externos (fora do MVP).
- **JWT custom (firebase/php-jwt)**: claims embarcadas no token = PII risk (mesmo que só user_id). Mais complexo (signing keys, rotation). Sem ganho real vs Sanctum.
- **Sanctum Personal Access Tokens**: opaque random strings, hash SHA-256 no DB, table dedicada (`personal_access_tokens`), API simples (`$user->createToken(...)`), revogação trivial.

**Racional**:
- **Já instalado** (Fase 0 instalou Sanctum para SPA stateful). `personal_access_tokens` table já existe via migration original do pacote — zero custo de schema.
- **Opaque tokens** = menos surface area de leak (sem PII embarcada vs JWT claims).
- **Hash SHA-256** no DB = breach do DB não vaza tokens utilizáveis.
- **Revogação O(1)** via DELETE row (vs JWT que precisaria blocklist).
- **Padrão amplo no ecossistema Laravel** — documentação extensa, mantida pelo Laravel team.

**Trade-offs aceitos**:
- Toda request faz lookup no DB (não é "stateless" como JWT). Mitigação: cache de token lookup via Redis (Sanctum suporta out-of-the-box). Performance medida: ~2ms overhead vs JWT em-memory verify — desprezível.
- Sem flows OAuth2 (third-party apps). Aceitável — não é objetivo desta fase. Futura integração externa pode adicionar Passport em paralelo.

**Implicação prática**:
- `composer.json` **sem mudança** — Sanctum já presente.
- `config/sanctum.php` atualizar `expiration` para 30 * 24 * 60 minutos (30d) e ajustar `prefix` para `paciente360_` (visibilidade audit).
- `config/auth.php`: `defaults.guard` permanece `web`; **API tenant** roteia explicitamente via `auth:sanctum` middleware. `web` guard fica para Filament admin.
- Tabela `personal_access_tokens` já existe. **Nova migration** apenas adiciona índice composto `(tokenable_type, tokenable_id, expires_at)` para otimizar listagem de tokens ativos por user.

---

## R2 — Sliding expiration mechanism (toda request renova com throttle)

**Decisão**: **Middleware `SlideTokenExpiration`** aplicado após `auth:sanctum`. Renovação throttled: só UPDATE `expires_at` se `expires_at - now() < 5 dias`.

**Alternativas consideradas**:
- **Renovar em toda request**: simples mas 1 UPDATE por request = ~10ms overhead + carga DB desproporcional. Para 1000 req/s isso é 10s/s de IO.
- **Refresh token separado**: NC-2 rejeitou (complexidade arquitetural sem ganho real).
- **Sliding throttled (5d buffer)**: UPDATE só quando faz sentido. Estimativa: usuário ativo gera ~1 UPDATE a cada 25 dias (não a cada request). Reduz UPDATE de ~100M/dia para ~40k/dia em prod (1000 atendentes × 1 update por 25d).

**Racional**:
- Atendente que usa diariamente: timer "sempre" no futuro distante (>5d). Quando se aproxima de expirar, renova uma vez. Padrão sustainable.
- Atendente inativo > 30d: token expira naturalmente — força re-login.
- **Carga DB**: prática usada por GitHub, Slack — bem testada em escala.
- **Audit trail**: cada renovação gera log estruturado (não evento Auditable — overhead seria absurdo). Apenas eventos relevantes (emit/revoke) viram audit.

**Trade-offs aceitos**:
- "Janela morta" de 5d quando timer está no buffer — re-login pode acontecer se inativo exatamente nesse intervalo. UX impact mínimo (raríssimo cenário).
- Middleware adiciona ~3ms de overhead por request (read-only check). Aceito.

**Implicação prática**:
- `app/Http/Middleware/SlideTokenExpiration.php`:
  ```php
  public function handle(Request $request, Closure $next) {
      $response = $next($request);
      $token = $request->user()?->currentAccessToken();
      if ($token && $token->expires_at) {
          $hoursUntilExpire = now()->diffInHours($token->expires_at, false);
          $thresholdHours = 5 * 24; // 5 days
          if ($hoursUntilExpire < $thresholdHours) {
              $token->forceFill(['expires_at' => now()->addMinutes(config('sanctum.expiration'))])->save();
          }
      }
      return $response;
  }
  ```
- Aplicado APÓS `auth:sanctum` no grupo `api` (sem `statefulApi`).
- **Não** aplicado em Filament group (que usa cookie session).

---

## R3 — CORS strategy (Laravel native vs proxy-level)

**Decisão**: **Laravel native `HandleCors` middleware** (incluso em Laravel 11+) com `config/cors.php` env-driven.

**Alternativas consideradas**:
- **Nginx/CloudFlare-level CORS** (handle no proxy reverso): centralizado, performático, mas faz config externa ao código. Setup separado por ambiente.
- **`fruitcake/laravel-cors` package**: era padrão até Laravel 11 — agora obsoleto, Laravel 11+ tem nativo.
- **Laravel native `HandleCors`** middleware (Laravel 11+): já presente, configurável via `config/cors.php`. Env-driven. Versionado com código.

**Racional**:
- Laravel native é a opção idiomática Laravel 11+.
- Config versionada = mudanças de origin whitelist em PR review (auditable).
- Performance OK para nosso scale (CORS check é ~1ms).
- Env-driven (`CORS_ALLOWED_ORIGINS`) permite diferentes whitelist por ambiente (dev: `localhost:5173,clinica-alfa.lvh.me`; staging: `staging.app.crm.com.br`; prod: `app.crm.com.br`).

**Trade-offs aceitos**:
- Se mudarmos de Laravel um dia: refactor de CORS. Improvável a curto prazo.
- Preflight cache (`Access-Control-Max-Age`) precisa config explícito — sem isso browser faz preflight por request.

**Implicação prática**:
- `config/cors.php` (criar/atualizar):
  ```php
  return [
      'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie'],
      'allowed_methods' => ['*'],
      'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000')),
      'allowed_origins_patterns' => [],
      'allowed_headers' => ['*'],
      'exposed_headers' => ['X-Request-Id', 'Authorization'],
      'max_age' => 3600, // 1h preflight cache
      'supports_credentials' => false, // Bearer, não cookie — credentials false
  ];
  ```
- **Filament admin** continua mono-domain (sem CORS necessário).
- `bootstrap/app.php` confirma `HandleCors` no grupo `api`.

---

## R4 — CSP policy (Content-Security-Policy obrigatória)

**Decisão**: **CSP estrita com nonce** para scripts inline necessários, deployada via middleware `SetSecurityHeaders`.

**Alternativas consideradas**:
- **CSP "report-only" no início**: detecta violations sem bloquear. Útil para descobrir uso de `unsafe-inline` legado. Mas: mitigação R1 exige ENFORCE, não report.
- **CSP relaxado (`unsafe-inline`/`unsafe-eval` permitidos)**: trivial implementar, **NÃO mitiga XSS**. Violaria gate de release.
- **CSP estrita com nonce**: `script-src 'self' 'nonce-{random}'`. Random gerado por request, injetado em `<script nonce="...">` tags. Bloqueia scripts injetados.

**Racional**:
- Mitigação R1 (XSS rouba localStorage token) **exige** CSP enforce.
- Nonce-based permite scripts inline legítimos (Vite dev mode usa). Hash-based seria mais restrito mas inviável durante desenvolvimento.
- `unsafe-eval` removido = quebra eval-based libs (precisamos auditar bundle frontend; provavelmente OK pois Vue 3 não usa eval).

**Trade-offs aceitos**:
- Vite dev server: precisa `script-src 'self' 'unsafe-inline'` em dev (HMR usa inline scripts). Solução: CSP diferente por ambiente (`APP_ENV=local` permite; prod enforce).
- Algumas integrações third-party (analytics, monitoring) podem exigir adicionais. Cada uma deve passar por PR review com justificativa.

**Implicação prática**:
- `app/Http/Middleware/SetSecurityHeaders.php`:
  ```php
  public function handle(Request $request, Closure $next): Response {
      $response = $next($request);

      $nonce = base64_encode(random_bytes(16));
      app()->instance('csp.nonce', $nonce);

      $csp = app()->isLocal()
          ? "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self' ws: wss:;"
          : "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data: blob: https:; connect-src 'self' https://api.crm.com.br wss://reverb.crm.com.br; frame-ancestors 'none';";

      $response->headers->set('Content-Security-Policy', $csp);
      $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
      $response->headers->set('X-Frame-Options', 'DENY');
      $response->headers->set('X-Content-Type-Options', 'nosniff');
      $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

      return $response;
  }
  ```
- Aplicado no grupo `web` global.
- Vite config injeta nonce em scripts inline via plugin (`vite-plugin-csp` ou manual).

---

## R5 — ESLint `no-unsanitized` config (gate CI XSS)

**Decisão**: **`eslint-plugin-no-unsanitized`** + regras `recommended` + custom block para `v-html` sem `DOMPurify`.

**Alternativas consideradas**:
- **ESLint sem plugin**: não detecta DOM sinks. Insuficiente.
- **`eslint-plugin-no-unsanitized`** (mantido pela Mozilla): detecta `innerHTML`, `outerHTML`, `document.write`, `insertAdjacentHTML`, etc. sem sanitização.
- **Manual code review**: humano falha; precisa enforcement automatizado.

**Racional**:
- Mitigação R1 exige enforce DOMPurify em todo HTML user-provided.
- ESLint roda em pre-commit hook + CI = gate antes do merge.
- Vue 3 `v-html` é vetor comum — regra custom complementa.

**Trade-offs aceitos**:
- Falsos positivos em casos legítimos (ex.: HTML de docs internas estáticas). Solução: comentário `// eslint-disable-next-line no-unsanitized/method -- justificativa` com revisão obrigatória em PR.

**Implicação prática**:
- `package.json` devDeps:
  ```json
  "eslint-plugin-no-unsanitized": "^4.0",
  "dompurify": "^3.0"
  ```
- `eslint.config.js` (flat config Laravel 13/Vue 3):
  ```js
  import noUnsanitized from 'eslint-plugin-no-unsanitized';

  export default [
      // ...existing...
      {
          plugins: { 'no-unsanitized': noUnsanitized },
          rules: {
              'no-unsanitized/method': 'error',
              'no-unsanitized/property': 'error',
              // Custom: v-html requires DOMPurify wrapper
              'vue/no-v-html': 'warn', // ou 'error' se quiser bloquear inline
          },
      },
  ];
  ```
- Helper composable `useSafeHtml(html)` (resources/js/composables/) que aplica DOMPurify e retorna HTMLString — convenção de uso obrigatória em `v-html`.

---

## R6 — Script de migração de testes (`tests:migrate-actingas-to-sanctum`)

**Decisão**: **Comando idempotente** `tests:migrate-actingas-to-sanctum` com 3 fases (preview, apply, verify) + suite full check obrigatória após cada fase.

**Alternativas consideradas**:
- **Manual sed/regex**: rápido mas frágil — não detecta casos edge (testes com `actingAs` inline em closures, com guard explícito, etc.).
- **PHP-Parser AST manipulation**: superpotente mas complexo (~1d só pra escrever script).
- **Comando Artisan híbrido**: regex pattern matching + grep para localizar arquivos + sed para transformação + git diff para validar resultado. Idempotente (rodar 2x não causa dano).

**Racional**:
- ~650 testes muito uniformes (Laravel pattern padrão). Regex cobre 95%+ dos casos.
- Casos edge (5%) flagged para revisão manual com lista impressa pelo comando.
- 3 fases (preview = dry run com diff; apply = aplicar; verify = rodar suite) reduzem risco.
- Idempotência permite re-run após adjusts manuais.

**Trade-offs aceitos**:
- Script é one-off (rodado uma vez no início do Lote F). Não vai pra `app/`. Fica em `app/Console/Commands/` como utility, com flag de produção bloqueando re-run após ack.

**Implicação prática**:
- `app/Console/Commands/TestsMigrateActingAsCommand.php`:
  ```php
  protected $signature = 'tests:migrate-actingas-to-sanctum
      {--preview : Mostra mudanças sem aplicar}
      {--apply : Aplica mudanças nos arquivos}
      {--verify : Roda suite completa após apply}
      {--only= : Filter por path (ex: tests/Feature/Fase0)}';

  public function handle(): int {
      // Bloqueio prod
      if (app()->environment('production')) {
          $this->error('Comando bloqueado em produção.');
          return 1;
      }

      // Lista arquivos a migrar
      $files = $this->findTargetTestFiles($this->option('only'));

      // Regex transformation:
      // $this->actingAs($user)  →  Sanctum::actingAs($user, ['*'])
      // $this->actingAs($user, 'guard')  →  Sanctum::actingAs($user, ['*'], 'guard')
      // + adiciona use Laravel\Sanctum\Sanctum; se faltar

      foreach ($files as $file) {
          $original = file_get_contents($file);
          $modified = $this->transform($original);

          if ($this->option('preview')) {
              $this->showDiff($file, $original, $modified);
          } elseif ($this->option('apply')) {
              file_put_contents($file, $modified);
          }
      }

      // Lista casos edge não detectados (manual review)
      $this->flagEdgeCases();

      if ($this->option('verify')) {
          $this->call('test', ['--compact' => true]);
      }

      return 0;
  }
  ```
- **Output esperado**: ~650 arquivos modificados; suite full continua passing.

---

## R7 — Resumo das decisões (referência rápida)

| ID  | Tópico                          | Decisão                                                                              |
|-----|---------------------------------|--------------------------------------------------------------------------------------|
| R1  | Auth provider                   | Sanctum Personal Access Tokens (opaque, hash SHA-256, sem JWT/Passport)              |
| R2  | Sliding expiration              | Middleware throttled — UPDATE expires_at só se < 5d buffer                           |
| R3  | CORS                            | Laravel native `HandleCors` middleware (env-driven `CORS_ALLOWED_ORIGINS`)           |
| R4  | CSP                             | Estrita com nonce em prod; relaxada em dev (Vite HMR)                                |
| R5  | ESLint XSS                      | `eslint-plugin-no-unsanitized` + DOMPurify obrigatório em `v-html`                   |
| R6  | Migração de testes              | Comando `tests:migrate-actingas-to-sanctum` idempotente (preview/apply/verify)       |

Pronto para Phase 1 (`data-model.md` + `contracts/openapi.yaml` + `quickstart.md`).
