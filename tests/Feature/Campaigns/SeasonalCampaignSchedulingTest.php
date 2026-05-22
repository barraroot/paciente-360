<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Events\CampanhaCriada;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Domain\Campaigns\Services\CampaignBuilder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T181 (Fase 8 — Lote C US-9.2)** — AC-9.2.1, AC-9.2.2, AC-9.2.3.
 *
 * Cobertura:
 *   1. Campanha com `scheduled_for` futuro entra em status=scheduled (AC-9.2.1).
 *   2. Cron `campaigns:dispatch-scheduled` detecta scheduled com scheduled_for <= now (AC-9.2.2).
 *   3. Campanha em status terminal (completed/canceled) é imutável (AC-9.2.3).
 */
class SeasonalCampaignSchedulingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_campaign_with_future_scheduled_for_enters_scheduled_status(): void
    {
        Event::fake([CampanhaCriada::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-seasonal-future', 'admin-clinica');

        $scheduledFor = Carbon::now()->addDays(7);

        $campaign = app(CampaignBuilder::class)->create($tenant, $admin, [
            'name' => 'Campanha de Vacinação Anual',
            'channel' => 'whatsapp',
            'scheduled_for' => $scheduledFor->toIso8601String(),
            'audience_filters' => ['tags' => ['vacinação']],
        ]);

        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertNotNull($campaign->scheduled_for);
        $this->assertTrue($campaign->scheduled_for->equalTo($scheduledFor));

        Event::assertDispatched(CampanhaCriada::class);
    }

    public function test_cron_command_picks_up_ready_to_dispatch_campaigns(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-seasonal-cron', 'admin-clinica');

        // Campanha com scheduled_for no passado — pronta para dispatch.
        $past = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
        ])->scheduled(Carbon::now()->subMinutes(5))->create();

        // Campanha com scheduled_for no futuro — não deve ser pegada.
        $future = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
        ])->scheduled(Carbon::now()->addDays(5))->create();

        $readyIds = Campaign::query()->readyToDispatch()->pluck('id')->all();

        $this->assertContains($past->id, $readyIds);
        $this->assertNotContains($future->id, $readyIds);

        // O cron command em si reportará apenas a count via output:
        $this->artisan('campaigns:dispatch-scheduled', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_terminal_status_campaign_cannot_be_canceled_twice(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-seasonal-immutable', 'admin-clinica');

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
        ])->canceled('Já cancelada anteriormente')->create();

        $this->expectException(\RuntimeException::class);
        app(CampaignBuilder::class)->cancel($campaign, $admin, 'Tentativa de cancelar duas vezes');
    }

    public function test_completed_campaign_cannot_be_dispatched_again(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-seasonal-completed', 'admin-clinica');

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
        ])->completed()->create();

        $this->expectException(\RuntimeException::class);
        app(\App\Domain\Campaigns\Services\CampaignDispatcher::class)->dispatch($campaign);
    }
}
