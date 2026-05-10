<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `LogStructuredRequestData` (T052 — Princípio V: Observabilidade).
 *
 * Adiciona contexto estruturado a cada entrada de log da request:
 *  - `request_id`  : ULID da request (lido do header X-Request-Id ou gerado).
 *  - `tenant_id`   : ID do tenant resolvido pelo ResolveTenant (null em rotas públicas).
 *  - `user_id`     : ID do usuário autenticado (null se anônimo).
 *
 * O contexto é propagado via `Log::shareContext()` (Laravel 11+), que
 * injeta os campos em TODOS os canais de log ao longo do ciclo da request.
 *
 * O `X-Request-Id` é também ecoado na response para facilitar
 * correlação de logs no cliente/APM.
 *
 * Posição na pilha: após `ResolveTenant` (tenant já resolvido), append
 * no grupo 'api' via `bootstrap/app.php`.
 *
 * @see ResolveTenant
 * @see specs/001-fundacao-multitenant/spec.md — RNF-016 (observabilidade estruturada)
 */
class LogStructuredRequestData
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::ulid();

        /** @var int|string|null $tenantId */
        $tenantId = app()->bound('tenant') ? app('tenant')->id : null;

        /** @var int|string|null $userId */
        $userId = Auth::id();

        Log::shareContext([
            'request_id' => $requestId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        // Expõe o request_id no container para que o PersistAuditLogListener
        // (e qualquer outro componente) possa correlacionar logs sem depender
        // do header HTTP diretamente (Princípio V — observabilidade).
        app()->instance('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
