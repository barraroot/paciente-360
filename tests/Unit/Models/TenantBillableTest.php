<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Tests\TestCase;

/**
 * Testes unitários para o Cashier wiring no Model Tenant (T183).
 */
class TenantBillableTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_tenant_has_billable_trait(): void
    {
        $this->assertContains(Billable::class, class_uses_recursive(Tenant::class));
    }

    /** @test */
    public function test_get_stripe_id_column_returns_stripe_customer_id(): void
    {
        $tenant = new Tenant;

        $this->assertSame('stripe_customer_id', $tenant->getStripeIdColumn());
    }

    /** @test */
    public function test_tenant_has_notifiable_trait(): void
    {
        $this->assertContains(Notifiable::class, class_uses_recursive(Tenant::class));
    }

    /** @test */
    public function test_route_notification_for_mail_returns_responsible_email(): void
    {
        $tenant = Tenant::factory()->create([
            'responsible_email' => 'admin@clinica.com',
        ]);

        $this->assertSame('admin@clinica.com', $tenant->routeNotificationForMail());
    }

    /** @test */
    public function test_tenant_has_users_relationship(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertInstanceOf(
            HasMany::class,
            $tenant->users()
        );
    }
}
