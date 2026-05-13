<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T068 — Evento de domínio: consulta criada (US-6.3 / FR-012).
 *
 * Auditável + Broadcast no canal `tenant.{X}.agenda` (Reverb) para sync multi-aba.
 * Consumido por: Fase 2 (move card no funil), Fase 3 (notifica paciente),
 * US-6.7 (sync Google Calendar).
 */
class ConsultaCriada implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly bool $notifyPatient = true,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->appointment->tenant_id}.agenda")];
    }

    public function broadcastAs(): string
    {
        return 'consulta.criada';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'professional_id' => $this->appointment->professional_id,
            'starts_at' => $this->appointment->starts_at?->toIso8601String(),
            'channel_origin' => $this->appointment->channel_origin,
        ];
    }
}
