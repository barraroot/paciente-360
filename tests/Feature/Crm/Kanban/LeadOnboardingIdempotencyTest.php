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
 * T091 — Lead onboarding idempotência (FR-012).
 *
 * Verifica que:
 * - 5 chamadas sequenciais para o mesmo identificador geram apenas 1 Paciente
 * - Apenas 1 KanbanCurationEvent('lead_created') é emitido
 * - Resto das chamadas são no-op
 */
class LeadOnboardingIdempotencyTest extends TestCase
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
    public function test_multiple_calls_same_identifier_idempotent(): void
    {
        Event::fake([KanbanCardCurated::class]);

        $service = app(LeadOnboardingService::class);
        $telefone = '+5511999999999';

        // Chamar 5 vezes sequencialmente
        for ($i = 0; $i < 5; $i++) {
            $paciente = $service->ensureFor(LeadOnboardingService::CHANNEL_WHATSAPP, $telefone, $this->tenant->id);
            $this->assertNotNull($paciente);
        }

        // Verifica que apenas 1 Paciente existe
        $pacientes = Paciente::where('tenant_id', $this->tenant->id)->get();
        $this->assertCount(1, $pacientes);

        // Verifica que apenas 1 KanbanCurationEvent('lead_created') foi emitido
        $events = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('event_kind', 'lead_created')
            ->get();
        $this->assertCount(1, $events);

        // Verifica que o evento foi disparado (Event::fake captura via listener)
        Event::assertDispatched(KanbanCardCurated::class, 1);
    }
}
