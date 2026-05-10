<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação enviada ao usuário após troca de senha bem-sucedida
 * (US-2.3 — FR-033).
 *
 * Informa que a senha foi alterada e instrui o usuário a contatar o
 * administrador caso não tenha sido o responsável pela mudança.
 */
final class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
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
        return (new MailMessage)
            ->subject(__('auth.password_reset.changed_subject', ['tenant' => $this->tenant->name]))
            ->greeting(__('auth.password_reset.changed_greeting', ['name' => $notifiable->name]))
            ->line(__('auth.password_reset.changed_body', ['tenant' => $this->tenant->name]))
            ->line(__('auth.password_reset.changed_not_you'));
    }
}
