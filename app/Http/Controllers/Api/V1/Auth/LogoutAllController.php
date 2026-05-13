<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Domain\Auth\Services\TokenRevocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controller de logout de todos os tokens Bearer do usuário (Lote D — T045).
 *
 * Revoga TODOS os Personal Access Tokens do usuário autenticado, incluindo
 * o token corrente. Útil para "sair de todos os dispositivos" (FR-004a).
 *
 * Cada token revogado dispara `TokenRevogado` (Auditable) com motivo
 * `MotivoRevogacaoToken::LogoutAll` via `TokenRevocationService::revokeAll`.
 *
 * @group Auth
 *
 * @see App\Domain\Auth\Services\TokenRevocationService
 * @see specs/004-token-auth-migration/spec.md §FR-004a
 */
final class LogoutAllController extends Controller
{
    /**
     * Revogar todos os tokens Bearer do usuário.
     *
     * @response 204 scenario="sucesso"
     */
    public function __invoke(Request $request, TokenRevocationService $svc): Response
    {
        $svc->revokeAll($request->user(), MotivoRevogacaoToken::LogoutAll);

        return response()->noContent();
    }
}
