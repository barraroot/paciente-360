<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Jobs\Messaging\SendOutboundMessageJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;
use Twilio\Rest\Client;

/**
 * Gate G1 (US4) — o envio é roteado pelo adapter correto conforme o provider:
 * evolution → EvolutionApiAdapter; twilio → WhatsAppCloudAdapter.
 */
class ChannelProviderRoutingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-routing', 'admin-clinica');
        config()->set('messaging.providers.evolution', [
            'api_url' => 'http://evo.test', 'api_key' => 'k', 'webhook_secret' => 's', 'http_timeout_seconds' => 5,
        ]);
    }

    private function makeMessage(Channel $channel): Message
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'external_thread_id' => '5511999999999',
            'status' => 'aberta',
            'opened_at' => now(),
            'priority' => 'normal',
            'received_outside_hours' => false,
            'unread_count' => 0,
        ]);

        return Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'user',
            'body' => 'olá',
            'content_type' => 'text',
            'status' => 'queued',
            'external_metadata' => [],
        ]);
    }

    public function test_evolution_channel_routes_to_evolution_adapter(): void
    {
        Http::fake(['evo.test/message/sendText/*' => Http::response(['key' => ['id' => 'EVOID']])]);

        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id, 'type' => 'whatsapp', 'provider' => 'evolution',
            'status' => 'ativo', 'provider_metadata' => ['instance_name' => 'tenant_routing'],
        ]);
        $message = $this->makeMessage($channel);

        SendOutboundMessageJob::dispatchSync($message->id);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'evo.test/message/sendText'));
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('EVOID', $message->fresh()->external_id);
    }

    public function test_twilio_channel_routes_to_whatsapp_cloud_adapter(): void
    {
        // Mock do Twilio Client — prova que NÃO foi pro Evolution.
        $twilio = Mockery::mock(Client::class);
        $messages = Mockery::mock();
        $twilio->messages = $messages;
        $sentMessage = Mockery::mock();
        $sentMessage->sid = 'TWILIOSID';
        $messages->shouldReceive('create')->andReturn($sentMessage);
        $this->app->instance(Client::class, $twilio);

        Http::fake(); // qualquer chamada HTTP a evo seria registrada

        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id, 'type' => 'whatsapp', 'provider' => 'twilio',
            'status' => 'ativo',
            'provider_metadata' => ['whatsapp_sender' => 'whatsapp:+5511888888888', 'messaging_service_sid' => 'MG'.str_repeat('a', 32)],
        ]);
        $message = $this->makeMessage($channel);

        SendOutboundMessageJob::dispatchSync($message->id);

        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('TWILIOSID', $message->fresh()->external_id);
        Http::assertNothingSent(); // não tocou no Evolution
    }
}
