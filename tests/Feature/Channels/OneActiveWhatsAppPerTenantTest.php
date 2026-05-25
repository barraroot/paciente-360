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
 * Gate G4 (US3) — apenas um canal WhatsApp ativo/conectando por tenant; trocar
 * de provedor exige desconectar o atual antes (R7).
 */
class OneActiveWhatsAppPerTenantTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-one-active', 'admin-clinica');

        config()->set('messaging.providers.evolution', [
            'api_url' => 'http://evo.test', 'api_key' => 'k', 'webhook_secret' => 's', 'http_timeout_seconds' => 5,
        ]);
        Http::fake([
            'evo.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'x'], 'hash' => 'T',
                'qrcode' => ['base64' => 'data:image/png;base64,AAAA', 'code' => '2@x'],
            ]),
        ]);
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    public function test_evolution_connect_is_blocked_when_a_whatsapp_is_already_active(): void
    {
        // Canal Twilio já ativo.
        Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'twilio',
            'status' => 'ativo',
        ]);

        $this->postJson($this->url('/inbox/channels/evolution/connect'), ['name' => 'Evolution'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'channel.already_connected');

        // Nenhum canal evolution criado.
        $this->assertSame(0, Channel::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('provider', 'evolution')->count());
    }

    public function test_evolution_connect_succeeds_after_disconnecting_the_active_one(): void
    {
        $active = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'twilio',
            'status' => 'ativo',
        ]);

        // Desconecta o atual.
        $this->deleteJson($this->url("/inbox/channels/{$active->id}"))->assertNoContent();

        // Agora o Evolution conecta.
        $this->postJson($this->url('/inbox/channels/evolution/connect'), ['name' => 'Evolution'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'evolution');
    }
}
