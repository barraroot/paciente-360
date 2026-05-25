<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Gate G3 (US2) — ciclo de vida da conexão Evolution: connect cria instância +
 * QR; connection.update reflete estado; regenerar QR.
 */
class EvolutionConnectionLifecycleTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-evo-life', 'admin-clinica');

        config()->set('messaging.providers.evolution', [
            'api_url' => 'http://evo.test',
            'api_key' => 'k',
            'webhook_secret' => 'wh-secret',
            'http_timeout_seconds' => 5,
        ]);
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    public function test_connect_creates_instance_and_returns_qr(): void
    {
        Http::fake([
            'evo.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'will-be-overridden'],
                'hash' => 'TOKEN',
                'qrcode' => ['base64' => 'data:image/png;base64,AAAA', 'code' => '2@xyz'],
            ]),
        ]);

        $response = $this->postJson($this->url('/inbox/channels/evolution/connect'), ['name' => 'WPP Não Oficial']);

        $response->assertCreated()
            ->assertJsonPath('data.provider', 'evolution')
            ->assertJsonPath('data.status', 'conectando')
            ->assertJsonPath('qr.base64', 'data:image/png;base64,AAAA');

        $channel = Channel::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('conectando', $channel->status);
        $this->assertArrayHasKey('instance_name', $channel->provider_metadata);
    }

    public function test_connection_update_webhook_transitions_status(): void
    {
        Http::fake([
            'evo.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'x'], 'hash' => 'T',
                'qrcode' => ['base64' => 'data:image/png;base64,AAAA', 'code' => '2@x'],
            ]),
        ]);
        $this->postJson($this->url('/inbox/channels/evolution/connect'), ['name' => 'WPP'])->assertCreated();
        $channel = Channel::where('tenant_id', $this->tenant->id)->firstOrFail();
        $instanceName = $channel->provider_metadata['instance_name'];

        // open → ativo
        $this->postJson('/api/v1/webhooks/evolution/'.$instanceName, [
            'event' => 'connection.update',
            'instance' => $instanceName,
            'data' => ['state' => 'open'],
        ], ['apikey' => 'wh-secret'])->assertOk();
        $this->assertSame('ativo', $channel->fresh()->status);

        // close → desconectado
        $this->postJson('/api/v1/webhooks/evolution/'.$instanceName, [
            'event' => 'connection.update',
            'instance' => $instanceName,
            'data' => ['state' => 'close'],
        ], ['apikey' => 'wh-secret'])->assertOk();
        $this->assertSame('desconectado', $channel->fresh()->status);
    }

    public function test_regenerate_qr(): void
    {
        Http::fake([
            'evo.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'x'], 'hash' => 'T',
                'qrcode' => ['base64' => 'data:image/png;base64,AAAA', 'code' => '2@x'],
            ]),
            'evo.test/instance/connect/*' => Http::response(['base64' => 'data:image/png;base64,BBBB', 'code' => '2@new']),
        ]);
        $this->postJson($this->url('/inbox/channels/evolution/connect'), ['name' => 'WPP'])->assertCreated();
        $channel = Channel::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->postJson($this->url("/inbox/channels/{$channel->id}/qr"))
            ->assertOk()
            ->assertJsonPath('qr.base64', 'data:image/png;base64,BBBB');
    }
}
