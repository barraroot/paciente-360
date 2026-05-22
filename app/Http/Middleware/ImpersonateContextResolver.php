<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\SuperAdmin\Services\ImpersonateService;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T095 (Fase 8 — Lote B US-12.1)** — Resolve contexto de tenant durante impersonate.
 *
 * Quando Super Admin tem sessão ativa em `impersonate_sessions`, este
 * middleware substitui o tenant resolvido pelo `ResolveTenant` da Fase 0
 * pelo tenant alvo da sessão. Permite que toda a SPA do tenant funcione
 * sob a perspectiva do Super Admin sem mudanças de código nos controllers.
 *
 * **Pipeline**: roda APÓS `ResolveTenant` (que precisa ter rodado para que
 * `auth()->user()` esteja resolvido). Sem sessão ativa, o middleware é
 * no-op e o tenant original (`X-Tenant-Slug`) prevalece.
 *
 * **Banner persistente**: o frontend lê `request->attributes->get('impersonate.active')`
 * via header de resposta ou através de endpoint `GET /api/v1/me/impersonate-status`
 * (implementação fica para próximo slice).
 */
final class ImpersonateContextResolver
{
    public function __construct(
        private readonly ImpersonateService $service,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->tenant_id !== null) {
            // Sem auth OU não é Super Admin (que tem tenant_id NULL).
            return $next($request);
        }

        $session = $this->service->activeSessionFor($user);

        if ($session === null) {
            return $next($request);
        }

        // Resolve tenant alvo e injeta no container (substitui o resolvido por ResolveTenant).
        $tenant = Tenant::query()->find($session->tenant_id);

        if ($tenant !== null) {
            app()->instance('tenant', $tenant);
            $request->attributes->set('impersonate.active', true);
            $request->attributes->set('impersonate.session_id', $session->id);
            $request->attributes->set('impersonate.tenant_id', $tenant->id);
        }

        $response = $next($request);

        // Adiciona header para o frontend identificar a sessão e exibir o banner.
        $response->headers->set('X-Impersonate-Active', '1');
        $response->headers->set('X-Impersonate-Session-Id', (string) $session->id);

        return $response;
    }
}
