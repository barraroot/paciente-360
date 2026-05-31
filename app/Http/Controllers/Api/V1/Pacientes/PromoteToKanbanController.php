<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\PromoteToKanbanRequest;
use App\Models\FunilColuna;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T108 (Fase 18 — US3, FR-011a UX)** — endpoint dedicado para o operador
 * promover um paciente existente (não-lead) para o kanban quando a conversa
 * representa nova oportunidade comercial.
 *
 * @group Kanban — Promote
 */
final class PromoteToKanbanController extends Controller
{
    /**
     * Promove um paciente existente para o kanban.
     *
     * @authenticated
     */
    public function store(PromoteToKanbanRequest $request, Paciente $paciente): JsonResponse
    {
        Gate::authorize('paciente.update');

        // Cross-tenant 404 (route model binding já filtra via global scope).
        if ($paciente->tenant_id !== app('tenant')->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        // Conflict se já está em coluna NÃO-terminal.
        if ($paciente->funil_coluna_atual_id !== null) {
            $current = FunilColuna::query()
                ->where('tenant_id', $paciente->tenant_id)
                ->find($paciente->funil_coluna_atual_id);

            if ($current !== null && ! $current->is_terminal) {
                return response()->json([
                    'error' => 'already_in_pipeline',
                    'message' => 'Paciente já está em uma coluna não-terminal do kanban.',
                    'current_coluna_id' => $current->id,
                ], Response::HTTP_CONFLICT);
            }
        }

        $coluna = FunilColuna::query()
            ->where('tenant_id', app('tenant')->id)
            ->findOrFail($request->validated('funil_coluna_id'));

        $fromColunaId = $paciente->funil_coluna_atual_id;
        $paciente->update(['funil_coluna_atual_id' => $coluna->id]);

        $event = KanbanCurationEvent::create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'event_kind' => 'manual_promoted_to_kanban',
            'source' => 'ia_tool',  // mantido para schema compat — fonte real é admin
            'from_coluna_id' => $fromColunaId,
            'to_coluna_id' => $coluna->id,
            'applied' => true,
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'reason' => (string) ($request->validated('reason') ?? 'Operador promoveu paciente para nova oportunidade no kanban.'),
            'created_at' => now(),
        ]);

        event(new KanbanCardCurated($event));

        return response()->json([
            'data' => [
                'paciente_id' => $paciente->id,
                'promoted_to_kanban' => true,
                'funil_coluna' => [
                    'id' => $coluna->id,
                    'nome' => $coluna->nome,
                ],
                'curation_event_id' => $event->id,
            ],
        ], Response::HTTP_CREATED);
    }
}
