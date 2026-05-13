<?php

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Contrato para emissão e revogação de Bearer tokens (T020).
 *
 * Implementado por `TokenIssuerService` e bindado no container como singleton
 * em `AppServiceProvider::register()`.
 *
 * @see App\Domain\Auth\Services\TokenIssuerService
 * @see specs/004-token-auth-migration/spec.md §FR-001, §FR-004, §FR-004a, §FR-005
 */
interface BearerAuthContract
{
    /**
     * Emite um novo Personal Access Token para o usuário.
     *
     * O token é armazenado via SHA-256 hash no DB (Sanctum default — FR-023).
     * O plain text é retornado APENAS aqui e nunca persistido.
     *
     * @param array<string> $abilities Abilities concedidas (default `['*']`).
     * @param DateTimeInterface|null $expiresAt Data de expiração; null = config sanctum.expiration.
     * @return array{token: string, token_model: PersonalAccessToken, expires_at: CarbonInterface}
     */
    public function issueToken(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
    ): array;

    /**
     * Revoga um token específico pelo seu ID primário.
     *
     * Idempotente — revogar um token já revogado não lança exceção (FR-025).
     */
    public function revokeToken(int $tokenId): void;

    /**
     * Revoga todos os tokens do usuário.
     *
     * Usado por `POST /auth/logout-all` (FR-004a).
     *
     * @return int Número de tokens revogados.
     */
    public function revokeAllForUser(
        User $user,
        MotivoRevogacaoToken $motivo = MotivoRevogacaoToken::LogoutAll,
    ): int;

    /**
     * Resolve o tenant a partir de um email globalmente único (FR-001 / NC-1.a).
     *
     * Retorna `null` quando nenhum usuário com o email existe.
     */
    public function resolveTenantByEmail(string $email): ?Tenant;
}
