<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Domain\SuperAdmin\Services\ImpersonateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T096 (Fase 8 — Lote B US-12.1)** — Audit trigger (Gate 7).
 *
 * Após o response, registra a tela visitada quando a sessão de impersonate
 * está ativa. Usa `terminate()` para não bloquear a response.
 *
 * Atributos consumidos: `impersonate.session_id` setado pelo
 * {@see ImpersonateContextResolver}.
 */
final class ImpersonateScreenAuditTrigger
{
    public function __construct(
        private readonly ImpersonateService $service,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Encaminha — registro do audit acontece em terminate() (após response enviada).
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->attributes->get('impersonate.active', false)) {
            return;
        }

        $sessionId = $request->attributes->get('impersonate.session_id');

        if (! is_int($sessionId)) {
            return;
        }

        $session = ImpersonateSession::query()->find($sessionId);

        if ($session === null || ! $session->isActive()) {
            return;
        }

        // Captura query params sem corpo de request (Princípio I — sem PII).
        $queryParams = $request->query->all();
        if ($queryParams === []) {
            $queryParams = null;
        }

        try {
            $this->service->recordScreenVisit(
                session: $session,
                route: $request->route()?->getName() ?? 'unknown',
                path: $request->path(),
                method: $request->method(),
                ipAddress: $request->ip() ?? '0.0.0.0',
                queryParams: $queryParams,
            );
        } catch (\Throwable $e) {
            // Falha de audit não pode quebrar request — apenas log.
            \Illuminate\Support\Facades\Log::error('super_admin.audit_screen.record_failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
