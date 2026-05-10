<?php

namespace App\Events\Users;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado após revogação de convite (FR-034).
 *
 * @see App\Services\Users\InvitationService
 */
final class InvitationRevoked implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}

    public function auditAction(): string
    {
        return 'user.invitation.revoked';
    }

    public function auditableModel(): ?Model
    {
        return $this->invitation;
    }

    public function auditTenantId(): ?int
    {
        return $this->invitation->tenant_id;
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
