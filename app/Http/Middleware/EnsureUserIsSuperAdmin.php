<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that blocks non-super-admin users from accessing the Filament admin panel.
 *
 * Unauthenticated users are left to Filament's built-in redirect-to-login behavior.
 * Authenticated users without the `super-admin` role get a 403.
 */
final class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! method_exists($user, 'hasRole') || ! $user->hasRole('super-admin')) {
            abort(403, 'Acesso restrito ao Super Admin.');
        }

        return $next($request);
    }
}
