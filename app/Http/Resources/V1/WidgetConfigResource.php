<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T213 — Resource de `WebWidgetConfig` para API responses (admin).
 *
 * Inclui aparência, horários, comportamento e origens autorizadas.
 * Não inclui dados sensíveis internos.
 *
 * @see specs/003-omnichannel-inbox/contracts/openapi.yaml § WidgetConfigResource
 */
final class WidgetConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'public_key' => $this->public_key,
            'allowed_origins' => $this->allowed_origins ?? [],
            'appearance' => $this->appearance ?? [],
            'initial_message' => $this->initial_message,
            'business_hours' => $this->business_hours ?? [],
            'outside_hours_behavior' => $this->outside_hours_behavior,
            'outside_hours_message' => $this->outside_hours_message,
            'pre_chat_form' => $this->pre_chat_form,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
