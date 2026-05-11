<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\CreateTagRequest;
use App\Http\Resources\V1\TagResource;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pacientes\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * T245 — Controller de catálogo de tags (US-3.5).
 *
 * `index` — lista tags do tenant com filtro por tipo.
 * `store` — find-or-create normalizado; 201 se nova, 409 se duplicata.
 */
class TagsController extends Controller
{
    public function __construct(
        private readonly TagService $tagService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'tipo' => ['sometimes', 'nullable', 'string', 'in:livre,sistemica'],
            'q' => ['sometimes', 'nullable', 'string', 'min:1'],
        ]);

        $query = Tag::query();

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('q')) {
            $query->where('nome', 'ilike', '%'.$request->input('q').'%');
        }

        return TagResource::collection($query->orderBy('nome')->get());
    }

    public function store(CreateTagRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        /** @var Tenant $tenant */
        $tenant = app('tenant');

        try {
            $tag = $this->tagService->findOrCreate($validated['nome'], $tenant, $user);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['nome' => [$e->getMessage()]],
                ], 422);
            }
            throw $e;
        }

        // Verifica se a tag já existia (find) ou foi criada (create)
        $wasRecentlyCreated = $tag->wasRecentlyCreated;

        if (! $wasRecentlyCreated) {
            return response()->json([
                'message' => 'Tag já existe com este nome normalizado.',
                'data' => TagResource::make($tag)->resolve($request),
            ], 409);
        }

        // Aplica campos opcionais (cor, descricao) se fornecidos
        $updates = array_filter([
            'cor' => $validated['cor'] ?? null,
            'descricao' => $validated['descricao'] ?? null,
        ]);

        if (! empty($updates)) {
            $tag->update($updates);
        }

        return response()->json([
            'data' => TagResource::make($tag)->resolve($request),
        ], 201);
    }
}
