<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StorePersonaChannelsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiPersonaChannelController extends Controller
{
    public function __construct(private readonly AiMatrixService $matrix) {}

    public function index(): JsonResponse
    {
        Gate::authorize('ai.persona.view');

        return response()->json(['data' => $this->matrix->matrix(app('tenant')->id)]);
    }

    public function update(StorePersonaChannelsRequest $request): JsonResponse
    {
        $tenantId = app('tenant')->id;

        /** @var list<array{ai_persona_id: int, channel_type: string, is_active: bool}> $cells */
        $cells = $request->validated('cells');

        $this->matrix->syncCells($tenantId, $cells);

        return response()->json([
            'message' => __('ai.matrix.updated'),
            'data' => $this->matrix->matrix($tenantId),
        ]);
    }

    public function channelConfig(string $channelType): JsonResponse
    {
        Gate::authorize('ai.persona.view');

        return response()->json([
            'data' => $this->matrix->channelConfig(app('tenant')->id, $channelType),
        ]);
    }
}
