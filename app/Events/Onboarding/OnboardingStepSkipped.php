<?php

namespace App\Events\Onboarding;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento disparado quando um step opcional do wizard de onboarding é pulado
 * (FR-034 — Princípios I e V). Audita `onboarding.step.skipped` com o `step`
 * chave no payload.
 *
 * @see App\Services\Onboarding\OnboardingService::skipStep()
 */
final class OnboardingStepSkipped implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $stepKey,
        public readonly ?int $userId = null,
    ) {}

    public function auditAction(): string
    {
        return 'onboarding.step.skipped';
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
