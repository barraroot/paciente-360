<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * **G8 (Spec 012)** — Desbloqueio progressivo do wizard de onboarding (FR-024/025/026/029).
 *
 * - clinic_data completed → first_professional unlocked
 * - first_professional completed → schedule_setup unlocked
 * - first_professional skipped → schedule_setup permanece locked
 * - channel_connection e ai_knowledge_base permanecem locked nesta versão
 */
final class OnboardingUnlockProgressionTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private OnboardingService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OnboardingService::class);
        $this->tenant = $this->createTenant(['slug' => 'unlock-progression', 'status' => 'active']);
    }

    private function statusOf(string $stepKey): string
    {
        $state = $this->service->getState($this->tenant->fresh());

        return collect($state['steps'])->firstWhere('key', $stepKey)['status'];
    }

    public function test_completing_clinic_data_unlocks_first_professional(): void
    {
        $this->assertSame('locked', $this->statusOf('first_professional'));

        $this->service->completeStep($this->tenant, 'clinic_data', ['nome_fantasia' => 'Clínica X']);

        $this->assertSame('pending', $this->statusOf('first_professional'));
    }

    public function test_completing_first_professional_unlocks_schedule_setup(): void
    {
        $this->service->completeStep($this->tenant, 'clinic_data', []);
        $this->assertSame('locked', $this->statusOf('schedule_setup'));

        $this->service->completeStep($this->tenant, 'first_professional', [
            'professional_id' => 42,
            'via' => 'linked_user',
        ]);

        $this->assertSame('pending', $this->statusOf('schedule_setup'));
    }

    public function test_skipping_first_professional_does_not_unlock_schedule_setup(): void
    {
        $this->service->completeStep($this->tenant, 'clinic_data', []);
        $this->assertSame('pending', $this->statusOf('first_professional'));

        $this->service->skipStep($this->tenant, 'first_professional');

        $this->assertSame('skipped', $this->statusOf('first_professional'));
        $this->assertSame('locked', $this->statusOf('schedule_setup'));
    }

    public function test_channel_connection_and_ai_knowledge_base_remain_locked(): void
    {
        $this->service->completeStep($this->tenant, 'clinic_data', []);
        $this->service->completeStep($this->tenant, 'first_professional', [
            'professional_id' => 1,
            'via' => 'invited_user',
        ]);

        $this->assertSame('locked', $this->statusOf('channel_connection'));
        $this->assertSame('locked', $this->statusOf('ai_knowledge_base'));
    }
}
