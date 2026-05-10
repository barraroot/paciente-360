<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Resource da assinatura ativa do tenant (T186).
 *
 * Expõe dados de assinatura conforme OpenAPI
 * `specs/001-fundacao-multitenant/contracts/openapi.yaml § SubscriptionResource`.
 *
 * @see Laravel\Cashier\Subscription
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * Converte um valor de data (Carbon ou string) para ISO 8601, ou null.
     */
    private function toIso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $planId = $this->resource->plan_id ?? null;
        $plan = $planId ? Plan::find($planId) : null;

        return [
            'id' => $this->resource->id,
            'stripe_status' => $this->resource->stripe_status,
            'stripe_id' => $this->resource->stripe_id,
            'plan' => $plan ? new PlanResource($plan) : null,
            'professionals_quantity' => $this->resource->professionals_quantity ?? null,
            'quantity' => $this->resource->quantity,
            'current_period_start' => $this->toIso($this->resource->current_period_start),
            'current_period_end' => $this->toIso($this->resource->current_period_end),
            'trial_ends_at' => $this->toIso($this->resource->trial_ends_at),
            'ends_at' => $this->toIso($this->resource->ends_at),
            'proration_preview' => $this->resource->proration_preview ?? null,
        ];
    }
}
