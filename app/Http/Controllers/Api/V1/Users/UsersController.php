<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserPatchRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Controller de usuários internos do tenant (US-2.2 — FR-026–029).
 *
 * Pipeline: Request → Controller → UserService → Resource.
 * Exceções (LastAdminClinicaException) mapeadas para JSON no bootstrap/app.php.
 *
 * @group Users
 */
final class UsersController extends Controller
{
    /**
     * Listar usuários internos do tenant.
     *
     * Suporta filtros por `status` (invited|active|disabled) e `role`.
     *
     * @response 200 scenario="lista" {"data": [{"id": 1, "name": "Dr. Exemplo", "status": "active"}]}
     */
    public function index(Request $request, UserService $service): ResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $tenant = app('tenant');

        $paginator = $service->list($tenant, $request->only(['status', 'role', 'per_page']));

        return UserResource::collection($paginator);
    }

    /**
     * Alterar perfil ou roles do usuário.
     *
     * @return JsonResponse 200 com UserResource.
     *
     * @response 200 scenario="atualizado" {"id": 1, "name": "Dr. Novo Nome", "roles": ["medico"]}
     */
    public function update(
        int $id,
        UserPatchRequest $request,
        UserService $service,
    ): JsonResponse {
        $tenant = app('tenant');

        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $updated = $service->update($user, $request->validated());

        return UserResource::make($updated)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Desativar usuário (soft-delete; preserva auditoria).
     *
     * Retorna 409 se for o último Admin Clínica do tenant.
     *
     * @return JsonResponse 204 No Content.
     *
     * @response 204 scenario="desativado"
     * @response 409 scenario="último admin" {"message": "Não é possível desativar o último Admin Clínica."}
     */
    public function destroy(
        int $id,
        UserService $service,
    ): JsonResponse {
        $tenant = app('tenant');

        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        Gate::authorize('delete', $user);

        $service->disable($user);

        return response()->json(null, 204);
    }
}
