<?php

namespace App\Jobs\Email;

use App\Jobs\TenantAwareJob;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Job que envia o e-mail de convite ao destinatário (FR-025).
 *
 * Roda no contexto do tenant (TenantAwareJob) para garantir que o
 * scope correto está ativo. O token claro (`$tokenClaro`) é passado
 * via constructor e serializado no payload da fila — em Redis produção,
 * o payload fica efêmero e nunca vai para o banco relacional (Princípio I).
 *
 * Idempotente: mesmo se re-executado, apenas reenvía o e-mail sem
 * alterar estado do banco (única consequência é um e-mail duplicado).
 *
 * @see App\Services\Users\InvitationService
 */
final class SendInvitationEmailJob extends TenantAwareJob
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $invitationId,
        public readonly string $tokenClaro,
    ) {
        parent::__construct();
    }

    protected function run(): void
    {
        $invitation = Invitation::withoutGlobalScopes()
            ->findOrFail($this->invitationId);

        // Carrega o tenant para gerar o link correto na notification.
        $invitation->load('tenant');

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($invitation, $this->tokenClaro));
    }
}
