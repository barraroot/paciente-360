<?php

namespace App\Domain\Auth\Services;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Serviço de sliding expiration para Personal Access Tokens (T025).
 *
 * Renova `personal_access_tokens.expires_at` quando o token está dentro
 * do buffer de expiração (default: 5 dias antes do vencimento). Caso
 * contrário, é idempotente — não gera UPDATE desnecessário.
 *
 * Lógica (NC-2 / FR-006):
 *  - Se `$token->expires_at` é null → token sem expiração → NÃO renova.
 *  - Se `$token->expires_at->diffInDays(now(), false) > -$bufferDays`
 *    (i.e. menos de 5 dias até expirar, ou já expirado) → renova.
 *  - `windowMinutes` default = `config('sanctum.expiration')` (30d em min).
 *
 * Throttle interno: renovação só ocorre quando faz sentido (~6x menos
 * UPDATEs vs renovar em toda request).
 *
 * @see specs/004-token-auth-migration/spec.md §FR-006, §NC-2
 * @see specs/004-token-auth-migration/research.md R2 (sliding expiration decisão)
 */
class SlidingExpirationService
{
    /**
     * Renova `expires_at` do token se ele estiver dentro do buffer de expiração.
     *
     * @param int $bufferDays Dias restantes antes do vencimento para acionar renovação.
     * @param int|null $windowMinutes Janela de renovação em minutos (null = config sanctum.expiration).
     * @return bool `true` se renovou, `false` se idempotente (sem UPDATE).
     */
    public function renewIfDue(
        PersonalAccessToken $token,
        int $bufferDays = 5,
        ?int $windowMinutes = null,
    ): bool {
        if ($token->expires_at === null) {
            return false;
        }

        // diffInDays(now(), false) retorna positivo se expiresAt > now (dias restantes),
        // negativo se já expirou (dias passados). Queremos renovar quando:
        // `expires_at - now < bufferDays` → `diffInDays < bufferDays`
        $daysRemaining = now()->diffInDays($token->expires_at, false);

        if ($daysRemaining >= $bufferDays) {
            return false;
        }

        $window = $windowMinutes ?? (int) config('sanctum.expiration', 43200);
        $newExpiresAt = now()->addMinutes($window);

        PersonalAccessToken::where('id', $token->id)->update([
            'expires_at' => $newExpiresAt,
        ]);

        return true;
    }
}
