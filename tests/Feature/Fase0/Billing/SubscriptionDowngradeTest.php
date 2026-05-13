<?php

namespace Tests\Feature\Fase0\Billing;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\StripeClientWrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Stripe\StripeClient;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes de downgrade de assinatura via PATCH /billing/subscription (T201).
 *
 * Downgrade preguiçoso: plano novo é aplicado imediatamente com `proration_behavior=none`;
 * sem estorno; recursos não são bloqueados durante o ciclo restante.
 */
class SubscriptionDowngradeTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function patchUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/billing/subscription";
    }

    private function mockStripeClientForPatch(int $qty = 1, string $priceId = 'price_starter_base'): void
    {
        $stripeSubscription = new \stdClass;
        $stripeSubscription->id = 'sub_test_123';
        $stripeSubscription->status = 'active';
        $stripeSubscription->quantity = $qty;
        $stripeSubscription->latest_invoice = null;

        $stripeItem = new \stdClass;
        $stripeItem->id = 'si_test_123';
        $stripeItem->price = new \stdClass;
        $stripeItem->price->id = $priceId;
        $stripeItem->price->product = 'prod_test';
        $stripeItem->price->recurring = null;
        $stripeItem->quantity = $qty;

        $itemsCollection = Mockery::mock();
        $itemsCollection->shouldReceive('count')->andReturn(1);
        $itemsCollection->shouldReceive('first')->andReturn($stripeItem);
        $itemsCollection->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$stripeItem]));

        $stripeSubscription->items = $itemsCollection;

        $mockSubscriptions = Mockery::mock();
        $mockSubscriptions
            ->shouldReceive('update')
            ->andReturn($stripeSubscription);

        $mockStripeClient = Mockery::mock(StripeClient::class);
        $mockStripeClient->subscriptions = $mockSubscriptions;

        $wrapper = new StripeClientWrapper($mockStripeClient);
        $this->app->instance(StripeClientWrapper::class, $wrapper);
    }

    private function createActiveSubscription(int $tenantId, int $planId, int $qty = 5): void
    {
        DB::table('subscriptions')->insert([
            'tenant_id' => $tenantId,
            'type' => 'default',
            'stripe_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro_base',
            'quantity' => $qty,
            'plan_id' => $planId,
            'plan_snapshot' => '{}',
            'professionals_quantity' => $qty,
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setupAdminForTenant(Tenant $tenant): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return $admin;
    }

    /** @test */
    public function test_decreasing_professionals_uses_proration_none(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'pro',
            'is_active' => true,
            'base_price_cents' => 30000,
            'stripe_price_id_base' => 'price_pro_base',
        ]);

        $tenant = $this->createTenant([
            'status' => 'active',
            'stripe_customer_id' => 'cus_test_123',
            'restrictions_applied_at' => null,
        ]);
        $this->createActiveSubscription($tenant->id, $plan->id, 5);

        $admin = $this->setupAdminForTenant($tenant);

        $this->mockStripeClientForPatch(2);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 2,
        ]);

        $response->assertStatus(200);

        // DB atualizado.
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'professionals_quantity' => 2,
        ]);

        // AuditLog: proration_behavior = none.
        $log = AuditLog::where('action', 'subscription.updated')
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('none', $log->payload['proration_behavior']);
        $this->assertEquals(5, $log->payload['old_qty']);
        $this->assertEquals(2, $log->payload['new_qty']);
        $this->assertFalse($log->payload['is_upgrade']);

        // Não bloqueia recursos: restrictions_applied_at permanece null.
        $tenant->refresh();
        $this->assertNull($tenant->restrictions_applied_at);
    }

    /** @test */
    public function test_downgrading_plan_uses_proration_none(): void
    {
        $proPlan = Plan::factory()->create([
            'code' => 'pro',
            'is_active' => true,
            'base_price_cents' => 30000,
            'stripe_price_id_base' => 'price_pro_base',
        ]);

        $starterPlan = Plan::factory()->create([
            'code' => 'starter',
            'is_active' => true,
            'base_price_cents' => 10000,
            'stripe_price_id_base' => 'price_starter_base',
        ]);

        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $proPlan->id, 3);

        $admin = $this->setupAdminForTenant($tenant);

        $this->mockStripeClientForPatch(3, 'price_starter_base');

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'plan_code' => 'starter',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $starterPlan->id,
        ]);

        $log = AuditLog::where('action', 'subscription.updated')
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('none', $log->payload['proration_behavior']);
        $this->assertEquals('pro', $log->payload['old_plan_code']);
        $this->assertEquals('starter', $log->payload['new_plan_code']);
    }

    /** @test */
    public function test_explicit_proration_behavior_none_is_respected(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'starter',
            'is_active' => true,
            'base_price_cents' => 10000,
            'stripe_price_id_base' => 'price_starter_base',
        ]);

        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);

        // Sub com qty=2; vamos aumentar para 3 (seria upgrade), mas forçamos none.
        DB::table('subscriptions')->insert([
            'tenant_id' => $tenant->id,
            'type' => 'default',
            'stripe_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_starter_base',
            'quantity' => 2,
            'plan_id' => $plan->id,
            'plan_snapshot' => '{}',
            'professionals_quantity' => 2,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = $this->setupAdminForTenant($tenant);

        $this->mockStripeClientForPatch(3);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 3,
            'proration_behavior' => 'none',
        ]);

        $response->assertStatus(200);

        $log = AuditLog::where('action', 'subscription.updated')
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('none', $log->payload['proration_behavior']);
    }

    /** @test */
    public function test_no_change_returns_422_when_payload_empty(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $plan->id, 1);

        $admin = $this->setupAdminForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($admin, ['*']);

        // Payload vazio: nenhum campo de mudança informado.
        $response = $this->patchJson($this->patchUrl($tenant->slug), []);

        // Decisão defensiva: retorna 422 pois nenhum campo de mudança foi fornecido.
        $response->assertStatus(422);
    }
}
