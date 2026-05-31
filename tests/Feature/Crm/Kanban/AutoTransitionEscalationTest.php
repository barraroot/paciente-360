<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Ai\Assignment\Events\IAEscalouParaHumano;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T117 — Auto-transição on AI escalation to human (FR-018, US3).
 *
 * Verifica que ao disparar IAEscalouParaHumano event, o listener
 * PromoteToHumanOnEscalation move o card para a coluna mapping
 * de 'ai_paused_to_human'.
 */
final class AutoTransitionEscalationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $newColumn;

    private FunilColuna $humanColumn;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar colunas
        $this->newColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Novos',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        $this->humanColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Humano',
            'slug' => 'humano',
            'posicao' => 3,
            'is_initial' => false,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Criar mapping para escalação
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenant->id,
            'event_kind' => 'ai_paused_to_human',
            'funil_coluna_id' => $this->humanColumn->id,
            'is_active' => true,
        ]);

        // Criar channel
        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
    }

    /**
     * @test
     */
    public function test_ia_escalation_event_promotes_to_human(): void
    {
        // Criar lead
        $paciente = Paciente::make([
            'nome' => 'Roberto Lima',
            'status' => 'lead',
            'telefone_primario' => '+5511966666666',
            'funil_coluna_atual_id' => $this->newColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar conversation
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'patient_id' => $paciente->id,
            'external_thread_id' => $paciente->telefone_primario,
        ]);

        // Disparar evento de escalação
        event(new IAEscalouParaHumano(
            tenantId: $this->tenant->id,
            conversationId: $conversation->id,
            aiPersonaId: null,
            reason: 'Clinical question detected',
        ));

        // Refresh
        $paciente->refresh();

        // Verify: paciente deve estar em human column
        $this->assertEquals($this->humanColumn->id, $paciente->funil_coluna_atual_id);

        // Verify: KanbanCurationEvent foi criado
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $paciente->id)
            ->where('event_kind', 'ai_paused_to_human')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);
        $this->assertEquals($this->newColumn->id, $event->from_coluna_id);
        $this->assertEquals($this->humanColumn->id, $event->to_coluna_id);
    }
}
