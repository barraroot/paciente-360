<?php

namespace Tests\Feature\Fase0\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature test do middleware `EnsureTenantNotSuspended` (T043).
 *
 * Cobertura:
 *  - tenant `active` atravessa;
 *  - tenant `suspended` recebe 403 com payload identificável;
 *  - Super Admin (tenant_id NULL) atravessa mesmo em tenant suspenso.
 *
 * Strategy: registra rota dummy de teste protegida pelo alias
 * `tenant.not-suspended` no `setUp` e bate nela com host de tenant.
 */
class EnsureTenantNotSuspendedTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'tenant.not-suspended'])
            ->prefix('api/v1')
            ->get('/_susp', function () {
                return response()->json(['ok' => true]);
            });
    }

    public function test_active_tenant_is_allowed(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-active', 'status' => 'active']);

        $response = $this->getJson('http://clinica-active.lvh.me/api/v1/_susp');

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_suspended_tenant_is_rejected_with_403(): void
    {
        $this->createTenant(['slug' => 'clinica-susp', 'status' => 'suspended']);

        $response = $this->getJson('http://clinica-susp.lvh.me/api/v1/_susp');

        $response->assertForbidden()
            ->assertJsonPath('error', 'tenant_suspended');
    }

    public function test_super_admin_bypasses_suspension(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-bypass', 'status' => 'suspended']);

        // Super Admin: User com tenant_id NULL (cross-tenant).
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($superAdmin)
            ->getJson('http://clinica-bypass.lvh.me/api/v1/_susp');

        $response->assertOk();
    }
}
