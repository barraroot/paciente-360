<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;
use Twilio\Rest\Client;

/**
 * Gate G8 (US1) — CRUD de canal por provider + isolamento por tenant + provider
 * no resource. Inclui a asserção de autorização (M1 do /speckit-analyze).
 */
class ChannelProviderConfigCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-ch-crud', 'admin-clinica');
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function mockTwilioOk(): void
    {
        $twilio = Mockery::mock(Client::class);
        $messages = Mockery::mock();
        $twilio->messages = $messages;
        $messages->shouldReceive('read')->andReturn([]);
        $this->app->instance(Client::class, $twilio);
    }

    public function test_create_twilio_channel_defaults_provider_and_exposes_it(): void
    {
        $this->mockTwilioOk();

        $response = $this->postJson($this->url('/inbox/channels'), [
            'type' => 'whatsapp',
            'name' => 'WhatsApp Oficial',
            'credentials' => [
                'account_sid' => 'AC'.str_repeat('a', 32),
                'auth_token' => 'tok-123',
                'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                'whatsapp_sender' => 'whatsapp:+5511999999999',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.provider', 'twilio')
            ->assertJsonPath('data.status', 'ativo');

        $this->assertDatabaseHas('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'twilio',
        ]);
    }

    public function test_create_evolution_channel_without_credentials(): void
    {
        $response = $this->postJson($this->url('/inbox/channels'), [
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'name' => 'WhatsApp Não Oficial',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.provider', 'evolution')
            ->assertJsonPath('data.status', 'conectando');

        $this->assertDatabaseHas('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'status' => 'conectando',
        ]);
    }

    public function test_user_without_channel_connect_ability_gets_403(): void
    {
        $doctor = $this->createUserForTenant($this->tenant);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->tenant->id);
        $registrar->forgetCachedPermissions();
        $doctor->assignRole('medico');
        Sanctum::actingAs($doctor, ['*']);

        $this->postJson($this->url('/inbox/channels'), [
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'name' => 'X',
        ])->assertForbidden();
    }

    public function test_index_isolates_channels_by_tenant(): void
    {
        $other = $this->createTenant(['slug' => 'clinica-ch-other']);
        Channel::factory()->create([
            'tenant_id' => $other->id,
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'name' => 'Canal do Outro Tenant',
        ]);

        $response = $this->getJson($this->url('/inbox/channels'));
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotContains('Canal do Outro Tenant', $names);
    }
}
