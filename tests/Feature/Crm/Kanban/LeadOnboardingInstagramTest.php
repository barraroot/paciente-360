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
 * T089 — Lead onboarding Instagram Direct path (FR-010, FR-012).
 *
 * Verifica que:
 * - Handle Instagram novo dispara criação de Paciente com status='lead'
 * - Paciente com instagram_handle armazenado
 * - origem='instagram_direct'
 * - KanbanCurationEvent emitido
 */
class LeadOnboardingInstagramTest extends TestCase
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
    public function test_new_instagram_handle_creates_lead_in_initial_column(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $igsid = '17841400000000001';

        $paciente = $service->ensureFor(LeadOnboardingService::CHANNEL_INSTAGRAM, $igsid, $this->tenant->id);

        // Verifica que o paciente foi criado
        $this->assertNotNull($paciente);
        $this->assertEquals('lead', $paciente->status);
        $this->assertEquals('instagram', $paciente->origem);
        $this->assertEquals($igsid, $paciente->instagram_handle);
        $this->assertEquals($this->initialColumn->id, $paciente->funil_coluna_atual_id);

        // Verifica que apenas 1 paciente existe
        $this->assertCount(1, Paciente::where('tenant_id', $this->tenant->id)->get());

        // Verifica o evento
        Event::assertDispatched(KanbanCardCurated::class);
    }
}
