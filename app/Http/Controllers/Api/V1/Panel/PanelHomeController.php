<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\PanelHomeIndexRequest;
use App\Http\Resources\Panel\PanelHomeResource;
use App\Models\User;
use App\Services\Panel\PanelHomeService;
use Illuminate\Http\JsonResponse;

/**
 * **T011 (Fase 10 — Spec 010)** — `GET /api/v1/panel/home`.
 *
 * Endpoint consolidado que retorna 4 seções do Dashboard Home em uma
 * única response. Cache, métricas e degradação graceful tratados no
 * service.
 *
 * @see specs/010-dashboard-home/contracts/api-panel-home.md
 */
final class PanelHomeController extends Controller
{
    public function __invoke(PanelHomeIndexRequest $request, PanelHomeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $payload = $service->getHome($user, $request->requestedScope());

        return PanelHomeResource::make($payload)->response();
    }
}
