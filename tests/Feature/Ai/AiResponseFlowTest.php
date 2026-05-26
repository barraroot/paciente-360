<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

/**
 * US3 / G6 — inbound dispara o job de IA e a resposta é enviada pelo canal
 * existente como mensagem `sender_type=ai`, com log de execução.
 */
final class AiResponseFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);
    }

    private function setupActivePersona(): AiPersona
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        return $persona;
    }

    private function inboundMessage(): array
    {
        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'status' => 'ativo',
        ]);

        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'last_inbound_message_at' => now(),
            'ai_paused_until' => null,
        ]);

        $message = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Quais são os horários de atendimento?',
        ]);

        return [$conversation, $message];
    }

    public function test_inbound_triggers_ai_response_sent_as_ai_message(): void
    {
        $this->setupActivePersona();
        [$conversation, $message] = $this->inboundMessage();

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Atendemos de segunda a sexta, das 8h às 18h.',
            'intencao' => 'informacao_geral',
            'confidence' => 0.96,
            'needs_human' => false,
        ]]);

        event(new MensagemRecebida($message, $conversation));

        $this->assertDatabaseHas('messaging_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
        ]);

        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $conversation->id,
            'status' => 'success',
            'action' => 'sent',
        ]);

        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'status' => 'assigned',
        ]);
    }

    public function test_no_response_when_channel_has_no_active_persona(): void
    {
        // sem persona ativa no canal
        [$conversation, $message] = $this->inboundMessage();

        Ai::fakeAgent(PersonaAgent::class, [['resposta' => 'x', 'intencao' => 'informacao_geral', 'confidence' => 0.9, 'needs_human' => false]]);

        event(new MensagemRecebida($message, $conversation));

        $this->assertDatabaseMissing('messaging_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'out',
        ]);
        $this->assertDatabaseCount('ai_execution_logs', 0);
    }

    public function test_clinical_intent_is_not_sent_but_escalated(): void
    {
        $this->setupActivePersona();
        [$conversation, $message] = $this->inboundMessage();

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Você está com diabetes, tome metformina.',
            'intencao' => 'diagnostico',
            'confidence' => 0.99,
            'needs_human' => false,
        ]]);

        event(new MensagemRecebida($message, $conversation));

        $this->assertDatabaseMissing('messaging_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
        ]);
        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $conversation->id,
            'status' => 'escalated',
            'action' => 'redirected_scheduling',
        ]);
    }
}
