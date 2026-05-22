<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignAudienceCalculator;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T176 (Fase 8 — Lote C US-9.1)** — GATE 2 (Princípio II — Multi-tenant).
 *
 * Campanha tenant A não pode incluir pacientes tenant B no público.
 * Validação dupla:
 *   1. CampaignAudienceCalculator filtra explicitamente por tenant_id.
 *   2. Global scope BelongsToTenant + filtro adicional explícito.
 */
class CrossTenantCampaignTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_audience_calculator_isolates_tenants(): void
    {
        [$tenantA, ] = $this->tenantAndUserForRole('clinica-a-camp', 'admin-clinica');
        $tenantB = $this->bootstrapTenantWithRoles('clinica-b-camp');

        // 2 pacientes em A + 5 em B.
        Paciente::factory()->count(2)->state(['tenant_id' => $tenantA->id])->create();
        Paciente::factory()->count(5)->state(['tenant_id' => $tenantB->id])->create();

        $countA = app(CampaignAudienceCalculator::class)->estimate(
            $tenantA->id,
            ['inactivity_months' => 1],
        );
        $countB = app(CampaignAudienceCalculator::class)->estimate(
            $tenantB->id,
            ['inactivity_months' => 1],
        );

        $this->assertSame(2, $countA);
        $this->assertSame(5, $countB);
        $this->assertNotSame($countA, $countB);
    }

    public function test_tenant_a_cannot_see_tenant_b_campaigns_via_endpoint(): void
    {
        [$tenantA, ] = $this->tenantAndUserForRole('clinica-a-list', 'admin-clinica');

        $tenantB = $this->bootstrapTenantWithRoles('clinica-b-list');
        Campaign::factory()->count(3)->state(['tenant_id' => $tenantB->id])->create();

        $response = $this->getJson(
            $this->tenantUrl($tenantA, '/campaigns'),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_gets_404_when_accessing_tenant_b_campaign(): void
    {
        [$tenantA, ] = $this->tenantAndUserForRole('clinica-a-show', 'admin-clinica');
        $tenantB = $this->bootstrapTenantWithRoles('clinica-b-show');

        $campaignB = Campaign::factory()->state(['tenant_id' => $tenantB->id])->create();

        $response = $this->getJson(
            $this->tenantUrl($tenantA, "/campaigns/{$campaignB->id}"),
            ['X-Tenant-Slug' => $tenantA->slug],
        );

        $response->assertNotFound();
    }
}
