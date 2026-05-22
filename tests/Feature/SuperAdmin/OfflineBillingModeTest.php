<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Events\TenantCriadoPorSuperAdmin;
use App\Domain\SuperAdmin\Services\TenantLifecycleService;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T110 (Fase 8 — Lote B US-12.1)** — R-8-8 — Q23 (criação manual offline_invoice).
 *
 * Valida:
 *   1. `createByAdmin()` com billing_mode='offline_invoice' persiste sem criar
 *      customer Stripe.
 *   2. billing_mode='stripe' aceito (default).
 *   3. billing_mode inválido lança InvalidArgumentException.
 *   4. Helper `isOfflineBilling()` retorna true para offline_invoice.
 */
class OfflineBillingModeTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_create_offline_invoice_tenant(): void
    {
        Event::fake([TenantCriadoPorSuperAdmin::class]);

        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $plan = Plan::factory()->create();

        $data = [
            'slug' => 'enterprise-x',
            'name' => 'Enterprise X',
            'cnpj' => '12345678000199',
            'responsible_name' => 'João da Silva',
            'responsible_email' => 'joao@enterprise-x.com',
            'responsible_phone' => '+5511999998888',
            'plan_id' => $plan->id,
            'terms_accepted_at' => now(),
            'terms_version' => '1.0',
        ];

        $tenant = app(TenantLifecycleService::class)->createByAdmin($data, $superAdmin, 'offline_invoice');

        $this->assertSame('offline_invoice', $tenant->billing_mode);
        $this->assertTrue($tenant->isOfflineBilling());
        $this->assertNull($tenant->stripe_customer_id, 'Tenant offline_invoice NÃO deve ter customer Stripe.');

        Event::assertDispatched(
            TenantCriadoPorSuperAdmin::class,
            fn (TenantCriadoPorSuperAdmin $e): bool => $e->billingMode === 'offline_invoice'
                && $e->tenantId === $tenant->id,
        );
    }

    public function test_create_stripe_tenant_default(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $plan = Plan::factory()->create();

        $tenant = app(TenantLifecycleService::class)->createByAdmin([
            'slug' => 'stripe-tenant',
            'name' => 'Stripe Tenant',
            'cnpj' => '98765432000199',
            'responsible_name' => 'Maria',
            'responsible_email' => 'maria@stripe.com',
            'responsible_phone' => '+5511988887777',
            'plan_id' => $plan->id,
            'terms_accepted_at' => now(),
            'terms_version' => '1.0',
        ], $superAdmin, 'stripe');

        $this->assertSame('stripe', $tenant->billing_mode);
        $this->assertFalse($tenant->isOfflineBilling());
    }

    public function test_invalid_billing_mode_throws(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        $this->expectException(InvalidArgumentException::class);

        app(TenantLifecycleService::class)->createByAdmin([
            'slug' => 'invalid-mode',
            'name' => 'Invalid Mode',
            'cnpj' => '00000000000199',
            'responsible_name' => 'X',
            'responsible_email' => 'x@x.com',
            'responsible_phone' => '+5500000000000',
            'plan_id' => Plan::factory()->create()->id,
            'terms_accepted_at' => now(),
            'terms_version' => '1.0',
        ], $superAdmin, 'paypal');
    }
}
