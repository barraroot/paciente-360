<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;

/**
 * Gate G7 (US4) — sem canal → pending_manual/no_channel + mensagem de sistema
 * na conversa + conversa sinalizada (priority alta).
 */
class OutboundPendingManualTest extends OutboundNotificationTestCase
{
    public function test_no_channel_routes_to_manual_with_system_message(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-g7', 'medico');

        // Canal WhatsApp DESCONECTADO → resolver não encontra canal ativo.
        $channel = $this->makeWhatsAppChannel($tenant, status: 'desconectado');
        $patient = $this->makePatient($tenant);

        // Conversa pré-existente (ex.: inbound anterior) para receber a mensagem de sistema.
        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'patient_id' => $patient->id,
            'external_thread_id' => $patient->fresh()->telefone_primario_normalizado,
            'status' => 'aberta',
            'opened_at' => now(),
            'priority' => 'normal',
            'received_outside_hours' => false,
            'unread_count' => 0,
        ]);

        $notification = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient));

        $this->assertSame(NotificationStatus::PendingManual, $notification->status);
        $this->assertSame(NotificationSkipReason::NoChannel, $notification->skip_reason);

        $conversation->refresh();
        $this->assertSame('alta', $conversation->priority);

        $systemMessage = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', 'system')
            ->first();

        $this->assertNotNull($systemMessage);
        $this->assertStringContainsString('contatar paciente manualmente', (string) $systemMessage->body);
        $this->assertStringContainsString('no_channel', (string) $systemMessage->body);
    }
}
