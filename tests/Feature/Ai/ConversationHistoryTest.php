<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Context\Models\ConversationSummary;
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
 * Feature 017 (US1) — T009: a IA recebe o histórico recente (nunca a janela
 * vazia) e o resumo quando existe; a mensagem atual vai no prompt, não no
 * histórico.
 */
final class ConversationHistoryTest extends TestCase
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
    }

    private function fakeAgent(): void
    {
        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Claro! A consulta custa R$300 à vista. 💛',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
    }

    public function test_prior_turns_are_sent_to_the_model_as_history(): void
    {
        $this->fakeAgent();
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'Enxaqueca'],
            ['role' => 'ai', 'body' => 'Com que frequência acontecem as crises?'],
            ['role' => 'patient', 'body' => 'Quase todo dia'],
            ['role' => 'ai', 'body' => 'Entendi. Isso atrapalha seu dia a dia?'],
        ]);
        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Qual o valor?',
        ]);

        event(new MensagemRecebida($current, $conversation));

        PersonaAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $messages = iterator_to_array($prompt->agent->messages());
            $contents = array_map(fn ($m) => $m->content, $messages);

            return count($messages) === 4
                && in_array('Enxaqueca', $contents, true)
                && in_array('Quase todo dia', $contents, true)
                && $prompt->prompt === 'Qual o valor?'
                && ! in_array('Qual o valor?', $contents, true); // mensagem atual não duplica no histórico
        });

        $this->assertDatabaseHas('messaging_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
        ]);
    }

    public function test_rolling_summary_is_included_when_present(): void
    {
        $this->fakeAgent();
        $conversation = AiConversationFactory::conversation($this->tenant);
        ConversationSummary::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'summary_text' => 'Paciente com enxaqueca quase diária; interessado na consulta.',
            'version' => 2,
        ]);
        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Quero agendar',
        ]);

        event(new MensagemRecebida($current, $conversation));

        PersonaAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'enxaqueca quase diária'));

        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $conversation->id,
            'summary_version' => 2,
        ]);
    }
}
