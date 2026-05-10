<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de boas-vindas enviada ao Admin Clínica recém-cadastrado
 * (US-1.1 — T145). Inclui link para o painel no subdomínio do tenant
 * + lembrete do trial 14 dias.
 *
 * Despachada via `SendWelcomeEmailJob` (filas — Princípio II — assíncrona,
 * idempotente).
 */
final class WelcomeNotification extends Notification
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
        $loginUrl = sprintf(
            'https://%s.%s/login',
            $this->tenant->slug,
            (string) config('tenancy.subdomain_suffix', 'crm.com.br'),
        );

        return (new MailMessage)
            ->subject(__('tenant.welcome.subject'))
            ->greeting(__('tenant.welcome.greeting', ['name' => $notifiable->name]))
            ->line(__('tenant.welcome.body_intro', ['clinic' => $this->tenant->name]))
            ->action(__('tenant.welcome.cta_label'), $loginUrl)
            ->line(__('tenant.welcome.body_outro'));
    }
}
