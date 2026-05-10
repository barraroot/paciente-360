<?php

namespace Tests\Feature\Fase0\Billing;

use App\Jobs\Billing\AlertAiUsageThresholdJob;
use App\Models\AiUsageMeter;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\HardCapTriggeredNotification;
use App\Services\Billing\AiUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes de configuração e acionamento do hard cap de IA (T221).
 *
 * PATCH /billing/ai-usage/hard-cap — define, remove ou zera cap.
 * AiUsageService::recordUsage() — aciona cap quando excede.
 */
class HardCapTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function hardCapUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/billing/ai-usage/hard-cap";
    }

    private function setUpTenantWithRole(Tenant $tenant, string $roleName = 'admin-clinica'): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->createUserForTenant($tenant);
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        $user->assignRole($role);

        return $user;
    }

    private function createMeter(Tenant $tenant, array $overrides = []): AiUsageMeter
    {
        $yearMonth = Carbon::now('America/Sao_Paulo')->format('Y-m');

        return AiUsageMeter::withoutGlobalScope(TenantScope::class)->create(array_merge([
            'tenant_id' => $tenant->id,
            'year_month' => $yearMonth,
            'messages_count' => 0,
            'included_quota_snapshot' => 10000,
            'overage_count' => 0,
            'hard_cap' => null,
            'hard_cap_triggered_at' => null,
            'last_reset_at' => now(),
        ], $overrides));
    }

    /** @test */
    public function test_patch_hard_cap_persists_value(): void
    {
        Notification::fake();

        $tenant = $this->createTenant(['status' => 'active']);
        $this->createMeter($tenant);

        $user = $this->setUpTenantWithRole($tenant, 'admin-clinica');
        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        $response = $this->patchJson($this->hardCapUrl($tenant->slug), ['hard_cap' => 5000]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_usage_meters', [
            'tenant_id' => $tenant->id,
            'hard_cap' => 5000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.hard_cap.configured',
        ]);

        $log = AuditLog::where('tenant_id', $tenant->id)
            ->where('action', 'tenant.hard_cap.configured')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(['old' => null, 'new' => 5000], $log->payload);
    }

    /** @test */
    public function test_patch_hard_cap_null_removes_cap(): void
    {
        Notification::fake();

        $tenant = $this->createTenant(['status' => 'active']);
        $this->createMeter($tenant, ['hard_cap' => 5000]);

        $user = $this->setUpTenantWithRole($tenant, 'admin-clinica');
        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        $response = $this->patchJson($this->hardCapUrl($tenant->slug), ['hard_cap' => null]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_usage_meters', [
            'tenant_id' => $tenant->id,
            'hard_cap' => null,
        ]);
    }

    /** @test */
    public function test_patch_hard_cap_zero_disables_ai_immediately(): void
    {
        Notification::fake();

        $tenant = $this->createTenant(['status' => 'active']);
        // messages_count = 0 >= hard_cap = 0, então triggerHardCap é chamado.
        $this->createMeter($tenant, ['messages_count' => 0]);

        $user = $this->setUpTenantWithRole($tenant, 'admin-clinica');
        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        $response = $this->patchJson($this->hardCapUrl($tenant->slug), ['hard_cap' => 0]);

        $response->assertStatus(200);

        $meter = AiUsageMeter::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($meter);
        $this->assertEquals(0, $meter->hard_cap);
        $this->assertNotNull($meter->hard_cap_triggered_at);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.hard_cap.triggered',
        ]);
    }

    /** @test */
    public function test_record_usage_triggers_hard_cap_when_exceeded(): void
    {
        Notification::fake();

        $tenant = $this->createTenant(['status' => 'active']);
        $this->createMeter($tenant, ['messages_count' => 4990, 'hard_cap' => 5000]);

        // Cria um admin-clinica para receber a notificação.
        $adminUser = $this->setUpTenantWithRole($tenant, 'admin-clinica');

        $this->app->instance('tenant', $tenant);

        $service = app(AiUsageService::class);
        $meter = $service->recordUsage($tenant, 15);

        $this->assertGreaterThanOrEqual(5000, $meter->messages_count);
        $this->assertNotNull($meter->hard_cap_triggered_at);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.hard_cap.triggered',
        ]);

        Notification::assertSentTo($adminUser, HardCapTriggeredNotification::class);
    }

    /** @test */
    public function test_record_usage_dispatches_threshold_alerts_at_80_and_100_percent(): void
    {
        Notification::fake();

        $plan = Plan::factory()->create(['code' => 'starter-thresh', 'included_ai_messages' => 10000, 'is_active' => true]);
        $tenant = $this->createTenant(['status' => 'active', 'plan_id' => $plan->id]);

        // Meter começa com 7990 msgs (79.9%) do ciclo atual.
        $this->createMeter($tenant, [
            'messages_count' => 7990,
            'included_quota_snapshot' => 10000,
        ]);

        $this->app->instance('tenant', $tenant);

        $service = app(AiUsageService::class);

        // 7990 → ainda 79.9%, não dispara.
        // Mas com +20 = 8010 → 80.1%, dispara alerta 80%.
        Bus::fake([AlertAiUsageThresholdJob::class]);

        // Antes: 7990 (< 80%).
        // +20 = 8010 (>= 80%) → job para 80%.
        $service->recordUsage($tenant, 20);

        Bus::assertDispatched(AlertAiUsageThresholdJob::class, function ($job) {
            return $job->threshold === 80;
        });

        // +2000 = 10010 (>= 100%) → job para 100%.
        $service->recordUsage($tenant, 2000);

        Bus::assertDispatched(AlertAiUsageThresholdJob::class, function ($job) {
            return $job->threshold === 100;
        });

        // Total: 2 dispatches (80 e 100).
        Bus::assertDispatchedTimes(AlertAiUsageThresholdJob::class, 2);
    }

    /** @test */
    public function test_only_admin_clinica_or_financeiro_can_set_hard_cap(): void
    {
        $tenant = $this->createTenant(['status' => 'active']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $medicoRole = Role::firstOrCreate(
            ['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        $medico = $this->createUserForTenant($tenant);
        $medico->assignRole($medicoRole);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($medico);

        $response = $this->patchJson($this->hardCapUrl($tenant->slug), ['hard_cap' => 5000]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_validation_rejects_negative_hard_cap(): void
    {
        $tenant = $this->createTenant(['status' => 'active']);

        $user = $this->setUpTenantWithRole($tenant, 'admin-clinica');
        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        $response = $this->patchJson($this->hardCapUrl($tenant->slug), ['hard_cap' => -100]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hard_cap']);
    }
}
