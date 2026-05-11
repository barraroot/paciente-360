<?php

namespace App\Http\Controllers\Api\V1\Convenios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convenios\CreateConvenioRequest;
use App\Http\Requests\Convenios\UpdateConvenioRequest;
use App\Http\Resources\V1\ConvenioResource;
use App\Models\Convenio;
use App\Models\Tenant;
use App\Services\Convenios\ConvenioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * T245 — Controller REST para o catálogo de convênios por tenant (US-3.5).
 *
 * Autorização delegada via `Gate::authorize()` + `ConvenioPolicy`.
 * Lógica de negócio delegada ao `ConvenioService`.
 *
 * Endpoints:
 *  - GET  /convenios               — listagem com filtro `?is_active=`
 *  - POST /convenios               — criação (admin-clinica via Form Request)
 *  - PATCH /convenios/{id}         — atualização (admin-clinica via Form Request)
 *  - DELETE /convenios/{id}        — exclusão (admin-clinica; 409 se em uso)
 */
class ConveniosController extends Controller
{
    public function __construct(
        private readonly ConvenioService $convenioService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Convenio::class);

        $query = Convenio::query();

        // Default: apenas ativos. Parâmetro explícito sobrepõe.
        // Aceita 'true'/'false'/'1'/'0' como query string.
        if ($request->has('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            $query->where('is_active', $isActive);
        } else {
            $query->where('is_active', true);
        }

        return ConvenioResource::collection($query->orderBy('nome')->get());
    }

    public function store(CreateConvenioRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('tenant');

        $convenio = $this->convenioService->create($request->validated(), $tenant);

        return response()->json(['data' => ConvenioResource::make($convenio)->resolve($request)], 201);
    }

    public function update(UpdateConvenioRequest $request, int $id): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);
        Gate::authorize('update', $convenio);

        $convenio = $this->convenioService->update($convenio, $request->validated());

        return response()->json(['data' => ConvenioResource::make($convenio)->resolve($request)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);
        Gate::authorize('delete', $convenio);

        try {
            $this->convenioService->delete($convenio);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Convênio excluído.']);
    }
}
