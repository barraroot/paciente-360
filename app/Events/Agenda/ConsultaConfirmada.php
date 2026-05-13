<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T100 — Evento: paciente confirmou (resposta "1" — clarify nº 6).
 */
class ConsultaConfirmada implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $via, // 24h | 2h | manual
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->appointment->tenant_id}.agenda")];
    }

    public function broadcastAs(): string
    {
        return 'consulta.confirmada';
    }
}
