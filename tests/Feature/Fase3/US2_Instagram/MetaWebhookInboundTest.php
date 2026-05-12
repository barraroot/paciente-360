<?php

namespace Tests\Feature\Fase3\US2_Instagram;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T186 — Feature tests para POST /api/v1/webhooks/instagram (inbound Meta Graph API).
 *
 * Cobre AC-4.2.3 (webhook entrega DM em tempo real com assinatura válida) e
 * AC-4.2.4 (idempotência por message.mid).
 *
 * Princípio I — validação HMAC obrigatória (app_secret global da Meta App).
 * Princípio II — endpoint público, canal resolvido via ig_business_account_id.
 * Princípio VII — dedup por external_id (mid).
 */
class MetaWebhookInboundTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    private string $appSecret = 'test-meta-app-secret';

    private string $igBusinessAccountId = '999888777666555';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-ig-webhook', 'admin-clinica');

        config(['messaging.providers.meta.app_secret' => $this->appSecret]);

        // Canal Instagram ativo com ig_business_account_id no provider_metadata
        $this->channel = Channel::factory()
            ->instagram()
            ->forTenant($this->tenant)
            ->create([
                'status' => 'ativo',
                'provider_metadata' => [
                    'page_id' => '111222333444555',
                    'ig_business_account_id' => $this->igBusinessAccountId,
                    'ig_username' => 'clinica_test',
                ],
                'credentials_encrypted' => encrypt([
                    'page_id' => '111222333444555',
                    'page_access_token' => 'EAABfake-page-token',
                    'ig_business_account_id' => $this->igBusinessAccountId,
                ]),
            ]);
    }

    /**
     * Calcula a assinatura HMAC SHA256 que a Meta enviaria no header.
     *
     * Meta assina o RAW body (não o JSON parseado) com app_secret.
     */
    private function computeMetaSignature(string $rawBody): string
    {
        return 'sha256='.hash_hmac('sha256', $rawBody, $this->appSecret);
    }

    /**
     * Payload padrão de DM Instagram (entry.messaging[]).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildInstagramPayload(
        string $senderIgsid = 'IGSID-USER-X',
        string $mid = 'msg-1',
        string $text = 'oi',
        array $overrides = [],
    ): array {
        return array_merge([
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => $this->igBusinessAccountId,
                    'time' => time(),
                    'messaging' => [
                        [
                            'sender' => ['id' => $senderIgsid],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => $mid,
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    /**
     * Envia webhook POST com assinatura válida.
     *
     * @param array<string, mixed> $payload
     * @return TestResponse
     */
    private function postWebhook(array $payload, ?string $signatureOverride = null)
    {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $signatureOverride ?? $this->computeMetaSignature($rawBody);

        return $this->call(
            'POST',
            '/api/v1/webhooks/instagram',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $rawBody
        );
    }

    /** @test */
    public function test_valid_signature_webhook_creates_message_and_conversation_for_new_igsid(): void
    {
        $payload = $this->buildInstagramPayload(
            senderIgsid: 'IGSID-USER-X',
            mid: 'msg-1',
            text: 'oi'
        );

        $response = $this->postWebhook($payload);

        $response->assertOk();

        // Verifica webhook_event registrado com deduplicação por mid
        $this->assertDatabaseHas('messaging_webhook_events', [
            'provider' => 'meta',
            'external_id' => 'msg-1',
            'signature_verified' => true,
        ]);

        // Conversa criada com external_thread_id = IGSID do remetente
        $this->assertDatabaseHas('messaging_conversations', [
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => 'IGSID-USER-X',
        ]);

        // Mensagem persistida com direção inbound
        $this->assertDatabaseHas('messaging_messages', [
            'direction' => 'in',
            'sender_type' => 'patient',
            'body_searchable' => 'oi',
        ]);

        // Busca e verifica decrypt do body (Princípio I LGPD — body cifrado em repouso)
        $message = Message::withoutGlobalScopes()
            ->where('external_id', 'msg-1')
            ->first();

        $this->assertNotNull($message);
        $this->assertEquals('oi', $message->body);
    }

    /** @test */
    public function test_invalid_signature_returns_403_and_does_not_record(): void
    {
        $payload = $this->buildInstagramPayload(
            senderIgsid: 'IGSID-USER-Y',
            mid: 'msg-invalid-sig'
        );

        $response = $this->postWebhook($payload, 'sha256=invalid-hmac-signature');

        $response->assertForbidden();

        // Nenhum webhook_event deve ser registrado
        $this->assertDatabaseMissing('messaging_webhook_events', [
            'external_id' => 'msg-invalid-sig',
        ]);
    }

    /** @test */
    public function test_duplicate_message_mid_is_deduplicated(): void
    {
        // AC-4.2.4 — Idempotência por message.mid
        $payload = $this->buildInstagramPayload(
            senderIgsid: 'IGSID-USER-Z',
            mid: 'msg-dup-1',
            text: 'mensagem duplicada'
        );

        // Primeira entrega
        $response1 = $this->postWebhook($payload);
        $response1->assertOk();

        // Segunda entrega (retry do Meta)
        $response2 = $this->postWebhook($payload);
        $response2->assertOk();

        // Apenas 1 webhook_event registrado
        $this->assertEquals(
            1,
            \DB::table('messaging_webhook_events')
                ->where('provider', 'meta')
                ->where('external_id', 'msg-dup-1')
                ->count()
        );

        // Apenas 1 mensagem persistida
        $this->assertEquals(
            1,
            Message::withoutGlobalScopes()
                ->where('external_id', 'msg-dup-1')
                ->count()
        );
    }

    /** @test */
    public function test_subsequent_message_from_same_igsid_reuses_conversation(): void
    {
        // FR-010 — Stream contínuo: mensagens do mesmo IGSID → mesma conversa
        $senderIgsid = 'IGSID-USER-CONTINUOUS';

        $payload1 = $this->buildInstagramPayload($senderIgsid, 'msg-first', 'primeira mensagem');
        $this->postWebhook($payload1)->assertOk();

        $payload2 = $this->buildInstagramPayload($senderIgsid, 'msg-second', 'segunda mensagem');
        $this->postWebhook($payload2)->assertOk();

        // Apenas 1 conversa para o mesmo IGSID
        $conversationCount = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('external_thread_id', $senderIgsid)
            ->count();

        $this->assertEquals(1, $conversationCount);

        // 2 mensagens na conversa
        $conversation = Conversation::withoutGlobalScopes()
            ->where('external_thread_id', $senderIgsid)
            ->first();

        $this->assertNotNull($conversation);
        $this->assertEquals(2, Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->count());
    }

    /** @test */
    public function test_handles_message_with_attachment_image(): void
    {
        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => $this->igBusinessAccountId,
                    'time' => time(),
                    'messaging' => [
                        [
                            'sender' => ['id' => 'IGSID-IMAGE-SENDER'],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'msg-with-image',
                                'attachments' => [
                                    [
                                        'type' => 'image',
                                        'payload' => [
                                            'url' => 'https://graph.instagram.com/v21.0/media/123',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postWebhook($payload);

        $response->assertOk();

        // Verifica que a mensagem foi criada com content_type=image
        $this->assertDatabaseHas('messaging_messages', [
            'external_id' => 'msg-with-image',
            'direction' => 'in',
            'content_type' => 'image',
        ]);
    }

    /** @test */
    public function test_responds_within_5_seconds_threshold(): void
    {
        $payload = $this->buildInstagramPayload('IGSID-PERF-TEST', 'msg-perf', 'performance test');

        $start = microtime(true);
        $response = $this->postWebhook($payload);
        $elapsed = microtime(true) - $start;

        $response->assertOk();

        // Webhook deve responder em menos de 1 segundo (assíncrono via job)
        $this->assertLessThan(1.0, $elapsed, 'Webhook deve responder em < 1 segundo (AC-4.2.3)');
    }

    /** @test */
    public function test_multiple_entries_and_messaging_dispatches_one_job_per_event(): void
    {
        // Meta pode entregar múltiplos entry[] com múltiplos messaging[]
        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => $this->igBusinessAccountId,
                    'time' => time(),
                    'messaging' => [
                        [
                            'sender' => ['id' => 'IGSID-MULTI-1'],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => time() * 1000,
                            'message' => ['mid' => 'msg-multi-1', 'text' => 'msg 1'],
                        ],
                        [
                            'sender' => ['id' => 'IGSID-MULTI-2'],
                            'recipient' => ['id' => $this->igBusinessAccountId],
                            'timestamp' => time() * 1000,
                            'message' => ['mid' => 'msg-multi-2', 'text' => 'msg 2'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postWebhook($payload);

        $response->assertOk();

        // Dois webhook_events registrados
        $this->assertEquals(
            1,
            \DB::table('messaging_webhook_events')
                ->where('external_id', 'msg-multi-1')
                ->count()
        );

        $this->assertEquals(
            1,
            \DB::table('messaging_webhook_events')
                ->where('external_id', 'msg-multi-2')
                ->count()
        );
    }
}
