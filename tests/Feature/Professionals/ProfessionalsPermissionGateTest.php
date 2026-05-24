<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G2 (Spec 012)** — Gate `professional.manage` (Princípio VII).
 *
 * - admin-clinica → 200/2xx
 * - medico/recepcionista → 403
 */
final class ProfessionalsPermissionGateTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_clinica_can_list_professionals(): void
    {
        [$tenant] = $this->tenantAndUserForRole('gate-admin', 'admin-clinica');

        $response = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->getJson($this->tenantUrl($tenant, '/professionals'));

        $response->assertOk();
    }

    public function test_medico_cannot_create_professional(): void
    {
        [$tenant] = $this->tenantAndUserForRole('gate-medico', 'medico');
        $linkedUser = $this->createUserForTenant($tenant);

        $response = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])->postJson(
            $this->tenantUrl($tenant, '/professionals'),
            [
                'name' => 'Dr. Tentando',
                'council_type' => 'CRM',
                'council_number' => '12345',
                'council_state' => 'SP',
                'user_id' => $linkedUser->id,
            ]
        );

        $response->assertForbidden();
    }

    public function test_recepcionista_cannot_update_professional(): void
    {
        [$tenant] = $this->tenantAndUserForRole('gate-recep', 'recepcionista');

        $professional = Professional::factory()->forTenant($tenant)->create();

        $response = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])->putJson(
            $this->tenantUrl($tenant, "/professionals/{$professional->id}"),
            ['especialidade' => 'tentando']
        );

        $response->assertForbidden();
    }

    public function test_medico_cannot_delete_professional(): void
    {
        [$tenant] = $this->tenantAndUserForRole('gate-medico-del', 'medico');

        $professional = Professional::factory()->forTenant($tenant)->create();

        $response = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])->deleteJson(
            $this->tenantUrl($tenant, "/professionals/{$professional->id}")
        );

        $response->assertForbidden();
    }
}
