<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Concerns;

use Illuminate\Http\Request;

/**
 * **T223-T228 helper (Fase 8 — Lote D US-11.2)** — Resolve tenant da request.
 *
 * Para api-public, tenant NÃO vem por subdomain. Vem do user autenticado
 * (Sanctum) ou do JWT decodificado pelo OauthAuthenticator. NUNCA via header
 * `X-Tenant-Slug` ou query — defesa contra cross-tenant attacks (Princípio II).
 */
trait ResolvesApiPublicTenant
{
    protected function tenantId(Request $request): int
    {
        if ($request->attributes->has('oauth.tenant_id')) {
            return (int) $request->attributes->get('oauth.tenant_id');
        }

        $user = $request->user();
        if ($user?->tenant_id === null) {
            abort(403, 'tenant_resolution_failed');
        }

        return (int) $user->tenant_id;
    }

    protected function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
