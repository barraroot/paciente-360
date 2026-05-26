<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\KnowledgeBase\Services\AiKnowledgeBaseService;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StoreKnowledgeBaseRequest;
use App\Http\Requests\Ai\SyncPersonaKnowledgeBasesRequest;
use App\Http\Requests\Ai\UpdateKnowledgeBaseRequest;
use App\Http\Resources\Ai\AiKnowledgeBaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiKnowledgeBaseController extends Controller
{
    public function __construct(private readonly AiKnowledgeBaseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ai.knowledge.view');

        $query = AiKnowledgeBase::query()
            ->withCount(['chunks', 'personas'])
            ->orderByDesc('created_at');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return AiKnowledgeBaseResource::collection($query->get());
    }

    public function store(StoreKnowledgeBaseRequest $request): JsonResponse
    {
        $base = $this->service->create(
            data: $request->validated(),
            tenantId: app('tenant')->id,
            userId: $request->user()?->id,
        );

        return (new AiKnowledgeBaseResource($base))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AiKnowledgeBase $knowledgeBase): AiKnowledgeBaseResource
    {
        Gate::authorize('ai.knowledge.view');

        $knowledgeBase->loadCount(['chunks', 'personas']);

        return new AiKnowledgeBaseResource($knowledgeBase);
    }

    public function update(UpdateKnowledgeBaseRequest $request, AiKnowledgeBase $knowledgeBase): AiKnowledgeBaseResource
    {
        $base = $this->service->update(
            base: $knowledgeBase,
            data: $request->validated(),
            userId: $request->user()?->id,
        );

        return new AiKnowledgeBaseResource($base->loadCount(['chunks', 'personas']));
    }

    public function destroy(AiKnowledgeBase $knowledgeBase): JsonResponse
    {
        Gate::authorize('ai.knowledge.manage');

        $knowledgeBase->delete();

        return response()->json(['message' => __('ai.knowledge_base.deleted')]);
    }

    public function activate(Request $request, AiKnowledgeBase $knowledgeBase): AiKnowledgeBaseResource
    {
        Gate::authorize('ai.knowledge.manage');

        $this->service->activate($knowledgeBase, $request->user()?->id);

        return new AiKnowledgeBaseResource($knowledgeBase);
    }

    public function deactivate(Request $request, AiKnowledgeBase $knowledgeBase): AiKnowledgeBaseResource
    {
        Gate::authorize('ai.knowledge.manage');

        $this->service->deactivate($knowledgeBase, $request->user()?->id);

        return new AiKnowledgeBaseResource($knowledgeBase);
    }

    /**
     * Associa/desassocia bases a uma persona (co-tenancy G2). O global scope de
     * tenant garante que `{persona}` e as bases sejam do tenant atual.
     */
    public function syncPersona(SyncPersonaKnowledgeBasesRequest $request, AiPersona $persona): AnonymousResourceCollection
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->validated('knowledge_base_ids'))));

        $persona->knowledgeBases()->sync($persona->pivotTenantMap($ids));

        return AiKnowledgeBaseResource::collection(
            $persona->knowledgeBases()->withCount(['chunks'])->get()
        );
    }
}
