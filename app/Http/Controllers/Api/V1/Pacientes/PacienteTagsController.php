<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TagResource;
use App\Models\Paciente;
use App\Models\Tag;
use App\Models\User;
use App\Services\Pacientes\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * T245 — Controller para vínculo paciente↔tag (US-3.5).
 *
 * `attach` — POST `/pacientes/{id}/tags` com `{tag_id}`.
 *   - Retorna 200 com `X-Soft-Warning: tag.soft_limit` quando ≥ 10 tags.
 * `detach` — DELETE `/pacientes/{id}/tags/{tag_id}`.
 *   - Retorna 403 ao tentar remover tag sistêmica sem ser admin.
 *
 * @group CRM Pacientes
 */
class PacienteTagsController extends Controller
{
    public function __construct(
        private readonly TagService $tagService,
    ) {}

    public function attach(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'tag_id' => ['required', 'integer'],
        ]);

        $paciente = Paciente::findOrFail($id);
        $tag = Tag::findOrFail($request->integer('tag_id'));

        /** @var User $user */
        $user = $request->user();

        $result = $this->tagService->attachTo($paciente, $tag, $user);

        $response = response()->json([
            'data' => TagResource::make($result['tag'])->resolve($request),
        ], 200);

        if ($result['soft_limit_atingido']) {
            $response->withHeaders([
                'X-Soft-Warning' => 'tag.soft_limit',
            ]);
        }

        return $response;
    }

    public function detach(int $id, int $tagId, Request $request): JsonResponse
    {
        $paciente = Paciente::findOrFail($id);
        $tag = Tag::findOrFail($tagId);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->tagService->detachFrom($paciente, $tag, $user);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['message' => 'Tag removida.'], 200);
    }
}
