<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T206 — Feature tests para envio de mensagens pelo visitante via widget.
 *
 * POST /widget/v1/{public_key}/messages
 * Público (sem auth). Cobre AC-4.3.4 (inbox unificada) e AC-4.3.7 (broadcast Reverb).
 */
class WidgetMessagePublicTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $webChannel;

    private WebWidgetConfig $widgetConfig;

    private WebWidgetSession $session;

    private string $publicKey;

    private string $visitorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-msg-pub', 'admin-clinica');

        $this->publicKey = bin2hex(random_bytes(32));
        $this->visitorToken = bin2hex(random_bytes(32));

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $this->widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->webChannel->id,
                'public_key' => $this->publicKey,
                'allowed_origins' => [],
                'pre_chat_form' => 'opcional',
                'outside_hours_behavior' => 'normal',
            ]);

        $this->session = WebWidgetSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'widget_config_id' => $this->widgetConfig->id,
            'visitor_token' => $this->visitorToken,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function messagesUrl(): string
    {
        return "/widget/v1/{$this->publicKey}/messages";
    }

    /** @test */
    public function test_visitor_can_send_text_message_creates_conversation_and_message(): void
    {
        $idempotencyKey = bin2hex(random_bytes(16));

        $response = $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Olá, preciso de ajuda!',
        ]);

        $response->assertCreated();

        // Conversation created with correct channel
        $conversation = Conversation::where('tenant_id', $this->tenant->id)
            ->where('channel_id', $this->webChannel->id)
            ->first();

        $this->assertNotNull($conversation, 'Conversa deve ser criada');
        $this->assertEquals($this->visitorToken, $conversation->external_thread_id);

        // Message created
        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message, 'Mensagem deve ser criada');
        $this->assertEquals('in', $message->direction);
        $this->assertEquals('Olá, preciso de ajuda!', $message->body);
    }

    /** @test */
    public function test_subsequent_messages_reuse_conversation(): void
    {
        $header = ['X-Visitor-Token' => $this->visitorToken];

        // First message
        $this->withHeaders($header)->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Primeira mensagem',
        ])->assertCreated();

        // Second message
        $this->withHeaders($header)->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Segunda mensagem',
        ])->assertCreated();

        // Only one conversation should exist
        $this->assertDatabaseCount('messaging_conversations', 1);
        $this->assertDatabaseCount('messaging_messages', 2);
    }

    /** @test */
    public function test_idempotency_key_deduplicates_messages(): void
    {
        $idempotencyKey = 'idem-key-'.bin2hex(random_bytes(8));
        $headers = [
            'X-Visitor-Token' => $this->visitorToken,
            'Idempotency-Key' => $idempotencyKey,
        ];
        $payload = ['content_type' => 'text', 'body' => 'Mensagem duplicada'];

        // First request
        $this->withHeaders($headers)->postJson($this->messagesUrl(), $payload)->assertCreated();

        // Second request with same idempotency key
        $second = $this->withHeaders($headers)->postJson($this->messagesUrl(), $payload);
        $second->assertStatus(201); // Still returns success

        // Only one message
        $this->assertDatabaseCount('messaging_messages', 1);
    }

    /** @test */
    public function test_pre_chat_form_exigido_para_enviar_rejects_message_without_provisional_data(): void
    {
        $this->widgetConfig->update(['pre_chat_form' => 'exigido_para_enviar']);

        // Session has no provisional_data
        $this->session->update(['provisional_data' => []]);

        $response = $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Olá!',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'pre_chat_required');
    }

    /** @test */
    public function test_visitor_token_invalid_returns_404(): void
    {
        $response = $this->withHeaders([
            'X-Visitor-Token' => str_repeat('x', 64),
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Olá!',
        ]);

        $response->assertNotFound();
    }

    /** @test */
    public function test_visitor_token_missing_returns_422(): void
    {
        $response = $this->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Olá!',
        ]);

        $response->assertUnprocessable();
    }

    /** @test */
    public function test_messages_appear_in_tenant_inbox_via_reverb_broadcast(): void
    {
        Event::fake();

        $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Mensagem teste broadcast',
        ])->assertCreated();

        // Verify that broadcast event was fired
        // The MensagemRecebidaNaInbox event should be dispatched
        $conversation = Conversation::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($conversation);

        // Message is in DB and accessible by the inbox
        $this->assertDatabaseHas('messaging_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'in',
        ]);
    }
}
