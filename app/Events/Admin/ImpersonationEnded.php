<?php

namespace App\Events\Admin;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando a impersonação é encerrada.
 *
 * Auditado com action `super_admin.impersonate.ended`.
 */
final class ImpersonationEnded implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly User $superAdmin,
        public readonly User $targetUser,
    ) {}

    public function auditAction(): string
    {
        return 'super_admin.impersonate.ended';
    }

    public function auditableModel(): ?Model
    {
        return $this->targetUser;
    }

    public function auditTenantId(): ?int
    {
        return $this->targetUser->tenant_id !== null ? (int) $this->targetUser->tenant_id : null;
    }

    public function auditUserId(): ?int
    {
        return (int) $this->superAdmin->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'target_user_id' => $this->targetUser->id,
        ];
    }
}
