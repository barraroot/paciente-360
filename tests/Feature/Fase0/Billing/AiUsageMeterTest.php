<?php

namespace Tests\Feature\Fase0\Billing;

use App\Models\AiUsageMeter;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\AiUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes de leitura do meter de uso de IA (T220).
 *
 * GET /billing/ai-usage retorna shape AiUsageResource.
 * Meter é criado automaticamente quando ausente.
 */
class AiUsageMeterTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function aiUsageUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/billing/ai-usage";
    }

    private function setUpAdminUser(Tenant $tenant): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->createUserForTenant($tenant);
        $role = Role::firstOrCreate(
            ['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        $user->assignRole($role);

        return $user;
    }

    private function createActiveSubscription(Tenant $tenant, Plan $plan): void
    {
        DB::table('subscriptions')->insert([
            'tenant_id' => $tenant->id,
            'type' => 'default',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_price_id_base,
            'quantity' => 1,
            'plan_id' => $plan->id,
            'plan_snapshot' => json_encode([
                'code' => $plan->code,
                'name' => $plan->name,
                'included_ai_messages' => $plan->included_ai_messages,
                'overage_price_cents' => $plan->overage_price_cents,
            ]),
            'professionals_quantity' => 1,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'trial_ends_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function test_get_creates_meter_when_absent_for_current_month(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'starter',
            'included_ai_messages' => 10000,
            'is_active' => true,
        ]);
        $tenant = $this->createTenant(['status' => 'active', 'plan_id' => $plan->id]);
        $this->createActiveSubscription($tenant, $plan);

        $user = $this->setUpAdminUser($tenant);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user, ['*']);

        $yearMonth = Carbon::now('America/Sao_Paulo')->format('Y-m');

        $this->assertDatabaseMissing('ai_usage_meters', [
            'tenant_id' => $tenant->id,
            'year_month' => $yearMonth,
        ]);

        $response = $this->getJson($this->aiUsageUrl($tenant->slug));

        $response->assertStatus(200);
        $response->assertJsonFragment(['year_month' => $yearMonth, 'consumed' => 0]);

        $this->assertDatabaseHas('ai_usage_meters', [
            'tenant_id' => $tenant->id,
            'year_month' => $yearMonth,
            'messages_count' => 0,
            'included_quota_snapshot' => 10000,
        ]);
    }

    /** @test */
    public function test_get_returns_existing_meter_when_present(): void
    {
        $plan = Plan::factory()->create(['code' => 'pro', 'included_ai_messages' => 50000, 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'plan_id' => $plan->id]);

        $yearMonth = Carbon::now('America/Sao_Paulo')->format('Y-m');

        AiUsageMeter::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'year_month' => $yearMonth,
            'messages_count' => 500,
            'included_quota_snapshot' => 50000,
            'overage_count' => 0,
            'last_reset_at' => now(),
        ]);

        $user = $this->setUpAdminUser($tenant);
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson($this->aiUsageUrl($tenant->slug));

        $response->assertStatus(200);
        $response->assertJsonFragment(['consumed' => 500]);
    }

    /** @test */
    public function test_projected_end_of_month_linear(): void
    {
        // Simula dia 10 de um mês com 30 dias: projected = round(300 * 30 / 10) = 900.
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 12, 0, 0, 'America/Sao_Paulo'));

        $plan = Plan::factory()->create(['code' => 'starter-proj', 'included_ai_messages' => 10000, 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'plan_id' => $plan->id]);

        AiUsageMeter::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'year_month' => '2026-06',
            'messages_count' => 300,
            'included_quota_snapshot' => 10000,
            'overage_count' => 0,
            'last_reset_at' => now(),
        ]);

        $service = app(AiUsageService::class);
        $meter = $service->getCurrentMeter($tenant);
        $resource = $service->buildResource($meter);

        $this->assertEquals(900, $resource['projected_end_of_month']);

        Carbon::setTestNow();
    }

    /** @test */
    public function test_overage_calculated_when_exceeded(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'starter-overage',
            'included_ai_messages' => 10000,
            'overage_price_cents' => 5,
            'is_active' => true,
        ]);
        $tenant = $this->createTenant(['status' => 'active', 'plan_id' => $plan->id]);
        $this->createActiveSubscription($tenant, $plan);

        $yearMonth = Carbon::now('America/Sao_Paulo')->format('Y-m');

        AiUsageMeter::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'year_month' => $yearMonth,
            'messages_count' => 12000,
            'included_quota_snapshot' => 10000,
            'overage_count' => 0,
            'last_reset_at' => now(),
        ]);

        $service = app(AiUsageService::class);
        $meter = $service->getCurrentMeter($tenant);
        $resource = $service->buildResource($meter);

        $this->assertEquals(2000, $resource['overage']);
        $this->assertEquals(2000 * 5, $resource['overage_cost_estimate_cents']);
    }

    /** @test */
    public function test_returns_zero_state_for_trial_without_subscription(): void
    {
        // Decisão: trial sem sub = cota 0 (acesso conservador; UI mostra CTA de upgrade).
        $tenant = $this->createTenant(['status' => 'trial']);

        $user = $this->setUpAdminUser($tenant);
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson($this->aiUsageUrl($tenant->slug));

        $response->assertStatus(200);
        $response->assertJsonFragment(['included_quota' => 0]);
    }

    /** @test */
    public function test_year_month_is_brt_timezone(): void
    {
        // Simula UTC 03:00 do dia 1 do mês = ainda dia anterior em BRT (UTC-3).
        // Ex.: 2026-06-01 00:30 UTC = 2025-05-31 21:30 BRT.
        // Escolhemos: 2026-07-01 02:00 UTC = 2026-06-30 23:00 BRT.
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 2, 0, 0, 'UTC'));

        $plan = Plan::factory()->create(['code' => 'starter-brt', 'included_ai_messages' => 10000, 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'plan_id' => $plan->id]);

        $service = app(AiUsageService::class);
        $meter = $service->getCurrentMeter($tenant);

        // Em BRT, ainda estamos em 2026-06.
        $expectedYearMonth = Carbon::now('America/Sao_Paulo')->format('Y-m');
        $this->assertEquals($expectedYearMonth, $meter->year_month);

        Carbon::setTestNow();
    }
}
