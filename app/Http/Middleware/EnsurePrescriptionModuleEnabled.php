<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrescriptionModuleEnabled
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('tenant')) {
            return $next($request);
        }

        $tenant = app('tenant');
        $enabled = (bool) data_get($tenant->settings ?? [], 'modules.prescriptions.enabled', false);

        if (! $enabled) {
            return response()->json([
                'error' => 'prescription_module_disabled',
                'message' => 'O modulo de receituarios esta temporariamente desabilitado para este tenant.',
            ], 503);
        }

        return $next($request);
    }
}
