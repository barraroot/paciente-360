<?php

namespace App\Domain\Messaging\Message\Observers;

use App\Domain\Messaging\Conversation\Events\ConversaReaberta;
use App\Domain\Messaging\Message\Models\Message;

/**
 * Observer para o model `Message`.
 *
 * Ao criar mensagem inbound em conversa resolvida, reabre a conversa
 * automaticamente (NC-2: nova msg inbound reabre como aberta).
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
}
