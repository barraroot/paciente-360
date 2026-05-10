<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de convite enviada ao e-mail do destinatário (FR-025).
 *
 * O token claro é passado no constructor (não salvo em DB — Princípio I LGPD).
 * O link de aceite aponta para o subdomínio do tenant convidante.
 *
 * @see App\Jobs\Email\SendInvitationEmailJob
 */
final class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $tokenClaro,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->invitation->tenant;
        $suffix = config('tenancy.subdomain_suffix', 'lvh.me');
        $role = $this->invitation->intended_role;

        $acceptUrl = "https://{$tenant->slug}.{$suffix}/accept-invitation?token={$this->tokenClaro}";

        return (new MailMessage)
            ->subject(__('users.invitation.email_subject', ['tenant' => $tenant->name]))
            ->greeting(__('users.invitation.email_greeting'))
            ->line(__('users.invitation.email_intro', ['tenant' => $tenant->name, 'role' => $role]))
            ->action(__('users.invitation.email_action'), $acceptUrl)
            ->line(__('users.invitation.email_expiry'))
            ->line(__('users.invitation.email_ignore'));
    }
}
