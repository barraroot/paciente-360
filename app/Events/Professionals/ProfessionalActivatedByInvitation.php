<?php

declare(strict_types=1);

namespace App\Events\Professionals;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Professional;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * **T009 (Spec 012)** — Profissional ativado automaticamente após aceite de
 * convite por email.
 *
 * Distinto de `ProfessionalCreated` (criação direta) porque a ativação é
 * disparada por evento sistêmico (`InvitationAccepted` da Fase 4), não por
 * ação humana direta — actor_type é `system` no audit log.
 *
 * @see specs/012-professionals-management/research.md R4
 */
final class ProfessionalActivatedByInvitation implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Professional $professional,
        public readonly int $invitationId,
    ) {}

    public function auditAction(): string
    {
        return 'professional.activated_by_invitation';
    }

    public function auditableModel(): ?Model
    {
        return $this->professional;
    }

    public function auditActorType(): ?string
    {
        return 'system';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'invitation_id' => $this->invitationId,
            'professional_name' => $this->professional->name,
            'user_id' => $this->professional->user_id,
        ];
    }
}
