<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Models\OutboundNotification;

/**
 * Gate G2 (Princípio II) — isolamento por tenant na resolução de canal/template.
 */
class OutboundCrossTenantTest extends OutboundNotificationTestCase
{
    public function test_dispatch_never_uses_another_tenants_channel(): void
    {
        // Tenant A: SEM canal. Tenant B: COM canal WhatsApp ativo.
        [$tenantA] = $this->tenantAndUserForRole('clinica-a-iso', 'medico');
        [$tenantB] = $this->tenantAndUserForRole('clinica-b-iso', 'medico');

        $this->makeWhatsAppChannel($tenantB);
        $patientA = $this->makePatient($tenantA);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenantA, $patientA));

        // Não pode usar o canal do tenant B → pending_manual/no_channel.
        $this->assertSame(NotificationStatus::PendingManual, $notification->status);
        $this->assertNull($notification->channel_id);
    }

    public function test_dispatch_uses_own_tenant_channel_and_template(): void
    {
        [$tenantA] = $this->tenantAndUserForRole('clinica-a-own', 'medico');
        [$tenantB] = $this->tenantAndUserForRole('clinica-b-own', 'medico');

        // Ambos os tenants têm canal + template, mas com provider ids distintos.
        $channelA = $this->makeWhatsAppChannel($tenantA);
        $this->makeTemplate($tenantA, providerTemplateId: 'HX_A');
        $this->makeApprovedChannelTemplate($tenantA, $channelA, 'HX_A');

        $channelB = $this->makeWhatsAppChannel($tenantB);
        $this->makeTemplate($tenantB, providerTemplateId: 'HX_B');
        $this->makeApprovedChannelTemplate($tenantB, $channelB, 'HX_B');

        $patientA = $this->makePatient($tenantA);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenantA, $patientA));

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame($channelA->id, $notification->channel_id);
        $this->assertNotSame($channelB->id, $notification->channel_id);

        // Nenhuma notificação criada para o tenant B.
        $this->assertSame(0, OutboundNotification::withoutTenantScope()->where('tenant_id', $tenantB->id)->count());
    }
}
