<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\MoveCardRequest;
use App\Http\Requests\Pacientes\UpdateColunaRequest;
use App\Http\Resources\V1\FunilColunaResource;
use App\Http\Resources\V1\PacienteResource;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Services\Funil\FunilService;
use App\Services\Funil\FunilTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * T214 — Controller do Funil Kanban (US-3.4).
 *
 * `colunas` garante lazy init das colunas via `FunilTemplateService`.
 * `updateColuna` trata colisão de UNIQUE (posicao) com 409.
 * `moveCard` delega ao `FunilService` que valida motivo e dispara evento.
 *
 * @group CRM Pacientes
 *
 * @see App\Services\Funil\FunilTemplateService
 * @see App\Services\Funil\FunilService
 */
class FunilController extends Controller
{
    public function __construct(
        private readonly FunilTemplateService $templateService,
        private readonly FunilService $funilService,
    ) {}

    /**
     * Lista as colunas do funil do tenant, criando o template padrão na
     * primeira chamada (lazy init — AC-3.4.1).
     */
    public function colunas(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FunilColuna::class);

        $tenant = app('tenant');

        $colunas = $this->templateService->ensureTenantHasColumns($tenant)
            ->load('pacientes');

        return FunilColunaResource::collection(
            FunilColuna::where('tenant_id', $tenant->id)
                ->withCount('pacientes')
                ->orderBy('posicao')
                ->get()
        );
    }

    /**
     * Atualiza configuração de uma coluna (Admin only).
     *
     * Retorna 409 se `posicao` colidir com outra coluna do mesmo tenant
     * (UNIQUE constraint em `(tenant_id, posicao)`).
     */
    public function updateColuna(int $id, UpdateColunaRequest $request): FunilColunaResource|JsonResponse
    {
        $coluna = FunilColuna::findOrFail($id);

        try {
            $coluna->update($request->validated());
        } catch (QueryException $e) {
            // PostgreSQL unique_violation = 23505
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                return response()->json([
                    'message' => 'Já existe outra coluna com esta posição neste tenant.',
                    'error' => 'posicao_duplicada',
                ], 409);
            }

            throw $e;
        }

        return FunilColunaResource::make($coluna->fresh());
    }

    /**
     * Move um paciente para outra coluna do funil (drag-and-drop ou automático).
     */
    public function moveCard(int $id, MoveCardRequest $request): PacienteResource
    {
        $paciente = Paciente::findOrFail($id);

        $destino = FunilColuna::findOrFail($request->integer('coluna_id'));

        $paciente = $this->funilService->moveCard(
            paciente: $paciente,
            destino: $destino,
            posicao: $request->input('posicao') !== null ? (float) $request->input('posicao') : null,
            motivo: $request->input('motivo'),
            motivoOutro: $request->input('motivo_outro'),
        );

        return PacienteResource::make($paciente);
    }
}
