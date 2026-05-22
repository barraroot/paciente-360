<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Events\TenantCancelado;
use App\Domain\SuperAdmin\Events\TenantReativado;
use App\Domain\SuperAdmin\Events\TenantSuspenso;
use App\Domain\SuperAdmin\Services\TenantLifecycleService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T105 (Fase 8 — Lote B US-12.1)** — AC-12.1.3, AC-12.1.4, AC-12.1.10.
 */
class TenantLifecycleTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_suspend_persists_tracking_columns_and_emits_event(): void
    {
        Event::fake([TenantSuspenso::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-lifecycle-suspend', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        /** @var TenantLifecycleService $svc */
        $svc = app(TenantLifecycleService::class);
        $svc->suspend($tenant, $superAdmin, 'Inadimplência prolongada — política de cobrança');

        $tenant->refresh();

        $this->assertSame('suspended', $tenant->status);
        $this->assertNotNull($tenant->suspended_at);
        $this->assertSame($superAdmin->id, $tenant->suspended_by_user_id);
        $this->assertStringContainsString('Inadimplência', $tenant->suspended_reason);

        Event::assertDispatched(TenantSuspenso::class);
    }

    public function test_suspend_throws_when_reason_below_10_chars(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-lifecycle-short', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        $this->expectException(InvalidArgumentException::class);
        app(TenantLifecycleService::class)->suspend($tenant, $superAdmin, 'curto');
    }

    public function test_reactivate_clears_suspension_tracking(): void
    {
        Event::fake([TenantReativado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-lifecycle-react', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        /** @var TenantLifecycleService $svc */
        $svc = app(TenantLifecycleService::class);
        $svc->suspend($tenant, $superAdmin, 'Suspensão de teste para reativação');
        $svc->reactivate($tenant, $superAdmin);

        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->suspended_at);
        $this->assertNull($tenant->suspended_by_user_id);
        $this->assertNull($tenant->suspended_reason);

        Event::assertDispatched(TenantReativado::class);
    }

    public function test_cancel_sets_canceled_at_and_retention_policy(): void
    {
        Event::fake([TenantCancelado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-lifecycle-cancel', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        app(TenantLifecycleService::class)->cancel($tenant, $superAdmin, 'Cliente cancelou contrato');

        $tenant->refresh();
        $this->assertSame('cancelled', $tenant->status);
        $this->assertNotNull($tenant->canceled_at);
        $this->assertSame('differentiated_per_category', $tenant->retention_policy);

        Event::assertDispatched(TenantCancelado::class);
    }
}
