<?php

namespace App\Http\Middleware;

use App\Events\TenantResolved;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `ResolveTenant` (T042 — Princípio II).
 *
 * Estratégia:
 *  1. Lê `Host` do request.
 *  2. Se o host está em `config('tenancy.public_hosts')` (ex.:
 *     `crm.lvh.me`, `admin.lvh.me`, `www.lvh.me`), passa direto sem
 *     resolver tenant — rota pública (cadastro, painel super admin).
 *  3. Caso contrário, extrai o `slug` (parte antes do primeiro
 *     `.{subdomain_suffix}`) e busca `Tenant` correspondente. Se não
 *     existir, retorna 404 (subdomínio inválido).
 *  4. Bind `app('tenant')` e dispara `TenantResolved` para listeners
 *     (cache prefix, Sentry tag, etc.).
 *
 * O middleware NÃO confia em headers `X-Tenant` ou query params
 * (Princípio II — tenant resolvido SEMPRE pelo subdomínio).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $suffix = (string) config('tenancy.subdomain_suffix', 'lvh.me');
        $publicHosts = (array) config('tenancy.public_hosts', []);

        $slug = $this->extractSlug($host, $suffix);

        // Public host (sem tenant): cadastro, super admin, www, webhooks.
        if ($slug === null || in_array($slug, $publicHosts, true)) {
            return $next($request);
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            abort(404, 'Tenant not found.');
        }

        app()->instance('tenant', $tenant);

        TenantResolved::dispatch($tenant);

        return $next($request);
    }

    /**
     * Extrai o slug do host, dado o sufixo público. Retorna `null`
     * quando o host não termina com o sufixo (ex.: requests internos
     * para `localhost`, `127.0.0.1` ou hosts não cobertos).
     */
    private function extractSlug(string $host, string $suffix): ?string
    {
        // Casos sem subdomínio (ex.: `lvh.me`, `localhost`) — sem
        // tenant; tratado como público.
        if ($host === $suffix || $host === 'localhost' || $host === '127.0.0.1') {
            return null;
        }

        $needle = '.'.$suffix;
        $pos = strpos($host, $needle);

        if ($pos === false) {
            return null;
        }

        $slug = substr($host, 0, $pos);

        return $slug !== '' ? strtolower($slug) : null;
    }
}
