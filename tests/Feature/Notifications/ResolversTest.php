<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\NotificationTemplateResolver;
use App\Domain\Messaging\Notification\Services\OutboundChannelResolver;

/**
 * T017 — Unit/integration dos resolvers de canal e template.
 */
class ResolversTest extends OutboundNotificationTestCase
{
    private function channelResolver(): OutboundChannelResolver
    {
        return app(OutboundChannelResolver::class);
    }

    private function templateResolver(): NotificationTemplateResolver
    {
        return app(NotificationTemplateResolver::class);
    }

    public function test_channel_resolver_returns_null_without_active_whatsapp(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-r1', 'medico');
        $patient = $this->makePatient($tenant);

        $this->assertNull($this->channelResolver()->resolve($tenant->id, $patient->id));
    }

    public function test_channel_resolver_returns_null_without_patient_phone(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-r2', 'medico');
        $this->makeWhatsAppChannel($tenant);
        $patient = $this->makePatient($tenant, phone: '');

        $this->assertNull($this->channelResolver()->resolve($tenant->id, $patient->id));
    }

    public function test_channel_resolver_resolves_and_detects_closed_window(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-r3', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $patient = $this->makePatient($tenant);

        $resolved = $this->channelResolver()->resolve($tenant->id, $patient->id);

        $this->assertNotNull($resolved);
        $this->assertSame($channel->id, $resolved->channel->id);
        $this->assertFalse($resolved->withinWindow);
    }

    public function test_template_resolver_requires_approved_channel_template(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-r4', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);

        // Sem ChannelTemplate aprovado → null (gate D1).
        $this->assertNull(
            $this->templateResolver()->resolve($tenant->id, NotificationType::AppointmentConfirmation, $channel),
        );

        $this->makeApprovedChannelTemplate($tenant, $channel);

        $this->assertNotNull(
            $this->templateResolver()->resolve($tenant->id, NotificationType::AppointmentConfirmation, $channel->fresh()),
        );
    }
}
