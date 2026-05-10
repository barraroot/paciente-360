<?php

namespace Tests\Feature\Fase0\Billing;

use App\Jobs\Tenant\ApplyOverdueRestrictionsJob;
use App\Models\AuditLog;
use App\Models\Role;
use App\Notifications\PaymentFailedNotification;
use App\Services\Billing\StripeWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes de inadimplência e restrições de acesso (T182).
 *
 * Cobre: 3 falhas → overdue, 7 dias → restrições, recuperação,
 * middleware premium, login preservado.
 */
class OverdueRestrictionsTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function makePaymentFailedPayload(string $customerId, string $invoiceId): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'customer' => $customerId,
                    'subscription' => 'sub_test_123',
                ],
            ],
        ];
    }

    private function makePaymentSucceededPayload(string $customerId, string $invoiceId): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'customer' => $customerId,
                ],
            ],
        ];
    }

    /** @test */
    public function test_three_failed_payments_move_tenant_to_overdue(): void
    {
        Notification::fake();

        $tenant = $this->createTenant([
            'status' => 'active',
            'stripe_customer_id' => 'cus_overdue_test',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = $this->createUserForTenant($tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $service = app(StripeWebhookService::class);

        // 3 falhas consecutivas.
        $service->handle($this->makePaymentFailedPayload('cus_overdue_test', 'in_fail_1'));
        $service->handle($this->makePaymentFailedPayload('cus_overdue_test', 'in_fail_2'));
        $service->handle($this->makePaymentFailedPayload('cus_overdue_test', 'in_fail_3'));

        $tenant->refresh();

        $this->assertSame('overdue', $tenant->status);
        $this->assertNotNull($tenant->overdue_since);

        // Pelo menos 3 audit logs de payment_failed (1 por chamada).
        $this->assertGreaterThanOrEqual(
            3,
            AuditLog::where('tenant_id', $tenant->id)
                ->where('action', 'subscription.payment_failed')
                ->count()
        );

        // Notificação enviada 3x ao admin-clinica.
        Notification::assertSentTo($admin, PaymentFailedNotification::class, 3);
    }

    /** @test */
    public function test_overdue_after_7_days_applies_restrictions(): void
    {
        $tenant = $this->createTenant([
            'status' => 'overdue',
            'overdue_since' => now()->subDays(8),
            'restrictions_applied_at' => null,
        ]);

        ApplyOverdueRestrictionsJob::dispatchSync($tenant->id);

        $tenant->refresh();

        $this->assertNotNull($tenant->restrictions_applied_at);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.restrictions.applied',
        ]);
    }

    /** @test */
    public function test_overdue_under_7_days_does_not_apply(): void
    {
        $tenant = $this->createTenant([
            'status' => 'overdue',
            'overdue_since' => now()->subDays(3),
            'restrictions_applied_at' => null,
        ]);

        ApplyOverdueRestrictionsJob::dispatchSync($tenant->id);

        $tenant->refresh();

        $this->assertNull($tenant->restrictions_applied_at);
    }

    /** @test */
    public function test_payment_succeeded_recovers_tenant(): void
    {
        $tenant = $this->createTenant([
            'status' => 'overdue',
            'stripe_customer_id' => 'cus_recover_test',
            'overdue_since' => now()->subDays(5),
            'restrictions_applied_at' => now()->subDays(1),
        ]);

        $service = app(StripeWebhookService::class);
        $service->handle($this->makePaymentSucceededPayload('cus_recover_test', 'in_success_1'));

        $tenant->refresh();

        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->overdue_since);
        $this->assertNull($tenant->restrictions_applied_at);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.recovered',
        ]);
    }

    /** @test */
    public function test_premium_route_middleware_returns_402_when_restricted(): void
    {
        // Adiciona rota dummy premium.
        config(['billing.premium_routes' => ['test.premium']]);

        Route::get('/_premium_test', function () {
            return response()->json(['ok' => true]);
        })->name('test.premium')->middleware('apply.overdue.restrictions');

        $tenant = $this->createTenant([
            'status' => 'overdue',
            'restrictions_applied_at' => now()->subDays(1),
        ]);

        $user = $this->createUserForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        // Tenant restrito → 402.
        $response = $this->getJson('/_premium_test');
        $response->assertStatus(402);
        $response->assertJsonPath('error', 'tenant_restricted');
    }

    /** @test */
    public function test_non_premium_route_is_accessible_when_restricted(): void
    {
        config(['billing.premium_routes' => ['test.premium']]);

        Route::get('/_not_premium_test', function () {
            return response()->json(['ok' => true]);
        })->name('test.not-premium')->middleware('apply.overdue.restrictions');

        $tenant = $this->createTenant([
            'status' => 'overdue',
            'restrictions_applied_at' => now()->subDays(1),
        ]);

        $user = $this->createUserForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        // Rota não premium → 200 mesmo restrito.
        $response = $this->getJson('/_not_premium_test');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_login_accessible_when_tenant_restricted(): void
    {
        // Login NÃO usa ApplyOverdueRestrictions — ele usa EnsureTenantNotSuspended.
        // A restrição de inadimplência NUNCA bloqueia login (FR-014 + Princípio II).
        $tenant = $this->createTenant([
            'status' => 'overdue',
            'restrictions_applied_at' => now()->subDays(1),
        ]);

        // A rota de login não inclui `apply.overdue.restrictions` no middleware.
        // Verifica que `auth.login` existe e não tem o middleware de restrição.
        $route = Route::getRoutes()->getByName('auth.login');

        $this->assertNotNull($route, 'Rota auth.login deve existir.');
        $this->assertNotContains(
            'apply.overdue.restrictions',
            $route->middleware(),
            'Login NÃO deve ter o middleware apply.overdue.restrictions.'
        );
    }
}
