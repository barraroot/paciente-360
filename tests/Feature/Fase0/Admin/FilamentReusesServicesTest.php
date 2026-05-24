<?php

namespace Tests\Feature\Fase0\Admin;

use App\Domain\SuperAdmin\Services\TenantLifecycleService;
use App\Filament\Resources\PlanResource\Pages\ListPlans;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\PlanService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes que verificam que o Filament delega ao Service correto (T281).
 *
 * Usa Mockery/spy para verificar que os métodos dos Services são chamados
 * quando as actions do Filament são acionadas.
 */
class FilamentReusesServicesTest extends TestCase
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
    public function test_suspend_action_invokes_tenant_state_service(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $spy = Mockery::spy(TenantLifecycleService::class);
        $spy->shouldReceive('suspend')
            ->once()
            ->with(Mockery::on(fn (Tenant $t): bool => $t->id === $tenant->id), Mockery::any(), Mockery::any());

        $this->app->instance(TenantLifecycleService::class, $spy);

        Sanctum::actingAs($this->superAdmin, ['*']);

        // A action exige o campo `reason` (min 10 chars) no schema do modal.
        Livewire::test(ListTenants::class)
            ->callTableAction('suspend', $tenant, ['reason' => 'Inadimplência confirmada']);

        $spy->shouldHaveReceived('suspend')->once();
    }

    /** @test */
    public function test_reactivate_action_invokes_service(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);

        $spy = Mockery::spy(TenantLifecycleService::class);
        $spy->shouldReceive('reactivate')
            ->once()
            ->with(Mockery::on(fn (Tenant $t): bool => $t->id === $tenant->id), Mockery::any());

        $this->app->instance(TenantLifecycleService::class, $spy);

        Sanctum::actingAs($this->superAdmin, ['*']);

        Livewire::test(ListTenants::class)
            ->callTableAction('reactivate', $tenant);

        $spy->shouldHaveReceived('reactivate')->once();
    }

    /** @test */
    public function test_change_plan_in_filament_invokes_plan_service(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $spy = Mockery::spy(PlanService::class);
        $spy->shouldReceive('toggleActive')
            ->once()
            ->with(Mockery::on(fn (Plan $p): bool => $p->id === $plan->id));

        $this->app->instance(PlanService::class, $spy);

        Sanctum::actingAs($this->superAdmin, ['*']);

        Livewire::test(ListPlans::class)
            ->callTableAction('toggle_active', $plan);

        $spy->shouldHaveReceived('toggleActive')->once();
    }
}
