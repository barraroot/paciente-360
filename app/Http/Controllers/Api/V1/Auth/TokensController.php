<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Domain\Auth\Services\TokenRevocationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TokenResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Controller de gestão de Personal Access Tokens do usuário (Lote D — T046).
 *
 * Endpoints:
 *  - `GET /auth/tokens` — lista todos os tokens do usuário com metadados
 *    e flag `is_current` para o token da request corrente.
 *  - `DELETE /auth/tokens/{tokenId}` — revoga token específico com
 *    enforcement de ownership (usuário só pode revogar seus próprios tokens).
 *
 * A flag `is_current` é calculada comparando `token.id` com o id do token
 * corrente da request, passado via `meta.current_token_id` no ResourceCollection.
 *
 * @group Auth
 *
 * @see App\Http\Resources\V1\TokenResource
 * @see App\Domain\Auth\Services\TokenRevocationService
 * @see specs/004-token-auth-migration/spec.md §AC-A.1.7
 */
final class TokensController extends Controller
{
    /**
     * Listar todos os tokens do usuário com metadados.
     *
     * @response 200 scenario="sucesso" {"data": [{"id": 1, "name": "Default", "token_id_prefix": "pacient3", "abilities": ["*"], "last_used_at": null, "expires_at": "ISO8601", "created_at": "ISO8601", "is_current": true}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken
            ? $currentToken->id
            : null;

        $tokens = $user->tokens()->latest()->get();

        // Agrega is_current diretamente no modelo via atributo virtual antes de
        // serializar — evita problema de propagação de `additional` collection → resource individual.
        $tokens->each(function (PersonalAccessToken $token) use ($currentTokenId): void {
            $token->setAttribute('is_current', $token->id === $currentTokenId);
        });

        return TokenResource::collection($tokens);
    }

    /**
     * Revogar token específico por ID (ownership enforced).
     *
     * Retorna 404 quando o token não pertence ao usuário autenticado
     * (prevenção de enumeração cross-user via ownership check implícito).
     *
     * @response 204 scenario="sucesso"
     * @response 404 scenario="token não encontrado ou de outro usuário"
     */
    public function destroy(
        Request $request,
        int $tokenId,
        TokenRevocationService $svc,
    ): Response {
        // Ownership enforced: find() com escopo do usuário retorna null
        // para tokens de outros usuários (sem revelar existência — FR-032).
        $token = $request->user()->tokens()->find($tokenId);

        if ($token === null) {
            abort(404);
        }

        $svc->revokeById(
            id: $tokenId,
            executorId: $request->user()->id,
            motivo: MotivoRevogacaoToken::Manual,
        );

        return response()->noContent();
    }
}
