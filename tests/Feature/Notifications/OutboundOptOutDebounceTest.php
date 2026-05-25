<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Events\NotificacaoSuprimida;
use App\Domain\Prescription\Preferences\PatientProfessionalPreference;
use Illuminate\Support\Facades\Event;

/**
 * Gate G5 — opt-out → skipped/opt_out (evento ainda emitido); 2º envio em <4h → skipped/debounced.
 */
class OutboundOptOutDebounceTest extends OutboundNotificationTestCase
{
    public function test_opt_out_skips_and_still_emits_audit_event(): void
    {
        Event::fake([NotificacaoSuprimida::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-optout', 'medico');
        $patient = $this->makePatient($tenant);

        PatientProfessionalPreference::create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'professional_id' => $medico->id,
            'suppress_renewal_notifications' => true,
        ]);

        $request = new NotificationRequest(
            tenantId: $tenant->id,
            patientId: $patient->id,
            type: NotificationType::PrescriptionExpiryAlert,
            milestone: 'd_7',
            sourceType: 'prescription',
            sourceId: 1,
            professionalId: $medico->id,
        );

        $notification = $this->dispatcher()->dispatch($request);

        $this->assertSame(NotificationStatus::Skipped, $notification->status);
        $this->assertSame(NotificationSkipReason::OptOut, $notification->skip_reason);
        Event::assertDispatched(NotificacaoSuprimida::class);
    }

    public function test_second_dispatch_within_debounce_window_is_skipped(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-debounce', 'medico');
        $patient = $this->makePatient($tenant);

        // 1ª notificação (marco distinto) — apenas marca a chave de debounce.
        $first = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient, 't_minus_24h'));
        $this->assertNotSame(NotificationStatus::Skipped, $first->status);

        // 2ª notificação, mesmo tipo, marco diferente, dentro de 4h → debounced.
        $second = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient, 't_minus_2h'));

        $this->assertSame(NotificationStatus::Skipped, $second->status);
        $this->assertSame(NotificationSkipReason::Debounced, $second->skip_reason);
    }
}
