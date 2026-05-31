<?php

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Crm\Kanban\Services\LeadOnboardingService;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T094 — Lead onboarding falha graceful (FR-015).
 *
 * Verifica que:
 * - Tenant sem coluna inicial retorna null
 * - Nenhum Paciente é criado
 * - Nenhum KanbanCurationEvent é persistido (porque paciente_id é FK NOT NULL)
 * - Falha é logada com warning
 */
class LeadOnboardingTenantSuspendedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        // Criar tenant SEM qualquer FunilColuna
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);
    }

    /**
     * @test
     */
    public function test_onboarding_fails_gracefully_without_initial_column(): void
    {
        Log::shouldReceive('warning')
            ->with('lead_onboarding.failed', \Mockery::on(function (array $context) {
                return isset($context['tenant_id'], $context['channel'], $context['error']);
            }))
            ->once();

        $service = app(LeadOnboardingService::class);
        $telefone = '+5511999999999';

        // Chamar onboarding (tenant sem coluna)
        $result = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenant->id);

        // Verifica que retorna null
        $this->assertNull($result);

        // Verifica que nenhum Paciente foi criado
        $this->assertCount(0, Paciente::where('tenant_id', $this->tenant->id)->get());

        // Verifica que nenhum KanbanCurationEvent foi persistido
        $this->assertCount(0, KanbanCurationEvent::where('tenant_id', $this->tenant->id)->get());
    }
}
