<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ai\AiModelResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiModelController extends Controller
{
    /**
     * Lista modelos ATIVOS do catálogo global + os já referenciados por
     * personas do tenant (mesmo inativos), para não quebrar histórico (FR-003).
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('ai.persona.view');

        $referencedIds = AiPersona::query()
            ->withTrashed()
            ->distinct()
            ->pluck('ai_model_id')
            ->all();

        $models = AiModel::query()
            ->where(function ($q) use ($referencedIds): void {
                $q->where('is_active', true);
                if ($referencedIds !== []) {
                    $q->orWhereIn('id', $referencedIds);
                }
            })
            ->orderBy('name')
            ->get();

        return AiModelResource::collection($models);
    }
}
