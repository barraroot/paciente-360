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
 * T093 — Lead onboarding reabertura em coluna terminal (FR-013).
 *
 * Verifica que:
 * - Lead em coluna terminal (is_terminal=true, ex: "perdido") que recebe mensagem
 * - É movido de volta para a coluna inicial
 * - KanbanCurationEvent('lead_reactivated') é emitido com from/to_coluna_id
 */
class LeadOnboardingTerminalReactivationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $initialColumn;

    private FunilColuna $terminalColumn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar coluna inicial
        $this->initialColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Novos Leads',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Criar coluna terminal
        $this->terminalColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Perdido',
            'slug' => 'perdido',
            'posicao' => 7,
            'is_initial' => false,
            'is_terminal' => true,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);
    }

    /**
     * @test
     */
    public function test_lead_in_terminal_column_reactivates_to_initial(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $telefone = '+5511999999999';

        // Criar lead na coluna terminal
        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'lead',
            'telefone_primario' => $telefone,
            'funil_coluna_atual_id' => $this->terminalColumn->id,
        ]);

        // Chamar onboarding
        $result = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenant->id);

        // Verifica que retorna o mesmo paciente
        $this->assertNotNull($result);
        $this->assertEquals($paciente->id, $result->id);

        // Verifica que o paciente foi movido para a coluna inicial
        $paciente->refresh();
        $this->assertEquals($this->initialColumn->id, $paciente->funil_coluna_atual_id);

        // Verifica o evento (lead_reactivated)
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('event_kind', 'lead_reactivated')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);
        $this->assertEquals($this->terminalColumn->id, $event->from_coluna_id);
        $this->assertEquals($this->initialColumn->id, $event->to_coluna_id);

        // Verifica que o evento foi disparado
        Event::assertDispatched(KanbanCardCurated::class);
    }
}
