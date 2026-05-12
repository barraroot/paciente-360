<?php

namespace App\Policies;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * T119 — Policy de autorização para `Message`.
 *
 * Delega para ConversationPolicy — mensagem é acessível se a conversa for acessível.
 */
final class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        $message->loadMissing('conversation');

        if ($message->conversation === null) {
            return false;
        }

        return Gate::allows('view', $message->conversation);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return Gate::allows('respond', $conversation);
    }
}
