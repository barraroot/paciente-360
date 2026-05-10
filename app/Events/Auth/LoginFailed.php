<?php

namespace App\Events\Auth;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado em tentativa de login falha (FR-034 — Princípios I e V).
 *
 * Quando `$user` é null (e-mail desconhecido), `user_id` e `auditable_id`
 * ficam null e o payload carrega o `email_attempted` para rastreabilidade
 * sem criar falso registro de usuário.
 *
 * Quando `$action` é `USER_LOGIN_LOCKED`, indica que esta falha atingiu
 * o threshold e disparou o bloqueio da conta.
 *
 * @see App\Events\Auth\Actions
 */
final class LoginFailed implements Auditable
{
    use IsAuditable;

    /**
     * @param array<string, mixed> $extraPayload
     */
    public function __construct(
        public readonly ?User $user,
        private readonly string $action = Actions::USER_LOGIN_FAILED,
        private readonly array $extraPayload = [],
    ) {}

    public function auditAction(): string
    {
        return $this->action;
    }

    public function auditableModel(): ?Model
    {
        return $this->user;
    }

    public function auditUserId(): ?int
    {
        return $this->user?->id;
    }

    public function auditTenantId(): ?int
    {
        return $this->user?->tenant_id ?? (app()->bound('tenant') ? app('tenant')?->id : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return $this->extraPayload;
    }
}
