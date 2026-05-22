<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Events\TokenApiEmitido;
use App\Domain\Integrations\Events\TokenApiRevogado;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * **T217 (Fase 8 — Lote D US-11.2)** — Emissão / revogação de tokens API públicos.
 *
 * Tokens Sanctum com prefix `pat_` (Personal Access Token). Plaintext
 * retornado **uma única vez** no create — depois apenas SHA-256 persistido.
 * `abilities` carregam scopes Q14: `patients.read`, `appointments.write`, etc.
 *
 * **OUT OF SCOPE** — refresh tokens (JWT, OAuth) ficam em `OauthClientService`.
 */
final class ApiTokenService
{
    /**
     * Cria um Personal Access Token para o tenant.
     *
     * @param array<int, string> $abilities
     * @return array{token: string, model: PersonalAccessToken}
     */
    public function create(Tenant $tenant, User $creator, string $name, array $abilities = ['*']): array
    {
        // Tokens são associados ao USER que cria, mas representam o TENANT.
        // tenant_id na request será resolvido pelo `$user->tenant_id` no middleware.
        $newToken = $creator->createToken("api:{$name}", $abilities);

        // Sanctum default expira em null. Para API pública, sem expiração
        // explícita — admin revoga via UI. (Compliance: rotação manual)
        Event::dispatch(new TokenApiEmitido(
            tenantId: $tenant->id,
            tokenId: $newToken->accessToken->id,
            tokenName: $name,
            abilities: $abilities,
            createdByUserId: $creator->id,
        ));

        return [
            'token' => $newToken->plainTextToken,
            'model' => $newToken->accessToken,
        ];
    }

    /**
     * Revoga um token. Idempotente — se já revogado retorna false.
     */
    public function revoke(int $tokenId, User $actor, string $reason = 'manual'): bool
    {
        $token = PersonalAccessToken::query()->find($tokenId);
        if ($token === null) {
            return false;
        }

        // Garante que o user só revoga tokens do próprio tenant.
        if ($token->tokenable?->tenant_id !== $actor->tenant_id) {
            return false;
        }

        $token->delete();

        Event::dispatch(new TokenApiRevogado(
            tenantId: (int) $actor->tenant_id,
            tokenId: $tokenId,
            tokenName: $token->name,
            revokedByUserId: $actor->id,
            reason: $reason,
        ));

        return true;
    }
}
