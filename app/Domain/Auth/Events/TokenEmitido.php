<?php

namespace App\Domain\Auth\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando um Personal Access Token é emitido com sucesso (T016).
 *
 * @see specs/004-token-auth-migration/spec.md §FR-021
 * @see specs/004-token-auth-migration/spec.md §6 (tabela de eventos)
 *
 * Payload auditado: `user_id`, `token_id`, `token_id_prefix` (primeiros 8 chars
 * do plain text — NUNCA o plain text completo), `ip`, `user_agent`, `expires_at`
 * (ISO 8601) e `abilities`.
 *
 * Não implementa `ShouldBroadcast` — evento interno apenas para auditoria.
 */
final readonly class TokenEmitido implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param array<string> $abilities Lista de abilities concedidas ao token.
     * @param string $tokenIdPrefix Primeiros 8 chars do plain text (nunca o full plain text).
     */
    public function __construct(
        public int $userId,
        public int $tokenId,
        public string $tokenIdPrefix,
        public string $ip,
        public string $userAgent,
        public CarbonInterface $expiresAt,
        public array $abilities,
        public User $user,
    ) {}

    public function auditAction(): string
    {
        return 'auth.token_emitido';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'token_id' => $this->tokenId,
            'token_id_prefix' => $this->tokenIdPrefix,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'abilities' => $this->abilities,
        ];
    }

    public function auditableModel(): ?Model
    {
        return $this->user;
    }

    public function auditTenantId(): ?int
    {
        return $this->user->tenant_id ?? null;
    }

    public function auditUserId(): ?int
    {
        return $this->userId;
    }
}
