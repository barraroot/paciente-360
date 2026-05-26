<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Services\AiPersonaService;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

/**
 * Polish / Princípio III — fluxo completo da IA: inbound → round-robin →
 * contexto (RAG + guardrails) → resposta enviada → log; mais escalonamento por
 * urgência/baixa confiança e reatribuição ao desativar persona.
 */
final class AiEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);
        Embeddings::fake();
    }

    private function activePersona(string $name): AiPersona
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create([
            'ai_model_id' => $model->id,
            'name' => $name,
        ]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        return $persona;
    }

    private function inbound(string $body): array
    {
        // Um único canal whatsapp ativo por tenant (UNIQUE one_active_whatsapp_per_tenant).
        $channel = Channel::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'whatsapp')
            ->where('status', 'ativo')
            ->first()
            ?? Channel::factory()->create([
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
            'body' => $body,
        ]);

        return [$conversation, $message];
    }

    public function test_full_flow_inbound_to_sent_response_with_rag_and_guardrails(): void
    {
        $persona = $this->activePersona('Recepção');
        // Base ativa associada (RAG) — contexto enriquecido.
        $base = AiKnowledgeBase::factory()->forTenant($this->tenant)->create(['is_active' => true]);
        $persona->knowledgeBases()->sync($persona->pivotTenantMap([$base->id]));

        [$conversation, $message] = $this->inbound('Quais os horários de atendimento?');

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Atendemos de segunda a sexta, das 8h às 18h.',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
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
            'ai_persona_id' => $persona->id,
            'status' => 'assigned',
        ]);
    }

    public function test_round_robin_distributes_across_personas(): void
    {
        $p1 = $this->activePersona('P1');
        $p2 = $this->activePersona('P2');

        Ai::fakeAgent(PersonaAgent::class, [
            ['resposta' => 'ok', 'intencao' => 'informacao_geral', 'confidence' => 0.9, 'needs_human' => false],
            ['resposta' => 'ok', 'intencao' => 'informacao_geral', 'confidence' => 0.9, 'needs_human' => false],
        ]);

        [$c1, $m1] = $this->inbound('oi 1');
        event(new MensagemRecebida($m1, $c1));

        [$c2, $m2] = $this->inbound('oi 2');
        event(new MensagemRecebida($m2, $c2));

        $assigned = AiConversationAssignment::query()
            ->whereIn('conversation_id', [$c1->id, $c2->id])
            ->pluck('ai_persona_id')
            ->unique();

        // Round-robin: duas conversas → duas personas distintas.
        $this->assertCount(2, $assigned);
    }

    public function test_urgency_is_escalated_with_high_priority(): void
    {
        $this->activePersona('Recepção');
        [$conversation, $message] = $this->inbound('Estou passando muito mal, socorro!');

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Procure atendimento.',
            'intencao' => 'urgencia',
            'confidence' => 0.97,
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
            'action' => 'escalated_human',
        ]);
        $this->assertDatabaseHas('messaging_conversations', [
            'id' => $conversation->id,
            'priority' => 'alta',
        ]);
    }

    public function test_low_confidence_is_escalated(): void
    {
        $this->activePersona('Recepção');
        [$conversation, $message] = $this->inbound('pergunta ambígua');

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'talvez…',
            'intencao' => 'informacao_geral',
            'confidence' => 0.2,
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
        ]);
    }

    public function test_deactivating_persona_reassigns_active_conversation(): void
    {
        $p1 = $this->activePersona('P1');
        $p2 = $this->activePersona('P2');

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'ok', 'intencao' => 'informacao_geral', 'confidence' => 0.9, 'needs_human' => false,
        ]]);

        [$conversation, $message] = $this->inbound('oi');
        event(new MensagemRecebida($message, $conversation));

        $assigned = AiConversationAssignment::where('conversation_id', $conversation->id)->first();
        $current = AiPersona::find($assigned->ai_persona_id);
        $other = $current->id === $p1->id ? $p2 : $p1;

        app(AiPersonaService::class)->deactivate($current, null);

        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'ai_persona_id' => $other->id,
            'status' => 'assigned',
        ]);
    }
}
