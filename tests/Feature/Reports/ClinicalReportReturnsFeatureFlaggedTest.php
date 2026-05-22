<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Reports\Services\ClinicalReportService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T277 (Fase 8 — Lote E US-10.3)** — Q14 — Feature flag de cadências de retorno.
 *
 * Quando a tabela `return_cadences` não existe ainda (ambiente pre-Fase
 * de Cadências de Retorno), o relatório clínico não quebra — apenas
 * omite a seção `returns_stats` ou retorna `null` com fallback claro.
 */
class ClinicalReportReturnsFeatureFlaggedTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_returns_stats_gracefully_absent_when_table_missing(): void
    {
        if (Schema::hasTable('return_cadences')) {
            $this->markTestSkipped('Tabela return_cadences existe nesse ambiente — teste valida fallback.');
        }

        [$tenant, $admin] = $this->tenantAndUserForRole('clinic-returns', 'admin-clinica');

        $service = app(ClinicalReportService::class);
        $data = $service->build($tenant, Carbon::now()->subDays(30), Carbon::now(), $admin);

        $this->assertTrue(
            ! isset($data['returns_stats']) || $data['returns_stats'] === null,
            'Quando return_cadences não existe, returns_stats deve estar ausente/null',
        );
    }
}
