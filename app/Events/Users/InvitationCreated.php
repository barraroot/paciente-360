<?php

namespace App\Events\Users;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado após um convite ser criado com sucesso (FR-034).
 *
 * @see App\Services\Users\InvitationService
 */
final class InvitationCreated implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}

    public function auditAction(): string
    {
        return 'user.invitation.created';
    }

    public function auditableModel(): ?Model
    {
        return $this->invitation;
    }

    public function auditTenantId(): ?int
    {
        return $this->invitation->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->invitation->inviter_user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'email' => $this->invitation->email,
            'intended_role' => $this->invitation->intended_role,
        ];
    }
}
