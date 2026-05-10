<?php

namespace App\Events\Admin;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando um Super Admin inicia a impersonação de um usuário.
 *
 * Auditado com action `super_admin.impersonate.started`.
 */
final class ImpersonationStarted implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly User $superAdmin,
        public readonly Tenant $targetTenant,
        public readonly User $targetUser,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'super_admin.impersonate.started';
    }

    public function auditableModel(): ?Model
    {
        return $this->targetUser;
    }

    public function auditTenantId(): ?int
    {
        return (int) $this->targetTenant->id;
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
            'target_tenant_id' => $this->targetTenant->id,
            'target_user_id' => $this->targetUser->id,
            'reason' => $this->reason,
        ];
    }
}
