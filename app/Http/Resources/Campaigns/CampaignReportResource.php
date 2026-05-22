<?php

declare(strict_types=1);

namespace App\Http\Resources\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T166 (Fase 8 — Lote C US-9.1)** — Relatório agregado de campanha (AC-9.1.4).
 *
 * Sem PII — apenas contadores por status + breakdown de motivos de bloqueio.
 *
 * @property-read Campaign $resource
 */
class CampaignReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusCounts = CampaignRecipient::query()
            ->where('campaign_id', $this->resource->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $blockedReasons = CampaignRecipient::query()
            ->where('campaign_id', $this->resource->id)
            ->where('status', CampaignRecipientStatus::Blocked->value)
            ->selectRaw('blocked_reason, COUNT(*) as total')
            ->groupBy('blocked_reason')
            ->pluck('total', 'blocked_reason')
            ->toArray();

        $attributedAppointments = (int) CampaignRecipient::query()
            ->where('campaign_id', $this->resource->id)
            ->whereNotNull('attributed_appointment_id')
            ->count();

        return [
            'campaign_id' => $this->resource->id,
            'status' => $this->resource->status->value,
            'total_eligible' => $this->resource->total_eligible,
            'total_dispatched' => $this->resource->total_dispatched,
            'total_blocked' => $this->resource->total_blocked,
            'status_breakdown' => $statusCounts,
            'blocked_breakdown' => $blockedReasons,
            'attributed_appointments' => $attributedAppointments,
            'dispatched_at' => $this->resource->dispatched_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
