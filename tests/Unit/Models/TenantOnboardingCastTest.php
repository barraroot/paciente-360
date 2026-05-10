<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes unitários para o cast `onboarding_state` do model `Tenant` (T161).
 *
 * Verifica que a coluna JSONB é corretamente cast para array PHP e que o
 * fillable está configurado.
 */
class TenantOnboardingCastTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_onboarding_state_default_is_empty_array(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertIsArray($tenant->onboarding_state);
        $this->assertEmpty($tenant->onboarding_state);
    }

    /** @test */
    public function test_onboarding_state_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->onboarding_state = ['steps' => ['clinic_data' => ['status' => 'completed']]];
        $tenant->save();

        $tenant->refresh();

        $this->assertIsArray($tenant->onboarding_state);
        $this->assertSame('completed', $tenant->onboarding_state['steps']['clinic_data']['status']);
    }

    /** @test */
    public function test_fillable_includes_onboarding_state(): void
    {
        $tenant = Tenant::factory()->create(['onboarding_state' => ['x' => 1]]);

        $this->assertIsArray($tenant->onboarding_state);
        $this->assertSame(1, $tenant->onboarding_state['x']);
    }
}
