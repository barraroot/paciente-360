<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Models\OutboundNotification;
use App\Events\Agenda\VagaAbertaNaListaDeEspera;
use App\Listeners\Agenda\DispatchWaitlistOfferToInbox;
use App\Models\Agenda\WaitlistEntry;

/**
 * Gate G10 (US3) — oferta enviada ao paciente da lista de espera; cada evento
 * (re-emitido pela Fase 5 ao próximo) gera uma oferta entregue.
 */
class OutboundWaitlistOfferTest extends OutboundNotificationTestCase
{
    public function test_waitlist_offer_is_delivered_to_patient(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-g10', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant, NotificationType::WaitlistOffer, 'HX_wait');
        $this->makeApprovedChannelTemplate($tenant, $channel, 'HX_wait');
        $patient = $this->makePatient($tenant);

        $entry = WaitlistEntry::factory()->notified()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $patient->id,
        ]);

        app(DispatchWaitlistOfferToInbox::class)->handle(
            new VagaAbertaNaListaDeEspera($entry, 120),
        );

        $notification = OutboundNotification::withoutTenantScope()
            ->where('patient_id', $patient->id)
            ->where('notification_type', NotificationType::WaitlistOffer->value)
            ->firstOrFail();

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame('offer', $notification->milestone);
    }

    public function test_next_in_queue_gets_offer_when_window_expires(): void
    {
        // A Fase 5 re-emite o evento para o próximo da fila; aqui validamos que
        // cada paciente distinto recebe sua oferta entregue.
        [$tenant] = $this->tenantAndUserForRole('clinica-g10-next', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant, NotificationType::WaitlistOffer, 'HX_wait');
        $this->makeApprovedChannelTemplate($tenant, $channel, 'HX_wait');

        $next = $this->makePatient($tenant, phone: '+5511955554444');
        $entry = WaitlistEntry::factory()->notified()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $next->id,
        ]);

        app(DispatchWaitlistOfferToInbox::class)->handle(
            new VagaAbertaNaListaDeEspera($entry, 120),
        );

        $this->assertSame(
            NotificationStatus::Sent,
            OutboundNotification::withoutTenantScope()->where('patient_id', $next->id)->firstOrFail()->status,
        );
    }
}
