<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T097 (Fase 8 — Lote B US-12.1)** — Gate de perfil Super Admin.
 *
 * Valida 2 condições simultâneas:
 *   1. Usuário autenticado tem role `super-admin`.
 *   2. Usuário NÃO pertence a nenhum tenant (`tenant_id IS NULL`).
 *
 * Princípio II — Super Admin é entidade global, não tenant-scoped.
 *
 * Aplicação esperada:
 *   - Rotas administrativas da API (`/api/v1/super-admin/*`).
 *   - Triggers de impersonate (que ocorrem via API call do Filament).
 *
 * **Não aplicado ao Filament panel diretamente** — Filament usa Policy/Gate
 * via `canAccessPanel()` em UserResource (não middleware).
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Não autenticado.');
        }

        if ($user->tenant_id !== null) {
            abort(403, 'Acesso restrito ao Super Admin (tenant_id deve ser NULL).');
        }

        if (! $user->hasRole('super-admin')) {
            abort(403, 'Acesso restrito ao perfil super-admin.');
        }

        return $next($request);
    }
}
