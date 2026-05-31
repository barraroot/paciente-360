<?php

declare(strict_types=1);

namespace App\Events\Ai\Persona;

use App\Domain\Ai\Persona\Models\PersonaTestSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T184 (Fase 18 — US6)** — sessão sandbox foi fechada (outro device fechou,
 * superseded por nova sessão, etc.). Modal deve reagir desconectando.
 */
final class PersonaTestSessionClosed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly PersonaTestSession $session,
        /** 'user_closed' | 'superseded' | 'expired' */
        public readonly string $reason = 'user_closed',
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
        return 'persona-test.session.closed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'reason' => $this->reason,
        ];
    }
}
