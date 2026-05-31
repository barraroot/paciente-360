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
 * T092 — Lead onboarding paciente regular (FR-011a, Q-clarify-3=B).
 *
 * Verifica que:
 * - Paciente existente com status != 'lead' NÃO entra no kanban
 * - Mensagem é anexada ao prontuário (sem novo card)
 * - KanbanCurationEvent('inbound_attached_to_existing_patient') é emitido
 * - Retorno é o paciente existente (não None)
 */
class LeadOnboardingExistingPatientTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $initialColumn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar coluna inicial para o tenant
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
    }

    /**
     * @test
     */
    public function test_existing_patient_not_regular_status_not_added_to_kanban(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $telefone = '+5511999999999';

        // Criar paciente existente como "ativo" (não lead)
        $existingPaciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'ativo',
            'telefone_primario' => $telefone,
        ]);

        $totalBefore = Paciente::where('tenant_id', $this->tenant->id)->count();

        // Chamar onboarding com o mesmo telefone
        $result = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenant->id);

        // Verifica que retorna o paciente existente
        $this->assertNotNull($result);
        $this->assertEquals($existingPaciente->id, $result->id);

        // Verifica que nenhum novo paciente foi criado
        $totalAfter = Paciente::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals($totalBefore, $totalAfter);

        // Verifica que funil_coluna_atual_id não foi modificado (era null ou já tinha valor anterior)
        $existingPaciente->refresh();
        $this->assertNull($existingPaciente->funil_coluna_atual_id);

        // Verifica o evento (inbound_attached_to_existing_patient)
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('event_kind', 'inbound_attached_to_existing_patient')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);
        $this->assertNull($event->to_coluna_id);
        $this->assertNull($event->from_coluna_id);

        // Verifica que o evento foi disparado
        Event::assertDispatched(KanbanCardCurated::class);
    }
}
