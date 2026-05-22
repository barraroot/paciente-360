<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T221 (Fase 8 — Lote D US-11.2)** — 503 tenant_suspended para API pública.
 *
 * Diferente do `EnsureTenantNotSuspended` interno (que retorna 403):
 * para API externa retornamos 503 (Service Unavailable temporário) — sinaliza
 * ao integrador que o problema é do tenant, não da API.
 *
 * Resolve tenant via:
 *   1. `app('tenant')` (raro em api-public — tenant não vem por subdomínio)
 *   2. `$user->tenant` (Sanctum auth)
 *   3. attribute `oauth.tenant_id` (OauthAuthenticator)
 */
class EnsureApiPublicTenantNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant !== null && $this->isSuspendedOrCanceled($tenant)) {
            return new JsonResponse([
                'error' => 'tenant_suspended',
                'message' => 'Clínica suspensa ou cancelada. Contate o suporte.',
            ], 503);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        if (app()->bound('tenant')) {
            $bound = app('tenant');
            if ($bound instanceof Tenant) {
                return $bound;
            }
        }

        $user = $request->user();
        if ($user?->tenant !== null) {
            return $user->tenant;
        }

        if ($request->attributes->has('oauth.tenant_id')) {
            return Tenant::query()->find($request->attributes->get('oauth.tenant_id'));
        }

        return null;
    }

    private function isSuspendedOrCanceled(Tenant $tenant): bool
    {
        $status = $tenant->status ?? null;

        if (in_array($status, ['suspenso', 'suspended', 'cancelado', 'canceled'], true)) {
            return true;
        }

        if (method_exists($tenant, 'isSuspended') && $tenant->isSuspended()) {
            return true;
        }

        if (($tenant->suspended_at ?? null) !== null) {
            return true;
        }

        if (($tenant->canceled_at ?? null) !== null) {
            return true;
        }

        return false;
    }
}
