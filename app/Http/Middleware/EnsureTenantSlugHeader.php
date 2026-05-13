<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida o header `X-Tenant-Slug` nas rotas autenticadas (T027 — NC-1 / FR-011).
 *
 * Responsabilidades:
 *  1. Header ausente → 400 `tenant_header_required` (exceto allow-list abaixo).
 *  2. Slug inválido (tenant não encontrado) → 404.
 *  3. Triple-check anti-token-roubo cross-tenant: `$user->tenant_id !== $tenant->id` → 403 (Princípio II / amendment v1.4.0).
 *
 * Allow-list (rotas que NÃO exigem o header):
 *  - `api/v1/auth/login` (antes de autenticar — resolve tenant por email)
 *  - `api/v1/auth/me`   (SPA chama sem tenant ainda armazenado após boot; server resolve por token)
 *
 * NOTA: Este middleware NÃO rebinda o tenant no container — o `ResolveTenant`
 * upstream já fez isso via subdomínio ou header. Este middleware apenas
 * valida a consistência do header com o usuário autenticado.
 *
 * Posição na pilha: após `auth:sanctum` (usuário autenticado disponível).
 *
 * @see specs/004-token-auth-migration/spec.md §FR-011, §NC-1
 * @see specs/004-token-auth-migration/plan.md §Princípio II (triple-check)
 */
class EnsureTenantSlugHeader
{
    /**
     * Padrões de path que dispensam o header `X-Tenant-Slug`.
     * Usado via `str_contains` contra `$request->path()`.
     *
     * @var list<string>
     */
    private const ALLOW_LIST = [
        'api/v1/auth/login',
        'api/v1/auth/me',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAllowListed($request)) {
            return $next($request);
        }

        $slug = $request->header('X-Tenant-Slug');

        if ($slug === null || $slug === '') {
            return response()->json([
                'error' => 'tenant_header_required',
                'message' => 'O header X-Tenant-Slug é obrigatório nesta rota.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null) {
            return response()->json([
                'error' => 'tenant_not_found',
                'message' => 'Tenant não encontrado para o slug informado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();

        if ($user !== null && (int) $user->tenant_id !== (int) $tenant->id) {
            Log::warning('auth.tenant_mismatch', [
                'user_id' => $user->getAuthIdentifier(),
                'user_tenant_id' => $user->tenant_id,
                'requested_slug' => $slug,
                'requested_tenant_id' => $tenant->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'tenant_mismatch',
                'message' => 'O tenant informado não corresponde ao usuário autenticado.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Verifica se o path da request está na allow-list.
     */
    private function isAllowListed(Request $request): bool
    {
        $path = $request->path();

        foreach (self::ALLOW_LIST as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
