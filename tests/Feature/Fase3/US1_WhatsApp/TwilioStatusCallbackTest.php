<?php

namespace Tests\Feature\Fase3\US1_WhatsApp;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T061 — Status callbacks Twilio para `/api/v1/webhooks/twilio/status`.
 *
 * Testa atualização de status de mensagens (delivered, read, failed).
 *
 * TDD RED: webhook controller para status ainda não existe.
 */
class TwilioStatusCallbackTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-status', 'admin-clinica');

        $this->channel = Channel::factory()
            ->whatsapp()
            ->forTenant($this->tenant)
            ->create([
                'status' => 'ativo',
                'credentials_encrypted' => encrypt([
                    'account_sid' => 'AC'.str_repeat('a', 32),
                    'auth_token' => 'webhook-token-secret',
                    'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                    'whatsapp_sender' => 'whatsapp:+5511999999999',
                ]),
            ]);
    }

    /**
     * Calcula assinatura HMAC válida para payload Twilio.
     */
    private function computeTwilioSignature(string $url, array $params, string $authToken): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }

    /** @test */
    public function test_delivered_callback_updates_message_status_to_delivered_with_timestamp(): void
    {
        // Cria uma conversa e mensagem outbound
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->create(['channel_id' => $this->channel->id]);

        $message = Message::factory()
            ->outbound()
            ->forTenant($this->tenant)
            ->create([
                'conversation_id' => $conversation->id,
                'external_id' => 'SM'.str_repeat('a', 32),
                'status' => 'sent',
            ]);

        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];
        $url = url('/api/v1/webhooks/twilio/status');

        $params = [
            'MessageSid' => $message->external_id,
            'MessageStatus' => 'delivered',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/status',
            $params,
            ['X-Twilio-Signature' => $signature]
        );

        $response->assertOk();

        // Verifica atualização
        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    /** @test */
    public function test_read_callback_updates_message_status_to_read(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->create(['channel_id' => $this->channel->id]);

        $message = Message::factory()
            ->outbound()
            ->forTenant($this->tenant)
            ->create([
                'conversation_id' => $conversation->id,
                'external_id' => 'SM'.str_repeat('b', 32),
                'status' => 'delivered',
                'delivered_at' => now()->subHour(),
            ]);

        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];
        $url = url('/api/v1/webhooks/twilio/status');

        $params = [
            'MessageSid' => $message->external_id,
            'MessageStatus' => 'read',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/status',
            $params,
            ['X-Twilio-Signature' => $signature]
        );

        $response->assertOk();

        $message->refresh();
        $this->assertEquals('read', $message->status);
        $this->assertNotNull($message->read_at);
    }

    /** @test */
    public function test_failed_callback_persists_error_code_and_message(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->create(['channel_id' => $this->channel->id]);

        $message = Message::factory()
            ->outbound()
            ->forTenant($this->tenant)
            ->create([
                'conversation_id' => $conversation->id,
                'external_id' => 'SM'.str_repeat('c', 32),
                'status' => 'sent',
            ]);

        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];
        $url = url('/api/v1/webhooks/twilio/status');

        $params = [
            'MessageSid' => $message->external_id,
            'MessageStatus' => 'failed',
            'ErrorCode' => '63016',
            'ErrorMessage' => 'Failed to send freeform message to number',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/status',
            $params,
            ['X-Twilio-Signature' => $signature]
        );

        $response->assertOk();

        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertStringContainsString('Failed to send freeform message', $message->failure_reason);
    }

    /** @test */
    public function test_unknown_message_sid_returns_200_silently(): void
    {
        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];
        $url = url('/api/v1/webhooks/twilio/status');

        $params = [
            'MessageSid' => 'SM'.str_repeat('unknown', 4),
            'MessageStatus' => 'delivered',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/status',
            $params,
            ['X-Twilio-Signature' => $signature]
        );

        // Deve retornar 200 mesmo que MessageSid não exista (idempotência Twilio)
        $response->assertOk();
    }

    /** @test */
    public function test_invalid_signature_returns_403(): void
    {
        $params = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'MessageStatus' => 'delivered',
        ];

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/status',
            $params,
            ['X-Twilio-Signature' => 'invalid-signature=']
        );

        $response->assertForbidden();
    }
}
