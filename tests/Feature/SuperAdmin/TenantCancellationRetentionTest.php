<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Services\TenantLifecycleService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T109 (Fase 8 — Lote B US-12.1)** — GATE 6: política de retenção diferenciada (Q20).
 *
 * Valida que o cron `super-admin:apply-retention-policy` processa tenants
 * cancelados respeitando os checkpoints diferenciados (config 30d, paciente 90d,
 * audit 1a, controladas 2a, billing 5a). Em dry-run (default), comando apenas
 * loga; flag `--apply` aciona as deleções reais (DEFERRED em MVP).
 */
class TenantCancellationRetentionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_canceled_tenants_are_picked_by_retention_command(): void
    {
        // Tenant cancelado há 45 dias — passou checkpoint de 30d (config) mas
        // não 90d (paciente).
        [$tenant, ] = $this->tenantAndUserForRole('clinica-retention-45d', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        app(TenantLifecycleService::class)->cancel($tenant, $superAdmin, 'Teste de retenção 45d');

        // Simula passagem de 45 dias.
        $tenant->update(['canceled_at' => Carbon::now()->subDays(45)]);

        // 45d cancelado → passou o checkpoint de 30d (config purge) → config=1.
        // (Uma única asserção de substring — chaining de expectsOutputToContain
        // não é confiável nesta versão do test harness.)
        $this->artisan('super-admin:apply-retention-policy')
            ->expectsOutputToContain('processed=1, config=1')
            ->assertSuccessful();
    }

    public function test_command_handles_no_canceled_tenants_gracefully(): void
    {
        // Tenant ativo — não deve aparecer no scan.
        $this->tenantAndUserForRole('clinica-active', 'admin-clinica');

        $this->artisan('super-admin:apply-retention-policy')
            ->expectsOutputToContain('processed=0')
            ->assertSuccessful();
    }

    public function test_recent_cancellation_under_30d_not_triggers_config_purge(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-retention-recent', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        app(TenantLifecycleService::class)->cancel($tenant, $superAdmin, 'Cancelamento muito recente');
        $tenant->update(['canceled_at' => Carbon::now()->subDays(5)]);

        $this->artisan('super-admin:apply-retention-policy')
            ->expectsOutputToContain('config=0') // ainda não passou checkpoint 30d
            ->assertSuccessful();
    }
}
