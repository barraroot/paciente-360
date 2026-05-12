<?php

namespace App\Domain\Messaging\Assignment\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use Illuminate\Broadcasting\Channel as BroadcastChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T160 — Broadcast event dispatched after every assign / transfer.
 *
 * Notifies:
 *  - `tenant.{id}.inbox`              — all inbox viewers (queue refreshes)
 *  - `tenant.{id}.user.{assignedId}`  — the target user receives in-app notification
 *
 * Implements AC-4.5.5.
 */
final class ConversaAtribuidaParaInbox implements ShouldBroadcast
{
    use Dispatchable;

    public readonly int $tenantId;

    public function __construct(
        public readonly Conversation $conversation,
        /** @var 'manual'|'auto_round_robin'|'auto_patient_owner'|'transfer' */
        public readonly string $mode,
        public readonly ?int $assignedUserId,
        public readonly ?int $fromUserId = null,
    ) {
        $this->tenantId = $conversation->tenant_id;
    }

    /**
     * @return array<int, BroadcastChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("tenant.{$this->tenantId}.inbox"),
        ];

        if ($this->assignedUserId !== null) {
            $channels[] = new PrivateChannel("tenant.{$this->tenantId}.user.{$this->assignedUserId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversa.atribuida';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'patient_id' => $this->conversation->patient_id,
            'channel_id' => $this->conversation->channel_id,
            'mode' => $this->mode,
            'assigned_user_id' => $this->assignedUserId,
            'from_user_id' => $this->fromUserId,
        ];
    }
}
