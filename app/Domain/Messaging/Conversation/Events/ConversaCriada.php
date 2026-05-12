<?php

namespace App\Domain\Messaging\Conversation\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Broadcasting\Channel as BroadcastChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T083 — Evento disparado quando uma nova conversa é criada.
 *
 * Auditado + Broadcast em `tenant.{id}.inbox`.
 */
final class ConversaCriada implements Auditable, ShouldBroadcast
{
    use Dispatchable;
    use IsAuditable;

    public readonly int $tenantId;

    public function __construct(
        public readonly Conversation $conversation,
    ) {
        $this->tenantId = $conversation->tenant_id;
    }

    public function auditAction(): string
    {
        return 'conversa.criada';
    }

    public function auditableModel(): ?Model
    {
        return $this->conversation;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'channel_id' => $this->conversation->channel_id,
            'patient_id' => $this->conversation->patient_id,
            'status' => $this->conversation->status,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function relatedPacienteId(): ?int
    {
        return $this->conversation->patient_id;
    }

    /**
     * @return array<int, BroadcastChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.inbox"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversa.criada';
    }
}
