<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource do Tenant — payload público consumido pelo SPA.
 *
 * Expõe apenas dados não-sensíveis: id, slug, name, status, trial_ends_at,
 * plano vigente (subset comercial), flag `restrictions_active` (D+7 do
 * estado overdue) e `onboarding_completed`. Não expõe CNPJ, dados do
 * responsável ou stripe_customer_id (Princípio I — LGPD minimização).
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § TenantResource
 * @see App\Http\Resources\AuthenticatedUserResource
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $onboardingState = is_array($this->onboarding_state) ? $this->onboarding_state : [];

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'plan' => $this->whenLoaded(
                'plan',
                fn () => $this->plan !== null ? PlanResource::make($this->plan) : null,
                fn () => null,
            ),
            'restrictions_active' => $this->restrictions_applied_at !== null,
            'onboarding_completed' => (bool) ($onboardingState['completed'] ?? false),
        ];
    }
}
