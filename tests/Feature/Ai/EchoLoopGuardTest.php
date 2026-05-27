<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US1) — T010 / FR-005: quando o paciente cola a mensagem anterior
 * da própria IA, o sistema avisa o modelo para avançar (não entra em loop).
 */
final class EchoLoopGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);

        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Perfeito, vamos seguir. ✨',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
    }

    public function test_echo_of_last_ai_message_adds_advance_hint(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'Oi'],
            ['role' => 'ai', 'body' => 'Faz sentido para você uma avaliação mais cuidadosa?'],
        ]);
        // Paciente cola exatamente a mensagem da IA (caso real da conversa1.txt).
        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Faz sentido para você uma avaliação mais cuidadosa?',
        ]);

        event(new MensagemRecebida($current, $conversation));

        PersonaAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'reenviou/colou'));
    }

    public function test_normal_message_has_no_echo_hint(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'ai', 'body' => 'Qual a sua queixa principal?'],
        ]);
        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Enxaqueca forte',
        ]);

        event(new MensagemRecebida($current, $conversation));

        PersonaAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains((string) $prompt->agent->instructions(), 'reenviou/colou'));
    }
}
