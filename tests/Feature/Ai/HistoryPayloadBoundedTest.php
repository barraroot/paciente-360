<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Context\Agents\ConversationSummarizerAgent;
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
 * Feature 017 (US3, SC-010) — numa conversa longa, o payload de histórico fica
 * limitado: janela verbatim curta + resumo, independentemente do total.
 */
final class HistoryPayloadBoundedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);

        config(['ai.matricial.history.window_messages' => 6]);

        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Claro! 💛',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
        Ai::fakeAgent(ConversationSummarizerAgent::class, [[
            'summary' => 'Histórico antigo condensado: paciente busca avaliação.',
            'funnel_stage' => 'qualifying',
        ]]);
    }

    public function test_long_conversation_sends_bounded_history_plus_summary(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);

        $turns = [];
        for ($i = 1; $i <= 40; $i++) {
            $turns[] = ['role' => $i % 2 === 1 ? 'patient' : 'ai', 'body' => "histórico mensagem número {$i}"];
        }
        AiConversationFactory::seedTurns($this->tenant, $conversation, $turns);

        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Qual o valor?',
        ]);

        event(new MensagemRecebida($current, $conversation));

        // Janela limitada (≤ window) mesmo com 40 mensagens + resumo presente.
        PersonaAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $messages = iterator_to_array($prompt->agent->messages());

            return count($messages) <= 6
                && str_contains((string) $prompt->agent->instructions(), 'Resumo da conversa até aqui');
        });

        $this->assertDatabaseHas('ai_conversation_summaries', [
            'conversation_id' => $conversation->id,
        ]);
    }
}
