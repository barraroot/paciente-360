<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Domain\Integrations\Services\ApiTokenService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * **T231 (Fase 8 — Lote D US-11.2)** — CRUD interno de tokens API.
 *
 * Acessível pelo Admin Clínica via painel SPA. Plaintext retornado UMA vez
 * no store (campo `meta.token_plaintext`).
 */
class ApiTokensController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $list = $user?->tokens()
            ->whereJsonContains('abilities', '*')
            ->orWhere('name', 'like', 'api:%')
            ->latest()
            ->limit(50)
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
            ?? collect();

        return response()->json(['data' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manageApiTokens');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string', 'max:60'],
        ]);

        $tenant = app('tenant');
        $user = $request->user();

        $result = $this->tokens->create(
            tenant: $tenant,
            creator: $user,
            name: $validated['name'],
            abilities: $validated['abilities'] ?? ['*'],
        );

        return response()->json([
            'data' => [
                'id' => $result['model']->id,
                'name' => $result['model']->name,
                'abilities' => $result['model']->abilities,
                'created_at' => $result['model']->created_at?->toIso8601String(),
            ],
            'meta' => [
                'token_plaintext' => $result['token'],
                'note' => 'Guarde este token agora — não será exibido novamente.',
            ],
        ], 201);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $this->authorize('manageApiTokens');

        $ok = $this->tokens->revoke($tokenId, $request->user());

        return response()->json([
            'message' => $ok ? 'Token revogado.' : 'Token não encontrado ou já revogado.',
        ], $ok ? 200 : 404);
    }

    private function authorize(string $ability): void
    {
        if (! request()->user()?->can('api_token.manage')) {
            abort(403, 'Sem permissão para gerenciar tokens API.');
        }
    }
}
