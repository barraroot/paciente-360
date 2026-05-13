<?php

namespace App\Domain\Auth\Events;

use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando um Personal Access Token é revogado (T017).
 *
 * @see specs/004-token-auth-migration/spec.md §FR-022
 * @see specs/004-token-auth-migration/spec.md §6 (tabela de eventos)
 *
 * Payload auditado: `user_id`, `token_id`, `motivo` e `executor_id`.
 * `executor_id` pode ser null (sistema) ou o ID de um usuário (admin force).
 *
 * Não implementa `ShouldBroadcast` — evento interno apenas para auditoria.
 */
final readonly class TokenRevogado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public int $userId,
        public int $tokenId,
        public MotivoRevogacaoToken $motivo,
        public ?int $executorId = null,
    ) {}

    public function auditAction(): string
    {
        return 'auth.token_revogado';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'token_id' => $this->tokenId,
            'motivo' => $this->motivo->value,
            'executor_id' => $this->executorId,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditUserId(): ?int
    {
        return $this->executorId ?? $this->userId;
    }
}
