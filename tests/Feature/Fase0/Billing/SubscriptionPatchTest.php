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
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Stripe\Exception\ApiConnectionException;
use Stripe\StripeClient;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes de upgrade de assinatura via PATCH /billing/subscription (T200).
 *
 * Mock do StripeClient via `app->instance(StripeClientWrapper::class, ...)`.
 */
class SubscriptionPatchTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function patchUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/billing/subscription";
    }

    /**
     * @param array<string, mixed> $subscriptionUpdates Resposta simulada do Stripe subscriptions->update.
     * @param array<string, mixed>|null $upcomingInvoice Resposta simulada do invoices->upcoming.
     */
    private function mockStripeClientForPatch(
        array $subscriptionUpdates = [],
        ?array $upcomingInvoice = null,
    ): void {
        // Objeto Stripe Subscription retornado pelo update.
        $stripeSubscription = new \stdClass;
        $stripeSubscription->id = 'sub_test_123';
        $stripeSubscription->status = 'active';
        $stripeSubscription->quantity = $subscriptionUpdates['quantity'] ?? 1;
        $stripeSubscription->latest_invoice = null;

        // SubscriptionItem para preencher ->items.
        $stripeItem = new \stdClass;
        $stripeItem->id = 'si_test_123';
        $stripeItem->price = new \stdClass;
        $stripeItem->price->id = $subscriptionUpdates['stripe_price'] ?? 'price_starter_base';
        $stripeItem->price->product = 'prod_test';
        $stripeItem->price->recurring = null;
        $stripeItem->quantity = $stripeSubscription->quantity;

        // Simula Collection do Stripe (iterável com count e first).
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

        // invoices->upcoming para proration preview.
        if ($upcomingInvoice !== null) {
            $invoiceObj = new \stdClass;
            $invoiceObj->amount_due = $upcomingInvoice['amount_due'] ?? 5000;
            $invoiceObj->currency = $upcomingInvoice['currency'] ?? 'brl';

            $mockInvoices = Mockery::mock();
            $mockInvoices->shouldReceive('upcoming')->andReturn($invoiceObj);
            $mockStripeClient->invoices = $mockInvoices;
        }

        $wrapper = new StripeClientWrapper($mockStripeClient);
        $this->app->instance(StripeClientWrapper::class, $wrapper);
    }

    private function createActiveSubscription(int $tenantId, int $planId, int $qty = 1): void
    {
        DB::table('subscriptions')->insert([
            'tenant_id' => $tenantId,
            'type' => 'default',
            'stripe_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_starter_base',
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
    public function test_increasing_professionals_uses_create_prorations(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'starter',
            'is_active' => true,
            'base_price_cents' => 10000,
            'stripe_price_id_base' => 'price_starter_base',
        ]);

        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $plan->id, 1);

        $admin = $this->setupAdminForTenant($tenant);

        $this->mockStripeClientForPatch(
            ['quantity' => 5],
            ['amount_due' => 8000, 'currency' => 'brl']
        );

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 5,
        ]);

        $response->assertStatus(200);

        // proration_preview deve estar preenchido (aumento).
        $response->assertJsonPath('proration_preview.amount_due_cents', 8000);

        // DB atualizado.
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'professionals_quantity' => 5,
            'quantity' => 5,
        ]);

        // AuditLog registrado.
        $log = AuditLog::where('action', 'subscription.updated')
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('create_prorations', $log->payload['proration_behavior']);
        $this->assertEquals(1, $log->payload['old_qty']);
        $this->assertEquals(5, $log->payload['new_qty']);
    }

    /** @test */
    public function test_upgrading_plan_uses_create_prorations(): void
    {
        $starterPlan = Plan::factory()->create([
            'code' => 'starter',
            'is_active' => true,
            'base_price_cents' => 10000,
            'stripe_price_id_base' => 'price_starter_base',
        ]);

        $proPlan = Plan::factory()->create([
            'code' => 'pro',
            'is_active' => true,
            'base_price_cents' => 30000,
            'stripe_price_id_base' => 'price_pro_base',
        ]);

        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $starterPlan->id, 1);

        $admin = $this->setupAdminForTenant($tenant);

        $this->mockStripeClientForPatch(
            ['stripe_price' => 'price_pro_base'],
            ['amount_due' => 20000, 'currency' => 'brl']
        );

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'plan_code' => 'pro',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $proPlan->id,
        ]);

        $log = AuditLog::where('action', 'subscription.updated')
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('starter', $log->payload['old_plan_code']);
        $this->assertEquals('pro', $log->payload['new_plan_code']);
        $this->assertEquals('create_prorations', $log->payload['proration_behavior']);
    }

    /** @test */
    public function test_403_when_user_lacks_billing_role(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $plan->id, 1);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $medico = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $medico->assignRole($role);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($medico);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 5,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_422_invalid_plan_code(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $plan->id, 1);

        $admin = $this->setupAdminForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'plan_code' => 'unknown',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_422_negative_quantity(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $plan->id, 1);

        $admin = $this->setupAdminForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 0,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_502_when_stripe_fails(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'starter',
            'is_active' => true,
            'stripe_price_id_base' => 'price_starter_base',
        ]);

        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_test_123']);
        $this->createActiveSubscription($tenant->id, $plan->id, 1);

        $admin = $this->setupAdminForTenant($tenant);

        // Mock que lança ApiErrorException.
        $mockSubscriptions = Mockery::mock();
        $mockSubscriptions
            ->shouldReceive('update')
            ->andThrow(new ApiConnectionException('Network error'));

        $mockStripeClient = Mockery::mock(StripeClient::class);
        $mockStripeClient->subscriptions = $mockSubscriptions;

        $wrapper = new StripeClientWrapper($mockStripeClient);
        $this->app->instance(StripeClientWrapper::class, $wrapper);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 5,
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('error', 'billing_gateway_error');
    }

    /** @test */
    public function test_404_when_no_active_subscription(): void
    {
        Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'trial', 'stripe_customer_id' => 'cus_test_123']);

        $admin = $this->setupAdminForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->patchJson($this->patchUrl($tenant->slug), [
            'professionals_quantity' => 3,
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'subscription_not_found');
    }
}
