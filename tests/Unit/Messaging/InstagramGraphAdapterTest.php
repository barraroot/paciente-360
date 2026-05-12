<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Channel\Adapters\InboundMessageDto;
use App\Domain\Messaging\Channel\Adapters\InstagramGraphAdapter;
use App\Domain\Messaging\Channel\Adapters\MessageDispatchResult;
use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService;
use App\Models\Tenant;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T187 — Unit tests para InstagramGraphAdapter.
 *
 * Usa GuzzleHttp MockHandler para simular responses da Graph API sem HTTP real.
 *
 * Princípio I — Segurança: token não aparece em logs.
 * Research R3 — Meta Graph API direta (não Twilio).
 * Research R7 — versão pinned via config('messaging.providers.meta.graph_api_version').
 */
class InstagramGraphAdapterTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    private CircuitBreakerService $circuitBreaker;

    private string $igBusinessAccountId = '123456789012345';

    private string $pageAccessToken = 'EAABfake-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-ig-adapter', 'admin-clinica');

        $this->circuitBreaker = app(CircuitBreakerService::class);

        $this->channel = Channel::factory()
            ->instagram()
            ->forTenant($this->tenant)
            ->create([
                'provider_metadata' => [
                    'page_id' => '98765432109876',
                    'ig_business_account_id' => $this->igBusinessAccountId,
                    'ig_username' => 'clinica_adapter_test',
                ],
                'credentials_encrypted' => encrypt([
                    'page_id' => '98765432109876',
                    'page_access_token' => $this->pageAccessToken,
                    'ig_business_account_id' => $this->igBusinessAccountId,
                ]),
            ]);
    }

    /**
     * Cria adapter com respostas Guzzle mockadas.
     *
     * @param array<GuzzleResponse|\Exception> $responses
     */
    private function adapterWithMockedClient(array $responses): InstagramGraphAdapter
    {
        $handler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($handler);
        $client = new GuzzleClient(['handler' => $handlerStack]);

        return new InstagramGraphAdapter($this->circuitBreaker, $client);
    }

    /** @test */
    public function test_get_type_returns_instagram(): void
    {
        $adapter = new InstagramGraphAdapter($this->circuitBreaker);

        $this->assertEquals('instagram', $adapter->getType());
    }

    /** @test */
    public function test_validate_credentials_calls_graph_api_me_endpoint_returns_true_on_business_account(): void
    {
        $adapter = $this->adapterWithMockedClient([
            // GET /me → válido
            new GuzzleResponse(200, [], json_encode(['id' => '98765432109876', 'name' => 'Clínica'])),
            // GET /{ig_business_account_id}?fields=username,account_type → Business
            new GuzzleResponse(200, [], json_encode([
                'id' => $this->igBusinessAccountId,
                'username' => 'clinica_test',
                'account_type' => 'BUSINESS',
            ])),
        ]);

        $result = $adapter->validateCredentials([
            'page_access_token' => $this->pageAccessToken,
            'ig_business_account_id' => $this->igBusinessAccountId,
            'page_id' => '98765432109876',
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_validate_credentials_returns_false_on_personal_account(): void
    {
        $adapter = $this->adapterWithMockedClient([
            new GuzzleResponse(200, [], json_encode(['id' => '98765432109876'])),
            new GuzzleResponse(200, [], json_encode([
                'id' => $this->igBusinessAccountId,
                'username' => 'pessoal_user',
                'account_type' => 'PERSONAL',
            ])),
        ]);

        $result = $adapter->validateCredentials([
            'page_access_token' => $this->pageAccessToken,
            'ig_business_account_id' => $this->igBusinessAccountId,
            'page_id' => '98765432109876',
        ]);

        $this->assertFalse($result);
    }

    /** @test */
    public function test_validate_credentials_returns_false_on_401(): void
    {
        $adapter = $this->adapterWithMockedClient([
            new GuzzleResponse(401, [], json_encode([
                'error' => ['code' => 190, 'message' => 'Invalid OAuth access token'],
            ])),
        ]);

        $result = $adapter->validateCredentials([
            'page_access_token' => 'invalid-token',
            'ig_business_account_id' => $this->igBusinessAccountId,
            'page_id' => '98765432109876',
        ]);

        $this->assertFalse($result);
    }

    /** @test */
    public function test_send_text_message_calls_graph_api_messages_endpoint(): void
    {
        $returnedMessageId = 'aA1bB2cC3dD4eE5';

        $adapter = $this->adapterWithMockedClient([
            // POST /{ig_business_account_id}/messages
            new GuzzleResponse(200, [], json_encode([
                'recipient_id' => 'IGSID-TARGET',
                'message_id' => $returnedMessageId,
            ])),
        ]);

        $outbound = new OutboundMessage(
            conversationExternalThreadId: 'IGSID-TARGET',
            contentType: 'text',
            body: 'Sua consulta está confirmada',
            idempotencyKey: 'key-123',
        );

        $result = $adapter->send($this->channel, $outbound);

        $this->assertInstanceOf(MessageDispatchResult::class, $result);
        $this->assertTrue($result->accepted);
        $this->assertEquals($returnedMessageId, $result->externalId);
    }

    /** @test */
    public function test_send_wraps_call_in_circuit_breaker_for_meta_provider(): void
    {
        // Simula falha que circuitBreaker deve registrar
        $adapter = $this->adapterWithMockedClient([
            new GuzzleResponse(500, [], json_encode(['error' => 'server error'])),
        ]);

        $outbound = new OutboundMessage(
            conversationExternalThreadId: 'IGSID-TARGET',
            contentType: 'text',
            body: 'Teste circuit breaker',
            idempotencyKey: 'key-cb',
        );

        $result = $adapter->send($this->channel, $outbound);

        // 500 retorna accepted=false (circuit breaker conta a falha)
        $this->assertFalse($result->accepted);
    }

    /** @test */
    public function test_parse_inbound_webhook_extracts_igsid_and_message_from_entry_messaging(): void
    {
        $adapter = new InstagramGraphAdapter($this->circuitBreaker);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => $this->igBusinessAccountId,
                    'time' => 1715400000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'IGSID-SENDER-123'],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => 1715400001000,
                            'message' => [
                                'mid' => 'mid-abc123',
                                'text' => 'Olá, gostaria de agendar',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $dto = $adapter->parseInboundWebhook($payload);

        $this->assertInstanceOf(InboundMessageDto::class, $dto);
        $this->assertEquals('meta', $dto->providerType);
        $this->assertEquals('IGSID-SENDER-123', $dto->externalThreadId);
        $this->assertEquals('mid-abc123', $dto->externalMessageId);
        $this->assertEquals('text', $dto->contentType);
        $this->assertEquals('Olá, gostaria de agendar', $dto->body);
    }

    /** @test */
    public function test_parse_inbound_webhook_handles_media_attachments(): void
    {
        $adapter = new InstagramGraphAdapter($this->circuitBreaker);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => $this->igBusinessAccountId,
                    'time' => 1715400000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'IGSID-SENDER-456'],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => 1715400002000,
                            'message' => [
                                'mid' => 'mid-image-789',
                                'attachments' => [
                                    [
                                        'type' => 'image',
                                        'payload' => [
                                            'url' => 'https://graph.instagram.com/v21.0/media/img123',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $dto = $adapter->parseInboundWebhook($payload);

        $this->assertEquals('meta', $dto->providerType);
        $this->assertEquals('IGSID-SENDER-456', $dto->externalThreadId);
        $this->assertEquals('mid-image-789', $dto->externalMessageId);
        $this->assertEquals('image', $dto->contentType);
        $this->assertNotEmpty($dto->mediaUrls);
        $this->assertStringContainsString('graph.instagram.com', $dto->mediaUrls[0]);
    }

    /** @test */
    public function test_parse_inbound_webhook_handles_audio_attachment(): void
    {
        $adapter = new InstagramGraphAdapter($this->circuitBreaker);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => $this->igBusinessAccountId,
                    'time' => 1715400000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'IGSID-AUDIO'],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => 1715400003000,
                            'message' => [
                                'mid' => 'mid-audio-abc',
                                'attachments' => [
                                    [
                                        'type' => 'audio',
                                        'payload' => ['url' => 'https://graph.instagram.com/v21.0/media/audio1'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $dto = $adapter->parseInboundWebhook($payload);

        $this->assertEquals('audio', $dto->contentType);
    }
}
