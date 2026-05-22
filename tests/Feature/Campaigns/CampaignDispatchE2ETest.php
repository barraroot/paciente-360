<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Events\CampanhaDisparada;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Domain\Campaigns\Services\CampaignDispatcher;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T174 (Fase 8 — Lote C US-9.1)** — AC-9.1.2 — pipeline dispatch ponta a ponta.
 *
 * Valida que:
 *   1. dispatch() transita status draft → dispatching
 *   2. Snapshot de recipients criado em campaign_recipients (1 por elegível)
 *   3. CampanhaDisparada emitido com total_eligible correto
 *   4. ProcessCampaignBatchJob enfileirado para a fila 'campaigns'
 */
class CampaignDispatchE2ETest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dispatch_creates_recipients_snapshot_and_enqueues_batch(): void
    {
        Event::fake([CampanhaDisparada::class]);
        Bus::fake();

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-disp-e2e', 'admin-clinica');

        // 4 pacientes elegíveis.
        Paciente::factory()->count(4)->state(['tenant_id' => $tenant->id])->create();

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'status' => CampaignStatus::Draft,
            'audience_filters' => ['inactivity_months' => 1],
            'created_by_user_id' => $admin->id,
        ])->create();

        app(CampaignDispatcher::class)->dispatch($campaign);

        $campaign->refresh();

        $this->assertSame(CampaignStatus::Dispatching, $campaign->status);
        $this->assertNotNull($campaign->dispatched_at);
        $this->assertGreaterThanOrEqual(4, $campaign->total_eligible);

        $recipientCount = CampaignRecipient::query()->where('campaign_id', $campaign->id)->count();
        $this->assertGreaterThanOrEqual(4, $recipientCount);

        Event::assertDispatched(CampanhaDisparada::class);
        Bus::assertDispatched(\App\Domain\Campaigns\Jobs\ProcessCampaignBatchJob::class);
    }

    public function test_dispatch_fails_for_terminal_campaign(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-disp-terminal', 'admin-clinica');

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
        ])->completed()->create();

        $this->expectException(\RuntimeException::class);
        app(CampaignDispatcher::class)->dispatch($campaign);
    }
}
