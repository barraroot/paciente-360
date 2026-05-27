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
 * Feature 017 (US1) — T011 / FR-006 / Princípio I: nem a janela de histórico
 * nem o prompt atual enviados ao provedor contêm PII bruta. Tudo passa pelo
 * PiiScrubber.
 */
final class NoRawPiiInProviderPayloadTest extends TestCase
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
            'resposta' => 'Recebido! 💛',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
    }

    public function test_history_and_prompt_are_pseudonymized(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'meu email é maria.silva@gmail.com'],
            ['role' => 'ai', 'body' => 'Obrigada! Como posso ajudar?'],
        ]);
        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'me liga no 11987654321 por favor',
        ]);

        event(new MensagemRecebida($current, $conversation));

        PersonaAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $payload = (string) $prompt->prompt;
            foreach ($prompt->agent->messages() as $message) {
                $payload .= "\n".$message->content;
            }

            return ! str_contains($payload, 'maria.silva@gmail.com')
                && ! str_contains($payload, '11987654321')
                && str_contains($payload, '<email>');
        });
    }
}
