<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `EnsureTenantNotSuspended` (T043 — Princípios II e VII).
 *
 * Rejeita requests para tenants em status `suspended`. Super Admin
 * (`tenant_id IS NULL` no User autenticado) atravessa para permitir
 * remediação via painel super admin.
 *
 * Registrado como alias `tenant.not-suspended` em `bootstrap/app.php`;
 * é opt-in por rota — rotas de billing/onboarding aceitam tenant
 * suspenso para regularização (US-1.4).
 */
class EnsureTenantNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('tenant')) {
            return $next($request);
        }

        $tenant = app('tenant');

        if (! is_object($tenant) || ! method_exists($tenant, 'isSuspended')) {
            return $next($request);
        }

        if (! $tenant->isSuspended()) {
            return $next($request);
        }

        $user = $request->user();

        // Super Admin (tenant_id NULL) atravessa para conseguir
        // remediar — Princípio VII (auditabilidade do bypass via logs
        // de acesso).
        if ($user !== null && $user->tenant_id === null) {
            return $next($request);
        }

        return new JsonResponse([
            'error' => 'tenant_suspended',
            'message' => 'Tenant suspended.',
        ], 403);
    }
}
