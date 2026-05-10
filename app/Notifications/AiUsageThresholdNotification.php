<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação de threshold de uso de IA (T224).
 *
 * Enviada ao Admin Clínica e Financeiro ao cruzar 80% ou 100% da cota.
 * Para 100%: informa que excedente será cobrado. Apenas pt-BR.
 */
final class AiUsageThresholdNotification extends Notification
{
    use Queueable;

    /**
     * @param int $threshold 80 ou 100
     */
    public function __construct(
        public readonly int $threshold,
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
        $subject = $this->threshold >= 100
            ? __('billing.ai_usage.threshold_100_subject')
            : __('billing.ai_usage.threshold_80_subject');

        $body = $this->threshold >= 100
            ? __('billing.ai_usage.threshold_100_body')
            : __('billing.ai_usage.threshold_80_body');

        return (new MailMessage)
            ->subject($subject)
            ->line($body);
    }
}
