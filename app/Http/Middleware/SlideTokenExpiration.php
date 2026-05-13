<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\SlidingExpirationService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de sliding expiration para Personal Access Tokens (T028 — FR-006 / NC-2).
 *
 * Renovação transparente de `expires_at` quando o token está dentro do buffer
 * de 5 dias antes do vencimento. Atendente ativo nunca expira — apenas quem
 * fica inativo por 30 dias consecutivos é forçado a re-autenticar.
 *
 * Aplicado após `auth:sanctum` — usuário e token já resolvidos.
 * Não renova tokens revogados (eles não chegam aqui — guard já rejeita).
 *
 * Posição na pilha: após `auth:sanctum`, antes de `EnsureTenantSlugHeader`.
 *
 * @see App\Domain\Auth\Services\SlidingExpirationService
 * @see specs/004-token-auth-migration/spec.md §FR-006, §NC-2
 */
class SlideTokenExpiration
{
    public function __construct(
        private readonly SlidingExpirationService $service,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Após processar o request — renova token se dentro do buffer.
        // Só atua em PersonalAccessToken (não em TransientToken de Filament etc.)
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $this->service->renewIfDue($token);
        }

        return $response;
    }
}
