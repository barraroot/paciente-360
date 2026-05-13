<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Services\TokenRevocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controller de logout do token Bearer corrente (Lote D — T044).
 *
 * Substitui o controller cookie/session-based da Fase 0. Revoga apenas
 * o token Bearer usado na request corrente, preservando outros tokens
 * do mesmo usuário (ex.: outros dispositivos).
 *
 * Para revogar TODOS os tokens, usar `LogoutAllController`.
 *
 * Dispara `TokenRevogado` (Auditable) via `TokenRevocationService::revokeCurrent`.
 *
 * @group Auth
 *
 * @see App\Domain\Auth\Services\TokenRevocationService
 * @see App\Http\Controllers\Api\V1\Auth\LogoutAllController
 * @see specs/004-token-auth-migration/spec.md §FR-004
 */
final class LogoutController extends Controller
{
    /**
     * Revogar apenas o token Bearer corrente.
     *
     * @response 204 scenario="sucesso"
     */
    public function __invoke(Request $request, TokenRevocationService $svc): Response
    {
        $svc->revokeCurrent($request->user());

        return response()->noContent();
    }
}
