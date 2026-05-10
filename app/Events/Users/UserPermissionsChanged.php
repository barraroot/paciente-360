<?php

namespace App\Events\Users;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando as permissões/roles de um usuário são alteradas (FR-034).
 *
 * @see App\Services\Users\UserService
 */
final class UserPermissionsChanged implements Auditable
{
    use IsAuditable;

    /**
     * @param list<string> $oldRoles
     * @param list<string> $newRoles
     */
    public function __construct(
        public readonly User $user,
        public readonly User $actor,
        public readonly array $oldRoles,
        public readonly array $newRoles,
    ) {}

    public function auditAction(): string
    {
        return 'user.permissions.changed';
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
            'old_roles' => $this->oldRoles,
            'new_roles' => $this->newRoles,
        ];
    }
}
