<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\WorkContext\Models\AiWorkContext;
use App\Domain\Ai\WorkContext\Services\AiWorkContextService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\UpsertWorkContextRequest;
use App\Http\Resources\Ai\AiWorkContextResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Feature 017 (US2) — Contexto de Trabalho da clínica (singleton por tenant).
 */
class AiWorkContextController extends Controller
{
    public function __construct(private readonly AiWorkContextService $service) {}

    public function show(): AiWorkContextResource
    {
        Gate::authorize('ai.work-context.view');

        $context = $this->service->getForTenant(app('tenant')->id)
            ?? new AiWorkContext;

        return new AiWorkContextResource($context);
    }

    public function update(UpsertWorkContextRequest $request): JsonResponse
    {
        $context = $this->service->upsert(
            tenantId: app('tenant')->id,
            data: $request->validated(),
        );

        // PUT idempotente (upsert) — sempre 200, mesmo na primeira criação.
        return (new AiWorkContextResource($context))->response()->setStatusCode(200);
    }
}
