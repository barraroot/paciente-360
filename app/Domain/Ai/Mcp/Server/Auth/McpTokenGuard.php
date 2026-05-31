<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Server\Auth;

use App\Domain\Ai\Mcp\Sandbox\SandboxContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * **T041 (Fase 18 — US7, FR-046/050)** — middleware de autenticação do servidor
 * MCP local.
 *
 * Cada chamada (inclusive `tools/list`) MUST:
 *  1. carregar um Sanctum PAT via header `Authorization: Bearer <token>`;
 *  2. ter a ability `mcp.invoke`;
 *  3. carregar um `tenant_id` (na coluna `personal_access_tokens.tenant_id`
 *     adicionada por T042) — `tenant_id` NUNCA é input do cliente.
 *
 * Popula o container global com a `Tenant` resolvida e (opcionalmente) com
 * o `SandboxContext` quando o token foi emitido em modo sandbox
 * (`abilities` contém `mcp.sandbox` — usado por Persona Test Session).
 */
final class McpTokenGuard
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return $this->deny('auth_missing', 401);
        }

        $token = PersonalAccessToken::findToken($bearer);
        if ($token === null) {
            return $this->deny('auth_invalid', 401);
        }

        if (! $token->can('mcp.invoke')) {
            return $this->deny('permission_denied', 403);
        }

        $tenantId = $token->tenant_id;
        if ($tenantId === null) {
            return $this->deny('tenant_required', 403);
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return $this->deny('tenant_required', 403);
        }

        // Popula contexto global de tenant — services com BelongsToTenant
        // global scope passam a filtrar automaticamente.
        app()->instance('tenant', $tenant);

        // Sandbox propagation (FR-040/041) — Persona Test Session emite tokens
        // com ability extra `mcp.sandbox`; capabilities de escrita neutralizam.
        if ($token->can('mcp.sandbox')) {
            SandboxContext::enable($token->id);
        }

        // Atualiza last_used_at (auditoria/expiração).
        $token->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('mcp.token', $token);
        $request->attributes->set('mcp.tenant_id', $tenantId);

        return $next($request);
    }

    private function deny(string $code, int $status): JsonResponse
    {
        return response()->json([
            'error' => $code,
            'error_description' => match ($code) {
                'auth_missing' => 'Authorization Bearer token required.',
                'auth_invalid' => 'Invalid or expired Bearer token.',
                'permission_denied' => 'Token does not have ability `mcp.invoke`.',
                'tenant_required' => 'Token must carry a tenant claim.',
                default => 'Unauthorized.',
            },
        ], $status);
    }
}
