<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US2) — T019 / FR-017/026: personalização por nome via placeholder
 * `{{primeiro_nome}}`, substituído só na saída. O nome real nunca vai ao
 * provedor; nome desconhecido → placeholder neutralizado (nunca literal).
 */
final class NameInjectionTest extends TestCase
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

    private function fakeReply(string $resposta): void
    {
        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => $resposta,
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
    }

    private function outboundBody(Conversation $conversation): string
    {
        /** @var Message $message */
        $message = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'out')
            ->where('sender_type', 'ai')
            ->latest('id')
            ->firstOrFail();

        return (string) $message->body;
    }

    public function test_known_name_is_injected_only_in_outbound_not_in_provider_payload(): void
    {
        $this->fakeReply('Entendi, {{primeiro_nome}} 💛');

        $patient = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Maria Silva',
        ]);
        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['patient_id' => $patient->id]);

        $message = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Quero agendar',
        ]);

        event(new MensagemRecebida($message, $conversation));

        // Saída ao paciente: nome real injetado.
        $body = $this->outboundBody($conversation);
        $this->assertStringContainsString('Maria', $body);
        $this->assertStringNotContainsString('{{primeiro_nome}}', $body);

        // Provedor: nunca recebeu o nome real.
        PersonaAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $payload = (string) $prompt->agent->instructions().' '.$prompt->prompt;
            foreach ($prompt->agent->messages() as $m) {
                $payload .= ' '.$m->content;
            }

            return ! str_contains($payload, 'Maria');
        });
    }

    public function test_unknown_name_neutralizes_placeholder(): void
    {
        $this->fakeReply('Olá {{primeiro_nome}}, tudo bem?');

        // Conversa sem paciente vinculado (lead novo, nome desconhecido).
        $conversation = AiConversationFactory::conversation($this->tenant);
        $message = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'oi',
        ]);

        event(new MensagemRecebida($message, $conversation));

        $body = $this->outboundBody($conversation);
        $this->assertStringNotContainsString('{{primeiro_nome}}', $body);
        $this->assertStringNotContainsString('{{', $body);
        $this->assertStringContainsString('Olá', $body);
    }
}
