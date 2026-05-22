<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Events\ImpersonateIniciado;
use App\Domain\SuperAdmin\Events\ImpersonateTelaVisitada;
use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Domain\SuperAdmin\Models\SuperAdminAuditScreen;
use App\Domain\SuperAdmin\Services\ImpersonateService;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T106 (Fase 8 — Lote B US-12.1)** — GATE 7 (Q19): audit granular por tela visitada.
 *
 * Cada `recordScreenVisit()` cria uma row em `super_admin_audit_screens` E
 * incrementa o `screens_visited_count` da sessão. Evento `ImpersonateTelaVisitada`
 * é emitido (audit-only).
 */
class ImpersonateScreenAuditTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_record_screen_visit_creates_audit_row_and_increments_counter(): void
    {
        Event::fake([ImpersonateIniciado::class, ImpersonateTelaVisitada::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-impr-audit', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        /** @var ImpersonateService $svc */
        $svc = app(ImpersonateService::class);

        $session = $svc->start(
            superAdmin: $superAdmin,
            tenant: $tenant,
            ipAddress: '192.0.2.1',
            userAgent: 'PHPUnit',
            reason: 'Ticket #4242 — bug ao salvar agendamento',
        );

        // Visita 3 telas.
        $svc->recordScreenVisit($session, 'tenant.patients.index', '/api/v1/patients', 'GET', '192.0.2.1');
        $svc->recordScreenVisit($session, 'tenant.patients.show', '/api/v1/patients/42', 'GET', '192.0.2.1');
        $svc->recordScreenVisit($session, 'tenant.appointments.index', '/api/v1/appointments', 'GET', '192.0.2.1', ['date' => '2026-05-23']);

        $session->refresh();
        $this->assertSame(3, $session->screens_visited_count);

        $screens = SuperAdminAuditScreen::query()
            ->where('impersonate_session_id', $session->id)
            ->get();
        $this->assertCount(3, $screens);

        Event::assertDispatchedTimes(ImpersonateTelaVisitada::class, 3);
    }
}
