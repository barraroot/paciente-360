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
 * T088 — Lead onboarding WhatsApp happy path (FR-009 a FR-012).
 *
 * Verifica que:
 * - Número novo dispara criação de Paciente com status='lead'
 * - Paciente entra na coluna is_initial=true
 * - KanbanCurationEvent é emitido com event_kind='lead_created'
 * - Latência < 30s (SC-002)
 */
class LeadOnboardingWhatsAppTest extends TestCase
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
    public function test_new_whatsapp_number_creates_lead_in_initial_column(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $telefone = '+5511999999999';

        $start = microtime(true);
        $paciente = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenant->id);
        $elapsed = microtime(true) - $start;

        // Verifica que o paciente foi criado
        $this->assertNotNull($paciente);
        $this->assertEquals('lead', $paciente->status);
        $this->assertEquals('whatsapp', $paciente->origem);
        $this->assertEquals($this->initialColumn->id, $paciente->funil_coluna_atual_id);

        // Verifica que apenas 1 paciente existe
        $this->assertCount(1, Paciente::where('tenant_id', $this->tenant->id)->get());

        // Verifica o evento
        Event::assertDispatched(KanbanCardCurated::class, function (KanbanCardCurated $event): bool {
            return $event->curationEvent->event_kind === 'lead_created'
                && $event->curationEvent->paciente_id === $event->curationEvent->paciente_id
                && $event->curationEvent->to_coluna_id !== null
                && $event->curationEvent->applied === true;
        });

        // Verifica latência (SC-002: <30s)
        $this->assertLessThan(30, $elapsed);
    }
}
