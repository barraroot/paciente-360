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
 *
 * @see specs/001-fundacao-multitenant/spec.md — RNF-009 (rate limit por tenant)
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
    }
}
