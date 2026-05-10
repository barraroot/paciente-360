<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Billing\AlertAiUsageThresholdJob;
use App\Models\AuditLog;
use App\Models\Role;
use App\Notifications\AiUsageThresholdNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes unit do `AlertAiUsageThresholdJob` (T224).
 *
 * Verifica:
 *  - Notificação enviada e audit gravado no primeiro dispatch.
 *  - Segundo dispatch idempotente (audit dedup previne re-envio).
 */
class AlertAiUsageThresholdJobTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function setUpTenantWithAdminRole(): array
    {
        $tenant = $this->createTenant(['status' => 'active']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->createUserForTenant($tenant);
        $role = Role::firstOrCreate(
            ['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        $user->assignRole($role);

        $this->app->instance('tenant', $tenant);

        return [$tenant, $user];
    }

    /** @test */
    public function test_dispatches_notification_and_records_audit_on_first_run(): void
    {
        Notification::fake();

        [$tenant, $user] = $this->setUpTenantWithAdminRole();

        $job = new AlertAiUsageThresholdJob($tenant->id, 80);
        $job->handle();

        // Notificação enviada para o admin.
        Notification::assertSentTo($user, AiUsageThresholdNotification::class, function ($notification) {
            return $notification->threshold === 80;
        });

        // Audit gravado.
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'ai_usage.threshold.alerted',
        ]);

        $log = AuditLog::where('tenant_id', $tenant->id)
            ->where('action', 'ai_usage.threshold.alerted')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(['threshold' => 80], $log->payload);
    }

    /** @test */
    public function test_second_dispatch_is_idempotent(): void
    {
        Notification::fake();

        [$tenant, $user] = $this->setUpTenantWithAdminRole();

        // Primeiro dispatch: envia notificação e grava audit.
        $job = new AlertAiUsageThresholdJob($tenant->id, 80);
        $job->handle();

        // Segundo dispatch: NÃO deve enviar nova notificação (audit dedup).
        $job2 = new AlertAiUsageThresholdJob($tenant->id, 80);
        $job2->handle();

        // Apenas 1 notificação deve ter sido enviada (no primeiro dispatch).
        Notification::assertSentToTimes($user, AiUsageThresholdNotification::class, 1);

        // Apenas 1 entrada de audit deve existir.
        $count = AuditLog::where('tenant_id', $tenant->id)
            ->where('action', 'ai_usage.threshold.alerted')
            ->count();

        $this->assertEquals(1, $count);
    }
}
