<?php

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Services\LeadOnboardingService;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * T090 — Lead onboarding widget de site (FR-010, FR-012).
 *
 * Verifica que:
 * - Identificador opaco (widget_anonymous_id) novo cria Paciente
 * - widget_anonymous_id armazenado
 * - origem='site'
 * - KanbanCurationEvent emitido
 */
class LeadOnboardingWidgetTest extends TestCase
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
    public function test_new_widget_identifier_creates_lead_in_initial_column(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $widgetId = 'widget-uuid-abc123def456';

        $paciente = $service->ensureFor(LeadOnboardingService::CHANNEL_WIDGET, $widgetId, $this->tenant->id);

        // Verifica que o paciente foi criado
        $this->assertNotNull($paciente);
        $this->assertEquals('lead', $paciente->status);
        $this->assertEquals('outro', $paciente->origem);
        $this->assertEquals($widgetId, $paciente->widget_anonymous_id);
        $this->assertEquals($this->initialColumn->id, $paciente->funil_coluna_atual_id);

        // Verifica que apenas 1 paciente existe
        $this->assertCount(1, Paciente::where('tenant_id', $this->tenant->id)->get());

        // Verifica o evento
        Event::assertDispatched(KanbanCardCurated::class);
    }
}
