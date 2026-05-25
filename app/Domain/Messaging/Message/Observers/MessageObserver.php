<?php

namespace App\Domain\Messaging\Message\Observers;

use App\Domain\Messaging\Conversation\Events\ConversaReaberta;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\Models\OutboundNotification;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;

/**
 * Observer para o model `Message`.
 *
 * - `created`: ao criar mensagem inbound em conversa resolvida, reabre a conversa
 *   automaticamente (NC-2: nova msg inbound reabre como aberta).
 * - `updated`: ao mudar o status (via `TwilioStatusCallbackController`), reconcilia
 *   a `OutboundNotification` ligada (feature 013 / R7).
 */
final class MessageObserver
{
    public function created(Message $message): void
    {
        if ($message->direction !== 'in') {
            return;
        }

        $message->loadMissing('conversation');
        $conversation = $message->conversation;

        if ($conversation === null) {
            return;
        }

        if ($conversation->status !== 'resolvida' && $conversation->status !== 'reaberta') {
            return;
        }

        $conversation->update([
            'status' => 'aberta',
            'resolved_at' => null,
            'resolution_mode' => null,
        ]);

        event(new ConversaReaberta($conversation, 'nova_msg'));
    }

    /**
     * Feature 013 — reconcilia o status de entrega das notificações outbound
     * a partir das transições de status da Message (callback do provedor).
     */
    public function updated(Message $message): void
    {
        if (! $message->wasChanged('status')) {
            return;
        }

        if (! in_array($message->status, ['delivered', 'failed'], true)) {
            return;
        }

        $notification = OutboundNotification::withoutTenantScope()
            ->where('message_id', $message->id)
            ->first();

        if ($notification === null) {
            return;
        }

        $dispatcher = app(OutboundNotificationDispatcher::class);

        if ($message->status === 'delivered') {
            $dispatcher->reconcileDelivered($notification);

            return;
        }

        $dispatcher->reconcileFailed($notification);
    }
}
