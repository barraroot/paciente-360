<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Services\GlobalMetricsService;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T138 (Fase 8 — Lote B US-12.3)** — GATE 5 (Constituição Princípio II):
 * métricas globais NÃO expõem dados individuais de paciente (AC-12.3.2).
 *
 * Valida:
 *   1. snapshot() retorna apenas valores agregados (numeric / counts).
 *   2. Cross-tenant isolation: tenants de diferentes tenant_id contam corretamente
 *      no MRR sem expor PII.
 *   3. Snapshot computado SEM acesso a `app('tenant')` no container (operação
 *      Super Admin cross-tenant).
 */
class GlobalMetricsTenantIsolationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_snapshot_returns_only_aggregated_values(): void
    {
        // Setup — 2 planos com preços distintos + 3 tenants ativos.
        $basico = Plan::factory()->create(['code' => 'basico-met', 'base_price_cents' => 9900]);
        $pro = Plan::factory()->create(['code' => 'pro-met', 'base_price_cents' => 29900]);

        Tenant::factory()->count(2)->create(['plan_id' => $basico->id, 'status' => 'active']);
        Tenant::factory()->create(['plan_id' => $pro->id, 'status' => 'active']);

        /** @var GlobalMetricsService $svc */
        $svc = app(GlobalMetricsService::class);
        $snapshot = $svc->snapshot();

        // MRR = 2 × 9900 + 1 × 29900 = 49700 centavos
        $this->assertSame(49700, $snapshot['mrr_cents']);
        $this->assertSame(49700 * 12, $snapshot['arr_cents']);
        $this->assertSame(3, $snapshot['tenants_active']);

        // GATE 5 — snapshot deve conter APENAS valores agregados, sem PII.
        // Validação: cada valor é número ou array de números/estatísticas.
        $this->assertIsInt($snapshot['mrr_cents']);
        $this->assertIsInt($snapshot['arr_cents']);
        $this->assertIsInt($snapshot['tenants_active']);
        $this->assertIsArray($snapshot['churn_primary']);
        $this->assertIsArray($snapshot['revenue_churn']);
        $this->assertIsArray($snapshot['trial_to_paid']);
        $this->assertIsInt($snapshot['ai_usage_total_month']);

        // GATE 5 — nenhuma chave deve ser um array de tenants ou nomes de paciente.
        foreach ($snapshot as $key => $value) {
            $this->assertNotContains($key, ['tenants', 'patients', 'patient_names'], "Snapshot expõe '{$key}' — viola Princípio II.");
        }
    }

    public function test_snapshot_works_without_tenant_in_container(): void
    {
        // Garante que o container NÃO tem tenant resolvido — operação Super Admin.
        $this->assertFalse(app()->bound('tenant'));

        Plan::factory()->create(['base_price_cents' => 50000]);

        /** @var GlobalMetricsService $svc */
        $svc = app(GlobalMetricsService::class);

        // Deve rodar sem exceção mesmo sem tenant.
        $snapshot = $svc->snapshot();

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('computed_at', $snapshot);
    }

    public function test_churn_rate_calculation_is_aggregate_only(): void
    {
        // Setup — 4 tenants criados há 60 dias; 1 cancelado nos últimos 30d.
        $oldDate = Carbon::now()->subDays(60);
        Tenant::factory()->count(4)->create([
            'status' => 'active',
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        Tenant::query()->withoutGlobalScopes()->limit(1)->update([
            'status' => 'cancelled',
            'canceled_at' => Carbon::now()->subDays(10),
        ]);

        /** @var GlobalMetricsService $svc */
        $svc = app(GlobalMetricsService::class);
        $churn = $svc->computeChurnPrimary();

        $this->assertSame(4, $churn['denominator']);
        $this->assertSame(1, $churn['cancelled']);
        $this->assertSame(25.0, $churn['rate_percent']);

        // GATE 5 — retorno é puramente numérico, sem nomes/IDs de tenant exposed.
        $this->assertArrayNotHasKey('tenant_ids', $churn);
        $this->assertArrayNotHasKey('tenants', $churn);
    }
}
