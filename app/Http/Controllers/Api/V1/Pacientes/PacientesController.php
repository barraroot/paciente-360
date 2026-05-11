<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\CreatePacienteRequest;
use App\Http\Requests\Pacientes\UpdatePacienteRequest;
use App\Http\Resources\V1\PacienteListResource;
use App\Http\Resources\V1\PacienteResource;
use App\Models\Paciente;
use App\Services\Pacientes\AnonimizacaoService;
use App\Services\Pacientes\PacienteService;
use App\Support\Tags\TagNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * T117 — Controller REST para pacientes (US-3.1).
 *
 * Todos os métodos:
 *  1. Delegam autorização via `$this->authorize()` (Policy).
 *  2. Usam Form Requests para validação.
 *  3. Delegam lógica de negócio ao `PacienteService`.
 *  4. Retornam `PacienteResource` / `PacienteListResource`.
 *
 * @group CRM Pacientes
 *
 * @see App\Services\Pacientes\PacienteService
 * @see App\Http\Resources\V1\PacienteResource
 */
class PacientesController extends Controller
{
    public function __construct(
        private readonly PacienteService $pacienteService,
        private readonly AnonimizacaoService $anonimizacaoService,
    ) {}

    /**
     * Lista pacientes com filtros e busca por similaridade.
     *
     * Query params:
     *  - `q`: busca fuzzy por nome ou telefone (mín. 2 chars)
     *  - `status`: filtro por status
     *  - `per_page`: tamanho da página (max 100)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Paciente::class);

        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'min:2'],
            'status' => ['sometimes', 'nullable', 'string', 'in:lead,ativo,inativo,bloqueado'],
            'origem' => ['sometimes', 'nullable', 'string'],
            'funil_coluna_id' => ['sometimes', 'nullable', 'integer'],
            'profissional_responsavel_id' => ['sometimes', 'nullable', 'integer'],
            'tag' => ['sometimes', 'nullable', 'string', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = Paciente::with(['tags'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('origem'), fn ($q, $origem) => $q->where('origem', $origem))
            ->when($request->input('funil_coluna_id'), fn ($q, $colunaId) => $q->where('funil_coluna_atual_id', $colunaId))
            ->when($request->input('profissional_responsavel_id'), fn ($q, $pid) => $q->where('profissional_responsavel_id', $pid))
            ->when($request->input('tag'), function ($q, $tagNome): void {
                $normalizado = TagNormalizer::normalize($tagNome);
                $q->whereHas('tags', fn ($tq) => $tq->where('nome_normalizado', $normalizado));
            });

        $q = $request->input('q');
        if (filled($q)) {
            $query = $this->applySearch($query, $q);
        }

        $perPage = (int) min($request->input('per_page', 25), 500);
        $pacientes = $query->paginate($perPage);

        return PacienteListResource::collection($pacientes);
    }

    /**
     * Cria um novo paciente.
     */
    public function store(CreatePacienteRequest $request): JsonResponse
    {
        $paciente = $this->pacienteService->create(
            $request->validated(),
            $request->user(),
        );

        return PacienteResource::make($paciente)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retorna um paciente pelo ID (404 se não existe no tenant).
     */
    public function show(int $id): PacienteResource
    {
        $paciente = Paciente::with(['tags', 'convenios', 'profissionalResponsavel'])->findOrFail($id);

        Gate::authorize('view', $paciente);

        return PacienteResource::make($paciente);
    }

    /**
     * Atualiza parcialmente um paciente (PATCH).
     */
    public function update(UpdatePacienteRequest $request, int $id): PacienteResource
    {
        $paciente = Paciente::findOrFail($id);

        $paciente = $this->pacienteService->update(
            $paciente,
            $request->validated(),
            $request->user(),
        );

        return PacienteResource::make($paciente);
    }

    /**
     * Soft-delete do paciente.
     */
    public function destroy(int $id): JsonResponse
    {
        $paciente = Paciente::findOrFail($id);

        Gate::authorize('delete', $paciente);

        $paciente->delete();

        return response()->json(null, 204);
    }

    /**
     * Anonimiza o paciente (LGPD — FR-035). Apenas Admin Clínica.
     */
    public function anonimizar(int $id, Request $request): JsonResponse
    {
        $paciente = Paciente::findOrFail($id);

        Gate::authorize('anonymize', $paciente);

        $this->anonimizacaoService->anonymize($paciente, $request->user());

        return response()->json(null, 204);
    }

    /**
     * Aplica busca por similaridade trigram (PostgreSQL) ou LIKE (fallback).
     */
    private function applySearch(Builder $query, string $q): Builder
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Busca por similaridade trigram ou prefixo no nome normalizado
            // e busca parcial no telefone normalizado.
            // Threshold 0.2 captura variantes com prefixo compartilhado ("marina" vs "maria").
            $normalized = mb_strtolower($q);

            return $query->where(function ($sub) use ($normalized): void {
                $sub->whereRaw('similarity(nome_normalizado, ?) > 0.2', [$normalized])
                    ->orWhereRaw('nome_normalizado ILIKE ?', [$normalized.'%'])
                    ->orWhere('telefone_primario_normalizado', 'LIKE', '%'.$normalized.'%');
            })->orderByRaw('similarity(nome_normalizado, ?) DESC', [$normalized]);
        }

        // Fallback SQLite/MySQL (testes unitários sem PG)
        return $query->where(function ($sub) use ($q): void {
            $sub->where('nome', 'LIKE', "%{$q}%")
                ->orWhere('telefone_primario', 'LIKE', "%{$q}%");
        });
    }
}
