<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T101 — Evento de broadcast para atualização do relatório de receitas.
 *
 * Sem dados clínicos — apenas metadados para triggerar refresh do frontend.
 * Canal: `prescriptions.{tenantId}` (privado, ver routes/channels.php T108).
 */
final class PrescriptionsReportRefresh implements ContainsNoClinicalData, ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $prescriptionId,
        public readonly string $criticality,
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("prescriptions.{$this->tenantId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'prescription.expiry.refresh';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'prescription_id' => $this->prescriptionId,
            'criticality' => $this->criticality,
        ];
    }
}
