<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mascara o header `Authorization: Bearer <token>` nos logs estruturados (T029).
 *
 * Posição na pilha: ANTES de `LogStructuredRequestData` (ou aplicado no grupo
 * `api` junto a ele). Garante que nenhum token Bearer apareça em logs,
 * audit_logs ou contexto de exceções (Princípio I — LGPD / NC-3 mitigação R1).
 *
 * Estratégia:
 *  - Injeta `Log::shareContext(['authorization_header' => 'Bearer SCRUBBED'])`
 *    para todos os canais de log ao longo do ciclo de vida da request.
 *  - Não modifica o header da request em memória (guard Sanctum ainda lê
 *    o token real para autenticação — este middleware deve vir DEPOIS do guard).
 *
 * Idempotente — chamadas subsequentes sobrescrevem o mesmo contexto.
 *
 * @see App\Http\Middleware\LogStructuredRequestData
 * @see specs/004-token-auth-migration/spec.md §FR-023 (LGPD token opaco)
 */
class MaskAuthorizationInLogs
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('Authorization')) {
            Log::shareContext([
                'authorization_header' => 'Bearer SCRUBBED',
            ]);
        }

        return $next($request);
    }
}
