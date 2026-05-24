<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding;

use App\Exceptions\Onboarding\UnknownStepException;
use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * **T045 (Spec 012)** — Cobertura de borda do método `OnboardingService::unlockStep`.
 *
 * Foco na idempotência: locked → pending; no-op quando já pending/completed/skipped;
 * step inexistente lança exceção.
 */
final class OnboardingServiceUnlockStepTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private OnboardingService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OnboardingService::class);
        $this->tenant = $this->createTenant(['slug' => 'unlock-step-unit', 'status' => 'active']);
    }

    private function statusOf(string $stepKey): string
    {
        $state = $this->service->getState($this->tenant->fresh());

        return collect($state['steps'])->firstWhere('key', $stepKey)['status'];
    }

    public function test_unlock_step_changes_locked_to_pending(): void
    {
        $this->assertSame('locked', $this->statusOf('first_professional'));

        $this->service->unlockStep($this->tenant, 'first_professional');

        $this->assertSame('pending', $this->statusOf('first_professional'));
    }

    public function test_unlock_step_is_idempotent_when_already_pending(): void
    {
        $this->service->unlockStep($this->tenant, 'first_professional');
        $this->service->unlockStep($this->tenant, 'first_professional');

        $this->assertSame('pending', $this->statusOf('first_professional'));
    }

    public function test_unlock_step_is_no_op_on_completed_step(): void
    {
        $this->service->completeStep($this->tenant, 'clinic_data', ['x' => 1]);
        $this->assertSame('completed', $this->statusOf('clinic_data'));

        $this->service->unlockStep($this->tenant, 'clinic_data');

        $this->assertSame('completed', $this->statusOf('clinic_data'));
    }

    public function test_unlock_unknown_step_throws(): void
    {
        $this->expectException(UnknownStepException::class);

        $this->service->unlockStep($this->tenant, 'nonexistent_step');
    }
}
