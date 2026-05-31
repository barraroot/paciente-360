<?php

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * T096 — Lead onboarding via listener (FR-009, integração).
 *
 * Verifica que:
 * - MensagemRecebida event dispara o listener
 * - Listener chama LeadOnboardingService
 * - Paciente é criado e vinculado à Conversation
 * - KanbanCurationEvent é emitido
 * - Latência < 30s (SC-002)
 */
class LeadOnboardingViaListenerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $initialColumn;

    private Channel $channel;

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

        // Criar channel
        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
    }

    /**
     * @test
     */
    public function test_inbound_message_triggers_lead_onboarding_via_listener(): void
    {
        Event::fake([KanbanCardCurated::class]);

        // Criar conversation
        $externalThreadId = '+5511999999999';
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => $externalThreadId,
            'patient_id' => null, // Sem paciente vinculado ainda
        ]);

        // Criar message inbound
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => 'Olá, gostaria de saber sobre consultas',
            'sandbox' => false,
        ]);

        // Medir latência
        $start = microtime(true);

        // Disparar o evento
        event(new MensagemRecebida($message, $conversation));

        $elapsed = microtime(true) - $start;

        // Verifica que o Paciente foi criado
        $paciente = Paciente::where('tenant_id', $this->tenant->id)
            ->where('telefone_primario', $externalThreadId)
            ->first();
        $this->assertNotNull($paciente);
        $this->assertEquals('lead', $paciente->status);

        // Verifica que está na coluna inicial
        $this->assertEquals($this->initialColumn->id, $paciente->funil_coluna_atual_id);

        // Verifica que o KanbanCurationEvent foi emitido
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('event_kind', 'lead_created')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);

        // Verifica que o evento foi disparado
        Event::assertDispatched(KanbanCardCurated::class);

        // Verifica latência (SC-002: <30s)
        $this->assertLessThan(30, $elapsed);
    }
}
