<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\Auth\AuthenticationService;

/**
 * Controller de login de usuário interno (US-2.1 — FR-021).
 *
 * Responsabilidade única: delegar ao `AuthenticationService` e retornar
 * o Resource. Exceções são mapeadas para JSON no Handler (bootstrap/app.php).
 *
 * @group Auth
 *
 * @see App\Services\Auth\AuthenticationService
 * @see App\Http\Resources\AuthenticatedUserResource
 */
final class LoginController extends Controller
{
    /**
     * Login de usuário interno.
     *
     * Estabelece sessão Sanctum (cookie HttpOnly + CSRF). O tenant é
     * resolvido pelo subdomínio antes deste controller executar.
     *
     * @unauthenticated
     *
     * @response 200 scenario="sucesso" {"user": {"id": 1, "name": "Dr. Exemplo", "email": "dr@clinica.com.br"}}
     * @response 401 scenario="credenciais inválidas" {"message": "Credenciais inválidas."}
     * @response 423 scenario="bloqueado" {"message": "Conta bloqueada temporariamente."}
     */
    public function __invoke(
        LoginRequest $request,
        AuthenticationService $auth,
    ): AuthenticatedUserResource {
        $user = $auth->attemptLogin($request);

        return AuthenticatedUserResource::make($user);
    }
}
