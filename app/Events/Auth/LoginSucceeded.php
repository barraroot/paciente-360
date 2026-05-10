<?php

namespace App\Events\Auth;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado após login bem-sucedido (FR-034 — Princípios I e V).
 *
 * Implementa `Auditable` para persistência automática em `audit_logs`
 * via `PersistAuditLogListener`.
 *
 * @see App\Events\Auth\Actions::USER_LOGIN_SUCCEEDED
 */
final class LoginSucceeded implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly User $user,
    ) {}

    public function auditAction(): string
    {
        return Actions::USER_LOGIN_SUCCEEDED;
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
