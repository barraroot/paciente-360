<?php

namespace App\Domain\Messaging\Assignment\Services\Strategies;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * T147 — Round-robin assignment strategy.
 *
 * Picks the next online user (last_seen_at >= now()-5min) with capacity
 * (current_assigned_count < max_concurrent_conversations).
 *
 * Uses a Redis cursor per tenant to ensure fair rotation across calls.
 * Pessimistic locking is handled by AssignmentService at the conversation level.
 */
final class RoundRobinStrategy implements AssignmentStrategy
{
    /** Number of minutes of inactivity before a user is considered offline. */
    private const IDLE_MINUTES = 5;

    public function pickUser(Conversation $conversation, ?Channel $channelOverride = null): ?User
    {
        $tenantId = $conversation->tenant_id;

        /** @var Collection<int, UserPresence> $presences */
        $presences = UserPresence::where('tenant_id', $tenantId)
            ->where('status', 'online')
            ->where('last_seen_at', '>', now()->subMinutes(self::IDLE_MINUTES))
            ->whereRaw('current_assigned_count < max_concurrent_conversations')
            ->with('user')
            ->orderBy('id')
            ->get();

        if ($presences->isEmpty()) {
            return null;
        }

        // Use a Redis counter as round-robin cursor for fair distribution
        $cursorKey = "cb:round_robin:{$tenantId}:cursor";
        $cursor = (int) Cache::increment($cursorKey);
        Cache::put($cursorKey, $cursor, now()->addDay());

        $index = ($cursor - 1) % $presences->count();

        return $presences->get($index)?->user;
    }
}
