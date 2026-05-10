<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\Response;

/**
 * Controller de recuperação de senha (US-2.3 — FR-030, FR-031, FR-032).
 *
 * Rotas:
 *  - POST /api/v1/auth/password/forgot  → `forgot()`
 *  - POST /api/v1/auth/password/reset   → `reset()`
 *
 * A validação é feita em Form Requests; a lógica de negócio no
 * `PasswordResetService`. Este controller apenas orquestra as chamadas.
 *
 * @group Auth
 *
 * @see App\Services\Auth\PasswordResetService
 * @see App\Http\Requests\Auth\ForgotPasswordRequest
 * @see App\Http\Requests\Auth\ResetPasswordRequest
 */
final class PasswordController extends Controller
{
    /**
     * Solicitar link de recuperação de senha.
     *
     * Resposta sempre 202, mesmo se o e-mail não existir (FR-032 — anti-enumeração).
     *
     * @unauthenticated
     *
     * @response 202 scenario="solicitação enviada"
     */
    public function forgot(ForgotPasswordRequest $request, PasswordResetService $service): Response
    {
        $service->requestReset($request->validated('email'));

        return response()->noContent(202);
    }

    /**
     * Redefinir senha com token por e-mail.
     *
     * Token inválido ou expirado retorna 410 (via handler global).
     *
     * @unauthenticated
     *
     * @response 204 scenario="senha redefinida"
     * @response 410 scenario="token inválido" {"message": "Token de redefinição inválido ou expirado."}
     */
    public function reset(ResetPasswordRequest $request, PasswordResetService $service): Response
    {
        $service->reset(
            email: $request->validated('email'),
            tokenInClaro: $request->validated('token'),
            newPassword: $request->validated('password'),
        );

        return response()->noContent(204);
    }
}
