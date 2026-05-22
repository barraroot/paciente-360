<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Domain\Campaigns\Services\CampaignDispatcher;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T175 (Fase 8 — Lote C US-9.1)** — AC-9.1.6 idempotência via UNIQUE.
 *
 * Re-dispatch acidental NUNCA cria recipients duplicados para o mesmo
 * (campaign_id, patient_id). UNIQUE INDEX `uq_campaign_recipients_idempotency`
 * é defesa DB-level; `insertOrIgnore` no dispatcher é a defesa application-level.
 */
class CampaignIdempotencyTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unique_constraint_blocks_duplicate_recipients(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-camp-uniq', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $campaign = Campaign::factory()->state(['tenant_id' => $tenant->id])->create();

        CampaignRecipient::query()->create([
            'tenant_id' => $tenant->id,
            'campaign_id' => $campaign->id,
            'patient_id' => $patient->id,
            'status' => 'pending',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Segunda tentativa via Eloquent (sem insertOrIgnore) deve falhar.
        CampaignRecipient::query()->create([
            'tenant_id' => $tenant->id,
            'campaign_id' => $campaign->id,
            'patient_id' => $patient->id,
            'status' => 'pending',
        ]);
    }

    public function test_dispatcher_insertOrIgnore_skips_existing_recipients(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-camp-idem', 'admin-clinica');

        // Seta plano sem limite para evitar interferência.
        Paciente::factory()->count(2)->state(['tenant_id' => $tenant->id])->create();

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'status' => CampaignStatus::Draft,
            'audience_filters' => ['inactivity_months' => 1],
            'created_by_user_id' => $admin->id,
        ])->create();

        // Primeira chamada cria recipients.
        app(CampaignDispatcher::class)->dispatch($campaign);

        $firstCount = CampaignRecipient::query()->where('campaign_id', $campaign->id)->count();

        // Não consegue chamar dispatch() de novo (status agora dispatching),
        // mas validamos diretamente que inserções duplicadas via insertOrIgnore
        // não criam linhas.
        $campaign->refresh();
        $existing = CampaignRecipient::query()->where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($existing);

        $beforeCount = CampaignRecipient::query()->count();
        CampaignRecipient::query()->insertOrIgnore([[
            'tenant_id' => $tenant->id,
            'campaign_id' => $campaign->id,
            'patient_id' => $existing->patient_id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        $afterCount = CampaignRecipient::query()->count();
        $this->assertSame($beforeCount, $afterCount, 'insertOrIgnore deve ser no-op em conflict.');
    }
}
