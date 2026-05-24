<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use App\Models\Agenda\Appointment;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T177 (Fase 8 — Lote C US-9.1)** — AC-9.1.7 — atribuição de agendamento.
 *
 * Paciente que recebeu campanha + criou agendamento em ≤7d aparece em
 * `attributed_appointments` do relatório. A vinculação é feita populando
 * `campaign_recipients.attributed_appointment_id` (mecanismo externo —
 * fora deste teste; aqui validamos apenas a leitura do relatório).
 */
class CampaignReportAttributionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_report_counts_attributed_appointments(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-report-attr', 'admin-clinica');

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'total_eligible' => 5,
            'total_dispatched' => 5,
        ])->completed()->create();

        // 3 recipients sent; 2 deles têm appointment_id atribuído.
        $patients = Paciente::factory()->count(3)->state(['tenant_id' => $tenant->id])->create();

        foreach ($patients as $i => $p) {
            // FK real em attributed_appointment_id → cria appointment para os 2 primeiros.
            $appointmentId = $i < 2
                ? Appointment::factory()->create([
                    'tenant_id' => $tenant->id,
                    'paciente_id' => $p->id,
                ])->id
                : null;

            CampaignRecipient::factory()->state([
                'tenant_id' => $tenant->id,
                'campaign_id' => $campaign->id,
                'patient_id' => $p->id,
                'status' => CampaignRecipientStatus::Sent,
                'attributed_appointment_id' => $appointmentId,
            ])->create();
        }

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/campaigns/{$campaign->id}/report"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.campaign_id', $campaign->id);
        $response->assertJsonPath('data.attributed_appointments', 2);
    }

    public function test_report_groups_blocked_reasons(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-report-blocks', 'admin-clinica');

        $campaign = Campaign::factory()->state([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
        ])->completed()->create();

        // 3 blocked: 2 sem opt-in, 1 fora de horário.
        CampaignRecipient::factory()->count(2)
            ->state(['campaign_id' => $campaign->id, 'tenant_id' => $tenant->id])
            ->blocked('no_marketing_opt_in')
            ->create();

        CampaignRecipient::factory()
            ->state(['campaign_id' => $campaign->id, 'tenant_id' => $tenant->id])
            ->blocked('outside_business_hours')
            ->create();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/campaigns/{$campaign->id}/report"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $response->assertJsonPath('data.blocked_breakdown.no_marketing_opt_in', 2);
        $response->assertJsonPath('data.blocked_breakdown.outside_business_hours', 1);
    }
}
