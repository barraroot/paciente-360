<?php

namespace Tests\Feature\Fase0\Billing;

use App\Models\Plan;
use App\Models\Role;
use App\Services\Billing\StripeClientWrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Stripe\StripeClient;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes do fluxo de checkout de assinatura (T181).
 *
 * O StripeClient é mockado via `app->instance(StripeClientWrapper::class, ...)`,
 * garantindo que nenhuma chamada real ao Stripe ocorra.
 */
class CheckoutTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function checkoutUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/billing/checkout";
    }

    private function mockStripeClient(string $checkoutUrl = 'https://checkout.stripe.com/pay/cs_test_123'): void
    {
        $mockSession = new \stdClass;
        $mockSession->url = $checkoutUrl;
        $mockSession->expires_at = 1234567890;

        $mockSessions = Mockery::mock();
        $mockSessions->shouldReceive('create')->andReturn($mockSession);

        $mockCheckout = new \stdClass;
        $mockCheckout->sessions = $mockSessions;

        $mockStripeClient = Mockery::mock(StripeClient::class);
        $mockStripeClient->checkout = $mockCheckout;

        $wrapper = new StripeClientWrapper($mockStripeClient);
        $this->app->instance(StripeClientWrapper::class, $wrapper);
    }

    /** @test */
    public function test_checkout_creates_stripe_customer_and_returns_url(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'trial', 'stripe_customer_id' => 'cus_pre_set']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $this->mockStripeClient();

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->postJson(
            $this->checkoutUrl($tenant->slug),
            ['plan_code' => 'starter', 'professionals_quantity' => 1]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['checkout_url', 'expires_at']);
        $this->assertStringContainsString('checkout.stripe.com', $response->json('checkout_url'));
    }

    /** @test */
    public function test_checkout_409_when_already_subscribed(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'stripe_customer_id' => 'cus_existing']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        // Cria assinatura ativa.
        DB::table('subscriptions')->insert([
            'tenant_id' => $tenant->id,
            'type' => 'default',
            'stripe_id' => 'sub_existing',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
            'plan_id' => $plan->id,
            'plan_snapshot' => '{}',
            'professionals_quantity' => 1,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->postJson(
            $this->checkoutUrl($tenant->slug),
            ['plan_code' => 'starter', 'professionals_quantity' => 1]
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'already_subscribed');
    }

    /** @test */
    public function test_checkout_422_when_plan_not_found(): void
    {
        $tenant = $this->createTenant(['status' => 'trial']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->postJson(
            $this->checkoutUrl($tenant->slug),
            ['plan_code' => 'unknown-plan', 'professionals_quantity' => 1]
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function test_checkout_422_when_professionals_below_minimum(): void
    {
        Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'trial']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($admin);

        $response = $this->postJson(
            $this->checkoutUrl($tenant->slug),
            ['plan_code' => 'starter', 'professionals_quantity' => 0]
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function test_only_admin_clinica_or_financeiro_can_checkout(): void
    {
        Plan::factory()->create(['code' => 'starter', 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'trial']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $medico = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $medico->assignRole($role);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($medico);

        $response = $this->postJson(
            $this->checkoutUrl($tenant->slug),
            ['plan_code' => 'starter', 'professionals_quantity' => 1]
        );

        $response->assertStatus(403);
    }
}
