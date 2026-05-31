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
 * **T184 (Fase 18 — US6)** — indicador "IA está pensando" no modal sandbox.
 */
final class PersonaTestThinking implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly PersonaTestSession $session,
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
        return 'persona-test.thinking';
    }
}
