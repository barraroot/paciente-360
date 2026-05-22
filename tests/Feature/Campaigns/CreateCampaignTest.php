<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Events\CampanhaCriada;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Domain\Campaigns\Services\CampaignBuilder;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T173 (Fase 8 — Lote C US-9.1)** — AC-9.1.1 + Q1 audience calculation.
 */
class CreateCampaignTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_builder_creates_draft_campaign_and_emits_event(): void
    {
        Event::fake([CampanhaCriada::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-camp-create', 'admin-clinica');

        $campaign = app(CampaignBuilder::class)->create(
            tenant: $tenant,
            createdBy: $admin,
            data: [
                'name' => 'Reativação Maio QA',
                'channel' => 'whatsapp',
                'audience_filters' => [
                    'inactivity_months' => 6,
                    'tags' => ['vacinação'],
                ],
            ],
        );

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame($tenant->id, $campaign->tenant_id);
        $this->assertSame($admin->id, $campaign->created_by_user_id);
        $this->assertSame($tenant->dailyCampaignLimit(), $campaign->daily_limit_applied);

        Event::assertDispatched(CampanhaCriada::class);
    }

    public function test_scheduled_campaign_transitions_to_scheduled_status(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-camp-scheduled', 'admin-clinica');

        $campaign = app(CampaignBuilder::class)->create(
            tenant: $tenant,
            createdBy: $admin,
            data: [
                'name' => 'Sazonal QA',
                'channel' => 'whatsapp',
                'scheduled_for' => now()->addDays(7)->toIso8601String(),
                'audience_filters' => ['inactivity_months' => 12],
            ],
        );

        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertNotNull($campaign->scheduled_for);
    }

    public function test_preview_returns_eligible_count_and_warnings(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-camp-preview', 'admin-clinica');

        // 3 pacientes sem nenhuma consulta realizada — todos elegíveis para
        // critério "inativo".
        Paciente::factory()->count(3)->state(['tenant_id' => $tenant->id])->create();

        $campaign = app(CampaignBuilder::class)->create($tenant, $admin, [
            'name' => 'Preview QA',
            'channel' => 'whatsapp',
            'audience_filters' => ['inactivity_months' => 6],
        ]);

        $preview = app(CampaignBuilder::class)->preview($campaign);

        $this->assertGreaterThanOrEqual(3, $preview['eligible_count']);
        $this->assertIsArray($preview['warnings']);
        // Warning esperado: template não selecionado.
        $this->assertNotEmpty(array_filter($preview['warnings'], fn ($w) => str_contains($w, 'Template')));
    }
}
