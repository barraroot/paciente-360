<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Domain\Auth\Events\TokenRevogado;
use App\Models\User;
use App\Support\Metrics\AuthMetricsContract;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Serviço de revogação de Personal Access Tokens (T023).
 *
 * Responsabilidades:
 *  - Revogar token corrente do usuário (logout do dispositivo atual).
 *  - Revogar todos os tokens do usuário (logout-all — FR-004a).
 *  - Revogar token específico por ID (admin force ou listagem própria).
 *  - Disparar `TokenRevogado` (Auditable) para cada operação.
 *
 * Não implementa `BearerAuthContract` — é um serviço auxiliar focado apenas
 * em revogação. O `TokenIssuerService` também provê `revokeToken()` e
 * `revokeAllForUser()` via contrato para compatibilidade.
 *
 * @see App\Domain\Auth\Events\TokenRevogado
 * @see specs/004-token-auth-migration/spec.md §FR-004, §FR-004a, §FR-025
 */
class TokenRevocationService
{
    public function __construct(
        private readonly AuthMetricsContract $metrics,
    ) {}

    /**
     * Revoga apenas o token corrente do usuário (FR-004 — logout do dispositivo atual).
     *
     * Idempotente — se não houver token corrente (TransientToken), não faz nada.
     */
    public function revokeCurrent(User $user): void
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        $tokenId = $token->id;
        $token->delete();

        Event::dispatch(new TokenRevogado(
            userId: $user->id,
            tokenId: $tokenId,
            motivo: MotivoRevogacaoToken::Manual,
        ));

        $this->metrics->tokenRevogadoTotal(MotivoRevogacaoToken::Manual->value);
    }

    /**
     * Revoga todos os tokens do usuário (FR-004a — logout-all / sair de todos dispositivos).
     *
     * @return int Número de tokens revogados.
     */
    public function revokeAll(User $user, MotivoRevogacaoToken $motivo = MotivoRevogacaoToken::LogoutAll): int
    {
        $tokens = $user->tokens()->get();
        $count = 0;

        foreach ($tokens as $token) {
            $token->delete();
            $count++;

            Event::dispatch(new TokenRevogado(
                userId: $user->id,
                tokenId: $token->id,
                motivo: $motivo,
            ));

            $this->metrics->tokenRevogadoTotal($motivo->value);
        }

        return $count;
    }

    /**
     * Revoga um token específico pelo ID (FR-005 / admin force — FR-022).
     *
     * Idempotente — revogar token já revogado (inexistente) não lança exceção (FR-025).
     */
    public function revokeById(
        int $id,
        ?int $executorId = null,
        MotivoRevogacaoToken $motivo = MotivoRevogacaoToken::Manual,
    ): void {
        $token = PersonalAccessToken::find($id);

        if ($token === null) {
            return;
        }

        $userId = $token->tokenable_id;
        $token->delete();

        Event::dispatch(new TokenRevogado(
            userId: $userId,
            tokenId: $id,
            motivo: $motivo,
            executorId: $executorId,
        ));

        $this->metrics->tokenRevogadoTotal($motivo->value);
    }
}
