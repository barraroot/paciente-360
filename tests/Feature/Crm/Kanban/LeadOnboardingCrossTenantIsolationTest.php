<?php

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Crm\Kanban\Services\LeadOnboardingService;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * T095 — Lead onboarding isolamento multi-tenant (Princípio II).
 *
 * Verifica que:
 * - Mesmo número de telefone em 2 tenants cria 2 Pacientes diferentes
 * - Cada um em seu tenant com sua coluna inicial
 * - Nenhuma colisão ou fusão automática
 */
class LeadOnboardingCrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private FunilColuna $initialColumnA;

    private FunilColuna $initialColumnB;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar 2 tenants
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        // Criar coluna inicial para cada tenant
        $this->initialColumnA = FunilColuna::create([
            'tenant_id' => $this->tenantA->id,
            'nome' => 'Novos Leads',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        $this->initialColumnB = FunilColuna::create([
            'tenant_id' => $this->tenantB->id,
            'nome' => 'Novos Leads',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);
    }

    /**
     * @test
     */
    public function test_same_phone_in_different_tenants_creates_separate_leads(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $telefone = '+5511999999999';

        // Chamar onboarding no tenant A
        app()->instance('tenant', $this->tenantA);
        $pacienteA = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenantA->id);

        // Chamar onboarding no tenant B com o MESMO número
        app()->instance('tenant', $this->tenantB);
        $pacienteB = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenantB->id);

        // Verifica que ambos retornam pacientes válidos
        $this->assertNotNull($pacienteA);
        $this->assertNotNull($pacienteB);

        // Verifica que são pacientes diferentes (IDs diferentes)
        $this->assertNotEquals($pacienteA->id, $pacienteB->id);

        // Verifica que cada um está em seu tenant
        $this->assertEquals($this->tenantA->id, $pacienteA->tenant_id);
        $this->assertEquals($this->tenantB->id, $pacienteB->tenant_id);

        // Verifica que cada um tem o mesmo telefone mas em tenants diferentes
        $this->assertEquals($telefone, $pacienteA->telefone_primario);
        $this->assertEquals($telefone, $pacienteB->telefone_primario);

        // Verifica que cada um está na sua coluna inicial
        $this->assertEquals($this->initialColumnA->id, $pacienteA->funil_coluna_atual_id);
        $this->assertEquals($this->initialColumnB->id, $pacienteB->funil_coluna_atual_id);

        // Verifica que há 2 Pacientes totais (1 por tenant)
        $this->assertCount(1, Paciente::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->get());
        $this->assertCount(1, Paciente::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->get());

        // Verifica que há 2 KanbanCurationEvent (1 por tenant)
        $eventsA = KanbanCurationEvent::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->where('event_kind', 'lead_created')
            ->get();
        $eventsB = KanbanCurationEvent::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantB->id)
            ->where('event_kind', 'lead_created')
            ->get();
        $this->assertCount(1, $eventsA);
        $this->assertCount(1, $eventsB);
    }
}
