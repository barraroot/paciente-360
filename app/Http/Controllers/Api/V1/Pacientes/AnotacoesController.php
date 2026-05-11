<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\CreateAnotacaoRequest;
use App\Http\Requests\Pacientes\RetratacaoRequest;
use App\Http\Resources\V1\AnotacaoResource;
use App\Models\Anotacao;
use App\Models\Paciente;
use App\Services\Pacientes\AnotacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * T154 — Controller de anotações do paciente.
 *
 * Endpoints:
 *  - `GET  /pacientes/{id}/anotacoes` — lista anotações visíveis para o usuário.
 *  - `POST /pacientes/{id}/anotacoes` — cria nova anotação.
 *  - `POST /pacientes/{id}/anotacoes/{anotacao_id}/retratacao` — retrata anotação.
 *
 * Autorização via `CreateAnotacaoRequest`/`RetratacaoRequest` (authorize())
 * e `AnotacaoPolicy::view` para listagem/visibilidade.
 *
 * @see App\Services\Pacientes\AnotacaoService
 * @see App\Policies\AnotacaoPolicy
 */
final class AnotacoesController extends Controller
{
    /**
     * Lista anotações do paciente visíveis para o usuário autenticado.
     *
     * GET /pacientes/{id}/anotacoes
     */
    public function index(int $id): AnonymousResourceCollection
    {
        $paciente = Paciente::findOrFail($id);

        Gate::authorize('viewAny', [Anotacao::class, $paciente]);

        $user = auth()->user();

        $anotacoes = Anotacao::query()
            ->where('tenant_id', $paciente->tenant_id)
            ->where('paciente_id', $paciente->id)
            ->with(['autor', 'retratacoes'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn (Anotacao $a) => $user->can('view', $a))
            ->values();

        return AnotacaoResource::collection($anotacoes);
    }

    /**
     * Cria nova anotação para o paciente.
     *
     * POST /pacientes/{id}/anotacoes
     */
    public function store(
        int $id,
        CreateAnotacaoRequest $request,
        AnotacaoService $service,
    ): JsonResponse {
        $paciente = Paciente::findOrFail($id);

        $anotacao = $service->create(
            paciente: $paciente,
            data: $request->validated(),
            autor: $request->user(),
        );

        $anotacao->load('autor');

        return (new AnotacaoResource($anotacao))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retrata uma anotação existente (cria nova anotação linkada).
     *
     * POST /pacientes/{id}/anotacoes/{anotacao_id}/retratacao
     */
    public function retratar(
        int $id,
        int $anotacaoId,
        RetratacaoRequest $request,
        AnotacaoService $service,
    ): JsonResponse {
        $paciente = Paciente::findOrFail($id);
        $original = Anotacao::where('tenant_id', $paciente->tenant_id)
            ->where('paciente_id', $paciente->id)
            ->findOrFail($anotacaoId);

        try {
            $retratacao = $service->retratar(
                original: $original,
                texto: $request->validated('texto'),
                autor: $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'anotacao_ja_eh_retratacao',
            ], 422);
        }

        $retratacao->load('autor');

        return (new AnotacaoResource($retratacao))
            ->response()
            ->setStatusCode(201);
    }
}
