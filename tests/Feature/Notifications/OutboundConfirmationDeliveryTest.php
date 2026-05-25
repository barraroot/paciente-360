<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Models\OutboundNotification;
use App\Events\Agenda\ConsultaConfirmacaoPendente;
use App\Listeners\Agenda\DispatchConfirmationToInbox;
use App\Models\Agenda\Appointment;

/**
 * Gate G1 (US1) — confirmação T-24h com canal+template → sent + Message template.
 */
class OutboundConfirmationDeliveryTest extends OutboundNotificationTestCase
{
    public function test_confirmation_dispatches_real_template_message(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-g1', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $patient->id,
        ]);

        $event = new ConsultaConfirmacaoPendente(
            appointment: $appointment,
            kind: '24h',
            viaIa: false,
            horarioBrasilia: '14:00',
            tzLabel: 'horário de São Paulo',
        );

        app(DispatchConfirmationToInbox::class)->handle($event);

        $notification = OutboundNotification::withoutTenantScope()
            ->where('patient_id', $patient->id)
            ->firstOrFail();

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame('t_minus_24h', $notification->milestone);
        $this->assertNotNull($notification->message_id);

        $message = Message::withoutGlobalScopes()->findOrFail($notification->message_id);
        $this->assertSame('template', $message->content_type);
    }

    public function test_via_ia_does_not_send_template(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-g1-ia', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $patient->id,
        ]);

        app(DispatchConfirmationToInbox::class)->handle(new ConsultaConfirmacaoPendente(
            appointment: $appointment,
            kind: '24h',
            viaIa: true,
            horarioBrasilia: '14:00',
            tzLabel: 'horário de São Paulo',
        ));

        $this->assertSame(0, OutboundNotification::withoutTenantScope()->where('patient_id', $patient->id)->count());
    }
}
