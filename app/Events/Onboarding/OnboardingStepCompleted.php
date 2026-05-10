<?php

namespace App\Events\Onboarding;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando um step do wizard de onboarding é completado
 * (FR-034 — Princípios I e V). Audita `onboarding.step.completed` com
 * o `step` chave no payload.
 *
 * @see App\Services\Onboarding\OnboardingService::completeStep()
 */
final class OnboardingStepCompleted implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $stepKey,
        public readonly ?int $userId = null,
    ) {}

    public function auditAction(): string
    {
        return 'onboarding.step.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return ['step' => $this->stepKey];
    }

    public function auditableModel(): ?Model
    {
        return $this->tenant;
    }

    public function auditTenantId(): ?int
    {
        return $this->tenant->id;
    }

    public function auditUserId(): ?int
    {
        return $this->userId ?? auth()->id();
    }
}
