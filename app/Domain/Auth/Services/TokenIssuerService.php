<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\BearerAuthContract;
use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Domain\Auth\Events\TokenEmitido;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Metrics\AuthMetricsContract;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Serviço de emissão de Bearer tokens via Sanctum Personal Access Tokens (T022).
 *
 * Implementa `BearerAuthContract`. Bindado como singleton no container
 * em `AppServiceProvider::register()`.
 *
 * Responsabilidades:
 *  - Emitir token com expiração configurável (sliding via `sanctum.expiration`).
 *  - Disparar `TokenEmitido` (Auditable) com `token_id_prefix` (8 chars — nunca plain).
 *  - Resolver tenant por email globalmente único (UNIQUE global em users.email — FR-001).
 *  - Revogar token(s) com disparo de `TokenRevogado` (delegado ao `TokenRevocationService`).
 *
 * @see App\Domain\Auth\Contracts\BearerAuthContract
 * @see specs/004-token-auth-migration/spec.md §FR-001, §FR-021, §FR-023
 */
class TokenIssuerService implements BearerAuthContract
{
    public function __construct(
        private readonly AuthMetricsContract $metrics,
    ) {}

    /**
     * Emite um novo Personal Access Token para o usuário.
     *
     * O token é armazenado via SHA-256 hash no DB (Sanctum default — FR-023).
     * O plain text é retornado APENAS aqui via `NewAccessToken::plainTextToken`
     * e nunca persistido nem logado integralmente.
     *
     * @param array<string> $abilities Abilities concedidas (default `['*']`).
     * @param DateTimeInterface|null $expiresAt Null = config sanctum.expiration em minutos.
     * @return array{token: string, token_model: PersonalAccessToken, expires_at: CarbonInterface}
     */
    public function issueToken(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
    ): array {
        $expiration = $expiresAt !== null
            ? Carbon::instance($expiresAt)
            : now()->addMinutes((int) config('sanctum.expiration', 43200));

        $newToken = $user->createToken($name, $abilities, $expiration);

        /** @var PersonalAccessToken $tokenModel */
        $tokenModel = $newToken->accessToken;

        $plainTextToken = $newToken->plainTextToken;

        // Primeiros 8 chars do plain text sem o "<id>|" prefix — NUNCA o full plain text (FR-023 / LGPD).
        // NewAccessToken::plainTextToken = "<id>|<plainTextToken>"
        // plainTextToken = "paciente360_<random40><crc32b>"
        // Removemos o "<id>|" parte para obter o token em si, depois pegamos 8 chars.
        [, $plainTokenPart] = explode('|', $plainTextToken, 2);
        $tokenIdPrefix = substr($plainTokenPart, 0, 8);

        Event::dispatch(new TokenEmitido(
            userId: $user->id,
            tokenId: $tokenModel->id,
            tokenIdPrefix: $tokenIdPrefix,
            ip: Request::ip() ?? '0.0.0.0',
            userAgent: (string) Request::userAgent(),
            expiresAt: $expiration,
            abilities: $abilities,
            user: $user,
        ));

        $this->metrics->tokenEmitidoTotal();

        return [
            'token' => $plainTextToken,
            'token_model' => $tokenModel,
            'expires_at' => $expiration,
        ];
    }

    /**
     * Revoga um token específico pelo ID (idempotente — FR-025).
     */
    public function revokeToken(int $tokenId): void
    {
        PersonalAccessToken::find($tokenId)?->delete();
    }

    /**
     * Revoga todos os tokens do usuário (FR-004a).
     *
     * @return int Número de tokens revogados.
     */
    public function revokeAllForUser(
        User $user,
        MotivoRevogacaoToken $motivo = MotivoRevogacaoToken::LogoutAll,
    ): int {
        return $user->tokens()->delete();
    }

    /**
     * Resolve o Tenant pelo email (UNIQUE global em users.email — FR-001 / NC-1.a).
     *
     * Retorna `null` quando o email não existe em nenhum tenant.
     */
    public function resolveTenantByEmail(string $email): ?Tenant
    {
        return User::withoutGlobalScopes()
            ->where('email', $email)
            ->first()
            ?->tenant;
    }
}
