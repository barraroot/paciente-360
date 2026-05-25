<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Feature 013 — Serialização de NotificationTemplate (US5).
 *
 * @mixin NotificationTemplate
 */
class NotificationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_type' => $this->notification_type->value,
            'channel_type' => $this->channel_type,
            'provider_template_id' => $this->provider_template_id,
            'language' => $this->language,
            'variables_map' => $this->variables_map,
            'is_active' => $this->is_active,
        ];
    }
}
