<?php

namespace Tests\Feature\Fase3\US1_WhatsApp;

use App\Domain\Messaging\Channel\Events\CanalConectado;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;
use Twilio\Rest\Client;

/**
 * T059 — Feature tests para endpoint POST /api/v1/inbox/channels (WhatsApp via Twilio).
 *
 * Cobre AC-4.1.1 (fluxo de conexão), AC-4.1.2 (validação de credenciais),
 * AC-4.1.6 (desconexão limpa), AC-4.1.9 (auditoria).
 *
 * TDD RED: testes devem falhar inicialmente pois controllers/routes/services
 * ainda não existem (implementação será Lote D).
 */
class ConnectWhatsAppEndpointTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-whatsapp', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_admin_clinica_can_connect_whatsapp_with_valid_twilio_credentials(): void
    {
        // Mock Twilio Client para validação de credenciais
        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;
        $messagesMock->shouldReceive('read')->with(['limit' => 1])->andReturn([]);
        $this->app->instance(Client::class, $twilioMock);

        $payload = [
            'type' => 'whatsapp',
            'name' => 'Agendamento WhatsApp',
            'credentials' => [
                'account_sid' => 'AC'.str_repeat('a', 32),
                'auth_token' => 'token-secreto-123',
                'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                'whatsapp_sender' => 'whatsapp:+5511999999999',
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertCreated();

        // Verifica persistência do canal
        $this->assertDatabaseHas('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'name' => 'Agendamento WhatsApp',
            'status' => 'ativo',
        ]);

        $channel = Channel::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($channel->credentials_encrypted, 'credentials devem estar criptografados');

        // Verifica auditoria (AC-4.1.9)
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'channel.connected',
            'executor_id' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function test_user_without_channel_connect_ability_gets_403(): void
    {
        // Cria um médico sem ability channel.connect
        $doctor = $this->createUserForTenant($this->tenant);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->tenant->id);
        $registrar->forgetCachedPermissions();
        $doctor->assignRole('medico');

        $this->actingAs($doctor);

        $payload = [
            'type' => 'whatsapp',
            'name' => 'Agendamento WhatsApp',
            'credentials' => [
                'account_sid' => 'AC'.str_repeat('a', 32),
                'auth_token' => 'token-secreto-123',
                'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                'whatsapp_sender' => 'whatsapp:+5511999999999',
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertForbidden();
    }

    /** @test */
    public function test_connect_with_invalid_credentials_returns_422_and_does_not_persist(): void
    {
        // Mock Twilio Client para simular erro de autenticação
        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;
        $messagesMock->shouldReceive('read')->andThrow(
            new \Exception('Unauthorized')
        );
        $this->app->instance(Client::class, $twilioMock);

        $payload = [
            'type' => 'whatsapp',
            'name' => 'Agendamento WhatsApp',
            'credentials' => [
                'account_sid' => 'AC'.str_repeat('invalid', 4),
                'auth_token' => 'invalid-token',
                'messaging_service_sid' => 'MG'.str_repeat('invalid', 4),
                'whatsapp_sender' => 'whatsapp:+5511999999999',
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertUnprocessable();

        // Verifica que nenhum canal foi persistido
        $this->assertDatabaseMissing('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
    }

    /** @test */
    public function test_duplicate_whatsapp_sender_in_same_tenant_returns_409(): void
    {
        // Mock Twilio para primeira conexão
        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;
        $messagesMock->shouldReceive('read')->andReturn([]);
        $this->app->instance(Client::class, $twilioMock);

        $credentials = [
            'account_sid' => 'AC'.str_repeat('a', 32),
            'auth_token' => 'token-secreto-123',
            'messaging_service_sid' => 'MG'.str_repeat('b', 32),
            'whatsapp_sender' => 'whatsapp:+5511999999999',
        ];

        // Primeira conexão — deve passar
        $payload1 = [
            'type' => 'whatsapp',
            'name' => 'Canal 1',
            'credentials' => $credentials,
        ];
        $this->postJson($this->baseUrl('/inbox/channels'), $payload1)->assertCreated();

        // Segunda conexão com mesmo sender — deve retornar 409
        $payload2 = [
            'type' => 'whatsapp',
            'name' => 'Canal 2',
            'credentials' => $credentials,
        ];
        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload2);

        $response->assertConflict();
    }

    /** @test */
    public function test_connect_channel_fires_audit_event(): void
    {
        // Mock Twilio
        $twilioMock = Mockery::mock(Client::class);
        $messagesMock = Mockery::mock();
        $twilioMock->messages = $messagesMock;
        $messagesMock->shouldReceive('read')->andReturn([]);
        $this->app->instance(Client::class, $twilioMock);

        // Fake event dispatcher para capturar CanalConectado
        Event::fake([CanalConectado::class]);

        $payload = [
            'type' => 'whatsapp',
            'name' => 'Agendamento WhatsApp',
            'credentials' => [
                'account_sid' => 'AC'.str_repeat('a', 32),
                'auth_token' => 'token-secreto-123',
                'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                'whatsapp_sender' => 'whatsapp:+5511999999999',
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertCreated();

        // Verifica que o evento foi disparado
        Event::assertDispatched(CanalConectado::class);
    }
}
