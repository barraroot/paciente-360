<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use App\Domain\Ai\Guardrail\Services\AiGuardrailService;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StoreGuardrailRequest;
use App\Http\Requests\Ai\SyncPersonaGuardrailsRequest;
use App\Http\Requests\Ai\UpdateGuardrailRequest;
use App\Http\Resources\Ai\AiGuardrailResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiGuardrailController extends Controller
{
    public function __construct(private readonly AiGuardrailService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ai.guardrail.view');

        $query = AiGuardrail::query()
            ->withCount('personas')
            ->orderByDesc('created_at');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $request->input('category'));
        }

        return AiGuardrailResource::collection($query->get());
    }

    public function store(StoreGuardrailRequest $request): JsonResponse
    {
        $guardrail = $this->service->create(
            data: $request->validated(),
            tenantId: app('tenant')->id,
            userId: $request->user()?->id,
        );

        return (new AiGuardrailResource($guardrail))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AiGuardrail $guardrail): AiGuardrailResource
    {
        Gate::authorize('ai.guardrail.view');

        $guardrail->loadCount('personas');

        return new AiGuardrailResource($guardrail);
    }

    public function update(UpdateGuardrailRequest $request, AiGuardrail $guardrail): AiGuardrailResource
    {
        $guardrail = $this->service->update(
            guardrail: $guardrail,
            data: $request->validated(),
            userId: $request->user()?->id,
        );

        return new AiGuardrailResource($guardrail->loadCount('personas'));
    }

    public function destroy(AiGuardrail $guardrail): JsonResponse
    {
        Gate::authorize('ai.guardrail.manage');

        $guardrail->delete();

        return response()->json(['message' => __('ai.guardrail.deleted')]);
    }

    public function activate(Request $request, AiGuardrail $guardrail): AiGuardrailResource
    {
        Gate::authorize('ai.guardrail.manage');

        $this->service->activate($guardrail, $request->user()?->id);

        return new AiGuardrailResource($guardrail);
    }

    public function deactivate(Request $request, AiGuardrail $guardrail): AiGuardrailResource
    {
        Gate::authorize('ai.guardrail.manage');

        $this->service->deactivate($guardrail, $request->user()?->id);

        return new AiGuardrailResource($guardrail);
    }

    /**
     * Associa/desassocia guardrails a uma persona (co-tenancy G2). O global
     * scope de tenant garante que `{persona}` e os guardrails sejam do tenant.
     */
    public function syncPersona(SyncPersonaGuardrailsRequest $request, AiPersona $persona): AnonymousResourceCollection
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->validated('guardrail_ids'))));

        $persona->guardrails()->sync($persona->pivotTenantMap($ids));

        return AiGuardrailResource::collection($persona->guardrails()->get());
    }
}
