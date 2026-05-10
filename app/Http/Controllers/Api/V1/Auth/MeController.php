<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;

/**
 * Controller do endpoint /me — retorna o usuário autenticado com permissões.
 *
 * Permissões efetivas são calculadas pelo `AuthenticatedUserResource` via
 * `getAllPermissions()`, que respeita o team mode do Spatie (tenant_id como
 * permissions_team_id, setado pelo listener `Authenticated`).
 *
 * @group Auth
 */
final class MeController extends Controller
{
    /**
     * Usuário autenticado e permissões efetivas.
     *
     * Retorna o perfil do usuário logado com todas as permissões calculadas
     * para o tenant atual (Spatie team mode).
     *
     * @response 200 scenario="sucesso" {"id": 1, "name": "Dr. Exemplo", "email": "dr@clinica.com.br", "permissions": ["users.invite"]}
     */
    public function __invoke(Request $request): AuthenticatedUserResource
    {
        return AuthenticatedUserResource::make($request->user());
    }
}
