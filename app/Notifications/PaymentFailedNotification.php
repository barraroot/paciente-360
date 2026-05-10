<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de falha de cobrança enviada ao Admin Clínica e Financeiro
 * (T189 — FR-014). Informa a falha e exibe CTA para regularização.
 *
 * Despachada via `SendPaymentFailedEmailJob`.
 */
final class PaymentFailedNotification extends Notification
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
        $billingUrl = sprintf(
            'https://%s.%s/panel/billing/subscription',
            $this->tenant->slug,
            (string) config('tenancy.subdomain_suffix', 'crm.com.br'),
        );

        return (new MailMessage)
            ->subject(__('billing.payment_failed.subject'))
            ->greeting(__('billing.payment_failed.greeting'))
            ->line(__('billing.payment_failed.body'))
            ->action(__('billing.payment_failed.cta_label'), $billingUrl)
            ->line(__('billing.payment_failed.body_outro'));
    }
}
