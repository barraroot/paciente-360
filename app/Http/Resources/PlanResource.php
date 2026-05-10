<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource do `Plan` — catálogo comercial.
 *
 * Sem campos sensíveis (Stripe Price IDs ficam expostos para o billing —
 * são identificadores não-secretos; secret keys vivem em env).
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § PlanResource
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'base_price_cents' => $this->base_price_cents,
            'included_professionals' => $this->included_professionals,
            'included_ai_messages' => $this->included_ai_messages,
            'overage_price_cents' => $this->overage_price_cents,
            'max_users' => $this->max_users,
            'max_channels' => $this->max_channels,
            'is_active' => $this->is_active,
        ];
    }
}
