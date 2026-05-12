<?php

namespace App\Domain\Messaging\Message\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Broadcasting\Channel as BroadcastChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T083 — Evento disparado quando uma mensagem inbound é recebida.
 *
 * Auditado + Broadcast em `tenant.{id}.inbox` e `tenant.{id}.conversa.{cid}`.
 */
final class MensagemRecebida implements Auditable, ShouldBroadcast
{
    use Dispatchable;
    use IsAuditable;

    public readonly int $tenantId;

    public readonly int $conversationId;

    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
    ) {
        $this->tenantId = $conversation->tenant_id;
        $this->conversationId = $conversation->id;
    }

    public function auditAction(): string
    {
        return 'mensagem.recebida';
    }

    public function auditableModel(): ?Model
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'direction' => $this->message->direction,
            'content_type' => $this->message->content_type,
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
            new PrivateChannel("tenant.{$this->tenantId}.conversa.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mensagem.recebida';
    }

    /**
     * Payload de broadcast — não inclui campos criptografados para evitar
     * DecryptException durante serialização do payload de broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'direction' => $this->message->direction,
            'content_type' => $this->message->content_type,
            'body_preview' => $this->message->body_preview,
            'sender_type' => $this->message->sender_type,
            'status' => $this->message->status,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
