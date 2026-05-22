<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignBuilder;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T182 (Fase 8 — Lote C US-9.2)** — AC-9.2.4 — Pré-visualização retorna avisos.
 *
 * Pré-visualização da campanha sazonal deve retornar:
 *   - eligible_count atual
 *   - warnings: "12 pacientes sem opt-in serão excluídos", "template requer
 *     aprovação Meta", "limite diário do plano menor que público estimado"
 */
class CampaignPreviewWarningsTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_preview_warns_when_no_template_selected(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-preview-no-tpl', 'admin-clinica');

        Paciente::factory()->count(3)->state(['tenant_id' => $tenant->id])->create();

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'template_id' => null,
            'created_by_user_id' => $admin->id,
            'audience_filters' => ['inactivity_months' => 1],
        ])->create();

        $preview = app(CampaignBuilder::class)->preview($campaign);

        $this->assertGreaterThan(0, $preview['eligible_count']);
        $this->assertIsArray($preview['warnings']);
        $templateWarning = array_filter($preview['warnings'], fn ($w) => str_contains($w, 'Template'));
        $this->assertNotEmpty($templateWarning, 'Preview deveria avisar sobre template não selecionado.');
    }

    public function test_preview_warns_when_audience_exceeds_daily_limit(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-preview-overlimit', 'admin-clinica');

        // Plan default daily_campaign_limit=200. Forçamos limite baixo via update.
        $tenant->plan->update(['daily_campaign_limit' => 5]);

        // 10 pacientes — excede o limite de 5.
        Paciente::factory()->count(10)->state(['tenant_id' => $tenant->id])->create();

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'audience_filters' => ['inactivity_months' => 1],
        ])->create();

        $preview = app(CampaignBuilder::class)->preview($campaign);

        $this->assertGreaterThanOrEqual(10, $preview['eligible_count']);
        $overlimitWarning = array_filter($preview['warnings'], fn ($w) => str_contains($w, 'Limite diário'));
        $this->assertNotEmpty($overlimitWarning, 'Preview deveria avisar que público excede limite diário.');
    }

    public function test_preview_warns_when_no_eligible_patients(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-preview-empty', 'admin-clinica');

        // Nenhum paciente cadastrado.
        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'audience_filters' => ['inactivity_months' => 1],
        ])->create();

        $preview = app(CampaignBuilder::class)->preview($campaign);

        $this->assertSame(0, $preview['eligible_count']);
        $emptyWarning = array_filter($preview['warnings'], fn ($w) => str_contains($w, 'Nenhum paciente'));
        $this->assertNotEmpty($emptyWarning);
    }
}
