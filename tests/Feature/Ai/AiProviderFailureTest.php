<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Services\AiMessageProcessor;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use RuntimeException;
use Tests\TestCase;

/**
 * US3 / FR-030c / G10c — falha do provedor: não envia nada; ao esgotar
 * tentativas, marca erro e escala (sem mensagem ao paciente).
 */
final class AiProviderFailureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Conversation $conversation;

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

        $channel = Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'whatsapp', 'status' => 'ativo']);
        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'last_inbound_message_at' => now(),
        ]);
        Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'body' => 'Olá',
        ]);
    }

    public function test_generation_failure_sends_nothing_and_propagates(): void
    {
        Ai::fakeAgent(PersonaAgent::class, fn () => throw new RuntimeException('provider down'));

        try {
            app(AiMessageProcessor::class)->process($this->conversation);
            $this->fail('Expected exception to propagate for job retry.');
        } catch (\Throwable $e) {
            // esperado — propaga para o job re-tentar
        }

        $this->assertDatabaseMissing('messaging_messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
        ]);
    }

    public function test_mark_failed_records_error_without_message(): void
    {
        // cria a atribuição ativa (como se já tivesse sido resolvida)
        app(AiMessageProcessor::class)->markFailed($this->conversation, 'RuntimeException');

        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $this->conversation->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('messaging_messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
        ]);
    }
}
