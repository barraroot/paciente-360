<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T028** (Fase 5) — Feature flag opcional para o módulo Agenda.
 *
 * Quando `config('features.agenda_module') === false`, retorna 503 com payload
 * estruturado `{ error: 'agenda_module_disabled' }`. Default = true (módulo
 * habilitado). Permite desligar a fase em runtime sem rollback de DB
 * (quickstart §11.2).
 */
class EnsureAgendaModuleEnabled
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('features.agenda_module', true)) {
            return response()->json([
                'error' => 'agenda_module_disabled',
                'message' => 'O módulo de agenda está temporariamente desabilitado.',
            ], 503);
        }

        return $next($request);
    }
}
