<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * `RouteServiceProvider` (T051 — Princípio VII: Segurança Operacional).
 *
 * Centraliza a definição de rate limiters nomeados para rotas da API.
 * Separado do `AppServiceProvider` para manter SRP (cada provider com
 * responsabilidade clara) — padrão idêntico ao usado pelo próprio
 * scaffolding do Laravel antes do slim 11+.
 *
 * Limiters definidos:
 *  - `login`           : 5 req/min por IP+host (protege enumeração de credenciais).
 *  - `tenant-register` : 3 req/h por IP (previne criação massiva de tenants).
 *  - `password-forgot` : 3 req/h por e-mail (previne enumeração de contas).
 *  - `api`             : 60 req/min por user_id+tenant_id (autenticado) ou IP+host (anônimo).
 *  - `webhooks`        : sem limite (Stripe/terceiros não devem ser bloqueados).
 *  - `inbox`           : 60 req/min por user_id+tenant_id — endpoints de inbox do painel.
 *  - `widget-public`   : 30 req/min por IP — defesa do widget público (sem auth).
 *  - `webhook-meta`    : 1000 req/min global — defesa contra ataque; Meta/Twilio fazem retry.
 *
 * @see specs/001-fundacao-multitenant/spec.md — RNF-009 (rate limit por tenant)
 * @see specs/003-omnichannel-inbox/spec.md — Princípio VII, RNF-009
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Registra os rate limiters nomeados da aplicação.
     *
     * Estratégia de chave:
     *  - `login`: IP + host do tenant (impede que um atacante de um IP
     *    tente vários slugs em paralelo contornando o limite por slug).
     *  - `api` autenticado: user_id + tenant_id para isolamento correto
     *    entre tenants com alto volume de requests.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by($request->ip().':'.$request->getHost())
                ->response(fn () => response()->json(['error' => 'too_many_requests'], 429)
                    ->header('Retry-After', '60'));
        });

        RateLimiter::for('tenant-register', function (Request $request): Limit {
            return Limit::perHour(3)
                ->by($request->ip())
                ->response(fn () => response()->json(['error' => 'too_many_requests'], 429)
                    ->header('Retry-After', '3600'));
        });

        RateLimiter::for('password-forgot', function (Request $request): Limit {
            return Limit::perHour(3)
                ->by((string) $request->input('email', $request->ip()))
                ->response(fn () => response()->json(['error' => 'too_many_requests'], 429)
                    ->header('Retry-After', '3600'));
        });

        RateLimiter::for('api', function (Request $request): Limit {
            if ($request->user() !== null) {
                return Limit::perMinute(60)
                    ->by($request->user()->id.':'.$request->user()->tenant_id);
            }

            return Limit::perMinute(60)
                ->by($request->ip().':'.$request->getHost());
        });

        RateLimiter::for('webhooks', fn (): Limit => Limit::none());

        RateLimiter::for('import', function (Request $request): Limit {
            $tenantId = app()->bound('tenant') ? app('tenant')->id : 'no-tenant';

            return Limit::perHour(5)->by("{$tenantId}:import");
        });

        RateLimiter::for('export', function (Request $request): Limit {
            $tenantId = app()->bound('tenant') ? app('tenant')->id : 'no-tenant';

            return Limit::perHour(10)->by("{$tenantId}:export");
        });

        // Fase 3 — Omnichannel Inbox (Princípio VII, RNF-009)

        RateLimiter::for('inbox', function (Request $request): Limit {
            $userId = $request->user()?->id ?? 'anon';
            $tenantId = $request->user()?->tenant_id ?? ($request->header('X-Tenant-Id', 'no-tenant'));

            return Limit::perMinute(60)
                ->by("{$userId}:{$tenantId}:inbox")
                ->response(fn () => response()->json(['error' => 'too_many_requests'], 429)
                    ->header('Retry-After', '60'));
        });

        RateLimiter::for('widget-public', function (Request $request): Limit {
            return Limit::perMinute(30)
                ->by($request->ip().':widget')
                ->response(fn () => response()->json(['error' => 'too_many_requests'], 429)
                    ->header('Retry-After', '60'));
        });

        RateLimiter::for('webhook-meta', function (Request $request): Limit {
            return Limit::perMinute(1000)
                ->by('webhook-meta:global')
                ->response(fn () => response()->json(['error' => 'too_many_requests'], 429)
                    ->header('Retry-After', '60'));
        });

        // Feature 018 (T037, FR-008a/b — Q-clarify-5=C) — rate limit anti-abuso
        // 2 camadas para o pipeline inbound. NÃO atende request HTTP — é
        // consumido pelo InboundConversationLimiter (T200, Polish) via
        // RateLimiter::tooManyAttempts / hit, com chave manual. Os defaults
        // vêm de config('messaging.rate.*') para permitir override por env.
        // Janela em minutos é a mesma para ambas (default 10min).
        RateLimiter::for('messaging:inbound:per-conversation', function (mixed $key): Limit {
            return Limit::perMinutes(
                (int) config('messaging.rate.window_minutes', 10),
                (int) config('messaging.rate.per_conversation', 30),
            )->by((string) $key);
        });

        RateLimiter::for('messaging:inbound:per-identifier', function (mixed $key): Limit {
            return Limit::perMinutes(
                (int) config('messaging.rate.window_minutes', 10),
                (int) config('messaging.rate.per_identifier', 100),
            )->by((string) $key);
        });
    }
}
