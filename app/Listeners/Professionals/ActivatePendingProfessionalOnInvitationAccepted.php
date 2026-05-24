<?php

declare(strict_types=1);

namespace App\Listeners\Professionals;

use App\Events\Professionals\ProfessionalActivatedByInvitation;
use App\Events\Users\InvitationAccepted;
use App\Models\Professional;
use Illuminate\Support\Facades\Log;

/**
 * **T054 (Spec 012)** — Ativa Professional pendente quando o convidado aceita.
 *
 * Fluxo:
 *   1. Admin cria Professional via convite por email → Professional fica com
 *      `pending_invitation_email = email, is_active=false, user_id=NULL`
 *   2. Email convidado aceita Invitation → `InvitationAccepted` é disparado
 *   3. Este listener busca Professional pendente match `pending_invitation_email
 *      = invitation.email` no mesmo tenant
 *   4. Vincula `user_id`, ativa, limpa `pending_invitation_email`, dispara
 *      `ProfessionalActivatedByInvitation` (auditável, actor_type=system)
 *
 * @see specs/012-professionals-management/research.md R4
 */
final class ActivatePendingProfessionalOnInvitationAccepted
{
    public function handle(InvitationAccepted $event): void
    {
        $email = mb_strtolower($event->invitation->email);
        $tenantId = $event->invitation->tenant_id;

        $professional = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('pending_invitation_email', $email)
            ->whereNull('user_id')
            ->where('is_active', false)
            ->first();

        if ($professional === null) {
            // Convite não estava ligado a um Professional pendente — invite normal de user.
            return;
        }

        $professional->update([
            'user_id' => $event->user->id,
            'pending_invitation_email' => null,
            'is_active' => true,
        ]);

        event(new ProfessionalActivatedByInvitation(
            $professional->fresh(),
            $event->invitation->id,
        ));

        Log::info('professional.activated_by_invitation', [
            'tenant_id' => $tenantId,
            'professional_id' => $professional->id,
            'user_id' => $event->user->id,
            'invitation_id' => $event->invitation->id,
        ]);
    }
}
