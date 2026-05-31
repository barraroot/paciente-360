<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\UpdateKanbanPipelineMappingRequest;
use App\Http\Resources\Kanban\KanbanPipelineMappingResource;
use Database\Seeders\DefaultKanbanPipelineMappingSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * **T106 (Fase 18 — US3, FR-019)** — CRUD do mapping evento→coluna do funil
 * por tenant.
 *
 * @group Kanban — Pipeline Mappings
 */
final class KanbanPipelineMappingController extends Controller
{
    /**
     * Lista todos os mappings ativos do tenant.
     *
     * @authenticated
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('paciente.update');

        $mappings = KanbanPipelineMapping::query()
            ->with('funilColuna')
            ->where('tenant_id', app('tenant')->id)
            ->get();

        return KanbanPipelineMappingResource::collection($mappings);
    }

    /**
     * Atualiza um mapping evento→coluna.
     *
     * @authenticated
     */
    public function update(UpdateKanbanPipelineMappingRequest $request, string $eventKind): KanbanPipelineMappingResource
    {
        Gate::authorize('paciente.update');

        $mapping = KanbanPipelineMapping::query()
            ->where('tenant_id', app('tenant')->id)
            ->where('event_kind', $eventKind)
            ->firstOrFail();

        $mapping->update($request->validated());
        $mapping->load('funilColuna');

        return new KanbanPipelineMappingResource($mapping);
    }

    /**
     * Restaura mappings ao default seedado.
     *
     * @authenticated
     */
    public function restoreDefaults(): JsonResponse
    {
        Gate::authorize('paciente.update');

        $seeder = new DefaultKanbanPipelineMappingSeeder;
        $seeder->run();

        $count = KanbanPipelineMapping::query()
            ->where('tenant_id', app('tenant')->id)
            ->count();

        return response()->json([
            'data' => ['restored' => $count],
        ]);
    }
}
