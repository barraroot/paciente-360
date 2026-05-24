<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Professionals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Professionals\StoreProfessionalRequest;
use App\Http\Requests\Professionals\UpdateProfessionalRequest;
use App\Http\Resources\Professionals\ProfessionalResource;
use App\Models\Professional;
use App\Models\User;
use App\Services\Professionals\ProfessionalInvitationService;
use App\Services\Professionals\ProfessionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * **T017 (Spec 012)** — CRUD interno de Professional para o painel do tenant.
 *
 * Endpoints:
 *   - GET    /api/v1/professionals               (lista com filtros)
 *   - POST   /api/v1/professionals               (cria — vincular ou convidar)
 *   - GET    /api/v1/professionals/{id}          (show)
 *   - PUT    /api/v1/professionals/{id}          (update — campos editáveis)
 *   - DELETE /api/v1/professionals/{id}          (desativa + soft-delete)
 *
 * Permission gate: `professional.manage` (apenas admin-clinica).
 * Isolamento multi-tenant: BelongsToTenant scope no Professional model.
 *
 * @see specs/012-professionals-management/contracts/api-professionals.md
 */
final class ProfessionalsController extends Controller
{
    public function __construct(
        private readonly ProfessionalService $service,
        private readonly ProfessionalInvitationService $inviteService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('professional.manage');

        $isActive = $request->string('is_active', 'true')->value();
        $search = $request->string('search')->value();
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        $query = Professional::query()->with('user');

        if ($isActive === 'true') {
            $query->where('is_active', true)->whereNull('deleted_at');
        } elseif ($isActive === 'false') {
            $query->where(function ($q) {
                $q->where('is_active', false)->orWhereNotNull('deleted_at');
            })->withTrashed();
        } else {
            $query->withTrashed();
        }

        if ($search !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $query->orderBy('name');

        return ProfessionalResource::collection($query->cursorPaginate($perPage));
    }

    public function store(StoreProfessionalRequest $request): JsonResponse
    {
        Gate::authorize('professional.manage');

        $data = $request->validated();
        $actor = $request->user();

        // Modo "vincular a user existente" — direto.
        if (! empty($data['user_id'])) {
            $professional = $this->service->create($data, $actor);

            return ProfessionalResource::make($professional)->response()->setStatusCode(201);
        }

        // Modo "convite por email" — delega ao InvitationService.
        // O service trata Q2 (confirmação se email já é user) e Princípio II
        // (cross-tenant email bloqueado).
        return $this->inviteService->createWithInvite($data, $actor);
    }

    public function show(Professional $professional): ProfessionalResource
    {
        Gate::authorize('professional.manage');

        return ProfessionalResource::make($professional->load('user'));
    }

    public function update(UpdateProfessionalRequest $request, Professional $professional): ProfessionalResource
    {
        Gate::authorize('professional.manage');

        $professional = $this->service->update($professional, $request->validated(), $request->user());

        return ProfessionalResource::make($professional);
    }

    public function destroy(Professional $professional, Request $request): JsonResponse
    {
        Gate::authorize('professional.manage');

        $this->service->deactivate($professional, $request->user());

        return response()->json(null, 204);
    }
}
