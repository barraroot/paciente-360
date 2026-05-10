<?php

namespace App\Notifications;

use App\Models\AiUsageMeter;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de hard cap de IA atingido (T223).
 *
 * Enviada ao Admin Clínica e Financeiro quando `messages_count >= hard_cap`.
 * Subject: "Limite de IA atingido".
 */
final class HardCapTriggeredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly AiUsageMeter $meter,
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
        $billingUrl = sprintf(
            'https://%s.%s/panel/billing/ai-usage',
            $this->tenant->slug,
            (string) config('tenancy.subdomain_suffix', 'crm.com.br'),
        );

        return (new MailMessage)
            ->subject(__('billing.hard_cap_triggered.subject'))
            ->greeting(__('billing.hard_cap_triggered.greeting'))
            ->line(__('billing.hard_cap_triggered.body', [
                'count' => $this->meter->messages_count,
                'cap' => $this->meter->hard_cap,
                'month' => $this->meter->year_month,
            ]))
            ->action(__('billing.hard_cap_triggered.cta_label'), $billingUrl);
    }
}
