<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Domain\Integrations\Models\WebhookDeadLetter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookDeadLetter
 */
class WebhookDeadLetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'original_delivery_id' => $this->original_delivery_id,
            'event_type' => $this->event_type,
            'event_id' => $this->event_id,
            'correlation_id' => $this->correlation_id,
            'failure_history' => $this->failure_history?->toArray(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'resent_at' => $this->resent_at?->toIso8601String(),
            'resent_by_user_id' => $this->resent_by_user_id,
        ];
    }
}
