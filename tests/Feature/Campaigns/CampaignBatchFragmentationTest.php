<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Domain\Campaigns\Services\CampaignDispatcher;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T183 (Fase 8 — Lote C US-9.2)** — AC-9.2.5 — Fragmentação em batch.
 *
 * Quando público > daily_limit, dispatcher precisa fragmentar — recipients
 * vão sendo enviados respeitando o limite diário. Sobra fica em pending
 * para o próximo dia (ProcessCampaignBatchJob re-enfileira chunks).
 *
 * Este teste valida:
 *   1. Snapshot recipients criado em batch insertOrIgnore (não trava em
 *      cenário de público grande).
 *   2. daily_limit_applied é capturado no snapshot (Q2).
 */
class CampaignBatchFragmentationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dispatcher_handles_large_audience_via_batch_insert(): void
    {
        Bus::fake();

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-frag-large', 'admin-clinica');

        // 15 pacientes — para validar que insertOrIgnore em chunks de 500 funciona
        // (em produção o número seria muito maior; aqui mantemos suite rápida).
        Paciente::factory()->count(15)->state(['tenant_id' => $tenant->id])->create();

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'status' => CampaignStatus::Draft,
            'created_by_user_id' => $admin->id,
            'audience_filters' => ['inactivity_months' => 1],
        ])->create();

        app(CampaignDispatcher::class)->dispatch($campaign);

        $campaign->refresh();

        $this->assertSame(CampaignStatus::Dispatching, $campaign->status);
        $recipientCount = CampaignRecipient::query()->where('campaign_id', $campaign->id)->count();
        $this->assertGreaterThanOrEqual(15, $recipientCount);

        // daily_limit_applied capturado no snapshot.
        $this->assertSame($tenant->dailyCampaignLimit(), $campaign->daily_limit_applied);
    }

    public function test_recipient_status_remains_pending_until_processed(): void
    {
        Bus::fake();

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-frag-pending', 'admin-clinica');

        Paciente::factory()->count(5)->state(['tenant_id' => $tenant->id])->create();

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'audience_filters' => ['inactivity_months' => 1],
        ])->create();

        app(CampaignDispatcher::class)->dispatch($campaign);

        // Como Bus::fake() impede o ProcessCampaignBatchJob de rodar de fato,
        // recipients permanecem em status=pending — exatamente o que o batch
        // job processará no próximo run.
        $pending = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->count();

        $this->assertGreaterThanOrEqual(5, $pending);
    }
}
