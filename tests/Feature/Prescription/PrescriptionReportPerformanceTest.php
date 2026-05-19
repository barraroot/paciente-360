<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T154 — NFR-002/SC-007 — Performance do relatório de receitas.
 *
 * Gate: p95 ≤ 3000ms em CI (produção meta ≤ 1500ms com 50k receitas).
 *
 * NOTA DE PERFORMANCE (DEFERRED para produção):
 *  - Este teste usa 500 receitas em vez de 50k para CI performance.
 *  - Em produção, executar com PRESCRIPTION_PERF_COUNT=50000 e gate 1500ms.
 *  - Índices utilizados: idx_prescriptions_tenant_status_expires,
 *    idx_prescriptions_tenant_type_expires (data-model.md §2).
 *
 * Constraint DB: common: (expires_at - issued_at) IN (30, 60, 90, 180).
 * As receitas são inseridas via DB::table para bypass do model/events.
 * Os valores de issued/expires respeitam a constraint.
 *
 * @group performance
 */
class PrescriptionReportPerformanceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_report_first_page_p95_within_ci_gate(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('perf-report', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        // CI: 500 prescriptions. Production: 50k (run with PRESCRIPTION_PERF_COUNT=50000)
        $count = (int) env('PRESCRIPTION_PERF_COUNT', 500);
        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Seed em batch via DB::table para evitar events/observers e respeitar constraints
        // Válido: issued + 30d = dentro da janela de 30d (daqui 1-30d)
        $now = Carbon::today();
        $rows = [];

        // Para constraint: issued = hoje - (30 - daysAhead), expires = issued + 30 = hoje + daysAhead
        // daysAhead varia de 1 a 30 (dentro da janela default)
        for ($i = 0; $i < $count; $i++) {
            $daysAhead = ($i % 30) + 1; // 1-30
            $issuedAt = $now->copy()->subDays(30 - $daysAhead); // hoje - (30 - daysAhead)
            $expiresAt = $issuedAt->copy()->addDays(30); // issued + 30 = hoje + daysAhead

            $rows[] = [
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'professional_id' => $medico->id,
                'type' => PrescriptionType::Common->value,
                'status' => PrescriptionStatus::Active->value,
                'source' => 'manual',
                'issued_at' => $issuedAt->toDateString(),
                'expires_at' => $expiresAt->toDateString(),
                'alert_disabled' => false,
                'pdf_version' => 0,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        // Insert em chunks de 100 para evitar limite de bind params
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('prescriptions')->insert($chunk);
        }

        // 10 iterações para calcular p95
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);

            $response = $this->getJson(
                $this->tenantUrl($tenant, '/prescription-reports?per_page=50'),
                ['X-Tenant-Slug' => $tenant->slug],
            );

            $elapsed = (microtime(true) - $start) * 1000; // ms
            $times[] = $elapsed;

            $response->assertOk();
        }

        sort($times);

        // p95 = 95th percentile (índice 9 de 10)
        $p95Index = (int) ceil(0.95 * count($times)) - 1;
        $p95 = $times[$p95Index];

        // CI gate: 3000ms (produção meta: 1500ms com 50k)
        $gate = 3000;

        $this->assertLessThanOrEqual(
            $gate,
            $p95,
            "p95 ({$p95}ms) excedeu o gate de {$gate}ms para {$count} receitas."
        );
    }
}
