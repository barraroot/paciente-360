<?php

namespace App\Events\Users;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado após aceite bem-sucedido de convite (FR-034).
 *
 * @see App\Services\Users\InvitationService
 */
final class InvitationAccepted implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly User $user,
    ) {}

    public function auditAction(): string
    {
        return 'user.invitation.accepted';
    }

    public function auditableModel(): ?Model
    {
        return $this->user;
    }

    public function auditTenantId(): ?int
    {
        return $this->invitation->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'email' => $this->invitation->email,
            'role' => $this->invitation->intended_role,
        ];
    }
}
