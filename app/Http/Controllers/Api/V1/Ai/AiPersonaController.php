<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Services\AiPersonaService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StorePersonaRequest;
use App\Http\Requests\Ai\UpdatePersonaRequest;
use App\Http\Resources\Ai\AiPersonaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiPersonaController extends Controller
{
    public function __construct(private readonly AiPersonaService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ai.persona.view');

        $query = AiPersona::query()
            ->with('model')
            ->withCount(['channels', 'knowledgeBases', 'guardrails'])
            ->orderByDesc('created_at');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('channel_type')) {
            $channelType = (string) $request->input('channel_type');
            $query->whereHas('channels', function ($q) use ($channelType): void {
                $q->where('channel_type', $channelType)->where('is_active', true);
            });
        }

        return AiPersonaResource::collection($query->get());
    }

    public function store(StorePersonaRequest $request): JsonResponse
    {
        $persona = $this->service->create(
            data: $request->safe()->except(['is_active']),
            tenantId: app('tenant')->id,
            userId: $request->user()?->id,
        );

        return (new AiPersonaResource($persona->load('model')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AiPersona $persona): AiPersonaResource
    {
        Gate::authorize('ai.persona.view');

        $persona->load(['model', 'knowledgeBases:id'])->loadCount(['channels', 'knowledgeBases', 'guardrails']);

        return new AiPersonaResource($persona);
    }

    public function update(UpdatePersonaRequest $request, AiPersona $persona): AiPersonaResource
    {
        $persona = $this->service->update(
            persona: $persona,
            data: $request->safe()->all(),
            userId: $request->user()?->id,
        );

        return new AiPersonaResource($persona->load('model'));
    }

    public function destroy(Request $request, AiPersona $persona): JsonResponse
    {
        Gate::authorize('ai.persona.manage');

        $persona->delete();

        return response()->json(['message' => __('ai.persona.deleted')]);
    }

    public function activate(Request $request, AiPersona $persona): AiPersonaResource
    {
        Gate::authorize('ai.persona.manage');

        $this->service->activate($persona, $request->user()?->id);

        return new AiPersonaResource($persona->load('model'));
    }

    public function deactivate(Request $request, AiPersona $persona): AiPersonaResource
    {
        Gate::authorize('ai.persona.manage');

        $this->service->deactivate($persona, $request->user()?->id);

        return new AiPersonaResource($persona->load('model'));
    }
}
