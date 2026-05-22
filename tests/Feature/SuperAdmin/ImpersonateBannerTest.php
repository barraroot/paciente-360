<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Domain\SuperAdmin\Services\ImpersonateService;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T107 (Fase 8 — Lote B US-12.1)** — SC-12.2 / AC-12.1.5.
 *
 * Valida que `ImpersonateService::start()` persiste a sessão corretamente
 * para que o middleware {@see App\Http\Middleware\ImpersonateContextResolver}
 * encontre `activeSessionFor($superAdmin)` em todas as requisições subsequentes.
 *
 * O frontend lerá `X-Impersonate-Active` no header de qualquer response E
 * exibe o banner — este teste valida o lado SERVIDOR (lookup correto).
 */
class ImpersonateBannerTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_active_session_is_retrievable_for_banner_to_render(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-impr-banner', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        /** @var ImpersonateService $svc */
        $svc = app(ImpersonateService::class);

        $session = $svc->start(
            superAdmin: $superAdmin,
            tenant: $tenant,
            ipAddress: '127.0.0.1',
            userAgent: 'TestSuite/1.0',
            reason: 'Validação de banner persistente',
        );

        // Lookup que o middleware faz em cada request.
        $active = $svc->activeSessionFor($superAdmin);

        $this->assertNotNull($active, 'Sessão ativa deve ser retornável para o banner renderizar.');
        $this->assertSame($session->id, $active->id);
        $this->assertSame($tenant->id, $active->tenant_id);
    }

    public function test_ended_session_is_not_returned_by_active_lookup(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-impr-ended', 'admin-clinica');
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        /** @var ImpersonateService $svc */
        $svc = app(ImpersonateService::class);

        $session = $svc->start(
            superAdmin: $superAdmin,
            tenant: $tenant,
            ipAddress: '127.0.0.1',
            userAgent: 'TestSuite/1.0',
            reason: 'Sessão para encerrar imediatamente',
        );

        $svc->end($session);

        $this->assertNull($svc->activeSessionFor($superAdmin));
    }
}
