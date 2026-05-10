<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AcceptInvitationRequest;
use App\Http\Requests\Users\CreateInvitationRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\Users\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Controller de convites de usuário (US-2.2 — FR-025).
 *
 * Pipeline: Request → Controller → InvitationService → Resource.
 * Exceções (PlanLimitReachedException, InvalidInvitationException)
 * são mapeadas para JSON no bootstrap/app.php.
 *
 * @group Users
 */
final class InvitationsController extends Controller
{
    /**
     * Listar convites pendentes.
     *
     * Retorna apenas convites com `status=pending` do tenant atual.
     *
     * @response 200 scenario="convites pendentes" [{"id": 1, "email": "novo@clinica.com.br", "intended_role": "medico"}]
     */
    public function index(Request $request): ResourceCollection
    {
        Gate::authorize('viewAny', Invitation::class);

        $tenant = app('tenant');

        $invitations = Invitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->with('inviter')
            ->orderByDesc('created_at')
            ->get();

        return InvitationResource::collection($invitations);
    }

    /**
     * Enviar convite de novo usuário interno.
     *
     * Dispara e-mail ao convidado. Retorna 409 se o limite de usuários do
     * plano foi atingido.
     *
     * @return JsonResponse 201 com InvitationResource.
     *
     * @response 201 scenario="convite enviado" {"id": 1, "email": "novo@clinica.com.br"}
     * @response 409 scenario="limite atingido" {"message": "Limite de usuários do plano atingido."}
     */
    public function store(
        CreateInvitationRequest $request,
        InvitationService $service,
    ): JsonResponse {
        $tenant = app('tenant');
        $invitation = $service->createInvitation(
            tenant: $tenant,
            inviter: $request->user(),
            email: $request->validated('email'),
            role: $request->validated('intended_role'),
        );

        $invitation->load('inviter');

        return InvitationResource::make($invitation)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Revogar convite pendente.
     *
     * Retorna 410 se o convite já foi aceito ou expirou.
     *
     * @return JsonResponse 204 No Content.
     *
     * @response 204 scenario="revogado"
     * @response 410 scenario="convite expirado" {"message": "Convite já aceito ou expirado."}
     */
    public function destroy(
        int $id,
        InvitationService $service,
    ): JsonResponse {
        Gate::authorize('delete', Invitation::class);

        $tenant = app('tenant');

        $invitation = Invitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $service->revokeInvitation($invitation);

        return response()->json(null, 204);
    }

    /**
     * Aceitar convite e definir senha.
     *
     * Endpoint público (token vem no e-mail). O tenant é resolvido pelo
     * subdomínio. Token de outro tenant retorna 410.
     *
     * @unauthenticated
     *
     * @return JsonResponse 200 com AuthenticatedUserResource.
     *
     * @response 200 scenario="usuário ativado" {"id": 1, "name": "Dr. Novo", "email": "novo@clinica.com.br"}
     * @response 410 scenario="token inválido" {"message": "Token expirado, inválido ou de outro tenant."}
     */
    public function accept(
        AcceptInvitationRequest $request,
        InvitationService $service,
    ): JsonResponse {
        $tenant = app('tenant');

        $user = $service->acceptInvitation(
            tokenClaro: $request->validated('token'),
            name: $request->validated('name'),
            password: $request->validated('password'),
            tenant: $tenant,
        );

        return AuthenticatedUserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }
}
