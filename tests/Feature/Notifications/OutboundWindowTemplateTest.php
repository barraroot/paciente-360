<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;

/**
 * Gate G4 (Princípio VI) — janela 24h + gate de aprovação de template (D1).
 */
class OutboundWindowTemplateTest extends OutboundNotificationTestCase
{
    public function test_outside_window_with_approved_template_sends(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-win-ok', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient));

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertNotNull($notification->notification_template_id);
    }

    public function test_outside_window_without_template_is_pending_manual_no_template(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-win-notpl', 'medico');
        $this->makeWhatsAppChannel($tenant);
        $patient = $this->makePatient($tenant);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient));

        $this->assertSame(NotificationStatus::PendingManual, $notification->status);
        $this->assertSame(NotificationSkipReason::NoTemplate, $notification->skip_reason);
    }

    public function test_outside_window_with_unapproved_provider_template_is_blocked(): void
    {
        // D1: NotificationTemplate existe, mas o ChannelTemplate correspondente NÃO está aprovado.
        [$tenant] = $this->tenantAndUserForRole('clinica-win-unappr', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant, providerTemplateId: 'HX_unapproved');
        ChannelTemplate::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'provider_template_id' => 'HX_unapproved',
        ]);
        $patient = $this->makePatient($tenant);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient));

        $this->assertSame(NotificationStatus::PendingManual, $notification->status);
        $this->assertSame(NotificationSkipReason::NoTemplate, $notification->skip_reason);
    }

    public function test_within_window_allows_free_text(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-win-free', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $patient = $this->makePatient($tenant);

        // Conversa com inbound recente → janela aberta (sem template necessário).
        Conversation::create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'patient_id' => $patient->id,
            'external_thread_id' => $patient->fresh()->telefone_primario_normalizado,
            'status' => 'aberta',
            'opened_at' => now(),
            'priority' => 'normal',
            'last_inbound_message_at' => now()->subHour(),
            'received_outside_hours' => false,
            'unread_count' => 0,
        ]);

        $request = new NotificationRequest(
            tenantId: $tenant->id,
            patientId: $patient->id,
            type: NotificationType::AppointmentConfirmation,
            milestone: 't_minus_2h',
            sourceType: 'appointment',
            sourceId: 1,
            context: [],
            freeFormBody: 'Olá! Confirmando sua consulta de hoje.',
        );

        $notification = $this->dispatcher()->dispatch($request);

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertNull($notification->notification_template_id);

        $message = Message::withoutGlobalScopes()->where('content_type', 'text')->where('direction', 'out')->firstOrFail();
        $this->assertSame('Olá! Confirmando sua consulta de hoje.', $message->body);
    }
}
