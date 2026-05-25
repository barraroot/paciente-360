<?php

namespace Tests\Unit\Channels;

use App\Domain\Messaging\Channel\Adapters\EvolutionApiAdapter;
use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Channel\Models\Channel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * T014 — Unit do EvolutionApiAdapter (HTTP fakeado, sem container Evolution).
 */
class EvolutionApiAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('messaging.providers.evolution', [
            'api_url' => 'http://evo.test',
            'api_key' => 'k',
            'webhook_secret' => 's',
            'http_timeout_seconds' => 5,
        ]);
    }

    private function channel(): Channel
    {
        return Channel::factory()->make([
            'id' => 1,
            'tenant_id' => 1,
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'provider_metadata' => ['instance_name' => 'tenant_1_ch_1'],
        ]);
    }

    public function test_create_instance_returns_qr_and_token(): void
    {
        Http::fake([
            'evo.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'tenant_1_ch_1'],
                'hash' => 'TOKEN123',
                'qrcode' => ['base64' => 'data:image/png;base64,AAAA', 'code' => '2@abc'],
            ]),
        ]);

        $conn = app(EvolutionApiAdapter::class)->createInstance($this->channel());

        $this->assertSame('tenant_1_ch_1', $conn->instanceName);
        $this->assertSame('TOKEN123', $conn->instanceToken);
        $this->assertNotNull($conn->qr);
        $this->assertSame('data:image/png;base64,AAAA', $conn->qr->base64);
    }

    public function test_connection_state_is_read_from_provider(): void
    {
        Http::fake(['evo.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']])]);

        $this->assertSame('open', app(EvolutionApiAdapter::class)->connectionState($this->channel()));
    }

    public function test_send_text_posts_correct_payload(): void
    {
        Http::fake(['evo.test/message/sendText/*' => Http::response(['key' => ['id' => 'MSGID']])]);

        $result = app(EvolutionApiAdapter::class)->send(
            $this->channel(),
            new OutboundMessage(conversationExternalThreadId: '5511988887777', contentType: 'text', body: 'olá'),
        );

        $this->assertTrue($result->accepted);
        $this->assertSame('MSGID', $result->externalId);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/message/sendText/tenant_1_ch_1')
            && $req['number'] === '5511988887777'
            && $req['text'] === 'olá');
    }

    public function test_parse_inbound_webhook_normalizes_text_message(): void
    {
        $dto = app(EvolutionApiAdapter::class)->parseInboundWebhook([
            'event' => 'messages.upsert',
            'instance' => 'tenant_1_ch_1',
            'data' => [
                'key' => ['remoteJid' => '5511988887777@s.whatsapp.net', 'fromMe' => false, 'id' => 'WAID'],
                'message' => ['conversation' => 'oi doutor'],
            ],
        ]);

        $this->assertSame('evolution', $dto->providerType);
        $this->assertSame('+5511988887777', $dto->externalThreadId);
        $this->assertSame('WAID', $dto->externalMessageId);
        $this->assertSame('text', $dto->contentType);
        $this->assertSame('oi doutor', $dto->body);
    }

    public function test_parse_inbound_webhook_extracts_image_media(): void
    {
        $dto = app(EvolutionApiAdapter::class)->parseInboundWebhook([
            'data' => [
                'key' => ['remoteJid' => '5511988887777@s.whatsapp.net', 'id' => 'IMG1'],
                'message' => ['imageMessage' => ['url' => 'https://evo/media/x.jpg', 'caption' => 'foto']],
            ],
        ]);

        $this->assertSame('image', $dto->contentType);
        $this->assertSame('foto', $dto->body);
        $this->assertSame(['https://evo/media/x.jpg'], $dto->mediaUrls);
    }
}
