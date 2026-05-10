<?php

namespace App\Events\Users;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando um usuário é desativado (FR-028, FR-034).
 *
 * @see App\Services\Users\UserService
 */
final class UserDisabled implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly User $user,
        public readonly User $actor,
    ) {}

    public function auditAction(): string
    {
        return 'user.disabled';
    }

    public function auditableModel(): ?Model
    {
        return $this->user;
    }

    public function auditTenantId(): ?int
    {
        return $this->user->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->actor->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'target_email' => $this->user->email,
        ];
    }
}
