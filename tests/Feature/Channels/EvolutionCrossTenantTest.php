<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Gate G2 (US4, Princípio II) — webhook da instância de um tenant nunca entrega
 * à inbox de outro; a resolução é escopada pela instância.
 */
class EvolutionCrossTenantTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config()->set('messaging.providers.evolution.webhook_secret', 'wh-secret');
    }

    private function evolutionChannel(Tenant $tenant, string $instanceName): Channel
    {
        return Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'status' => 'ativo',
            'provider_metadata' => ['instance_name' => $instanceName],
        ]);
    }

    public function test_inbound_for_tenant_a_instance_never_reaches_tenant_b(): void
    {
        $tenantA = $this->createTenant(['slug' => 'evo-iso-a']);
        $tenantB = $this->createTenant(['slug' => 'evo-iso-b']);
        $channelA = $this->evolutionChannel($tenantA, 'inst_a');
        $channelB = $this->evolutionChannel($tenantB, 'inst_b');

        // Webhook inbound para a instância do tenant A.
        $this->postJson('/api/v1/webhooks/evolution/inst_a', [
            'event' => 'messages.upsert',
            'instance' => 'inst_a',
            'data' => [
                'key' => ['remoteJid' => '5511977776666@s.whatsapp.net', 'fromMe' => false, 'id' => 'WAID-A'],
                'message' => ['conversation' => 'oi do paciente A'],
            ],
        ], ['apikey' => 'wh-secret'])->assertOk();

        // Mensagem criada SOMENTE sob o canal/tenant A.
        $msg = Message::withoutGlobalScopes()->where('external_id', 'WAID-A')->firstOrFail();
        $this->assertSame($tenantA->id, $msg->tenant_id);

        $convA = Conversation::withoutGlobalScopes()->where('channel_id', $channelA->id)->count();
        $convB = Conversation::withoutGlobalScopes()->where('channel_id', $channelB->id)->count();
        $this->assertSame(1, $convA);
        $this->assertSame(0, $convB);

        // Nenhuma mensagem no tenant B.
        $this->assertSame(0, Message::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }
}
