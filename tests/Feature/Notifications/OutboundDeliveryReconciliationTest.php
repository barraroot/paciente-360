<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;

/**
 * Gate G8 (US1) — callback de status do provedor transiciona sent → delivered/failed;
 * falha definitiva → pending_manual/send_failed.
 */
class OutboundDeliveryReconciliationTest extends OutboundNotificationTestCase
{
    public function test_delivered_callback_transitions_notification_to_delivered(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-g8-ok', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient));
        $this->assertSame(NotificationStatus::Sent, $notification->status);

        $message = Message::withoutGlobalScopes()->findOrFail($notification->message_id);
        $message->update(['status' => 'delivered']);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Delivered, $notification->status);
        $this->assertNotNull($notification->delivered_at);
    }

    public function test_failed_callback_routes_to_pending_manual(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-g8-fail', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient));

        $message = Message::withoutGlobalScopes()->findOrFail($notification->message_id);
        $message->update(['status' => 'failed']);

        $notification->refresh();
        $this->assertSame(NotificationStatus::PendingManual, $notification->status);
        $this->assertSame(NotificationSkipReason::SendFailed, $notification->skip_reason);
        $this->assertNotNull($notification->failed_at);
    }
}
