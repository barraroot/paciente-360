<?php

namespace App\Events\Auth;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado após redefinição de senha bem-sucedida
 * (FR-033, FR-034 — Princípios I e V).
 *
 * @see App\Events\Auth\Actions::USER_PASSWORD_RESET
 */
final class PasswordResetCompleted implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly User $user,
    ) {}

    public function auditAction(): string
    {
        return Actions::USER_PASSWORD_RESET;
    }

    public function auditableModel(): ?Model
    {
        return $this->user;
    }

    public function auditUserId(): ?int
    {
        return $this->user->id;
    }

    public function auditTenantId(): ?int
    {
        return $this->user->tenant_id;
    }
}
