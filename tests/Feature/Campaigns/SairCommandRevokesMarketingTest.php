<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Services\CampaignComplianceGate;
use App\Domain\Privacy\Listeners\ProcessSairCommandListener;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T149 (Fase 8 — Lote C US-9.3)** — Integração entre Privacy/`/sair` e Campaign gate.
 *
 * Cobre AC-9.3.1#4 / Q25: paciente que envia `/sair` tem seu opt-in marketing
 * revogado imediatamente, e o CampaignComplianceGate detecta a revogação no
 * próximo dispatch (sem race condition).
 *
 * Complementa T036 (ConsentRevocationViaSairTest do Lote A) com a perspectiva
 * do dispatcher.
 */
class SairCommandRevokesMarketingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dispatcher_detects_recent_sair_revoke_via_consent_lookup(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-sair-dispatch', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $consent */
        $consent = app(ConsentService::class);
        $consent->record($patient, 'whatsapp', ConsentFinalidade::Marketing);

        // Antes do /sair — consent ativo, hasGranted=true.
        $this->assertTrue($consent->hasGranted($patient->id, ConsentFinalidade::Marketing));

        // Paciente envia /sair.
        $listener = app(ProcessSairCommandListener::class);
        $revoked = $listener->process($patient, 'whatsapp', '/sair');

        $this->assertNotEmpty($revoked, '/sair deveria revogar marketing');

        // Próximo dispatch: gate detecta opt-in ausente, bloqueia.
        $this->assertFalse($consent->hasGranted($patient->id, ConsentFinalidade::Marketing));

        // E o helper hasReceivedSairCommandRecently retorna true.
        /** @var CampaignComplianceGate $gate */
        $gate = app(CampaignComplianceGate::class);
        $this->assertTrue($gate->hasReceivedSairCommandRecently($patient->id, hours: 24));
    }

    public function test_old_sair_outside_24h_window_returns_false(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-sair-old', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $consent */
        $consent = app(ConsentService::class);
        $consent->record($patient, 'whatsapp', ConsentFinalidade::Marketing);

        // Simula revogação há 48h.
        $listener = app(ProcessSairCommandListener::class);
        $listener->process($patient, 'whatsapp', '/sair');

        // Backdating manual da revogação.
        \App\Domain\Privacy\Models\ConsentRecord::query()
            ->where('patient_id', $patient->id)
            ->where('state', 'revoked')
            ->update(['revoked_at' => now()->subHours(48)]);

        /** @var CampaignComplianceGate $gate */
        $gate = app(CampaignComplianceGate::class);

        $this->assertFalse($gate->hasReceivedSairCommandRecently($patient->id, hours: 24));
    }

    public function test_patient_without_any_revoke_returns_false(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-no-sair', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var CampaignComplianceGate $gate */
        $gate = app(CampaignComplianceGate::class);

        $this->assertFalse($gate->hasReceivedSairCommandRecently($patient->id));
    }
}
