<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * **T048 (Spec 012)** — Backfill idempotente de unlocks do onboarding.
 *
 * Tenants criados antes da Fase 12 podem ter steps `locked` indevidamente;
 * o comando reaplica os unlocks via OnboardingService (idempotente).
 */
final class OnboardingBackfillUnlocksCommandTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function statusOf(Tenant $tenant, string $stepKey): string
    {
        $state = app(OnboardingService::class)->getState($tenant->fresh());

        return collect($state['steps'])->firstWhere('key', $stepKey)['status'];
    }

    private function withSteps(string $slug, array $steps): Tenant
    {
        return $this->createTenant([
            'slug' => $slug,
            'status' => 'active',
            'onboarding_state' => ['steps' => $steps],
        ]);
    }

    public function test_backfill_unlocks_first_professional_when_clinic_data_completed(): void
    {
        $tenant = $this->withSteps('backfill-a', [
            'clinic_data' => ['status' => 'completed'],
            'first_professional' => ['status' => 'locked'],
        ]);

        $this->artisan('onboarding:backfill-unlocks')->assertSuccessful();

        $this->assertSame('pending', $this->statusOf($tenant, 'first_professional'));
    }

    public function test_backfill_unlocks_schedule_setup_when_first_professional_completed(): void
    {
        $tenant = $this->withSteps('backfill-b', [
            'clinic_data' => ['status' => 'completed'],
            'first_professional' => ['status' => 'completed'],
            'schedule_setup' => ['status' => 'locked'],
        ]);

        $this->artisan('onboarding:backfill-unlocks')->assertSuccessful();

        $this->assertSame('pending', $this->statusOf($tenant, 'schedule_setup'));
    }

    public function test_backfill_is_idempotent_and_leaves_locked_when_prereq_not_met(): void
    {
        // clinic_data ainda pending → first_professional NÃO deve desbloquear.
        $tenant = $this->withSteps('backfill-c', [
            'clinic_data' => ['status' => 'pending'],
            'first_professional' => ['status' => 'locked'],
        ]);

        $this->artisan('onboarding:backfill-unlocks')->assertSuccessful();
        $this->artisan('onboarding:backfill-unlocks')->assertSuccessful();

        $this->assertSame('locked', $this->statusOf($tenant, 'first_professional'));
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $tenant = $this->withSteps('backfill-dry', [
            'clinic_data' => ['status' => 'completed'],
            'first_professional' => ['status' => 'locked'],
        ]);

        $this->artisan('onboarding:backfill-unlocks', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('locked', $this->statusOf($tenant, 'first_professional'));
    }
}
