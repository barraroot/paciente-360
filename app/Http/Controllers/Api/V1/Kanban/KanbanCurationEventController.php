<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kanban\KanbanCurationEventResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * **T107 (Fase 18 — US3, FR-022)** — histórico de mutações automáticas no
 * kanban (audit trail).
 *
 * @group Kanban — Curation Events
 */
final class KanbanCurationEventController extends Controller
{
    /**
     * Lista eventos de auto-curadoria do tenant (paginado).
     *
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('paciente.view');

        $query = KanbanCurationEvent::query()
            ->where('tenant_id', app('tenant')->id)
            ->orderByDesc('created_at');

        if ($request->filled('paciente_id')) {
            $query->where('paciente_id', (int) $request->input('paciente_id'));
        }
        if ($request->filled('event_kind')) {
            $query->where('event_kind', $request->input('event_kind'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        return KanbanCurationEventResource::collection($query->paginate($perPage));
    }
}
