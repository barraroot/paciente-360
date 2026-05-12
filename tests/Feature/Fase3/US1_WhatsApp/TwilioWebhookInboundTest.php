<?php

namespace Tests\Feature\Fase3\US1_WhatsApp;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T060 — Webhook inbound Twilio para WhatsApp (POST /api/v1/webhooks/twilio/whatsapp).
 *
 * Cobre AC-4.1.3 (validação de signature + persistência) e AC-4.1.4 (idempotência).
 *
 * TDD RED: testes devem falhar pois webhook controller ainda não existe.
 */
class TwilioWebhookInboundTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-webhook', 'admin-clinica');

        // Cria um canal WhatsApp ativo com credenciais
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
     * Twilio usa HMAC SHA1 do URL + form params concatenados.
     */
    private function computeTwilioSignature(string $url, array $params, string $authToken): string
    {
        // Twilio concatena: URL + cada param em ordem (sorted keys)
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        // HMAC SHA1 e encode em Base64
        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }

    /** @test */
    public function test_valid_signature_webhook_creates_message_and_conversation_for_new_thread(): void
    {
        $url = url('/api/v1/webhooks/twilio/whatsapp');
        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];

        $params = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'AccountSid' => $this->channel->provider_metadata['account_sid'] ?? decrypt($this->channel->credentials_encrypted)['account_sid'],
            'MessagingServiceSid' => $this->channel->provider_metadata['messaging_service_sid'],
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Olá, gostaria de agendar consulta',
            'NumMedia' => '0',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params,
            ['X-Twilio-Signature' => $signature]
        );

        $response->assertOk();

        // Verifica que webhook event foi registrado
        $this->assertDatabaseHas('messaging_webhook_events', [
            'provider' => 'twilio',
            'external_id' => 'SM'.str_repeat('a', 32),
            'signature_verified' => true,
        ]);

        // Verifica que conversa foi criada
        $this->assertDatabaseHas('messaging_conversations', [
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => '+5511988887777',
            'patient_id' => null,
        ]);

        // Verifica que mensagem foi persistida
        $this->assertDatabaseHas('messaging_messages', [
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => 'Olá, gostaria de agendar consulta',
        ]);
    }

    /** @test */
    public function test_invalid_signature_returns_403_and_does_not_record(): void
    {
        $params = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Test message',
            'NumMedia' => '0',
        ];

        $response = $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params,
            ['X-Twilio-Signature' => 'invalid-signature-base64=']
        );

        $response->assertForbidden();

        // Verifica que nenhum webhook event foi registrado
        $this->assertDatabaseMissing('messaging_webhook_events', [
            'external_id' => 'SM'.str_repeat('a', 32),
        ]);
    }

    /** @test */
    public function test_duplicate_message_sid_is_deduplicated_no_message_persisted_twice(): void
    {
        $url = url('/api/v1/webhooks/twilio/whatsapp');
        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];
        $messageSid = 'SM'.str_repeat('a', 32);

        $params = [
            'MessageSid' => $messageSid,
            'AccountSid' => $this->channel->provider_metadata['account_sid'] ?? decrypt($this->channel->credentials_encrypted)['account_sid'],
            'MessagingServiceSid' => $this->channel->provider_metadata['messaging_service_sid'],
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Mensagem duplicada',
            'NumMedia' => '0',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        // Primeira requisição
        $response1 = $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params,
            ['X-Twilio-Signature' => $signature]
        );
        $response1->assertOk();

        // Segunda requisição com mesmo MessageSid
        $response2 = $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params,
            ['X-Twilio-Signature' => $signature]
        );
        $response2->assertOk();

        // Verifica deduplicação: apenas 1 webhook event
        $this->assertEquals(1, \DB::table('messaging_webhook_events')
            ->where('external_id', $messageSid)
            ->count());

        // Verifica deduplicação: apenas 1 mensagem persistida
        $this->assertEquals(1, Message::where('external_id', $messageSid)->count());

        // Verifica que evento MensagemRecebida foi disparado apenas 1 vez
        Event::fake([MensagemRecebida::class]);
        $signature = $this->computeTwilioSignature($url, $params, $authToken);
        $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params,
            ['X-Twilio-Signature' => $signature]
        );
        Event::assertDispatched(MensagemRecebida::class);
    }

    /** @test */
    public function test_subsequent_message_from_same_thread_reuses_conversation(): void
    {
        $url = url('/api/v1/webhooks/twilio/whatsapp');
        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];

        // Primeira mensagem
        $params1 = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'AccountSid' => $this->channel->provider_metadata['account_sid'] ?? decrypt($this->channel->credentials_encrypted)['account_sid'],
            'MessagingServiceSid' => $this->channel->provider_metadata['messaging_service_sid'],
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Primeira mensagem',
            'NumMedia' => '0',
        ];

        $signature1 = $this->computeTwilioSignature($url, $params1, $authToken);
        $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params1,
            ['X-Twilio-Signature' => $signature1]
        )->assertOk();

        // Segunda mensagem do mesmo thread
        $params2 = [
            'MessageSid' => 'SM'.str_repeat('b', 32),
            'AccountSid' => $this->channel->provider_metadata['account_sid'] ?? decrypt($this->channel->credentials_encrypted)['account_sid'],
            'MessagingServiceSid' => $this->channel->provider_metadata['messaging_service_sid'],
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Segunda mensagem',
            'NumMedia' => '0',
        ];

        $signature2 = $this->computeTwilioSignature($url, $params2, $authToken);
        $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params2,
            ['X-Twilio-Signature' => $signature2]
        )->assertOk();

        // Verifica que existe apenas 1 conversa
        $this->assertEquals(1, Conversation::where(
            'tenant_id',
            $this->tenant->id
        )->where('external_thread_id', '+5511988887777')->count());

        // Verifica que existem 2 mensagens
        $this->assertEquals(2, Message::where('conversation_id',
            Conversation::where('external_thread_id', '+5511988887777')->first()->id
        )->count());
    }

    /** @test */
    public function test_responds_within_5_seconds_threshold_assertion(): void
    {
        $url = url('/api/v1/webhooks/twilio/whatsapp');
        $authToken = decrypt($this->channel->credentials_encrypted)['auth_token'];

        $params = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'AccountSid' => $this->channel->provider_metadata['account_sid'] ?? decrypt($this->channel->credentials_encrypted)['account_sid'],
            'MessagingServiceSid' => $this->channel->provider_metadata['messaging_service_sid'],
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Performance test',
            'NumMedia' => '0',
        ];

        $signature = $this->computeTwilioSignature($url, $params, $authToken);

        $startTime = microtime(true);
        $response = $this->postJson(
            '/api/v1/webhooks/twilio/whatsapp',
            $params,
            ['X-Twilio-Signature' => $signature]
        );
        $endTime = microtime(true);

        $response->assertOk();

        $elapsedSeconds = $endTime - $startTime;
        $this->assertLessThan(1.0, $elapsedSeconds, 'Webhook deve responder em < 1 segundo (AC-4.1.3)');
    }
}
