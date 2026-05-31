<?php

declare(strict_types=1);

namespace App\Events\Ai\Persona;

use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Domain\Messaging\Message\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T184 (Fase 18 — US6)** — emitido quando a IA responde dentro de uma
 * Persona Test Session. NÃO sai por canal real (Twilio/IG/Evolution) —
 * apenas via Reverb para o modal de chat sandbox do admin.
 */
final class PersonaTestMessageBroadcasted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly PersonaTestSession $session,
        public readonly Message $message,
        /** 'ia' | 'admin' | 'system' */
        public readonly string $kind,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("persona-test.{$this->session->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'persona-test.message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'kind' => $this->kind,
            'message' => [
                'id' => $this->message->id,
                'direction' => $this->message->direction,
                'sender_type' => $this->message->sender_type,
                'body' => $this->message->body,
                'sandbox' => true,
                'created_at' => optional($this->message->created_at)->toIso8601String(),
            ],
        ];
    }
}
