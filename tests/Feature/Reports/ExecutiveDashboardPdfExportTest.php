<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Reports\Events\RelatorioExportado;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T262 (Fase 8 — Lote E US-10.1)** — AC-10.1.5 — Export PDF + audit.
 *
 * Valida:
 *   1. POST /api/v1/reports/executive/export-pdf retorna 200 com content-type application/pdf
 *   2. Linha persistida em report_exports
 *   3. Evento `RelatorioExportado` emitido (Auditable)
 */
class ExecutiveDashboardPdfExportTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_export_pdf_persists_record_and_emits_event(): void
    {
        Event::fake([RelatorioExportado::class]);

        [$tenant, $user] = $this->tenantAndUserForRole('clinic-e', 'admin-clinica');

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/v1/reports/executive/export-pdf', ['preset' => '30d']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('report_exports', [
            'tenant_id' => $tenant->id,
            'tipo' => 'executive_dashboard',
            'formato' => 'pdf',
            'exported_by_user_id' => $user->id,
        ]);

        Event::assertDispatched(RelatorioExportado::class, function (RelatorioExportado $event) use ($tenant): bool {
            return $event->tenantId === $tenant->id
                && $event->tipo === 'executive_dashboard'
                && $event->formato === 'pdf';
        });
    }
}
