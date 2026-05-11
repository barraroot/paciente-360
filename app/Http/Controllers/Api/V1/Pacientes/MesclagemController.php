<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\MesclagemRequest;
use App\Http\Resources\V1\MesclagemResource;
use App\Models\MesclagemPaciente;
use App\Models\Paciente;
use App\Services\Pacientes\MergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * T118 — Controller de mesclagem reversível de pacientes (US-3.1).
 *
 * Exceções mapeadas em `bootstrap/app.php`:
 *  - `MesclagemExpiradaException` → 410
 *  - `MesclagemJaRevertidaException` → 422
 *
 * @group CRM Pacientes
 *
 * @see App\Services\Pacientes\MergeService
 */
class MesclagemController extends Controller
{
    public function __construct(
        private readonly MergeService $mergeService,
    ) {}

    /**
     * Executa a mesclagem de pacientes.
     */
    public function store(MesclagemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $alvo = Paciente::findOrFail($validated['paciente_alvo_id']);
        $origens = Paciente::whereIn('id', $validated['pacientes_origem_ids'])->get();
        $resolucoes = (array) ($validated['resolucoes'] ?? []);

        $mesclagem = $this->mergeService->merge($alvo, $origens, $resolucoes, $request->user());

        return MesclagemResource::make($mesclagem)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reverte uma mesclagem dentro do prazo de 30 dias.
     */
    public function reverter(int $id, Request $request): JsonResponse
    {
        $mesclagem = MesclagemPaciente::findOrFail($id);

        $this->mergeService->revert($mesclagem, $request->user());

        return response()->json(null, 204);
    }
}
