<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T101 — Evento: consulta cancelada (US-6.4 / US-6.5).
 *
 * Consumido por: US-6.6 (abre lista de espera), Fase 2 (timeline),
 * métricas de no-show.
 */
class ConsultaCancelada implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $quemCancelou,  // paciente | atendente | profissional | sistema
        public readonly string $motivo,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->appointment->tenant_id}.agenda")];
    }

    public function broadcastAs(): string
    {
        return 'consulta.cancelada';
    }
}
