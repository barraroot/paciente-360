<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\ImpersonationService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se uma sessão de impersonação expirou (T286).
 *
 * Quando a sessão expira (> 60 minutos), força `ImpersonationService::stop()`
 * restaurando o Super Admin original. Deve ser aplicado em rotas autenticadas.
 */
final class EnforceImpersonationExpiry
{
    public function __construct(
        private readonly ImpersonationService $impersonationService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('impersonation')) {
            return $next($request);
        }

        /** @var array{original_user_id: int, target_user_id: int, expires_at: string} $impersonation */
        $impersonation = $request->session()->get('impersonation');

        $expiresAt = Carbon::parse($impersonation['expires_at']);

        if (now()->greaterThan($expiresAt)) {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();

            if ($currentUser !== null) {
                $this->impersonationService->stop($currentUser);
            }
        }

        return $next($request);
    }
}
