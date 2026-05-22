<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\CampaignTemplateMeta;
use App\Domain\Campaigns\Services\CampaignComplianceGate;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T147 (Fase 8 — Lote C US-9.3)** — GATE 1 CONSTITUCIONAL (Princípio VI).
 *
 * Valida que `CampaignComplianceGate::evaluate()` aplica as 4 validações
 * sequenciais em runtime — falha em qualquer uma bloqueia o envio com motivo
 * auditável.
 */
class CampaignDispatcherComplianceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_block_when_no_marketing_opt_in(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-compl-no-optin', 'admin-clinica');
        $this->configureBusinessHoursWideOpen($tenant);

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        // PROPOSITALMENTE não registra opt-in.

        $templateMeta = CampaignTemplateMeta::factory()
            ->state(['tenant_id' => $tenant->id])
            ->approved()
            ->create();

        $result = app(CampaignComplianceGate::class)->evaluate(
            patient: $patient,
            tenant: $tenant,
            templateMeta: $templateMeta,
            alreadyDispatchedToday: 0,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('no_marketing_opt_in', $result->blockReason);
    }

    public function test_block_when_template_not_approved(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-compl-no-template', 'admin-clinica');
        $this->configureBusinessHoursWideOpen($tenant);

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $this->grantMarketing($patient);

        $templateMeta = CampaignTemplateMeta::factory()
            ->state(['tenant_id' => $tenant->id])
            ->rejected()
            ->create();

        $result = app(CampaignComplianceGate::class)->evaluate(
            patient: $patient,
            tenant: $tenant,
            templateMeta: $templateMeta,
            alreadyDispatchedToday: 0,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('no_template_approved', $result->blockReason);
    }

    public function test_block_when_template_has_no_unsubscribe(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-compl-no-unsubscribe', 'admin-clinica');
        $this->configureBusinessHoursWideOpen($tenant);

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $this->grantMarketing($patient);

        // Template aprovado pela Meta mas SEM unsubscribe.
        $templateMeta = CampaignTemplateMeta::factory()
            ->state(['tenant_id' => $tenant->id])
            ->approved()
            ->withoutUnsubscribe()
            ->create();

        $result = app(CampaignComplianceGate::class)->evaluate(
            patient: $patient,
            tenant: $tenant,
            templateMeta: $templateMeta,
            alreadyDispatchedToday: 0,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('no_template_approved', $result->blockReason);
        $this->assertStringContainsString('has_unsubscribe=false', $result->details);
    }

    public function test_block_when_outside_business_hours(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-compl-hours', 'admin-clinica');
        // business_hours fechado segunda-feira (00:00-00:00 = nunca).
        $tenant->update([
            'settings' => array_merge((array) $tenant->settings, [
                'business_hours' => [
                    'monday' => '00:00-00:00',
                    'tuesday' => '00:00-00:00',
                    'wednesday' => '00:00-00:00',
                    'thursday' => '00:00-00:00',
                    'friday' => '00:00-00:00',
                    'saturday' => '00:00-00:00',
                    'sunday' => '00:00-00:00',
                    'timezone' => 'America/Sao_Paulo',
                ],
            ]),
        ]);
        $tenant->refresh();

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $this->grantMarketing($patient);

        $templateMeta = CampaignTemplateMeta::factory()
            ->state(['tenant_id' => $tenant->id])
            ->approved()
            ->create();

        $result = app(CampaignComplianceGate::class)->evaluate(
            patient: $patient,
            tenant: $tenant,
            templateMeta: $templateMeta,
            alreadyDispatchedToday: 0,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('outside_business_hours', $result->blockReason);
    }

    public function test_block_when_daily_limit_exceeded(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-compl-limit', 'admin-clinica');
        $this->configureBusinessHoursWideOpen($tenant);

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $this->grantMarketing($patient);

        $templateMeta = CampaignTemplateMeta::factory()
            ->state(['tenant_id' => $tenant->id])
            ->approved()
            ->create();

        $dailyLimit = $tenant->dailyCampaignLimit();

        $result = app(CampaignComplianceGate::class)->evaluate(
            patient: $patient,
            tenant: $tenant,
            templateMeta: $templateMeta,
            alreadyDispatchedToday: $dailyLimit,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('daily_limit_exceeded', $result->blockReason);
        $this->assertStringContainsString("limit={$dailyLimit}", $result->details);
    }

    public function test_all_pass_for_compliant_dispatch(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-compl-ok', 'admin-clinica');
        $this->configureBusinessHoursWideOpen($tenant);

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $this->grantMarketing($patient);

        $templateMeta = CampaignTemplateMeta::factory()
            ->state(['tenant_id' => $tenant->id])
            ->approved()
            ->create();

        $result = app(CampaignComplianceGate::class)->evaluate(
            patient: $patient,
            tenant: $tenant,
            templateMeta: $templateMeta,
            alreadyDispatchedToday: 5, // bem abaixo do limit (default 200)
        );

        $this->assertTrue($result->passed);
        $this->assertNull($result->blockReason);
    }

    /**
     * Helper: grants marketing opt-in para um paciente via ConsentService.
     */
    private function grantMarketing(Paciente $patient): void
    {
        app(ConsentService::class)->record($patient, 'web', ConsentFinalidade::Marketing);
    }

    /**
     * Helper: configura business_hours 00:00-23:59 todos os dias (sempre dentro).
     */
    private function configureBusinessHoursWideOpen(Tenant $tenant): void
    {
        $tenant->update([
            'settings' => array_merge((array) ($tenant->settings ?? []), [
                'business_hours' => [
                    'monday' => '00:00-23:59',
                    'tuesday' => '00:00-23:59',
                    'wednesday' => '00:00-23:59',
                    'thursday' => '00:00-23:59',
                    'friday' => '00:00-23:59',
                    'saturday' => '00:00-23:59',
                    'sunday' => '00:00-23:59',
                    'timezone' => 'America/Sao_Paulo',
                ],
            ]),
        ]);
        $tenant->refresh();
    }
}
