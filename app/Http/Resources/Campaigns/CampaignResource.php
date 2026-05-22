<?php

declare(strict_types=1);

namespace App\Http\Resources\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T166 (Fase 8 — Lote C US-9.1)** — Serialização de Campaign.
 *
 * @property-read Campaign $resource
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'channel' => $this->resource->channel->value,
            'template_id' => $this->resource->template_id,
            'audience_filters' => $this->resource->audience_filters,
            'scheduled_for' => $this->resource->scheduled_for?->toIso8601String(),
            'dispatched_at' => $this->resource->dispatched_at?->toIso8601String(),
            'total_eligible' => $this->resource->total_eligible,
            'total_dispatched' => $this->resource->total_dispatched,
            'total_blocked' => $this->resource->total_blocked,
            'daily_limit_applied' => $this->resource->daily_limit_applied,
            'canceled_at' => $this->resource->canceled_at?->toIso8601String(),
            'canceled_reason' => $this->resource->canceled_reason,
            'created_by_user_id' => $this->resource->created_by_user_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
