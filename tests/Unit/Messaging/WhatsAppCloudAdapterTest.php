<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Channel\Adapters\InboundMessageDto;
use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;
use Twilio\Rest\Client;

/**
 * T065 — Unit tests para WhatsAppCloudAdapter (implementação Lote D).
 *
 * Testa contrato do adapter contra mocked Twilio SDK.
 *
 * TDD RED: classe WhatsAppCloudAdapter ainda não existe.
 */
class WhatsAppCloudAdapterTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    private WhatsAppCloudAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-adapter', 'admin-clinica');

        $this->channel = Channel::factory()
            ->whatsapp()
            ->forTenant($this->tenant)
            ->create([
                'credentials_encrypted' => encrypt([
                    'account_sid' => 'AC'.str_repeat('a', 32),
                    'auth_token' => 'token-secret-123',
                    'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                    'whatsapp_sender' => 'whatsapp:+5511999999999',
                ]),
            ]);

        // Adapters serão instanciados quando a classe existir
        if (class_exists(WhatsAppCloudAdapter::class)) {
            $this->adapter = app(WhatsAppCloudAdapter::class);
        }
    }

    /** @test */
    public function test_send_text_message_calls_twilio_messages_create_with_correct_payload_and_returns_dispatch_result(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;

        $responseObject = (object) ['sid' => 'SM'.str_repeat('a', 32)];
        $messagesMock->shouldReceive('create')
            ->with('whatsapp:+5511988887777', Mockery::any())
            ->andReturn($responseObject);

        $this->app->instance(Client::class, $twilioMock);

        $outbound = new OutboundMessage(
            conversationExternalThreadId: '+5511988887777',
            contentType: 'text',
            body: 'Sua consulta foi confirmada',
            idempotencyKey: 'idempkey-abc',
        );

        $result = $this->adapter->send($this->channel, $outbound);

        $this->assertTrue($result->accepted);
        $this->assertEquals('SM'.str_repeat('a', 32), $result->externalId);
    }

    /** @test */
    public function test_send_template_message_uses_content_sid_and_variables(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;

        $responseObject = (object) ['sid' => 'SM'.str_repeat('b', 32)];
        $messagesMock->shouldReceive('create')
            ->with('whatsapp:+5511988887777', Mockery::on(function ($args) {
                return isset($args['contentSid']) && isset($args['contentVariables']);
            }))
            ->andReturn($responseObject);

        $this->app->instance(Client::class, $twilioMock);

        $outbound = new OutboundMessage(
            conversationExternalThreadId: '+5511988887777',
            contentType: 'template',
            templateProviderId: 'HXabc123',
            templateVariables: ['1' => 'João', '2' => '14h'],
            idempotencyKey: 'idempkey-template',
        );

        $result = $this->adapter->send($this->channel, $outbound);

        $this->assertTrue($result->accepted);
    }

    /** @test */
    public function test_send_rejects_invalid_content_type(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestIncomplete('WhatsAppCloudAdapter não existe — Lote D. Decisão de design: thrown exception ou result.accepted=false?');
        }

        $outbound = new OutboundMessage(
            conversationExternalThreadId: '+5511988887777',
            contentType: 'audio',
            body: null,
            idempotencyKey: 'idempkey-audio',
        );

        // Adapter deve rejeitar ou lançar exceção
        try {
            $result = $this->adapter->send($this->channel, $outbound);
            $this->assertFalse($result->accepted);
        } catch (\Exception $e) {
            $this->assertStringContainsString('audio', strtolower($e->getMessage()));
        }
    }

    /** @test */
    public function test_validate_credentials_calls_messages_read_with_limit_1(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;

        $messagesMock->shouldReceive('read')->with(['limit' => 1])->andReturn([]);

        $this->app->instance(Client::class, $twilioMock);

        $credentials = decrypt($this->channel->credentials_encrypted);

        $isValid = $this->adapter->validateCredentials($credentials);

        $this->assertTrue($isValid);
    }

    /** @test */
    public function test_parse_inbound_webhook_returns_inbound_message_dto(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $payload = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Test message',
            'NumMedia' => '0',
        ];

        $dto = $this->adapter->parseInboundWebhook($payload);

        $this->assertInstanceOf(InboundMessageDto::class, $dto);
        $this->assertEquals('twilio', $dto->providerType);
        $this->assertEquals('+5511988887777', $dto->externalThreadId);
        $this->assertEquals('SM'.str_repeat('a', 32), $dto->externalMessageId);
        $this->assertEquals('text', $dto->contentType);
        $this->assertEquals('Test message', $dto->body);
    }

    /** @test */
    public function test_parse_inbound_with_media_populates_media_urls(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $payload = [
            'MessageSid' => 'SM'.str_repeat('b', 32),
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => null,
            'NumMedia' => '1',
            'MediaUrl0' => 'https://twilio.example.com/media/SM123/image.jpg',
            'MediaContentType0' => 'image/jpeg',
        ];

        $dto = $this->adapter->parseInboundWebhook($payload);

        $this->assertNotEmpty($dto->mediaUrls);
        $this->assertEquals('image', $dto->contentType);
    }

    /** @test */
    public function test_circuit_breaker_open_throws_circuit_open_exception_without_calling_twilio(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $this->markTestIncomplete('Circuit breaker integration — Lote D implementa');
    }

    /** @test */
    public function test_get_type_returns_whatsapp(): void
    {
        if (! class_exists(WhatsAppCloudAdapter::class)) {
            $this->markTestSkipped('WhatsAppCloudAdapter não existe — Lote D');
        }

        $type = $this->adapter->getType();

        $this->assertEquals('whatsapp', $type);
    }
}
