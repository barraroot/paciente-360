<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Controller do endpoint /me — retorna o usuário autenticado, tenant e
 * metadados do token Bearer corrente (Lote D — T043).
 *
 * Substitui o controller cookie/session-based da Fase 0. Retorna:
 *  - `user`: subset público do usuário autenticado.
 *  - `tenant`: dados não-sensíveis do tenant (sem CNPJ/stripe_id).
 *  - `token`: metadados do token Bearer corrente (id, name, abilities,
 *    last_used_at, expires_at) para gestão de sessões no SPA.
 *
 * @group Auth
 *
 * @see App\Http\Resources\UserResource
 * @see App\Http\Resources\TenantResource
 * @see specs/004-token-auth-migration/contracts/openapi.yaml § /auth/me
 */
final class MeController extends Controller
{
    /**
     * Usuário autenticado, tenant e metadados do token corrente.
     *
     * @response 200 scenario="sucesso" {"user": {}, "tenant": {}, "token": {"id": 1, "name": "Default", "abilities": ["*"], "last_used_at": null, "expires_at": "ISO8601"}}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $lastUsedAt = $token instanceof PersonalAccessToken
            ? $token->last_used_at?->toIso8601String()
            : null;

        $expiresAt = $token instanceof PersonalAccessToken
            ? $token->expires_at?->toIso8601String()
            : null;

        return response()->json([
            'user' => UserResource::make($user),
            'tenant' => TenantResource::make($user->tenant),
            'token' => [
                'id' => $token?->id,
                'name' => $token?->name,
                'abilities' => $token?->abilities ?? [],
                'last_used_at' => $lastUsedAt,
                'expires_at' => $expiresAt,
            ],
        ]);
    }
}
