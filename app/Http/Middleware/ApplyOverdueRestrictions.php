<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que bloqueia rotas premium quando o tenant está em estado de
 * inadimplência há mais de 7 dias (T188 — FR-014).
 *
 * Regra:
 *  - Se `tenant.restrictions_applied_at` é null → deixa passar.
 *  - Se a rota está em `config('billing.premium_routes')` → 402 com mensagem.
 *  - Acesso a login e dados históricos NÃO é bloqueado (Princípio II + FR-014).
 *
 * A lista de `premium_routes` é gerenciada em `config/billing.php` e
 * expandida conforme novas fases introduzem funcionalidades restringíveis.
 */
final class ApplyOverdueRestrictions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('tenant')) {
            return $next($request);
        }

        $tenant = app('tenant');

        if ($tenant->restrictions_applied_at === null) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        $premiumRoutes = (array) config('billing.premium_routes', []);

        if ($routeName !== null && in_array($routeName, $premiumRoutes, strict: true)) {
            return response()->json([
                'error' => 'tenant_restricted',
                'message' => __('billing.tenant_restricted'),
            ], 402);
        }

        return $next($request);
    }
}
