<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Services\ImpersonateService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T108 (Fase 8 — Lote B US-12.1)** — PARTIAL UNIQUE garante 1 sessão ativa.
 *
 * Mesmo Super Admin NÃO pode ter 2 sessões simultâneas — DB-level enforcement
 * via UNIQUE INDEX `(super_admin_id) WHERE ended_at IS NULL`. Service também
 * faz pre-check com lockForUpdate, lançando RuntimeException com mensagem
 * acionável (UX-friendly).
 */
class ImpersonateConcurrencyTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_second_concurrent_session_throws_runtime_exception(): void
    {
        [$tenantA, ] = $this->tenantAndUserForRole('clinica-impr-conc-a', 'admin-clinica');
        [$tenantB, ] = $this->tenantAndUserForRole('clinica-impr-conc-b', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        /** @var ImpersonateService $svc */
        $svc = app(ImpersonateService::class);

        $svc->start(
            superAdmin: $superAdmin,
            tenant: $tenantA,
            ipAddress: '127.0.0.1',
            userAgent: 'TestSuite',
            reason: 'Primeira sessão ativa',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('já tem sessão ativa');

        $svc->start(
            superAdmin: $superAdmin,
            tenant: $tenantB,
            ipAddress: '127.0.0.1',
            userAgent: 'TestSuite',
            reason: 'Segunda sessão (deveria falhar)',
        );
    }

    public function test_different_super_admins_can_have_concurrent_sessions(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-impr-multi-sa', 'admin-clinica');
        $sa1 = User::factory()->create(['tenant_id' => null]);
        $sa2 = User::factory()->create(['tenant_id' => null]);

        /** @var ImpersonateService $svc */
        $svc = app(ImpersonateService::class);

        $session1 = $svc->start($sa1, $tenant, '127.0.0.1', 'TestSuite', 'Sessão SA #1');
        $session2 = $svc->start($sa2, $tenant, '127.0.0.1', 'TestSuite', 'Sessão SA #2');

        $this->assertTrue($session1->isActive());
        $this->assertTrue($session2->isActive());
        $this->assertNotSame($session1->id, $session2->id);
    }
}
