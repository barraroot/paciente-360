<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Execution\Models\AiExecutionLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ai\AiExecutionLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * US7 — consulta de logs de execução da IA, escopados por tenant (G11/G1).
 * Somente leitura; conteúdo já pseudonimizado.
 */
class AiExecutionLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ai.log.view');

        $query = AiExecutionLog::query()
            ->with('persona:id,name')
            ->latest('id');

        if ($request->filled('conversation_id')) {
            $query->where('conversation_id', (int) $request->input('conversation_id'));
        }

        if ($request->filled('ai_persona_id')) {
            $query->where('ai_persona_id', (int) $request->input('ai_persona_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return AiExecutionLogResource::collection(
            $query->paginate((int) $request->integer('per_page', 25))->withQueryString()
        );
    }

    public function show(AiExecutionLog $executionLog): AiExecutionLogResource
    {
        Gate::authorize('ai.log.view');

        return new AiExecutionLogResource($executionLog->load('persona:id,name'));
    }
}
