<?php

namespace App\Events\Auth;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando um usuário solicita recuperação de senha
 * (FR-030, FR-032, FR-034 — Princípios I e V).
 *
 * Quando `$user` é `null`, significa que o e-mail informado não corresponde
 * a nenhum usuário ativo no tenant (solicitação para e-mail inexistente).
 * O sistema responde igualmente em ambos os casos (FR-032), mas o evento
 * diferencia via `payload.user_found` para rastreabilidade.
 *
 * @see App\Events\Auth\Actions::USER_PASSWORD_FORGOT_REQUESTED
 */
final class PasswordResetRequested implements Auditable
{
    use IsAuditable;

    /**
     * @param string $emailAttempted E-mail informado na solicitação.
     * @param bool $userFound Se o e-mail corresponde a um usuário ativo.
     */
    public function __construct(
        public readonly ?User $user,
        public readonly string $emailAttempted,
        public readonly bool $userFound,
    ) {}

    public function auditAction(): string
    {
        return Actions::USER_PASSWORD_FORGOT_REQUESTED;
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
        if ($this->user !== null) {
            return $this->user->tenant_id;
        }

        // Fallback ao tenant do container (resolvido pelo middleware).
        if (! app()->bound('tenant')) {
            return null;
        }

        $tenant = app('tenant');

        return (is_object($tenant) && isset($tenant->id)) ? (int) $tenant->id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'email_attempted' => $this->emailAttempted,
            'user_found' => $this->userFound,
        ];
    }
}
