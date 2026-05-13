<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * T069 — Evento de domínio: consulta reagendada (US-6.3/6.5 — clarify nº 7).
 */
class ConsultaReagendada implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly Carbon $startsAtAnterior,
        public readonly Carbon $startsAtNovo,
        public readonly string $quemSolicitou,
        public readonly ?string $motivo = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->appointment->tenant_id}.agenda")];
    }

    public function broadcastAs(): string
    {
        return 'consulta.reagendada';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'starts_at_anterior' => $this->startsAtAnterior->toIso8601String(),
            'starts_at_novo' => $this->startsAtNovo->toIso8601String(),
            'quem_solicitou' => $this->quemSolicitou,
        ];
    }
}
