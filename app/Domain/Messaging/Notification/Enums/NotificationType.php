<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Enums;

/**
 * Tipo de notificação outbound entregue ao paciente (feature 013).
 *
 * Cada tipo mapeia para um template aprovado por tenant em `notification_templates`
 * e para a preferência de opt-out aplicável (quando houver).
 *
 * @see specs/013-outbound-notifications/data-model.md
 */
enum NotificationType: string
{
    case AppointmentConfirmation = 'appointment_confirmation';
    case PrescriptionExpiryAlert = 'prescription_expiry_alert';
    case WaitlistOffer = 'waitlist_offer';
    case CancellationEscalation = 'cancellation_escalation';
    case RescheduleLimitEscalation = 'reschedule_limit_escalation';
    case AiRenewalTask = 'ai_renewal_task';

    /**
     * Tipos que respeitam o opt-out de renovação (`suppress_renewal_notifications`).
     */
    public function respectsRenewalOptOut(): bool
    {
        return $this === self::PrescriptionExpiryAlert
            || $this === self::AiRenewalTask;
    }
}
