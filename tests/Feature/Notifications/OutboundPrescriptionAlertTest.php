<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Prescription\Preferences\PatientProfessionalPreference;

/**
 * Gate G9 (US2) — alerta D-7 entregue, sem dado clínico, respeitando opt-out.
 */
class OutboundPrescriptionAlertTest extends OutboundNotificationTestCase
{
    private function prescriptionRequest(int $tenantId, int $patientId, ?int $professionalId): NotificationRequest
    {
        return new NotificationRequest(
            tenantId: $tenantId,
            patientId: $patientId,
            type: NotificationType::PrescriptionExpiryAlert,
            milestone: 'd_7',
            sourceType: 'prescription',
            sourceId: 1,
            context: ['days_until_expiry' => 7],
            professionalId: $professionalId,
        );
    }

    public function test_alert_is_delivered_without_clinical_data(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-g9', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant, NotificationType::PrescriptionExpiryAlert, 'HX_presc');
        $this->makeApprovedChannelTemplate($tenant, $channel, 'HX_presc');
        $patient = $this->makePatient($tenant);

        $notification = $this->dispatcher()->dispatch($this->prescriptionRequest($tenant->id, $patient->id, $medico->id));

        $this->assertSame(NotificationStatus::Sent, $notification->status);

        $message = Message::withoutGlobalScopes()->findOrFail($notification->message_id);
        $serialized = json_encode($message->template_variables);
        foreach (['medicament', 'posolog', 'diagn'] as $clinical) {
            $this->assertStringNotContainsStringIgnoringCase($clinical, (string) $serialized);
        }
    }

    public function test_alert_respects_renewal_opt_out(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-g9-opt', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant, NotificationType::PrescriptionExpiryAlert, 'HX_presc');
        $this->makeApprovedChannelTemplate($tenant, $channel, 'HX_presc');
        $patient = $this->makePatient($tenant);

        PatientProfessionalPreference::create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'professional_id' => $medico->id,
            'suppress_renewal_notifications' => true,
        ]);

        $notification = $this->dispatcher()->dispatch($this->prescriptionRequest($tenant->id, $patient->id, $medico->id));

        $this->assertSame(NotificationStatus::Skipped, $notification->status);
        $this->assertSame(NotificationSkipReason::OptOut, $notification->skip_reason);
    }
}
