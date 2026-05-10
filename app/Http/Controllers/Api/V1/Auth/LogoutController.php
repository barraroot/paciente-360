<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Events\Auth\LogoutSucceeded;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Controller de logout (US-2.1 — FR-028).
 *
 * Dispara evento auditável antes de invalidar a sessão (o listener precisa
 * do `user_id` enquanto o usuário ainda está autenticado no guard).
 *
 * Retorna 204 No Content conforme contrato OpenAPI.
 *
 * @group Auth
 */
final class LogoutController extends Controller
{
    /**
     * Encerrar sessão atual.
     *
     * Invalida a sessão Sanctum e regenera o CSRF token.
     *
     * @response 204 scenario="sucesso"
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user !== null) {
            Event::dispatch(new LogoutSucceeded($user));
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
