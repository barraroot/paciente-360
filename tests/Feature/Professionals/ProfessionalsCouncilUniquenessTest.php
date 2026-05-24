<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G3 (Spec 012)** — UNIQUE composto
 * `(tenant_id, council_type, council_number, council_state) WHERE deleted_at IS NULL`.
 *
 * Inscrição duplicada no mesmo tenant → 422. Inscrição igual em tenants distintos → OK.
 */
final class ProfessionalsCouncilUniquenessTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_duplicate_council_inside_same_tenant_returns_422(): void
    {
        [$tenant] = $this->tenantAndUserForRole('uniq-same', 'admin-clinica');
        $userA = $this->createUserForTenant($tenant);
        $userB = $this->createUserForTenant($tenant);

        $base = [
            'council_type' => 'CRM',
            'council_number' => '98765',
            'council_state' => 'SP',
        ];
        $headers = ['X-Tenant-Slug' => $tenant->slug];

        $first = $this->withHeaders($headers)->postJson(
            $this->tenantUrl($tenant, '/professionals'),
            array_merge($base, ['name' => 'Dr. Primeiro', 'user_id' => $userA->id])
        );
        $first->assertCreated();

        $second = $this->withHeaders($headers)->postJson(
            $this->tenantUrl($tenant, '/professionals'),
            array_merge($base, ['name' => 'Dr. Duplicado', 'user_id' => $userB->id])
        );
        $second->assertStatus(422);
    }

    public function test_same_council_across_distinct_tenants_is_allowed(): void
    {
        [$tenantA] = $this->tenantAndUserForRole('uniq-a', 'admin-clinica');
        $userA = $this->createUserForTenant($tenantA);

        $base = [
            'council_type' => 'CRM',
            'council_number' => '55555',
            'council_state' => 'RJ',
        ];

        $this->withHeaders(['X-Tenant-Slug' => $tenantA->slug])->postJson(
            $this->tenantUrl($tenantA, '/professionals'),
            array_merge($base, ['name' => 'Dr. Tenant A', 'user_id' => $userA->id])
        )->assertCreated();

        // Troca para tenant B.
        [$tenantB] = $this->tenantAndUserForRole('uniq-b', 'admin-clinica');
        $userB = $this->createUserForTenant($tenantB);

        $resp = $this->withHeaders(['X-Tenant-Slug' => $tenantB->slug])->postJson(
            $this->tenantUrl($tenantB, '/professionals'),
            array_merge($base, ['name' => 'Dr. Tenant B', 'user_id' => $userB->id])
        );
        $resp->assertCreated();

        $this->assertSame(
            2,
            Professional::query()
                ->withoutGlobalScopes()
                ->where('council_type', 'CRM')
                ->where('council_number', '55555')
                ->where('council_state', 'RJ')
                ->count()
        );
    }
}
