<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\UpdateAssignmentRulesRequest;
use App\Http\Resources\V1\AssignmentRuleResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * T151 — Controller para GET/PUT /inbox/assignment-rules.
 *
 * GET: lista regras ativas do tenant ordenadas por priority.
 * PUT: substitui o conjunto completo de regras (replace set) em transação.
 */
final class AssignmentRulesController extends Controller
{
    /**
     * GET /inbox/assignment-rules
     *
     * Lista regras ativas ordenadas por priority (asc = maior prioridade).
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('admin-clinica')) {
            abort(403);
        }

        $tenant = app('tenant');

        $rules = AssignmentRule::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        return response()->json([
            'data' => AssignmentRuleResource::collection($rules),
        ]);
    }

    /**
     * PUT /inbox/assignment-rules
     *
     * Substitui todas as regras do tenant em uma única transação.
     * Atomicamente: apaga as antigas, insere as novas.
     */
    public function update(UpdateAssignmentRulesRequest $request): JsonResponse
    {
        $tenant = app('tenant');

        $rules = DB::transaction(function () use ($request, $tenant): Collection {
            AssignmentRule::where('tenant_id', $tenant->id)->delete();

            foreach ($request->input('rules', []) as $ruleData) {
                AssignmentRule::create([
                    'tenant_id' => $tenant->id,
                    'strategy' => $ruleData['strategy'],
                    'priority' => $ruleData['priority'],
                    'is_active' => $ruleData['is_active'],
                    'config' => $ruleData['config'] ?? [],
                    'channel_id' => $ruleData['channel_id'] ?? null,
                ]);
            }

            return AssignmentRule::where('tenant_id', $tenant->id)
                ->orderBy('priority')
                ->get();
        });

        return response()->json([
            'data' => AssignmentRuleResource::collection($rules),
        ]);
    }
}
