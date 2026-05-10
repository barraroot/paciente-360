<?php

namespace Tests\Feature\Fase0\Admin;

use App\Filament\Widgets\SuspensionEligibleTenantsWidget;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantStateService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes do widget de tenants elegíveis para suspensão (T287).
 */
class SuspensionEligibleWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super@admin.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    /** @test */
    public function test_widget_shows_only_tenants_overdue_for_31_days(): void
    {
        // Eligible: overdue for 31 days
        $eligibleTenant = Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => now()->subDays(31),
        ]);

        // Not eligible: only 5 days overdue
        $notEligibleTenant = Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => now()->subDays(5),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(SuspensionEligibleTenantsWidget::class)
            ->assertCanSeeTableRecords([$eligibleTenant])
            ->assertCanNotSeeTableRecords([$notEligibleTenant]);
    }

    /** @test */
    public function test_suspend_action_calls_tenant_state_service(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => now()->subDays(31),
        ]);

        $suspendCalled = false;

        $mock = Mockery::mock(TenantStateService::class);
        $mock->shouldReceive('suspend')
            ->andReturnUsing(function () use (&$suspendCalled): void {
                $suspendCalled = true;
            });

        $this->app->instance(TenantStateService::class, $mock);

        $this->actingAs($this->superAdmin);

        Livewire::test(SuspensionEligibleTenantsWidget::class)
            ->mountTableAction('suspend', $tenant)
            ->callMountedTableAction();

        $this->assertTrue($suspendCalled, 'TenantStateService::suspend should have been called');
    }
}
