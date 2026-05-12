<?php

namespace App\Domain\Messaging\Assignment\Services\Strategies;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;

/**
 * Interface for auto-assignment strategies.
 *
 * Each strategy picks a User to assign to a conversation, or returns null
 * when no eligible user is found. The AssignmentService orchestrates fallback chains.
 */
interface AssignmentStrategy
{
    /**
     * Pick the best eligible user for the conversation.
     *
     * Returns null when no user qualifies (caller decides fallback behavior).
     */
    public function pickUser(Conversation $conversation, ?Channel $channelOverride = null): ?User;
}
